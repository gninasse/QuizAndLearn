<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id',
        'quiz_id',
        'started_at',
        'completed_at',
        'submitted_at',
        'score',
        'points_earned',
        'points_total',
        'passed',
        'time_spent',
        'answers',
        'attempt_number',
        'status',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'score' => 'decimal:2',
            'points_earned' => 'integer',
            'points_total' => 'integer',
            'passed' => 'boolean',
            'time_spent' => 'integer',
            'answers' => 'array',
            'attempt_number' => 'integer',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function quizAnswers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'attempt_id');
    }
}
