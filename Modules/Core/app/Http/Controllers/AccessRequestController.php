<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Models\AccessRequest;
use Modules\Core\Models\AccessRequestPayment;
use Modules\Core\Services\ShowcaseSettings;
use Modules\Core\Support\PhoneNumber;

/**
 * Back-office « Demandes d'accès » du mode découverte : suivi des demandes
 * et des paiements Mobile Money saisis à la main. Réservé aux admins.
 */
class AccessRequestController extends Controller
{
    public function index(): \Illuminate\Contracts\View\View
    {
        $this->authorizeAdmin();

        return view('core::access-requests.index', [
            'statuses' => AccessRequest::STATUSES,
            'operators' => AccessRequestPayment::OPERATORS,
            'currency' => app(ShowcaseSettings::class)->currency(),
        ]);
    }

    /**
     * GET ?q=&status= — liste et compteurs par statut.
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $counts = array_fill_keys(AccessRequest::STATUSES, 0);
        foreach (AccessRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $total) {
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $total;
            }
        }

        $query = AccessRequest::query()->with('group:id,name')->withCount('payments')->withSum('payments', 'amount');

        $status = $request->query('status');
        if (is_string($status) && in_array($status, AccessRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($like, $digits) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE ?", [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        $rows = $query->latest('id')->limit(500)->get()->map(fn (AccessRequest $r) => $this->summary($r))->values();

        return response()->json(['success' => true, 'rows' => $rows, 'counts' => $counts]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(['success' => true, 'request' => $this->detail(AccessRequest::findOrFail($id))]);
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin();
        $accessRequest = AccessRequest::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(AccessRequest::STATUSES)],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        if ($accessRequest->status !== $data['status']) {
            $accessRequest->transitionTo($data['status'], auth()->user(), $data['comment'] ?? null);
            $this->log('access_request.status', $accessRequest, ['to' => $data['status']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour.',
            'request' => $this->detail($accessRequest->fresh()),
        ]);
    }

    public function notes(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin();
        $accessRequest = AccessRequest::findOrFail($id);

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:5000']]);
        $accessRequest->update(['notes' => $data['notes'] ?? null]);

        return response()->json(['success' => true, 'message' => 'Notes enregistrées.']);
    }

    public function storePayment(Request $request, int $id): JsonResponse
    {
        $this->authorizeAdmin();
        $accessRequest = AccessRequest::findOrFail($id);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'operator' => ['required', Rule::in(AccessRequestPayment::OPERATORS)],
            'reference' => ['required', 'string', 'max:100'],
            'paid_at' => ['required', 'date'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payerPhone = null;
        if (filled($data['payer_phone'] ?? null)) {
            $payerPhone = PhoneNumber::normalize($data['payer_phone']);
            if ($payerPhone === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le numéro du payeur est invalide.',
                    'errors' => ['payer_phone' => ['Le numéro du payeur est invalide.']],
                ], 422);
            }
        }

        $accessRequest->payments()->create([
            'amount' => $data['amount'],
            'currency' => app(ShowcaseSettings::class)->currency(),
            'operator' => $data['operator'],
            'reference' => $data['reference'],
            'paid_at' => $data['paid_at'],
            'payer_phone' => $payerPhone,
            'note' => $data['note'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        if (in_array($accessRequest->status, ['new', 'contacted', 'payment_pending'], true)) {
            $accessRequest->transitionTo('paid', auth()->user(), 'Paiement enregistré ('.$data['reference'].')');
        }
        $this->log('access_request.payment', $accessRequest, ['amount' => $data['amount'], 'operator' => $data['operator']]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement enregistré.',
            'request' => $this->detail($accessRequest->fresh()),
        ], 201);
    }

    public function destroyPayment(int $id, int $paymentId): JsonResponse
    {
        $this->authorizeAdmin();
        $accessRequest = AccessRequest::findOrFail($id);
        $accessRequest->payments()->findOrFail($paymentId)->delete();
        $this->log('access_request.payment_deleted', $accessRequest, ['payment_id' => $paymentId]);

        return response()->json([
            'success' => true,
            'message' => 'Paiement supprimé.',
            'request' => $this->detail($accessRequest->fresh()),
        ]);
    }

    private function summary(AccessRequest $r): array
    {
        return [
            'id' => $r->id,
            'first_name' => $r->first_name,
            'last_name' => $r->last_name,
            'full_name' => trim($r->first_name.' '.$r->last_name),
            'phone' => $r->phone,
            'email' => $r->email,
            'organization' => $r->organization,
            'group' => $r->group?->name,
            'status' => $r->status,
            'payments_count' => (int) $r->payments_count,
            'paid_total' => (int) ($r->payments_sum_amount ?? 0),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function detail(AccessRequest $r): array
    {
        $r->load(['group:id,name', 'payments.recorder', 'events.user', 'handler']);

        return $this->summary($r->loadCount('payments')->loadSum('payments', 'amount')) + [
            'whatsapp' => $r->whatsapp,
            'message' => $r->message,
            'notes' => $r->notes,
            'consent_at' => $r->consent_at?->toIso8601String(),
            'learner_id' => $r->learner_id,
            'handled_by' => $r->handler?->full_name,
            'payments' => $r->payments->sortByDesc('id')->values()->map(fn (AccessRequestPayment $p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'operator' => $p->operator,
                'reference' => $p->reference,
                'paid_at' => $p->paid_at?->toDateString(),
                'payer_phone' => $p->payer_phone,
                'note' => $p->note,
                'recorded_by' => $p->recorder?->full_name,
            ]),
            'events' => $r->events->sortByDesc('id')->values()->map(fn ($e) => [
                'from_status' => $e->from_status,
                'to_status' => $e->to_status,
                'comment' => $e->comment,
                'user' => $e->user?->full_name,
                'created_at' => $e->created_at?->toIso8601String(),
            ]),
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403, 'Action non autorisée.');
    }

    private function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'admin', 'Admin']);
    }

    private function log(string $event, AccessRequest $accessRequest, array $properties): void
    {
        activity('core')
            ->causedBy(auth()->user())
            ->performedOn($accessRequest)
            ->withProperties($properties)
            ->tap(function ($activity) {
                $activity->module = 'core';
                $activity->ip_address = request()->ip();
                $activity->user_agent = request()->userAgent();
            })
            ->log($event);
    }
}
