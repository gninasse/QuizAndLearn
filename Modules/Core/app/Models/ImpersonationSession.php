<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session de consultation « Voir en tant que » : un super admin consulte
 * l'espace d'un apprenant en lecture seule. Seul le hash SHA-256 du jeton
 * est conservé.
 */
class ImpersonationSession extends Model
{
    protected $fillable = [
        'admin_id',
        'learner_id',
        'token_hash',
        'expires_at',
        'ended_at',
        'ip',
        'user_agent',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /**
     * Sessions ni terminées ni expirées.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at')->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}
