<?php

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\User;
use RuntimeException;

/**
 * Bascule `shared` → `isolated` : chaque contenu (hors decks publics) attribué à
 * plusieurs groupes reste dans le groupe qui a le plus d'apprenants (le plus petit
 * id départage) ; les autres groupes reçoivent une copie au même dossier et au même
 * rang, et l'historique de leurs apprenants suit la copie.
 *
 * Les apprenants qui sont aussi membres du groupe conservé gardent l'original, et
 * donc leur historique. Un apprenant de plusieurs groupes cibles suit la copie du
 * premier (id croissant).
 */
class IsolationMigrator
{
    private const CHUNK = 500;

    public function __construct(
        private ContentCopier $copier,
        private FolderTree $tree,
    ) {}

    /**
     * Contenus attribués à plusieurs groupes (decks publics exclus).
     *
     * @return Collection<int, array{type: string, id: int, title: string, group_ids: array<int, int>}>
     */
    public function conflicts(): Collection
    {
        $out = collect();

        foreach (ContentSharing::TYPES as $type => [$model, $pivot, $fk]) {
            $ids = DB::table($pivot)
                ->select($fk)
                ->groupBy($fk)
                ->havingRaw('COUNT(DISTINCT group_id) > 1')
                ->pluck($fk)
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($ids === []) {
                continue;
            }

            $query = $model::query()->whereKey($ids);
            if ($model === FlashcardDeck::class) {
                $query->where('is_public', false);
            }
            $titleColumn = $model === FlashcardDeck::class ? 'titre' : 'title';
            $titles = $query->pluck($titleColumn, 'id');

            $groups = DB::table($pivot)->whereIn($fk, $titles->keys())->orderBy('group_id')->get([$fk, 'group_id'])
                ->groupBy($fk);

            foreach ($titles as $id => $title) {
                $out->push([
                    'type' => $type,
                    'id' => (int) $id,
                    'title' => (string) $title,
                    'group_ids' => $groups[$id]->pluck('group_id')->map(fn ($g) => (int) $g)->unique()->values()->all(),
                ]);
            }
        }

        return $out->sortBy([['type', 'asc'], ['id', 'asc']])->values();
    }

    /**
     * Plan de bascule, sans rien écrire.
     *
     * @return array<int, array{type: string, id: int, keep_group_id: int, copies: array<int, array{group_id: int, learners: int}>}>
     */
    public function plan(): array
    {
        $conflicts = $this->conflicts();
        $groupIds = $conflicts->pluck('group_ids')->flatten()->unique()->values()->all();
        $learners = DB::table('group_learner')
            ->whereIn('group_id', $groupIds)
            ->select('group_id', DB::raw('COUNT(*) as total'))
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        return $conflicts->map(function (array $c) use ($learners) {
            $counts = collect($c['group_ids'])->map(fn (int $g) => ['group_id' => $g, 'learners' => (int) ($learners[$g] ?? 0)]);
            $keep = $counts->sortBy([['learners', 'desc'], ['group_id', 'asc']])->first()['group_id'];

            return [
                'type' => $c['type'],
                'id' => $c['id'],
                'keep_group_id' => $keep,
                'copies' => $counts->reject(fn ($g) => $g['group_id'] === $keep)->values()->all(),
            ];
        })->all();
    }

    /**
     * Exécute le plan : une transaction par contenu. Rejouer après succès ne fait rien.
     *
     * @param  ?User  $by  propriétaire des copies dont l'auteur d'origine n'existe plus (défaut : utilisateur connecté)
     * @return array{copied: int, moved_rows: int}
     */
    public function apply(?User $by = null): array
    {
        $by ??= auth()->user();
        if (! $by instanceof User) {
            throw new RuntimeException('Bascule impossible sans utilisateur responsable des copies.');
        }

        $copied = 0;
        $moved = 0;
        foreach ($this->plan() as $item) {
            [$c, $m] = DB::transaction(fn () => $this->migrateItem($item, $by));
            $copied += $c;
            $moved += $m;
        }

        return ['copied' => $copied, 'moved_rows' => $moved];
    }

    /**
     * @param  array{type: string, id: int, keep_group_id: int, copies: array<int, array{group_id: int, learners: int}>}  $item
     * @return array{0: int, 1: int}
     */
    private function migrateItem(array $item, User $by): array
    {
        $type = $item['type'];
        $id = $item['id'];
        [, $pivot, $fk] = ContentSharing::TYPES[$type];

        // Les copies automatiques gardent l'auteur de l'original (repli : l'admin qui bascule).
        $creatorId = ContentSharing::TYPES[$type][0]::whereKey($id)->value('created_by');
        $owner = ($creatorId !== null ? User::find($creatorId) : null) ?? $by;
        $keptLearners = $this->learnerIds($item['keep_group_id']);
        $copied = 0;
        $moved = 0;

        foreach ($item['copies'] as ['group_id' => $groupId]) {
            $place = DB::table($pivot)->where('group_id', $groupId)->where($fk, $id)->first(['folder_id', 'position']);
            if ($place === null) {
                continue; // retiré entre-temps
            }
            $folderId = $place->folder_id !== null ? (int) $place->folder_id : null;
            $group = Group::findOrFail($groupId);

            // 1. Copie au même dossier ; 2. retrait de l'original ; puis rang d'origine à l'identique.
            ['model' => $copy, 'map' => $map] = $this->copier->copy($type, $id, $groupId, $folderId, $owner);
            $this->tree->detach($group, $type, $id);
            DB::table($pivot)->where('group_id', $groupId)->where($fk, $copy->getKey())
                ->update(['position' => (int) $place->position]);
            $copied++;

            // 3. Historique des apprenants de ce groupe qui n'ont plus accès à l'original.
            $learners = array_values(array_diff($this->learnerIds($groupId), $keptLearners));
            $moved += $this->moveHistory($type, $id, (int) $copy->getKey(), $map, $learners);
            $moved += DB::table('push_assignment_snapshots')
                ->where('group_id', $groupId)->where('content_type', $type)->where('content_id', $id)
                ->update(['content_id' => $copy->getKey()]);
        }

        return [$copied, $moved];
    }

