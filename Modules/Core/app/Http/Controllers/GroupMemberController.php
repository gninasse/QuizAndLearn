<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Core\Models\ExamAttempt;
use Modules\Core\Models\Group;
use Modules\Core\Models\Learner;
use Modules\Core\Models\QuizAttempt;
use Modules\Core\Models\Trainer;

/**
 * Page dédiée « Membres du groupe » : liste détaillée et recherchable des
 * apprenants et formateurs, ajout/retrait, suspension temporaire.
 *
 * Périmètre : les admins voient tous les groupes ; un formateur ne gère que
 * les groupes auxquels il est assigné, et uniquement leurs apprenants
 * (l'affectation des formateurs reste réservée aux admins).
 */
class GroupMemberController extends Controller
{
    /** Nombre maximal de formateurs par groupe (règle déjà appliquée par l'ancienne modale). */
    private const MAX_TRAINERS = 3;

    public function page(int $id): \Illuminate\Contracts\View\View
    {
        $group = $this->authorizedGroup($id);

        return view('core::groups.members', [
            'group' => $group,
            'canManageTrainers' => $this->isAdmin(),
        ]);
    }

    /**
     * GET — toutes les données de la page en un appel.
     */
    public function data(int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $group->load(['learners.user', 'learners.xp', 'learners.groups', 'trainers.user']);

        $learnerIds = $group->learners->pluck('id');

        // Agrégats en une requête chacun (pas de N+1 sur des groupes de 100+).
        $quizStats = QuizAttempt::whereIn('learner_id', $learnerIds)
            ->where('status', 'completed')
            ->selectRaw('learner_id, COUNT(*) as completed, AVG(score) as avg_score, MAX(completed_at) as last_at')
            ->groupBy('learner_id')
            ->get()
            ->keyBy('learner_id');

        $examStats = ExamAttempt::whereIn('learner_id', $learnerIds)
            ->whereIn('status', ['termine', 'time_up'])
            ->selectRaw('learner_id, COUNT(*) as taken, AVG(note_sur_vingt) as avg_note')
            ->groupBy('learner_id')
            ->get()
            ->keyBy('learner_id');

        $learners = $group->learners->map(function (Learner $learner) use ($group, $quizStats, $examStats) {
            $user = $learner->user;
            $quiz = $quizStats->get($learner->id);
            $exam = $examStats->get($learner->id);

            return [
                'learner_id' => $learner->id,
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_name' => $user->user_name,
                'phone' => $user->phone,
                'matricule' => $learner->matricule,
                'avatar_url' => $user->avatar_url,
                'is_active' => (bool) $user->is_active,
                'suspended' => $learner->isSuspended(),
                'suspended_until' => $learner->isSuspended() ? $learner->suspended_until?->toIso8601String() : null,
                'suspension_reason' => $learner->isSuspended() ? $learner->suspension_reason : null,
                'xp' => (int) ($learner->xp?->total_xp ?? 0),
                'level' => (int) ($learner->xp?->current_level ?? 1),
                'streak' => (int) ($learner->xp?->current_streak ?? 0),
                'last_activity' => $learner->xp?->last_activity_date?->toDateString()
                    ?? ($quiz?->last_at ? Carbon::parse($quiz->last_at)->toDateString() : null),
                'quizzes_completed' => (int) ($quiz?->completed ?? 0),
                'quiz_avg_score' => $quiz?->avg_score !== null ? round((float) $quiz->avg_score, 1) : null,
                'exams_taken' => (int) ($exam?->taken ?? 0),
                'exam_avg_note' => $exam?->avg_note !== null ? round((float) $exam->avg_note, 2) : null,
                'other_groups' => $learner->groups
                    ->where('id', '!=', $group->id)
                    ->pluck('name')
                    ->values(),
                'created_at' => $user->created_at?->toDateString(),
            ];
        })->sortBy('full_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $trainers = $group->trainers->map(fn (Trainer $trainer) => [
            'trainer_id' => $trainer->id,
            'full_name' => $trainer->user->full_name,
            'email' => $trainer->user->email,
            'phone' => $trainer->user->phone,
            'specialty' => $trainer->specialty,
            'avatar_url' => $trainer->user->avatar_url,
            'is_active' => (bool) $trainer->user->is_active,
        ])->values();

