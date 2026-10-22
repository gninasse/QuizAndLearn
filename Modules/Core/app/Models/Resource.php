<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ressource pédagogique (vidéo, audio, image, PDF, document ou lien) rattachée à des groupes.
 */
class Resource extends Model
{
    public const KINDS = ['video', 'audio', 'image', 'pdf', 'document', 'link'];

    protected $fillable = [
        'kind',
        'title',
        'description',
        'disk',
        'path',
        'mime',
        'size',
        'original_name',
        'pdf_path',
        'conversion_status',
        'conversion_error',
        'video_id',
        'url',
        'allow_download',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'allow_download' => 'boolean',
            'is_active' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_resource', 'resource_id', 'group_id')
            ->withPivot('folder_id', 'position')
            ->withTimestamps();
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