    /**
     * @param  array{questions: array<int,int>, items: array<int,int>, exam_questions: array<int,int>}  $map
     * @param  array<int, int>  $learnerIds
     */
    private function moveHistory(string $type, int $from, int $to, array $map, array $learnerIds): int
    {
        $moved = 0;

        foreach (array_chunk($learnerIds, self::CHUNK) as $learners) {
            $moved += DB::table('learner_progress')
                ->where('content_type', $type)->where('content_id', $from)->whereIn('learner_id', $learners)
                ->update(['content_id' => $to]);

            $moved += match ($type) {
                'quiz' => $this->moveQuiz($from, $to, $map['questions'], $learners),
                'exam' => $this->moveAttempts('exam_attempts', 'exam_id', $from, $to, $map['exam_questions'], $learners),
                'deck' => $this->moveDeck($from, $to, $map['items'], $learners),
                default => 0,
            };
        }

        return $moved;
    }

    /**
     * @param  array<int,int>  $questions
     * @param  array<int,int>  $learners
     */
    private function moveQuiz(int $from, int $to, array $questions, array $learners): int
    {
        $attemptIds = DB::table('quiz_attempts')->where('quiz_id', $from)->whereIn('learner_id', $learners)->pluck('id')->all();
        $moved = $this->moveAttempts('quiz_attempts', 'quiz_id', $from, $to, $questions, $learners);

        foreach (array_chunk($attemptIds, self::CHUNK) as $attempts) {
            foreach ($questions as $old => $new) {
                $moved += DB::table('quiz_answers')->whereIn('attempt_id', $attempts)->where('question_id', $old)
                    ->update(['question_id' => $new]);
            }
        }

        // Cartes legacy générées depuis les questions du quiz.
        foreach ($questions as $old => $new) {
            $moved += DB::table('flashcards')->whereIn('learner_id', $learners)->where('question_id', $old)
                ->update(['question_id' => $new]);
        }

        return $moved;
    }

    /**
     * Re-pointe les tentatives et remappe les clés de `answers` (objet indexé par id de question).
     *
     * @param  array<int,int>  $questions
     * @param  array<int,int>  $learners
     */
    private function moveAttempts(string $table, string $column, int $from, int $to, array $questions, array $learners): int
    {
        $rows = DB::table($table)->where($column, $from)->whereIn('learner_id', $learners)->get(['id', 'answers']);

        foreach ($rows as $row) {
            DB::table($table)->where('id', $row->id)->update([
                $column => $to,
                'answers' => $this->remapAnswers($row->answers, $questions),
            ]);
        }

        return $rows->count();
    }

    /**
     * `answers` = objet JSON { "<id de question>": réponse } (QuizScoringService::score,
     * ExamEvaluationService::evaluate). Une clé hors table est conservée telle quelle.
     *
     * @param  array<int,int>  $questions
     */
    private function remapAnswers(?string $json, array $questions): ?string
    {
        if ($json === null) {
            return null;
        }
        $answers = json_decode($json, true);
        if (! is_array($answers) || $answers === []) {
            return $json;
        }

        $changed = false;
        $out = [];
        if (array_is_list($answers)) {
            // Format liste [{question_id, …}] : seul le champ question_id est remappé
            // (les index 0..n ne sont pas des ids de question).
            foreach ($answers as $entry) {
                if (is_array($entry) && isset($entry['question_id'], $questions[(int) $entry['question_id']])) {
                    $entry['question_id'] = $questions[(int) $entry['question_id']];
                    $changed = true;
                }
                $out[] = $entry;
            }
        } else {
            foreach ($answers as $key => $value) {
                if (is_int($key) && isset($questions[$key])) {
                    $key = $questions[$key];
                    $changed = true;
                }
                $out[$key] = $value;
            }
        }

        return $changed ? json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $json;
    }

    /**
     * @param  array<int,int>  $items
     * @param  array<int,int>  $learners
     */
    private function moveDeck(int $from, int $to, array $items, array $learners): int
    {
        $moved = DB::table('flashcard_sessions')->where('deck_id', $from)->whereIn('learner_id', $learners)
            ->update(['deck_id' => $to]);

        foreach ($items as $old => $new) {
            $moved += DB::table('flashcard_item_reviews')->whereIn('learner_id', $learners)->where('flashcard_item_id', $old)
                ->update(['flashcard_item_id' => $new]);
        }

        return $moved;
    }

    /**
     * @return array<int, int>
     */
    private function learnerIds(int $groupId): array
    {
        return DB::table('group_learner')->where('group_id', $groupId)->pluck('learner_id')->map(fn ($id) => (int) $id)->all();
    }
}
