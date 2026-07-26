<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerActionLog extends Model
{
    protected $table = 'learner_action_log';

    protected $fillable = [
        'learner_id',
        'client_action_id',
        'type',
        'status',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
