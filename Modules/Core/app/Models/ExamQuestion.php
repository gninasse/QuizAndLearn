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
