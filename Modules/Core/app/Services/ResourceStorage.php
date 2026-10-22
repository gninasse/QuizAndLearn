<?php

namespace Modules\Core\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Jobs\ConvertDocumentToPdf;
use Modules\Core\Models\Resource;
use Modules\Core\Models\User;
use Modules\Core\Services\Video\VideoManager;
use Throwable;

/**
 * Stockage des ressources : fichiers privés (disque `local`, sous
 * resources/{id}/), conversion des documents bureautiques en PDF,
 * vidéos confiées au pilote vidéo, liens externes.
 */
class ResourceStorage
{
    public const DISK = 'local';

    /** Documents convertis en PDF (extension → types MIME reconnus). */
    public const DOCUMENT_TYPES = [
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'odp' => ['application/vnd.oasis.opendocument.presentation'],
    ];

    /** MIME génériques parfois détectés pour les formats Office/OpenDocument : l'extension tranche. */
    private const GENERIC_MIMES = ['application/zip', 'application/octet-stream', 'application/x-ole-storage', 'application/cdfv2', 'application/vnd.ms-office'];

    public function __construct(private VideoManager $videos) {}

    /**
     * @param  array{title?:string, description?:?string, allow_download?:bool, is_active?:bool}  $attrs
     */
    public function store(UploadedFile $file, array $attrs, User $by): Resource
    {
        $mime = strtolower((string) $file->getMimeType());
        $kind = $this->detectKind($file, $mime);

        $maxKb = $kind === 'video'
            ? (int) config('media.video_max_kb')
            : (int) config('media.documents.max_size_kb');
        if ((int) $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => 'Le fichier dépasse la taille maximale autorisée ('.round($maxKb / 1024).' Mo).',
            ]);
        }

        $base = [
            'kind' => $kind,
            'title' => (string) ($attrs['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            'description' => $attrs['description'] ?? null,
            'allow_download' => (bool) ($attrs['allow_download'] ?? false),
            'is_active' => (bool) ($attrs['is_active'] ?? true),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'created_by' => $by->id,
        ];

        if ($kind === 'video') {
            $video = $this->videos->store($file, $by);

            return Resource::create($base + ['video_id' => $video->id]);
        }

        $resource = DB::transaction(function () use ($file, $base, $kind) {
            $resource = Resource::create($base + [
                'disk' => self::DISK,
                'conversion_status' => $kind === 'document' ? 'pending' : null,
            ]);

            $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
            $path = $file->storeAs("resources/{$resource->id}", Str::random(32).'.'.$extension, self::DISK);
            if ($path === false) {
                throw new \RuntimeException("Écriture du fichier impossible (ressource {$resource->id}).");
            }
            $resource->update(['path' => $path]);

            return $resource;
        });

        if ($kind === 'document') {
            ConvertDocumentToPdf::dispatch($resource->id)->afterCommit();
        }

        return $resource;
    }

    public function createLink(string $url, string $title, User $by): Resource
    {
        $url = trim($url);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false || ! parse_url($url, PHP_URL_HOST)) {
            throw ValidationException::withMessages(['url' => 'Le lien doit être une adresse http ou https valide.']);
        }

        return Resource::create([
            'kind' => 'link',
            'title' => $title,
            'url' => $url,
            'created_by' => $by->id,
        ]);
    }

    /**
     * Supprime les fichiers physiques d'une ressource (original et PDF
     * converti) quand aucune AUTRE ressource ne les référence (copies).
     * Ne supprime pas la ressource elle-même.
     */
    public function release(Resource $resource): void
    {
        if (! $resource->disk) {
            return;
        }

        foreach (['path', 'pdf_path'] as $column) {
            $path = $resource->{$column};
            if (! $path) {
                continue;
            }
            $shared = Resource::query()
                ->whereKeyNot($resource->id)
                ->where('disk', $resource->disk)
                ->where(fn ($q) => $q->where('path', $path)->orWhere('pdf_path', $path))
                ->exists();
            if ($shared) {
                continue;
            }
            try {
                Storage::disk($resource->disk)->delete($path);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function detectKind(UploadedFile $file, string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        $extension = strtolower($file->getClientOriginalExtension());
        foreach (self::DOCUMENT_TYPES as $ext => $mimes) {
            if (in_array($mime, $mimes, true)) {
                return 'document';
            }
        }
        if (isset(self::DOCUMENT_TYPES[$extension]) && in_array($mime, self::GENERIC_MIMES, true)) {
            return 'document';
        }

        throw ValidationException::withMessages([
            'file' => 'Type de fichier non pris en charge (images, audio, vidéo, PDF, Word, PowerPoint, OpenDocument).',
        ]);
    }
}
