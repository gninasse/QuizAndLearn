<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Models\Group;
use Modules\Core\Services\Kpi\KpiScope;
use Modules\Core\Services\Kpi\KpiService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $stats = [
            'users_count' => \Modules\Core\Models\User::count(),
            'roles_count' => \Spatie\Permission\Models\Role::count(),
            'modules_count' => \Modules\Core\Models\Module::count(),
            'activities_today' => \Modules\Core\Models\Activity::whereDate('created_at', today())->count(),
        ];

        $recentActivities = \Modules\Core\Models\Activity::with('causer')
            ->latest()
            ->limit(10)
            ->get();

        $user = auth()->user();
        $groups = Group::query()
            ->when(! $user->hasAnyRole(['super-admin', 'admin', 'Admin']), function ($q) use ($user) {
                $trainerId = $user->trainer?->id;
                $q->whereHas('trainers', fn ($t) => $t->where('trainers.id', $trainerId));
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('core::dashboard.index', compact('stats', 'recentActivities', 'groups'));
    }

    /** Tous les indicateurs (JSON, mis en cache par KpiService). */
    public function kpis(Request $request, KpiService $kpis): JsonResponse
    {
        return response()->json($kpis->all($this->scope($request)));
    }

    /** Export CSV d'un tableau du tableau de bord, même périmètre et filtres que le JSON. */
    public function export(Request $request, KpiService $kpis, string $table): StreamedResponse
    {
        abort_unless(in_array($table, ['completion', 'failed_questions', 'inactive'], true), 404);

        $scope = $this->scope($request);
        $data = $table === 'inactive' ? $kpis->followUp($scope) : $kpis->learning($scope);
        $rows = $data[$table];

        $typeLabels = ['quiz' => 'Quiz', 'article' => 'Article', 'deck' => 'Deck', 'flashcard_deck' => 'Deck'];

        [$header, $map] = match ($table) {
            'completion' => [
                ['Type', 'Contenu', 'Assignés', 'Terminé', 'Taux (%)'],
                fn ($r) => [$typeLabels[$r['type']] ?? $r['type'], $r['title'], $r['assigned'], $r['completed'], $r['rate']],
            ],
            'failed_questions' => [
                ['Question', 'Quiz', 'Réponses', "Taux d'échec (%)"],
                fn ($r) => [$r['label'], $r['quiz_title'], $r['answers'], $r['fail_rate']],
            ],
            'inactive' => [
                ['Apprenant', 'Matricule', 'Dernière activité'],
                fn ($r) => [$r['name'], $r['matricule'], $r['last_activity'] ?? 'Jamais'],
            ],
        };

        $filename = sprintf('kpi-%s-%s.csv', $table, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($header, $map, $rows) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, $header, ';');
            foreach ($rows as $row) {
                fputcsv($h, array_map(fn ($v) => $this->csvSafe($v), $map($row)), ';');
            }
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function scope(Request $request): KpiScope
    {
        return KpiScope::for(
            $request->user(),
            (string) $request->query('period', '30'),
            $request->query('from'),
            $request->query('to'),
            $request->filled('group') ? (int) $request->query('group') : null,
        );
    }

    /** Neutralise l'injection de formules dans les tableurs. */
    private function csvSafe(mixed $v): mixed
    {
        return is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v;
    }
}
