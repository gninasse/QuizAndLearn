<?php

namespace Modules\Core\Http\Controllers\Learner\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Services\LearnerContentService;

/**
 * Contenu du volet apprenant (API v1) : amorçage complet + delta sync.
 */
class ContentController extends Controller
{
    public function __construct(
        private readonly LearnerContentService $content,
    ) {}

    /**
     * GET /api/learner/v1/bootstrap — payload complet initial + cursor.
     */
    public function bootstrap(): JsonResponse
    {
        $learner = Auth::user()->learner;

        return response()->json([
            'success' => true,
            ...$this->content->bootstrap($learner),
        ]);
    }

    /**
     * GET /api/learner/v1/changes?since={cursor} — delta depuis un cursor.
     */
    public function changes(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['required', 'date'],
        ]);

        $learner = Auth::user()->learner;
        $since = Carbon::parse($request->query('since'));

        return response()->json([
            'success' => true,
            ...$this->content->changes($learner, $since),
        ]);
    }
}
