<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashcardItemReview extends Model
{
    use HasFactory;

    protected $table = 'flashcard_item_reviews';

    protected $fillable = [
        'flashcard_item_id',
        'learner_id',
        'easiness_factor',
        'interval_days',
        'repetitions',
        'last_reviewed',
        'next_review',
        'status',
        'review_history',
    ];

    protected function casts(): array
    {
        return [
            'easiness_factor' => 'float',
            'interval_days' => 'integer',
            'repetitions' => 'integer',
            'last_reviewed' => 'datetime',
            'next_review' => 'datetime',
            'review_history' => 'json',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(FlashcardItem::class, 'flashcard_item_id');
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class, 'learner_id');
    }
}
