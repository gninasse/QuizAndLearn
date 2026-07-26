<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashcardSession extends Model
{
    use HasFactory;

    protected $table = 'flashcard_sessions';

    protected $fillable = [
        'learner_id',
        'deck_id',
        'date_debut',
        'date_fin',
        'duree_seconds',
        'cartes_etudiees',
        'cartes_nouvelles',
        'cartes_revues',
        'cartes_maitrisees',
        'grades',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'duree_seconds' => 'integer',
            'cartes_etudiees' => 'integer',
            'cartes_nouvelles' => 'integer',
            'cartes_revues' => 'integer',
            'cartes_maitrisees' => 'integer',
            'grades' => 'json',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'learner_id');
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(FlashcardDeck::class, 'deck_id');
    }
}
