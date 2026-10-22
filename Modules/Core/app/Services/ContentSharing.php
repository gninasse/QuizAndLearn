<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Models\AppSetting;
use Modules\Core\Models\Article;
use Modules\Core\Models\Exam;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Quiz;
use Modules\Core\Models\Resource;

/**
 * Mode de partage des contenus entre groupes : `shared` (défaut, un contenu
 * peut être dans plusieurs groupes) ou `isolated` (un seul groupe par contenu).
 */
class ContentSharing
{
    public const KEY = 'content.sharing_mode';

    public const MODES = ['shared', 'isolated'];

    /**
     * Type de contenu => [classe du modèle, table pivot, clé étrangère du contenu].
     *
     * @var array<string, array{0: class-string, 1: string, 2: string}>
     */
    public const TYPES = [
        'article' => [Article::class, 'group_article', 'article_id'],
        'quiz' => [Quiz::class, 'group_quiz', 'quiz_id'],
        'exam' => [Exam::class, 'group_exam', 'exam_id'],
        'deck' => [FlashcardDeck::class, 'group_flashcard_deck', 'flashcard_deck_id'],
        'resource' => [Resource::class, 'group_resource', 'resource_id'],
    ];

    public static function mode(): string
    {
        $mode = AppSetting::get(self::KEY, 'shared');

        return in_array($mode, self::MODES, true) ? $mode : 'shared';
    }

    public static function isolated(): bool
    {
        return self::mode() === 'isolated';
    }

    public static function setMode(string $mode): void
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Mode de partage invalide : {$mode}");
        }

        AppSetting::put(self::KEY, $mode);
    }

    public const ALREADY_OWNED = 'Ce contenu appartient déjà à un autre groupe. Utilisez « Copier vers… ».';

    /**
     * Règle du mode `isolated` : un contenu déjà rattaché à un AUTRE groupe ne
     * peut pas en rejoindre un second (les decks publics en sont exemptés).
     * Sans effet en mode `shared`.
     *
     * @throws ValidationException (clé `group_id`)
     */
    public static function assertCanAttach(string $type, int $id, int $groupId): void
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Type de contenu inconnu : {$type}");
        }
        if (! self::isolated()) {
            return;
        }

        [$model, $pivot, $foreignKey] = self::TYPES[$type];
        if ($model === FlashcardDeck::class && FlashcardDeck::whereKey($id)->where('is_public', true)->exists()) {
            return;
        }

        $elsewhere = DB::table($pivot)
            ->where($foreignKey, $id)
            ->where('group_id', '!=', $groupId)
            ->exists();

        if ($elsewhere) {
            throw ValidationException::withMessages(['group_id' => self::ALREADY_OWNED]);
        }
    }

    /**
     * Règle du mode `isolated` pour une liste complète de groupes (formulaires
     * `group_ids`) : au plus un groupe, et pas un autre que celui qui possède déjà
     * le contenu ($id null = contenu en création). Un deck public (état demandé :
     * $publicDeck) en est exempté.
     *
     * @param  array<int, int|string>  $groupIds
     *
     * @throws ValidationException (clé `group_ids`)
     */
    public static function assertCanSync(string $type, ?int $id, array $groupIds, bool $publicDeck = false): void
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Type de contenu inconnu : {$type}");
        }
        if (! self::isolated() || ($type === 'deck' && $publicDeck)) {
            return;
        }

        $ids = self::groupIds($groupIds);
        if (count($ids) > 1) {
            throw ValidationException::withMessages(['group_ids' => self::ALREADY_OWNED]);
        }
        if ($id !== null && count($ids) === 1) {
            self::assertCanAttach($type, $id, $ids[0]);
        }
    }

    /**
     * Identifiants de groupe d'un formulaire, normalisés (entiers > 0, sans doublon) :
     * une sélection unique vide envoie `group_ids[]=` (null après ConvertEmptyStringsToNull).
     *
     * @param  array<int, mixed>  $groupIds
     * @return array<int, int>
     */
    public static function groupIds(array $groupIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $groupIds), fn (int $id) => $id > 0)));
    }
}
