<?php

namespace Modules\Core\Services\Video;

use RuntimeException;
use Symfony\Component\Process\Process;

class FfmpegTranscoder implements Transcoder
{
    public function __construct(private string $binary = 'ffmpeg') {}

    public function toHls(string $inputPath, string $outputDir): array
    {
        $process = new Process([
            $this->binary, '-y', '-i', $inputPath,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
            '-c:a', 'aac', '-b:a', '128k',
            '-hls_time', '6', '-hls_playlist_type', 'vod',
            '-hls_segment_filename', 'seg_%04d.ts',
            'index.m3u8',
        ], $outputDir, null, null, 3600);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('ffmpeg a échoué : '.mb_substr($process->getErrorOutput(), -500));
        }

        return glob(rtrim($outputDir, '/').'/{index.m3u8,seg_*.ts}', GLOB_BRACE) ?: [];
    }
}
