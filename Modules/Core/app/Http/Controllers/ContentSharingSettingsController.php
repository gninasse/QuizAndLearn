<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Models\Group;
use Modules\Core\Services\ContentSharing;
use Modules\Core\Services\IsolationMigrator;

/**
 * Mode de partage des contenus entre groupes et bascule `shared` → `isolated` :
 * réservé aux administrateurs.
 */
class ContentSharingSettingsController extends Controller
{
    public const TYPE_LABELS = [
        'article' => 'Article',
        'quiz' => 'Quiz',
        'exam' => 'Examen',
        'deck' => 'Deck',
        'resource' => 'Ressource',
    ];

    public function __construct(private IsolationMigrator $migrator) {}

    public function edit(): View
    {
        $this->authorizeAdmin();

        return view('core::settings.content-sharing', [
            'mode' => ContentSharing::mode(),
            'conflicts' => $this->migrator->conflicts(),
            'groupNames' => Group::query()->pluck('name', 'id'),
            'typeLabels' => self::TYPE_LABELS,
        ]);
    }

    public function simulate(): JsonResponse
    {
        $this->authorizeAdmin();

        $titles = $this->migrator->conflicts()->mapWithKeys(fn ($c) => [$c['type'].':'.$c['id'] => $c['title']]);

        $multi = $this->migrator->multiExemplarLearners();

        return response()->json([
            'success' => true,
            'plan' => $this->migrator->plan(),
            'multi_exemplar_learners' => $multi,
            'multi_exemplar_message' => $multi === 1
                ? '1 apprenant verra plusieurs exemplaires'
                : "{$multi} apprenants verront plusieurs exemplaires",
            'titles' => $titles,
            'groups' => Group::query()->pluck('name', 'id'),
        ]);
    }

    public function apply(): JsonResponse
    {
        $this->authorizeAdmin();

        $result = $this->migrator->apply(auth()->user());

        return response()->json([
            'success' => true,
            ...$result,
            'message' => "{$result['copied']} copie(s) créée(s), {$result['moved_rows']} ligne(s) d'historique transférée(s), "
                ."{$result['duplicated_attempts']} tentative(s) d'examen dupliquée(s).",
        ]);
    }

    public function mode(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate(['mode' => ['required', Rule::in(ContentSharing::MODES)]]);

        if ($validated['mode'] === 'isolated' && $this->migrator->conflicts()->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Des contenus appartiennent encore à plusieurs '.term('groups').'. Lancez d\'abord la bascule.',
            ], 422);
        }

        ContentSharing::setMode($validated['mode']);

        return response()->json([
            'success' => true,
            'mode' => $validated['mode'],
            'message' => $validated['mode'] === 'isolated' ? 'Mode isolé activé.' : 'Mode partagé activé.',
        ]);
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
