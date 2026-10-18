<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Services\Terminology;

/**
 * Paramètres généraux (terminologie) : réservé aux administrateurs.
 */
class GeneralSettingsController extends Controller
{
    public function edit(Terminology $terminology): View
    {
        $this->authorizeAdmin();

        return view('core::settings.general', [
            'config' => $terminology->config(),
            'presets' => Terminology::PRESETS,
        ]);
    }

    public function save(Request $request, Terminology $terminology): JsonResponse
    {
        $this->authorizeAdmin();

        $word = ['regex:/^[\pL][\pL \'’-]*$/u', 'max:30'];
        $validated = $request->validate([
            'preset' => ['required', Rule::in([...array_keys(Terminology::PRESETS), 'custom'])],
            'singular' => ['exclude_unless:preset,custom', 'required', 'string', ...$word],
            'plural' => ['exclude_unless:preset,custom', 'required', 'string', ...$word],
            'feminine' => ['exclude_unless:preset,custom', 'nullable', 'boolean'],
        ], [], [
            'singular' => 'terme au singulier',
            'plural' => 'terme au pluriel',
        ]);

        $terminology->save($validated);

        return response()->json(['success' => true, 'message' => 'Paramètres enregistrés.']);
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
