<?php

namespace Modules\Core\Services\Video;

use Carbon\Carbon;

final class PlaybackSource
{
    public function __construct(
        public string $type, // 'mp4' | 'hls'
        public string $url,
        public ?Carbon $expiresAt = null,
    ) {}

    /** @return array{type: string, url: string, expires_at: ?string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
