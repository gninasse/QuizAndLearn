<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;

/**
 * Cochage « Visible en mode découverte » : réservé aux administrateurs.
 */
class ShowcaseAdminController extends Controller
{
    private const MODELS = [
        'quiz' => Quiz::class,
        'article' => Article::class,
        'deck' => FlashcardDeck::class,
    ];

    public function toggle(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorizeAdmin();
        $modelClass = self::MODELS[$type] ?? null;
        abort_if($modelClass === null, 404);

        $validated = $request->validate(['is_showcase' => ['required', 'boolean']]);

        $item = $modelClass::findOrFail($id);
        $item->forceFill(['is_showcase' => (bool) $validated['is_showcase']])->save();

        return response()->json(['success' => true, 'is_showcase' => (bool) $item->is_showcase]);
    }

    public function openForRequests(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate(['open_for_requests' => ['required', 'boolean']]);

        $group = Group::findOrFail($id);
        $group->forceFill(['open_for_requests' => (bool) $validated['open_for_requests']])->save();

        return response()->json(['success' => true, 'open_for_requests' => (bool) $group->open_for_requests]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['super-admin', 'admin', 'Admin']),
            403,
            'Action non autorisée.'
        );
    }
}
