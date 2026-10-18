<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Vidéo hébergée. La colonne `driver` fixe le pilote qui la lit, quelle que
 * soit la configuration courante.
 */
class Video extends Model
{
    protected $fillable = [
        'ref', 'driver', 'location', 'status', 'mime', 'size',
        'original_name', 'duration', 'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Video $video) {
            $video->ref ??= (string) Str::uuid();
        });
        // Les fichiers locaux des vidéos vitrine font partie de la liste invitée.
        static::saved(fn () => \Modules\Core\Services\ShowcaseMedia::flush());
        static::deleted(fn () => \Modules\Core\Services\ShowcaseMedia::flush());
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
