<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json($this->videos->playback($video, $viewer ?? new User)->toArray());
    }
}
