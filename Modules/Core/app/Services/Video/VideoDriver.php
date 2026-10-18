<?php

namespace Modules\Core\Services\Video;

use Illuminate\Http\UploadedFile;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;

interface VideoDriver
{
    public function name(): string;

    /** @return array{location: string, status: string, ref?: string} */
    public function store(UploadedFile $file): array;

    public function playback(Video $video, ?User $viewer): PlaybackSource;

    public function delete(Video $video): void;

    /** Interroge le pilote et renvoie le statut courant (ready|processing|failed). */
    public function refreshStatus(Video $video): string;

    /** @return array{ok: bool, message: string} */
    public function healthCheck(): array;
}
