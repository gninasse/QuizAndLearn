<?php

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Console\Commands\Concerns\TargetsPushLearners;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Quiz;
use Modules\Core\Services\ContentPlacement;
use Modules\Core\Services\Push\PushMessage;
use Modules\Core\Services\Push\PushNotifier;

class PushNewContent extends Command
{
    use TargetsPushLearners;

    protected $signature = 'push:new-content';

    protected $description = 'Notifie les apprenants des nouveaux contenus (quiz, articles, paquets) assignés à leurs groupes';

    private const PIVOTS = [
        'quiz' => ['group_quiz', 'quiz_id'],
        'article' => ['group_article', 'article_id'],
        'deck' => ['group_flashcard_deck', 'flashcard_deck_id'],
    ];

    public function handle(PushNotifier $notifier): int
    {
        $current = $this->currentPairs();
        $snapshot = DB::table('push_assignment_snapshots')->get(['group_id', 'content_type', 'content_id'])
            ->mapWithKeys(fn ($r) => [$this->key($r->group_id, $r->content_type, $r->content_id) => true]);

        // Premier passage : on mémorise l'existant sans rien envoyer.
        if (AppSetting::get('push.snapshot_initialized_at') === null) {
            $this->insertPairs($current);
            AppSetting::put('push.snapshot_initialized_at', now()->toIso8601String());
            $this->info(count($current).' affectation(s) mémorisée(s), aucune notification envoyée.');

            return self::SUCCESS;
        }

        // Les contenus restent « nouveaux » jusqu'à la fin de la plage silencieuse.
        if ($this->inQuietHours($this->pushLocalNow())) {
            return self::SUCCESS;
        }

        $new = array_filter($current, fn ($p) => ! isset($snapshot[$this->key($p['group_id'], $p['type'], $p['id'])]));
        $now = now();

        $contents = $this->availableContents($new);

        // Les affectations pas encore diffusables (inactif, pas encore ouvert)
        // restent hors instantané et seront reprises quand elles le deviennent.
        $handled = array_filter($new, fn ($p) => isset($contents[$p['type'].':'.$p['id']]));

        /** @var array<int, array<string, array{type: string, id: int, title: string}>> $byLearner */
        $byLearner = [];
        $groupContents = [];
        foreach ($handled as $p) {
            $groupContents[$p['group_id']][] = $contents[$p['type'].':'.$p['id']];
        }
        $membership = DB::table('group_learner')->whereIn('group_id', array_keys($groupContents))->get(['group_id', 'learner_id']);
        $pushable = $this->pushableLearners($now)->whereIn('id', $membership->pluck('learner_id')->unique())->pluck('id')->flip();
        foreach ($membership as $row) {
            if (! isset($pushable[$row->learner_id])) {
                continue;
            }
            foreach ($groupContents[$row->group_id] as $c) {
                $byLearner[$row->learner_id][$c['type'].':'.$c['id']] = $c;
            }
        }

        $sent = 0;
        foreach ($this->pushableLearners($now)->whereIn('id', array_keys($byLearner))->get() as $learner) {
            if ($notifier->notify($learner, $this->message($byLearner[$learner->id]), $now)) {
                $sent++;
            }
        }

        $this->insertPairs($handled);
        $this->deleteRemoved($current);
        $this->info(count($handled)." nouvelle(s) affectation(s), {$sent} apprenant(s) notifié(s).");

        return self::SUCCESS;
    }

    /**
     * Affectations visibles (racine ou dossier visible) : une affectation
     * masquée reste hors instantané et sera notifiée quand elle le deviendra.
     *
     * @return array<int, array{group_id: int, type: string, id: int}>
     */
    private function currentPairs(): array
    {
        $groupIds = DB::table('folders')->distinct()->pluck('group_id');
        $visibleFolders = array_flip(app(ContentPlacement::class)->visibleFolderIds($groupIds));

        $pairs = [];
        foreach (self::PIVOTS as $type => [$table, $column]) {
            foreach (DB::table($table)->get(['group_id', $column, 'folder_id']) as $row) {
                if ($row->folder_id !== null && ! isset($visibleFolders[$row->folder_id])) {
                    continue;
                }
                $pairs[$this->key($row->group_id, $type, $row->{$column})] = [
                    'group_id' => (int) $row->group_id, 'type' => $type, 'id' => (int) $row->{$column},
                ];
            }
        }

        return $pairs;
    }

    /**
     * Contenus diffusables parmi les nouvelles paires, indexés par ref.
     *
     * @param  array<int, array{group_id: int, type: string, id: int}>  $new
     * @return array<string, array{type: string, id: int, title: string}>
     */
    private function availableContents(array $new): array
    {
        $ids = [];
        foreach ($new as $p) {
            $ids[$p['type']][$p['id']] = $p['id'];
        }
        $now = now();
        $available = fn ($q) => $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', $now));

        $out = [];
        if (isset($ids['quiz'])) {
            foreach ($available(Quiz::query())->whereIn('id', $ids['quiz'])->get(['id', 'title']) as $r) {
                $out["quiz:{$r->id}"] = ['type' => 'quiz', 'id' => $r->id, 'title' => $r->title];
            }
        }
        if (isset($ids['article'])) {
            foreach ($available(Article::query())->whereIn('id', $ids['article'])->get(['id', 'title']) as $r) {
                $out["article:{$r->id}"] = ['type' => 'article', 'id' => $r->id, 'title' => $r->title];
            }
        }
        if (isset($ids['deck'])) {
            foreach (FlashcardDeck::query()->where('active', true)->whereIn('id', $ids['deck'])->get(['id', 'titre']) as $r) {
                $out["deck:{$r->id}"] = ['type' => 'deck', 'id' => $r->id, 'title' => $r->titre];
            }
        }

        return $out;
    }

    /** @param array<string, array{type: string, id: int, title: string}> $contents */
    private function message(array $contents): PushMessage
    {
        if (count($contents) === 1) {
            $c = reset($contents);
            [$title, $url] = match ($c['type']) {
                'quiz' => ['Nouveau quiz disponible', '/quizzes/'.$c['id']],
                'article' => ['Nouvel article disponible', '/articles/'.$c['id']],
                default => ['Nouveau paquet de cartes disponible', '/reviser'],
            };

            return new PushMessage('new_content', $title, $c['title'], $url, $c['type'].':'.$c['id']);
        }

        $refs = array_keys($contents);
        sort($refs);

        return new PushMessage(
            'new_content',
            count($refs).' nouveaux contenus disponibles',
            "Ouvrez l'application pour les découvrir.",
            '/',
            'new:'.sha1(implode(',', $refs)),
        );
    }

    /** @param array<int|string, array{group_id: int, type: string, id: int}> $pairs */
    private function insertPairs(array $pairs): void
    {
        $rows = array_map(fn ($p) => [
            'group_id' => $p['group_id'], 'content_type' => $p['type'], 'content_id' => $p['id'],
        ], array_values($pairs));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('push_assignment_snapshots')->insertOrIgnore($chunk);
        }
    }

    /** @param array<string, mixed> $current */
    private function deleteRemoved(array $current): void
    {
        $stale = DB::table('push_assignment_snapshots')->get()
            ->filter(fn ($r) => ! isset($current[$this->key($r->group_id, $r->content_type, $r->content_id)]))
            ->pluck('id');

        foreach ($stale->chunk(500) as $chunk) {
            DB::table('push_assignment_snapshots')->whereIn('id', $chunk->all())->delete();
        }
    }

    private function key(int|string $group, string $type, int|string $id): string
    {
        return "{$group}:{$type}:{$id}";
    }
}
