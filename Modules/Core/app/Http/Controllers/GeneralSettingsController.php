<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Services\Push\PushNotifier;
use Modules\Core\Services\Push\PushSettings;
use Modules\Core\Services\Terminology;

/**
 * Paramètres généraux (terminologie) : réservé aux administrateurs.
 */
class GeneralSettingsController extends Controller
{
    public function edit(Terminology $terminology, PushSettings $push): View
    {
        $this->authorizeAdmin();

        $learner = auth()->user()?->learner;

        return view('core::settings.general', [
            'config' => $terminology->config(),
            'presets' => Terminology::PRESETS,
            'push' => $push->toArray(),
            'vapidConfigured' => PushSettings::vapidConfigured(),
            'canTestPush' => $learner !== null && $learner->pushSubscriptions()->exists(),
        ]);
    }

    public function save(Request $request, Terminology $terminology, PushSettings $push): JsonResponse
    {
        $this->authorizeAdmin();

        $word = ['regex:/^[\pL][\pL \'’-]*$/u', 'max:30', 'not_regex:/^(l|le|la|les|un|une|du|des)([\'’ ]|$)/iu'];
        $validated = $request->validate([
            'preset' => ['required', Rule::in([...array_keys(Terminology::PRESETS), 'custom'])],
            'singular' => ['exclude_unless:preset,custom', 'required', 'string', ...$word],
            'plural' => ['exclude_unless:preset,custom', 'required', 'string', ...$word],
            'feminine' => ['exclude_unless:preset,custom', 'nullable', 'boolean'],
            'push' => ['sometimes', 'array'],
            'push.types' => ['sometimes', 'array'],
            'push.types.*' => ['boolean'],
            'push.daily_hour' => ['required_with:push', 'integer', 'between:'.PushSettings::DAILY_HOUR_MIN.','.PushSettings::DAILY_HOUR_MAX],
            'push.daily_cap' => ['required_with:push', 'integer', 'between:1,5'],
        ], [
            'singular.not_regex' => 'Saisissez le mot seul, sans article (ex. « équipe », pas « l\'équipe »).',
            'plural.not_regex' => 'Saisissez le mot seul, sans article (ex. « équipes », pas « les équipes »).',
        ], [
            'singular' => 'terme au singulier',
            'plural' => 'terme au pluriel',
            'push.daily_hour' => 'heure du rappel quotidien',
            'push.daily_cap' => 'plafond de notifications par jour',
        ]);

        $terminology->save($validated);
        if (isset($validated['push'])) {
            $push->save($validated['push']);
        }

        return response()->json(['success' => true, 'message' => 'Paramètres enregistrés.']);
    }

    public function sendPushTest(PushNotifier $notifier): JsonResponse
    {
        $this->authorizeAdmin();

        if (! PushSettings::vapidConfigured()) {
            return response()->json(['success' => false, 'message' => 'Les clés VAPID ou VAPID_SUBJECT ne sont pas configurés (php artisan push:vapid).'], 422);
        }

        $learner = auth()->user()?->learner;
        if ($learner === null || ! $learner->pushSubscriptions()->exists()) {
            return response()->json(['success' => false, 'message' => 'Aucun abonnement : abonnez-vous depuis l\'espace apprenant d\'un compte de test.'], 422);
        }

        $count = $notifier->sendTest($learner);

        return response()->json(['success' => true, 'message' => "Notification envoyée à {$count} appareil(s)."]);
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
