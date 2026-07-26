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
        static::deleting(function (Question $question) {
            if (! empty($question->question_text)) {
                preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $question->question_text, $matches);
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
