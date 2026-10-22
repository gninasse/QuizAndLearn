<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashcardItem extends Model
{
    use HasFactory;

    protected $table = 'flashcard_items';

    protected $fillable = [
        'deck_id',
        'recto',
        'verso',
        'recto_media',
        'verso_media',
        'tags',
        'note',
        'ordre',
        'total_revisions',
        'taux_reussite',
    ];

    protected function casts(): array
    {
        return [
            'recto_media' => 'json',
            'verso_media' => 'json',
            'ordre' => 'integer',
            'total_revisions' => 'integer',
            'taux_reussite' => 'float',
        ];
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(FlashcardDeck::class, 'deck_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(FlashcardItemReview::class, 'flashcard_item_id');
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Modules\Core\Services\ShowcaseMedia::flush());
        static::deleted(fn () => \Modules\Core\Services\ShowcaseMedia::flush());

        static::deleting(function (FlashcardItem $item) {
            // Images inline : supprimées seulement si aucun autre contenu ne les référence.
            \Modules\Core\Services\MediaReferences::releaseInline($item, [$item->recto, $item->verso]);
        });
    }
}
