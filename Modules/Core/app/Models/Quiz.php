<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\QuizFactory;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'duration',
        'passing_score',
        'is_active',
        'shuffle_questions',
        'max_attempts',
        'show_correct_answers',
        'allow_partial_score',
        'available_from',
        'available_until',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'passing_score' => 'integer',
            'is_active' => 'boolean',
            'shuffle_questions' => 'boolean',
            'max_attempts' => 'integer',
            'show_correct_answers' => 'boolean',
            'allow_partial_score' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
        ];
    }

    /**
     * Obtenir l'utilisateur qui a créé le quiz.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Obtenir les questions associées au quiz.
     */
    public function questions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order', 'asc');
    }

    /**
     * Obtenir les groupes d'apprenants assignés à ce quiz.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_quiz', 'quiz_id', 'group_id');
    }
}
