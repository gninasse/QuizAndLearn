<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\QuestionFactory;

class Question extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'quiz_id',
        'question_text',
        'type',
        'points',
        'order',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'order' => 'integer',
            'options' => 'array',
        ];
    }

    /**
     * Obtenir le quiz auquel appartient cette question.
     */
    public function quiz(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    protected static function booted(): void
    {
        static::saved(fn () => \Modules\Core\Services\ShowcaseMedia::flush());
        static::deleted(fn () => \Modules\Core\Services\ShowcaseMedia::flush());

        static::deleting(function (Question $question) {
            // Images inline : supprimées seulement si aucun autre contenu ne les référence.
            \Modules\Core\Services\MediaReferences::releaseInline($question, [$question->question_text]);
        });
    }
}
