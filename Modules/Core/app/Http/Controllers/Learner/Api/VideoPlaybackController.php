<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\User;
use Modules\Core\Models\Video;
use Modules\Core\Services\GuestToken;
use Modules\Core\Services\ShowcaseMedia;
use Modules\Core\Services\ShowcaseSettings;
use Modules\Core\Services\Video\VideoManager;

/**
 * Source de lecture d'une vidéo par référence. Apprenant (session/Bearer,
 * consultation comprise) ou invité vitrine (jeton + référence vitrine).
 */
class VideoPlaybackController extends Controller
{
    public function __construct(private VideoManager $videos) {}

    public function show(Request $request, string $ref): JsonResponse
    {
        return $this->respond($ref, $request->user());
    }

    public function guest(Request $request, string $ref): JsonResponse
    {
        $token = $request->cookie(GuestToken::COOKIE) ?? $request->header('X-Guest-Token');
        abort_unless(
            app(ShowcaseSettings::class)->enabled() && GuestToken::valid($token) && ShowcaseMedia::allowsVideo($ref),
            403,
            'Accès aux vidéos réservé aux utilisateurs connectés.'
        );

        return $this->respond($ref, null);
    }

    private function respond(string $ref, ?User $viewer): JsonResponse
    {
        $video = preg_match('/^[0-9a-f-]{36}$/i', $ref) ? Video::where('ref', $ref)->first() : null;
        abort_unless($video, 404);

        if ($video->status === 'processing') {
            return response()->json(['status' => 'processing'], 202);
        }
        if ($video->status === 'failed') {
            return response()->json(['status' => 'failed'], 410);
        }

        return response()->json($this->videos->playback($video, $viewer)->toArray());
    }

    /**
     * Playlist HLS réécrite : chaque segment devient une URL S3 temporaire.
     * Protégée par le middleware `signed` seulement (voir routes).
     */
    public function hlsPlaylist(string $ref, string $file): Response
    {
        $video = Video::where('ref', $ref)->where('driver', 's3_hls')->where('status', 'ready')->first();
        abort_unless($video, 404);

        $disk = Storage::disk((string) config('media.video.s3_hls.disk', 'videos'));
        $path = "hls/{$video->ref}/{$file}";
        abort_unless($disk->exists($path), 404);

        $expires = now()->addSeconds((int) config('media.video.signed_ttl', 900));
        $lines = preg_split('/\r\n|\n|\r/', (string) $disk->get($path));
        $out = array_map(function (string $line) use ($disk, $video, $expires) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                return $line;
            }

            return $disk->temporaryUrl("hls/{$video->ref}/".basename($trim), $expires);
        }, $lines);

        return response(implode("\n", $out), 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
