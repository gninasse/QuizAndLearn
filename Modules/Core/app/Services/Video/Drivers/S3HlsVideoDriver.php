<?php

namespace Modules\Core\Services\Video\Drivers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Core\Jobs\TranscodeVideoToHls;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\AfterStoreHook;
use Modules\Core\Services\Video\PlaybackSource;
use Modules\Core\Services\Video\VideoDriver;
use Throwable;

/**
 * Original sur un disque S3 compatible, converti en HLS par ffmpeg (job).
 * La lecture passe par une playlist signée dont les segments sont des URL
 * S3 temporaires : la PWA ne voit jamais l'URL brute ni la clé.
 */
class S3HlsVideoDriver implements AfterStoreHook, VideoDriver
{
    /**
     * @param  ?string  $healthDisk  disque du test de connexion (délais courts) ; par défaut $disk
     */
    public function __construct(
        private string $disk = 'videos',
        private ?string $healthDisk = null,
        private string $ffmpeg = 'ffmpeg',
    ) {}

    public function name(): string
    {
        return 's3_hls';
    }

    public function store(UploadedFile $file): array
    {
        $ref = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'mp4');
        $location = "originals/{$ref}.{$ext}";
        Storage::disk($this->disk)->putFileAs('originals', $file, "{$ref}.{$ext}");

        return ['location' => $location, 'status' => 'processing', 'ref' => $ref];
    }

    public function afterStore(Video $video): void
    {
        TranscodeVideoToHls::dispatch($video);
    }

    public function playback(Video $video, ?User $viewer): PlaybackSource
    {
        $expires = now()->addSeconds((int) config('media.video.signed_ttl', 900));
        $url = URL::temporarySignedRoute('learn.v1.videos.hls', $expires, ['ref' => $video->ref, 'file' => 'index.m3u8']);

        return new PlaybackSource('hls', $url, $expires);
    }

    public function delete(Video $video): void
    {
        $disk = Storage::disk($this->disk);
        $disk->delete($video->location);
        $disk->deleteDirectory("hls/{$video->ref}");
    }

    public function refreshStatus(Video $video): string
    {
        return $video->status;
    }

    public function healthCheck(): array
    {
        $storage = $this->storageCheck();
        $ffmpeg = $this->ffmpegCheck();

        return [
            'ok' => $storage['ok'] && $ffmpeg['ok'],
            'message' => $storage['message'].' '.$ffmpeg['message'],
        ];
    }

    /** @return array{ok: bool, message: string} */
    private function storageCheck(): array
    {
        try {
            $disk = Storage::disk($this->healthDisk ?? $this->disk);
            $path = 'healthcheck/'.Str::uuid().'.txt';
            $disk->put($path, 'ok');
            $disk->delete($path);

            return ['ok' => true, 'message' => 'Stockage S3 accessible.'];
        } catch (Throwable $e) {
            // Le détail (bucket, endpoint, clé…) reste dans les journaux, jamais dans l'interface.
            report($e);

            return ['ok' => false, 'message' => 'Stockage S3 inaccessible. Consultez les journaux.'];
        }
    }

    /** Le binaire configuré (FFMPEG_BINARY) doit répondre à « -version ». */
    private function ffmpegCheck(): array
    {
        try {
            $ok = Process::timeout(15)->run([$this->ffmpeg, '-version'])->successful();
        } catch (Throwable $e) {
            report($e);
            $ok = false;
        }

        return $ok
            ? ['ok' => true, 'message' => 'ffmpeg disponible.']
            : ['ok' => false, 'message' => 'ffmpeg introuvable ou inutilisable (FFMPEG_BINARY). Consultez les journaux.'];
    }
}
