<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class LearnerBadge extends Pivot
{
    protected $table = 'learner_badges';

    protected $fillable = [
        'learner_id',
        'badge_id',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
        ];
    }
}
