<?php

namespace Modules\Core\Services\Video\Drivers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\PlaybackSource;
use Modules\Core\Services\Video\VideoDriver;

/**
 * Pilote historique : fichier sur le disque public, servi par la route
 * protégée /storage (MediaController).
 */
class LocalVideoDriver implements VideoDriver
{
    public function name(): string
    {
        return 'local';
    }

    public function store(UploadedFile $file): array
    {
        return ['location' => $file->store('uploads/media', 'public'), 'status' => 'ready'];
    }

    public function playback(Video $video, ?User $viewer): PlaybackSource
    {
        return new PlaybackSource('mp4', '/storage/'.ltrim($video->location, '/'), null);
    }

    public function delete(Video $video): void
    {
        Storage::disk('public')->delete($video->location);
    }

    public function refreshStatus(Video $video): string
    {
        return $video->status;
    }

    public function healthCheck(): array
    {
        $ok = is_writable(Storage::disk('public')->path(''));

        return ['ok' => $ok, 'message' => $ok ? 'Stockage local disponible.' : 'Dossier de stockage non inscriptible.'];
    }
}
