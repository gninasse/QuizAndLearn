<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'learner_id',
        'date_debut',
        'date_fin',
        'duree_reelle',
        'answers',
        'score_brut',
        'score_total',
        'pourcentage',
        'note_sur_vingt',
        'status',
        'capture_attempts',
        'navigation_violations',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'duree_reelle' => 'integer',
            'answers' => 'array',
            'score_brut' => 'decimal:2',
            'score_total' => 'decimal:2',
            'pourcentage' => 'decimal:2',
            'note_sur_vingt' => 'decimal:2',
            'capture_attempts' => 'integer',
            'navigation_violations' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
