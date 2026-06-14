<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\ArticleMediaFactory;

class ArticleMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'file_path',
        'file_type',
        'original_name',
    ];

    /**
     * Obtenir l'article auquel appartient le média.
     */
    public function article(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
