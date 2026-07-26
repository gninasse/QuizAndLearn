<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'duration',
        'passing_score',
        'is_active',
        'max_attempts',
        'available_from',
        'available_until',
        'plein_ecran_force',
        'anti_capture_strict',
        'navigation_interdite',
        'publication_resultats',
        'classement_visible',
        'classement_anonyme',
        'note_max',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'passing_score' => 'integer',
            'is_active' => 'boolean',
            'max_attempts' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'plein_ecran_force' => 'boolean',
            'anti_capture_strict' => 'boolean',
            'navigation_interdite' => 'boolean',
            'classement_visible' => 'boolean',
            'classement_anonyme' => 'boolean',
            'note_max' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('order', 'asc');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_exam', 'exam_id', 'group_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Exam $exam) {
            foreach ($exam->questions as $q) {
                $q->delete();
            }
        });
    }
}
