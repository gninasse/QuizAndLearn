<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccessRequest extends Model
{
    public const STATUSES = [
        'new', 'contacted', 'payment_pending', 'paid', 'account_created', 'rejected', 'abandoned',
    ];

    /** Statuts d'une demande encore en cours de traitement. */
    public const OPEN = ['new', 'contacted', 'payment_pending', 'paid'];

    protected $fillable = [
        'first_name', 'last_name', 'phone', 'whatsapp', 'email', 'organization',
        'group_id', 'message', 'consent_at', 'consent_text', 'status', 'notes',
        'ip', 'user_agent', 'learner_id', 'handled_by', 'extra_fields',
    ];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime', 'extra_fields' => 'array'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AccessRequestPayment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AccessRequestEvent::class);
    }

    /**
     * Change le statut et trace la transition dans l'historique.
     */
    public function transitionTo(string $status, ?User $by, ?string $comment = null): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Statut inconnu : {$status}");
        }

        $from = $this->status;
        $this->status = $status;
        if ($by) {
            $this->handled_by = $by->id;
        }
        $this->save();

        $this->events()->create([
            'from_status' => $from,
            'to_status' => $status,
            'comment' => $comment,
            'user_id' => $by?->id,
        ]);
    }
}
