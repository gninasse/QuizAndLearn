<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des notifications push envoyées (plafond quotidien, dédoublonnage par `ref`).
 */
class PushDelivery extends Model
{
    public $timestamps = false;

    protected $fillable = ['learner_id', 'type', 'ref', 'sent_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
