<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Video;
use Modules\Core\Services\Video\Transcoder;
use Modules\Core\Services\Video\VideoManager;
use Throwable;

class TranscodeVideoToHls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /** Supérieur au délai d'ffmpeg (3600 s) : le job peut nettoyer après un échec ffmpeg. */
    public int $timeout = 4000;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Video $video)
    {
        // File dédiée : une conversion longue ne retarde pas les notifications push.
        $this->onQueue((string) config('media.video.s3_hls.queue', 'videos'));
    }

    public function handle(Transcoder $transcoder, VideoManager $manager): void
    {
        $video = $this->video;
        $disk = Storage::disk((string) config('media.video.s3_hls.disk', 'videos'));
        $tmp = self::tmpDir($video);
        $files = new Filesystem;

        try {
            $files->ensureDirectoryExists($tmp);
            $extension = pathinfo($video->location, PATHINFO_EXTENSION) ?: 'mp4';
            $input = "{$tmp}/original.{$extension}";

            $in = $disk->readStream($video->location);
            if (! is_resource($in)) {
                throw new \RuntimeException("Original introuvable : {$video->location}");
            }
            file_put_contents($input, $in);
            fclose($in);

            $outDir = "{$tmp}/out";
            $files->ensureDirectoryExists($outDir);
            $produced = $transcoder->toHls($input, $outDir);

            if (! in_array("{$outDir}/index.m3u8", $produced, true)) {
                throw new \RuntimeException('Playlist HLS absente.');
            }
            foreach ($produced as $path) {
                $stream = fopen($path, 'r');
                $disk->writeStream("hls/{$video->ref}/".basename($path), $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $video->update(['status' => 'ready']);
        } catch (Throwable $e) {
            Log::error('Conversion HLS échouée', ['video' => $video->ref, 'error' => $e->getMessage()]);
            $this->markFailed();
        } finally {
            $files->deleteDirectory($tmp);
        }
    }

    /**
     * Appelé par le worker quand le job échoue hors de handle() (délai
     * dépassé, processus tué) : statut « failed » et aucun résidu.
     */
    public function failed(?Throwable $e = null): void
    {
        Log::error('Conversion HLS interrompue', ['video' => $this->video->ref, 'error' => $e?->getMessage()]);
        (new Filesystem)->deleteDirectory(self::tmpDir($this->video));
        $this->markFailed();
    }

    public static function tmpDir(Video $video): string
    {
        return storage_path("app/tmp/hls-{$video->ref}");
    }

    private function markFailed(): void
    {
        try {
            // Segments éventuellement déjà envoyés : jamais de HLS partiel.
            Storage::disk((string) config('media.video.s3_hls.disk', 'videos'))
                ->deleteDirectory("hls/{$this->video->ref}");
        } catch (Throwable $e) {
            report($e);
        }
        $this->video->update(['status' => 'failed']);
    }
}
