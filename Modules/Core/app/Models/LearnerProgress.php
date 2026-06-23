<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LearnerProgress extends Model
{
    use HasFactory;

    protected $table = 'learner_progress';

    protected $fillable = [
        'learner_id',
        'content_type',
        'content_id',
        'status',
        'progress_percentage',
        'time_spent',
        'rating',
        'is_favorite',
        'last_accessed_at',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'integer',
            'time_spent' => 'integer',
            'rating' => 'integer',
            'is_favorite' => 'boolean',
            'last_accessed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /**
     * Morph relation for the progressed content (Article or Quiz).
     */
    public function content(): MorphTo
    {
        return $this->morphTo(null, 'content_type', 'content_id');
    }
}
