<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Article;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Group;
use Modules\Core\Models\Quiz;
use Modules\Core\Services\ShowcaseSettings;

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

    public function settings(ShowcaseSettings $settings): View
    {
        $this->authorizeAdmin();

        return view('core::showcase.settings', [
            'settings' => $settings,
            'operators' => [
                'orange_money' => 'Orange Money',
                'mtn_momo' => 'MTN MoMo',
                'moov_money' => 'Moov Money',
                'wave' => 'Wave',
                'other' => 'Autre',
            ],
        ]);
    }

    public function saveSettings(Request $request, ShowcaseSettings $settings): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'default_country_code' => ['required', 'digits_between:1,4'],
            'currency' => ['required', 'string', 'max:8'],
            'fee' => ['nullable', 'integer', 'min:0'],
            'payment_accounts' => ['nullable', 'array', 'max:10'],
            'payment_accounts.*.operator' => ['required', Rule::in(ShowcaseSettings::OPERATORS)],
            'payment_accounts.*.number' => ['required', 'string', 'max:30'],
            'payment_accounts.*.holder' => ['required', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'notification_emails' => ['nullable', 'string', 'max:1000'],
            'privacy_text' => ['nullable', 'string', 'max:20000'],
            'privacy_url' => ['nullable', 'url', 'max:500'],
            'retention_months' => ['required', 'integer', 'min:1', 'max:120'],
        ], [], [
            'default_country_code' => 'indicatif',
            'payment_accounts.*.operator' => 'opérateur',
            'payment_accounts.*.number' => 'numéro',
            'payment_accounts.*.holder' => 'bénéficiaire',
            'privacy_url' => 'lien de confidentialité',
            'retention_months' => 'durée de conservation',
        ]);

        foreach (preg_split('/[\s,;]+/', (string) ($validated['notification_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json([
                    'success' => false,
                    'message' => "Adresse e-mail invalide : {$email}",
                    'errors' => ['notification_emails' => ["Adresse e-mail invalide : {$email}"]],
                ], 422);
            }
        }

        $settings->save($validated);

        return response()->json(['success' => true, 'message' => 'Réglages enregistrés.']);
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
