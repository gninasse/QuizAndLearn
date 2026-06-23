<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Flashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id',
        'question_id',
        'difficulty_factor',
        'interval_days',
        'repetitions',
        'next_review_date',
        'last_reviewed_at',
        'ease_rating',
    ];

    protected function casts(): array
    {
        return [
            'difficulty_factor' => 'decimal:2',
            'interval_days' => 'integer',
            'repetitions' => 'integer',
            'next_review_date' => 'date',
            'last_reviewed_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
