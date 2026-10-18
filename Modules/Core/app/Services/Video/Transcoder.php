<?php

namespace Modules\Core\Services\Video;

interface Transcoder
{
    /**
     * Convertit une vidéo en HLS dans $outputDir.
     *
     * @return string[] chemins locaux produits (playlist + segments)
     */
    public function toHls(string $inputPath, string $outputDir): array;
}
