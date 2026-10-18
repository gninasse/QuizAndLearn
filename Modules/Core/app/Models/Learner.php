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
        'suspended_at',
        'suspended_until',
        'suspension_reason',
        'suspended_by',
    ];

    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'suspended_until' => 'datetime',
        ];
    }

    /**
     * Suspension en vigueur : posée et pas encore échue. Une suspension
     * dont la date de fin est passée se lève d'elle-même (aucun job requis).
     */
    public function isSuspended(): bool
    {
        if (! $this->suspended_at) {
            return false;
        }

        return $this->suspended_until === null || $this->suspended_until->isFuture();
    }

    /**
     * Suspend l'accès au volet apprenant sans toucher aux groupes.
     */
    public function suspend(?\DateTimeInterface $until, ?string $reason, ?int $byUserId): void
    {
        $this->forceFill([
            'suspended_at' => now(),
            'suspended_until' => $until,
            'suspension_reason' => $reason,
            'suspended_by' => $byUserId,
        ])->save();
    }

    public function liftSuspension(): void
    {
        $this->forceFill([
            'suspended_at' => null,
            'suspended_until' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();
    }

    /**
     * Message affiché à l'apprenant suspendu (login, API).
     */
    public function suspensionMessage(): string
    {
        $message = $this->suspended_until
            ? 'Votre accès est suspendu jusqu\'au '.$this->suspended_until->format('d/m/Y à H:i').'.'
            : 'Votre accès est suspendu jusqu\'à nouvel ordre.';

        if ($this->suspension_reason) {
            $message .= ' Motif : '.$this->suspension_reason;
        }

        return $message.' Contactez votre formateur pour plus d\'informations.';
    }

    public function suspendedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

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
     * Obtenir les révisions de cartes individuelles (SRS).
     */
    public function cardReviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FlashcardItemReview::class, 'learner_id');
    }

    /**
     * Obtenir les sessions d'étude des flashcards de l'apprenant.
     */
    public function cardSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FlashcardSession::class, 'learner_id');
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

    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
