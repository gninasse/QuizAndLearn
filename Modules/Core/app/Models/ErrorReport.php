<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ErrorReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'learner_id',
        'content_type',
        'content_id',
        'error_type',
        'comment',
        'status',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    /**
     * Polymorphic relation for the reported content (Article or Quiz).
     */
    public function content(): MorphTo
    {
        return $this->morphTo(null, 'content_type', 'content_id');
    }
}
