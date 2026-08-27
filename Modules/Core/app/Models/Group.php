<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\GroupFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Obtenir les formateurs assignés au groupe.
     */
    public function trainers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Trainer::class, 'group_trainer', 'group_id', 'trainer_id');
    }

    /**
     * Obtenir les apprenants inscrits dans le groupe.
     */
    public function learners(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Learner::class, 'group_learner', 'group_id', 'learner_id');
    }

    /**
     * Scope pour ne récupérer que les groupes actifs.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Groupes « en cours » : actifs ET dans leur fenêtre de dates.
     * C'est LE filtre du volet apprenant — un groupe suspendu, fermé
     * (end_date dépassée) ou pas encore ouvert ne délivre plus de contenu.
     */
    public function scopeCurrent(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', now());
            });
    }

    /**
     * Statut lisible du groupe pour le volet apprenant.
     */
    public function learnerStatus(): string
    {
        if (! $this->is_active) {
            return 'suspended';
        }
        if ($this->end_date && $this->end_date->isPast() && ! $this->end_date->isToday()) {
            return 'closed';
        }
        if ($this->start_date && $this->start_date->isFuture()) {
            return 'upcoming';
        }

        return 'active';
    }

    /**
     * Obtenir les quiz assignés à ce groupe.
     */
    public function quizzes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'group_quiz', 'group_id', 'quiz_id');
    }

    /**
     * Obtenir les articles assignés à ce groupe.
     */
    public function articles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'group_article', 'group_id', 'article_id');
    }

    /**
     * Obtenir les examens assignés à ce groupe.
     */
    public function exams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'group_exam', 'group_id', 'exam_id');
    }

    /**
     * Obtenir les decks de flashcards assignés à ce groupe.
     */
    public function flashcardDecks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(FlashcardDeck::class, 'group_flashcard_deck', 'group_id', 'flashcard_deck_id');
    }
}
