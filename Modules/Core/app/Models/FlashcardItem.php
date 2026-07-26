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
        static::deleting(function (FlashcardItem $item) {
            $fields = [$item->recto, $item->verso];
            foreach ($fields as $content) {
                if (empty($content)) {
                    continue;
                }
                preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches);
                foreach ($matches[1] ?? [] as $url) {
                    $storageUrl = asset('storage/');
                    if (str_starts_with($url, $storageUrl)) {
                        $path = str_replace($storageUrl, '', $url);
                        $path = ltrim($path, '/');
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                        }
                    }
                }
            }
        });
    }
}
