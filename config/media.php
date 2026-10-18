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

    /*
    | Pilote d'hébergement vidéo : local | s3_hls | bunny. Les vidéos déjà
    | enregistrées restent lues par leur pilote d'origine (colonne `driver`).
    */
    'video' => [
        'driver' => env('MEDIA_VIDEO_DRIVER', 'local'),
        'signed_ttl' => 900, // secondes (15 min) pour les URL signées externes
        's3_hls' => [
            'disk' => env('VIDEO_S3_DISK', 'videos'),
            // Disque du test de connexion (mêmes accès, délais courts).
            // Sans VIDEO_S3_HEALTH_DISK, un disque personnalisé est testé tel quel.
            'health_disk' => env('VIDEO_S3_HEALTH_DISK', env('VIDEO_S3_DISK', 'videos') === 'videos' ? 'videos_health' : null),
            'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        ],
        'bunny' => [
            'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
            'api_key' => env('BUNNY_STREAM_API_KEY'),
            'cdn_host' => env('BUNNY_STREAM_CDN_HOST'),
            'token_key' => env('BUNNY_STREAM_TOKEN_KEY'),
        ],
    ],
];
