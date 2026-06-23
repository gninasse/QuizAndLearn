<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'condition_type',
        'condition_value',
    ];

    protected function casts(): array
    {
        return [
            'condition_value' => 'array',
        ];
    }

    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'learner_badges', 'badge_id', 'learner_id')
            ->withPivot('earned_at')
            ->withTimestamps();
    }
}
