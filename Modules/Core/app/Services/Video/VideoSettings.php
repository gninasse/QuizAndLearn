<?php

namespace Modules\Core\Services\Video;

use Modules\Core\Models\AppSetting;

/**
 * Réglages admin des vidéos externes (embeds YouTube / Vimeo), `video.embeds.*`.
 */
class VideoSettings
{
    public const PROVIDERS = ['youtube', 'vimeo'];

    public function embedsEnabled(): bool
    {
        return (bool) AppSetting::get('video.embeds.enabled', true);
    }

    /** @return list<string> */
    public function providers(): array
    {
        $stored = AppSetting::get('video.embeds.providers', self::PROVIDERS);
        $list = is_array($stored) ? $stored : self::PROVIDERS;

        return array_values(array_intersect(self::PROVIDERS, $list));
    }

    /**
     * @param  array{embeds?: array{enabled?: mixed, providers?: array<int, string>}}  $validated
     */
    public function save(array $validated): void
    {
        $embeds = $validated['embeds'] ?? [];
        if (array_key_exists('enabled', $embeds)) {
            AppSetting::put('video.embeds.enabled', (bool) $embeds['enabled']);
        }
        if (array_key_exists('providers', $embeds)) {
            AppSetting::put('video.embeds.providers', array_values(array_intersect(self::PROVIDERS, (array) $embeds['providers'])));
        }
    }

    /**
     * Charge utile publique (`/me`, vitrine) : l'éditeur et la PWA s'y fient.
     *
     * @return array{embeds: array{enabled: bool, providers: list<string>}}
     */
    public function toArray(): array
    {
        $enabled = $this->embedsEnabled();

        return ['embeds' => ['enabled' => $enabled, 'providers' => $enabled ? $this->providers() : []]];
    }

    /** Variables d'environnement requises par pilote (jamais leurs valeurs). */
    public const REQUIRED = [
        'local' => [],
        's3_hls' => [
            'VIDEO_S3_KEY' => 'filesystems.disks.{disk}.key',
            'VIDEO_S3_SECRET' => 'filesystems.disks.{disk}.secret',
            'VIDEO_S3_REGION' => 'filesystems.disks.{disk}.region',
            'VIDEO_S3_BUCKET' => 'filesystems.disks.{disk}.bucket',
        ],
        'bunny' => [
            'BUNNY_STREAM_LIBRARY_ID' => 'media.video.bunny.library_id',
            'BUNNY_STREAM_API_KEY' => 'media.video.bunny.api_key',
            'BUNNY_STREAM_CDN_HOST' => 'media.video.bunny.cdn_host',
            'BUNNY_STREAM_TOKEN_KEY' => 'media.video.bunny.token_key',
        ],
    ];

    /**
     * État « configurée / manquante » de chaque variable du pilote actif.
     *
     * @return array<string, bool>
     */
    public function requirementStatus(?string $driver = null): array
    {
        $driver ??= (string) config('media.video.driver', 'local');
        $status = [];
        foreach (self::REQUIRED[$driver] ?? [] as $env => $configKey) {
            $key = str_replace('{disk}', (string) config('media.video.s3_hls.disk', 'videos'), $configKey);
            $status[$env] = filled(config($key));
        }

        return $status;
    }
}
