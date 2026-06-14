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
}
