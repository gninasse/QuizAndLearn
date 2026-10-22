<?php

namespace Modules\Core\Services;

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
}
