<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LearnerProfileController extends Controller
{
    /**
     * Espace profil de l'apprenant (données personnelles, thèmes, son, notifications).
     */
    public function index(): View
    {
        $user = Auth::user();
        $learner = $user->learner;

        $xp = $learner->xp ?? $learner->xp()->create([
            'total_xp' => 0,
            'current_level' => 1,
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity_date' => null,
        ]);

        $preferences = $learner->preferences ?? $learner->preferences()->create([
            'locale' => 'fr',
            'theme' => 'light',
            'font_size' => 'medium',
            'sound_enabled' => true,
            'notifications_enabled' => ['new_quiz' => true, 'new_article' => true],
        ]);

        $badges = $learner->badges;

        return view('core::learner.profil', compact('user', 'learner', 'xp', 'preferences', 'badges'));
    }

    /**
     * Mettre à jour les préférences de l'apprenant.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'theme' => 'nullable|string|in:light,dark',
            'font_size' => 'nullable|string|in:small,medium,large',
            'sound_enabled' => 'nullable|boolean',
            'locale' => 'nullable|string|in:fr,en',
        ]);

        $user = Auth::user();
        $learner = $user->learner;

        $preferences = $learner->preferences ?? $learner->preferences()->create([
            'locale' => 'fr',
            'theme' => 'light',
            'font_size' => 'medium',
            'sound_enabled' => true,
            'notifications_enabled' => ['new_quiz' => true, 'new_article' => true],
        ]);

        $preferences->update([
            'theme' => $request->input('theme', $preferences->theme),
            'font_size' => $request->input('font_size', $preferences->font_size),
            'sound_enabled' => $request->has('sound_enabled') ? $request->boolean('sound_enabled') : $preferences->sound_enabled,
            'locale' => $request->input('locale', $preferences->locale),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Préférences mises à jour.',
            'preferences' => $preferences,
        ]);
    }
}
