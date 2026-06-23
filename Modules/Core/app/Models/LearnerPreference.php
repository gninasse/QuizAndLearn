<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id',
        'locale',
        'theme',
        'font_size',
        'sound_enabled',
        'notifications_enabled',
        'streak_reminder_time',
        'dnd_start',
        'dnd_end',
    ];

    protected function casts(): array
    {
        return [
            'sound_enabled' => 'boolean',
            'notifications_enabled' => 'array',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
