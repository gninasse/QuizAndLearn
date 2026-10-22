<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Article;
use Modules\Core\Models\ArticleMedia;
use Throwable;

/**
 * Références aux fichiers du disque `public` depuis les contenus (HTML et JSON).
 *
 * Un fichier physique n'est supprimé que lorsqu'aucun contenu ne le référence plus :
 * une copie (« Copier vers… ») partage les fichiers de l'original.
 */
class MediaReferences
{
    /**
     * Colonnes pouvant référencer un fichier, par table.
     */
    public const COLUMNS = [
        'questions' => ['question_text', 'options'],
        'exam_questions' => ['question_text', 'options'],
        'flashcard_items' => ['recto', 'verso', 'recto_media', 'verso_media'],
        'articles' => ['content'],
        'article_media' => ['file_path'],
    ];

    /**
     * Vrai si un contenu autre que $except référence $path.
     * Pour un article, ses propres médias (article_media) sont aussi exclus.
     */
    public static function isReferenced(string $path, ?Model $except = null): bool
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return false;
        }
        // Le JSON (casts `array`/`json`) échappe les barres obliques.
        $patterns = array_unique([$path, str_replace('/', '\\/', $path)]);

        foreach (self::COLUMNS as $table => $columns) {
            $query = DB::table($table)->where(function ($q) use ($columns, $patterns) {
                foreach ($columns as $column) {
                    foreach ($patterns as $pattern) {
                        $q->orWhereRaw("{$column} LIKE ? ESCAPE '!'", ['%'.self::escapeLike($pattern).'%']);
                    }
                }
            });

            if ($except !== null) {
                if ($except->getTable() === $table) {
                    $query->where('id', '!=', $except->getKey());
                }
                if ($except instanceof Article && $table === 'article_media') {
                    $query->where('article_id', '!=', $except->getKey());
                }
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Supprime $path du disque `public` s'il n'est plus référencé ailleurs qu'en $except.
     */
    public static function deleteIfUnreferenced(string $path, ?Model $except = null): void
    {
        $path = ltrim($path, '/');
        if ($path === '' || self::isReferenced($path, $except)) {
            return;
        }

        try {
            $disk = Storage::disk('public');
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Chemins (relatifs au disque `public`) des <img> d'un HTML pointant vers /storage.
     *
     * @return array<int, string>
     */
    public static function inlineStoragePaths(?string $html): array
    {
        if ($html === null || $html === '') {
            return [];
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);
        $storageUrl = asset('storage/');
        $paths = [];
        foreach ($matches[1] ?? [] as $url) {
            if (str_starts_with($url, $storageUrl)) {
                $paths[] = ltrim(str_replace($storageUrl, '', $url), '/');
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * Nettoyage à la suppression d'un contenu : fichiers des <img> de ses champs HTML.
     *
     * @param  array<int, ?string>  $htmlFields
     */
    public static function releaseInline(Model $owner, array $htmlFields): void
    {
        foreach ($htmlFields as $html) {
            foreach (self::inlineStoragePaths($html) as $path) {
                self::deleteIfUnreferenced($path, $owner);
            }
        }
    }

    /**
     * Nettoyage à la suppression d'un article : fichiers de ses médias.
     */
    public static function releaseArticleMedia(Article $article): void
    {
        $article->media->each(fn (ArticleMedia $media) => self::deleteIfUnreferenced($media->file_path, $article));
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
