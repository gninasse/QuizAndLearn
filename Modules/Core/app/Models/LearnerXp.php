<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerXp extends Model
{
    use HasFactory;

    protected $table = 'learner_xp';

    protected $fillable = [
        'learner_id',
        'total_xp',
        'current_level',
        'current_streak',
        'longest_streak',
        'last_activity_date',
    ];

    protected function casts(): array
    {
        return [
            'total_xp' => 'integer',
            'current_level' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_activity_date' => 'date',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
