<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\LearnerFactory;

class Learner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matricule',
    ];

    /**
     * Obtenir l'utilisateur associé à l'apprenant.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir les groupes d'appartenance de l'apprenant.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_learner', 'learner_id', 'group_id');
    }

    /**
     * Obtenir les tentatives de quiz de l'apprenant.
     */
    public function attempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Obtenir le suivi de progression de l'apprenant.
     */
    public function progress(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LearnerProgress::class);
    }

    /**
     * Obtenir les flashcards de l'apprenant (répétition espacée).
     */
    public function flashcards(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Flashcard::class);
    }

    /**
     * Obtenir les badges obtenus par l'apprenant.
     */
    public function badges(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'learner_badges', 'learner_id', 'badge_id')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    /**
     * Obtenir les informations XP, niveaux et streak de l'apprenant.
     */
    public function xp(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LearnerXp::class);
    }

    /**
     * Obtenir les rapports d'erreurs signalés par l'apprenant.
     */
    public function errorReports(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ErrorReport::class);
    }

    /**
     * Obtenir les tentatives de capture d'écran détectées de l'apprenant.
     */
    public function screenshotAttempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScreenshotAttempt::class);
    }

    /**
     * Obtenir les préférences utilisateur de l'apprenant.
     */
    public function preferences(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LearnerPreference::class);
    }
}
