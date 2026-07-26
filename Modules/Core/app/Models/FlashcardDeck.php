<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashcardDeck extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'description',
        'matiere',
        'source_type',
        'source_id',
        'created_by',
        'is_public',
        'algorithme',
        'easiness_default',
        'interval_min',
        'interval_max',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'active' => 'boolean',
            'easiness_default' => 'float',
            'interval_min' => 'integer',
            'interval_max' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(FlashcardItem::class, 'deck_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_flashcard_deck', 'flashcard_deck_id', 'group_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FlashcardSession::class, 'deck_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (FlashcardDeck $deck) {
            foreach ($deck->cards as $card) {
                $card->delete();
            }
        });
    }
}
