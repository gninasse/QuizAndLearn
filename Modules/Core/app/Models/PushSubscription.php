<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement Web Push d'un navigateur d'apprenant. L'endpoint est unique
 * (via son hash) : un même navigateur n'appartient qu'à un seul apprenant.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'learner_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'user_agent',
        'last_used_at',
    ];

    protected $hidden = ['p256dh', 'auth'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
