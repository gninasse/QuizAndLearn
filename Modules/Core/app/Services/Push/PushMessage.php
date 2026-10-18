<?php

namespace Modules\Core\Services\Push;

/**
 * Message push. `ref` identifie la source : "quiz:{id}", "article:{id}",
 * "deck:{id}", ou toute clé de dédoublonnage (null = jamais dédoublonné).
 */
final class PushMessage
{
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public string $url,
        public ?string $ref = null,
    ) {}

    public function toPayload(): string
    {
        return json_encode([
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tag' => $this->type,
            'icon' => '/icons/icon-192x192.png',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
