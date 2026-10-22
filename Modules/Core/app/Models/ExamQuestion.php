<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'question_text',
        'type',
        'points',
        'points_negatifs',
        'order',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'points_negatifs' => 'decimal:2',
            'order' => 'integer',
            'options' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (ExamQuestion $question) {
            // Images inline : supprimées seulement si aucun autre contenu ne les référence.
            \Modules\Core\Services\MediaReferences::releaseInline($question, [$question->question_text]);
        });
    }
}