        return response()->json([
            'success' => true,
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'start_date' => $group->start_date?->toDateString(),
                'end_date' => $group->end_date?->toDateString(),
                'is_active' => (bool) $group->is_active,
                'status' => $group->learnerStatus(),
            ],
            'learners' => $learners,
            'trainers' => $trainers,
            'content' => [
                'quizzes' => $group->quizzes()->count(),
                'exams' => $group->exams()->count(),
                'decks' => $group->flashcardDecks()->count(),
                'articles' => $group->articles()->count(),
            ],
            'max_trainers' => self::MAX_TRAINERS,
        ]);
    }

    /**
     * GET ?type=learner|trainer&q= — candidats non encore membres.
     */
    public function candidates(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $type = $request->query('type') === 'trainer' ? 'trainer' : 'learner';
        $search = trim((string) $request->query('q', ''));

        if ($type === 'trainer') {
            abort_unless($this->isAdmin(), 403);
            $query = Trainer::with('user')->whereNotIn('id', $group->trainers()->pluck('trainers.id'));
        } else {
            $query = Learner::with('user')->whereNotIn('id', $group->learners()->pluck('learners.id'));
        }

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like, $type) {
                $q->whereHas('user', function ($u) use ($like) {
                    $u->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(user_name) LIKE ?', [$like]);
                });
                if ($type === 'learner') {
                    $q->orWhereRaw('LOWER(matricule) LIKE ?', [$like]);
                }
            });
        }

        $rows = $query->limit(30)->get()->map(fn ($member) => [
            'id' => $member->id,
            'full_name' => $member->user->full_name,
            'email' => $member->user->email,
            'matricule' => $member->matricule ?? null,
            'avatar_url' => $member->user->avatar_url,
        ]);

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function addLearners(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $validated = $request->validate([
            'learner_ids' => ['required', 'array', 'min:1'],
            'learner_ids.*' => ['integer', 'exists:learners,id'],
        ]);

        $group->learners()->syncWithoutDetaching($validated['learner_ids']);

        return response()->json([
            'success' => true,
            'message' => count($validated['learner_ids']) > 1
                ? count($validated['learner_ids']).' apprenants ajoutés '.term('to_group').'.'
                : 'Apprenant ajouté '.term('to_group').'.',
        ]);
    }

    public function removeLearner(int $id, int $learnerId): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $group->learners()->detach($learnerId);

        return response()->json(['success' => true, 'message' => 'Apprenant retiré '.term('of_group').'.']);
    }

    public function addTrainers(Request $request, int $id): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);
        $group = $this->authorizedGroup($id);
        $validated = $request->validate([
            'trainer_ids' => ['required', 'array', 'min:1'],
            'trainer_ids.*' => ['integer', 'exists:trainers,id'],
        ]);

        $current = $group->trainers()->pluck('trainers.id');
        $merged = $current->merge($validated['trainer_ids'])->unique();
        if ($merged->count() > self::MAX_TRAINERS) {
            return response()->json([
                'success' => false,
                'message' => ucfirst(term('a_group')).' ne peut pas avoir plus de '.self::MAX_TRAINERS.' formateurs.',
            ], 422);
        }

        $group->trainers()->syncWithoutDetaching($validated['trainer_ids']);

        return response()->json(['success' => true, 'message' => 'Formateur(s) ajouté(s) '.term('to_group').'.']);
    }

    public function removeTrainer(int $id, int $trainerId): JsonResponse
    {
        abort_unless($this->isAdmin(), 403);
        $group = $this->authorizedGroup($id);
        $group->trainers()->detach($trainerId);

        return response()->json(['success' => true, 'message' => 'Formateur retiré '.term('of_group').'.']);
    }

    /**
     * POST — suspension temporaire. L'apprenant reste membre de TOUS ses
     * groupes ; seul son accès au volet apprenant est coupé.
     */
    public function suspend(Request $request, int $id, int $learnerId): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $learner = $group->learners()->where('learners.id', $learnerId)->firstOrFail();

        $validated = $request->validate([
            'until' => ['nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'until.after' => 'La date de fin de suspension doit être dans le futur.',
        ]);

        $until = ! empty($validated['until']) ? Carbon::parse($validated['until']) : null;
        $learner->suspend($until, $validated['reason'] ?? null, auth()->id());

        // Coupe immédiatement l'app mobile (les sessions PWA sont coupées par le middleware).
        $learner->user->tokens()->delete();

        $this->log('learner_suspended', $learner, [
            'group_id' => $group->id,
            'until' => $until?->toIso8601String(),
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $until
                ? 'Apprenant suspendu jusqu\'au '.$until->format('d/m/Y à H:i').'.'
                : 'Apprenant suspendu jusqu\'à levée manuelle.',
        ]);
    }

    public function unsuspend(int $id, int $learnerId): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $learner = $group->learners()->where('learners.id', $learnerId)->firstOrFail();
        $learner->liftSuspension();

        $this->log('learner_unsuspended', $learner, ['group_id' => $group->id]);

        return response()->json(['success' => true, 'message' => 'Suspension levée.']);
    }

    // ------------------------------------------------------------------

    private function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'admin', 'Admin']);
    }

    private function authorizedGroup(int $id): Group
    {
        $group = Group::findOrFail($id);

        if (! $this->isAdmin()) {
            $trainer = auth()->user()->trainer;
            $assigned = $trainer && $group->trainers()->where('trainers.id', $trainer->id)->exists();
            abort_unless($assigned, 403, 'Action non autorisée.');
        }

        return $group;
    }

    private function log(string $event, Learner $learner, array $properties): void
    {
        activity('core')
            ->causedBy(auth()->user())
            ->performedOn($learner)
            ->withProperties($properties + ['learner' => $learner->user->full_name])
            ->tap(function ($activity) {
                $activity->module = 'core';
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log($event);
    }
}
