<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardItem;
use Modules\Core\Models\Question;

/**
 * Médias (/storage/...) référencés par le contenu vitrine actif : seuls
 * ceux-là sont lisibles par un invité. Liste mise en cache, vidée à chaque
 * modification d'un contenu susceptible d'en changer.
 */
class ShowcaseMedia
{
    public const CACHE_KEY = 'showcase.media';

    /**
     * @return array<string, true> chemins relatifs au disque public
     */
    public static function paths(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::compute());
    }

    public static function allows(string $path): bool
    {
        return isset(self::paths()[$path]);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, true>
     */
    private static function compute(): array
    {
        $found = [];
        $collect = function (?string $html) use (&$found): void {
            foreach (self::extract($html) as $path) {
                $found[$path] = true;
            }
        };

        Question::whereHas('quiz', fn ($q) => $q->where('is_showcase', true)->where('is_active', true))
            ->pluck('question_text')->each($collect);

        Article::where('is_showcase', true)->where('is_active', true)
            ->pluck('content')->each($collect);

        FlashcardItem::whereHas('deck', fn ($q) => $q->where('is_showcase', true)->where('active', true))
            ->get(['recto', 'verso'])
            ->each(function ($card) use ($collect) {
                $collect($card->recto);
                $collect($card->verso);
            });

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function extract(?string $html): array
    {
        if (! $html) {
            return [];
        }

        $html = ContentSerializer::relativizeMediaUrls($html);
        preg_match_all('#\b(?:src|href)\s*=\s*["\']/storage/([^"\'\s?\#]+)#i', $html, $m);

        $paths = [];
        foreach ($m[1] as $raw) {
            $path = ltrim(rawurldecode($raw), '/');
            if ($path !== '' && ! str_contains($path, '..') && ! str_contains($path, "\0")) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
