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
     * GET /api/learner/v1/leaderboard — classement XP par groupe.
     *
     * Pour chaque groupe de l'apprenant : top 20 + sa propre position.
     */
    public function leaderboard(): JsonResponse
    {
        $learner = Auth::user()->learner;

        $groups = $learner->groups()->with(['learners.user', 'learners.xp'])->get();

        $payload = $groups->map(function ($group) use ($learner) {
            $ranked = $group->learners
                ->map(function ($peer) {
                    $xp = $peer->xp;

                    return [
                        'learner_id' => $peer->id,
                        'name' => $peer->user?->full_name ?? 'Apprenant',
                        'total_xp' => $xp?->total_xp ?? 0,
                        'current_level' => $xp?->current_level ?? 1,
                        'current_streak' => $xp?->current_streak ?? 0,
                    ];
                })
                ->sortByDesc('total_xp')
                ->values()
                ->map(fn ($row, $index) => [...$row, 'rank' => $index + 1]);

            $me = $ranked->firstWhere('learner_id', $learner->id);

            return [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'total_participants' => $ranked->count(),
                'my_rank' => $me['rank'] ?? null,
                'rows' => $ranked->take(20)
                    ->map(fn ($row) => [
                        'rank' => $row['rank'],
                        'name' => $row['name'],
                        'total_xp' => $row['total_xp'],
                        'current_level' => $row['current_level'],
                        'current_streak' => $row['current_streak'],
                        'is_me' => $row['learner_id'] === $learner->id,
                    ])
                    ->values(),
            ];
        });

        return response()->json(['success' => true, 'groups' => $payload]);
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
