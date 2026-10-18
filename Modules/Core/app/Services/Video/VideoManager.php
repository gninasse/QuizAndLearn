<?php

namespace Modules\Core\Services\Video;

use Closure;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\Drivers\BunnyVideoDriver;
use Modules\Core\Services\Video\Drivers\LocalVideoDriver;
use Modules\Core\Services\Video\Drivers\S3HlsVideoDriver;

class VideoManager
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, VideoDriver> */
    private array $resolved = [];

    public function __construct()
    {
        $this->factories['local'] = fn () => new LocalVideoDriver;
        $this->factories['s3_hls'] = fn () => new S3HlsVideoDriver((string) config('media.video.s3_hls.disk', 'videos'));
        $this->factories['bunny'] = fn () => new BunnyVideoDriver((array) config('media.video.bunny', []));
    }

    public function extend(string $name, Closure $factory): static
    {
        $this->factories[$name] = $factory;
        unset($this->resolved[$name]);

        return $this;
    }

    public function getDefaultDriver(): string
    {
        return (string) config('media.video.driver', 'local');
    }

    public function driver(?string $name = null): VideoDriver
    {
        $name ??= $this->getDefaultDriver();

        if (! isset($this->factories[$name])) {
            throw new InvalidArgumentException("Pilote vidéo [{$name}] inconnu.");
        }

        return $this->resolved[$name] ??= ($this->factories[$name])();
    }

    /** Stocke avec le pilote courant et enregistre la vidéo (pilote mémorisé). */
    public function store(UploadedFile $file, User $uploader): Video
    {
        $driver = $this->driver();
        $stored = $driver->store($file);

        $video = Video::create([
            'ref' => $stored['ref'] ?? null,
            'driver' => $driver->name(),
            'location' => $stored['location'],
            'status' => $stored['status'],
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $uploader->id,
        ]);

        if ($driver instanceof AfterStoreHook) {
            $driver->afterStore($video);
        }

        return $video;
    }

    /** Lit avec le pilote d'origine de la vidéo, pas celui de la config. */
    public function playback(Video $video, ?User $viewer): PlaybackSource
    {
        return $this->driver($video->driver)->playback($video, $viewer);
    }
}
