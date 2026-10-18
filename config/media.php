<?php

/*
|--------------------------------------------------------------------------
| Médias pédagogiques (images, audio, vidéo)
|--------------------------------------------------------------------------
| Tailles maximales acceptées à l'upload, en kilo-octets. La limite vidéo
| doit rester cohérente avec upload_max_filesize / post_max_size (PHP) et
| client_max_body_size (nginx) — voir DEPLOIEMENT.md.
*/

return [
    'image_max_kb' => (int) env('MEDIA_IMAGE_MAX_MB', 5) * 1024,
    'audio_max_kb' => (int) env('MEDIA_AUDIO_MAX_MB', 10) * 1024,
    'video_max_kb' => (int) env('MEDIA_VIDEO_MAX_MB', 100) * 1024,
];
