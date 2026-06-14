<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\ArticleFactory;

class Article extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'content',
        'is_active',
        'category',
        'seo_description',
        'seo_keywords',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Obtenir l'utilisateur qui a créé l'article.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Obtenir les groupes d'apprenants assignés à cet article.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_article', 'article_id', 'group_id');
    }

    /**
     * Obtenir les fichiers média associés à cet article.
     */
    public function media(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ArticleMedia::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Article $article) {
            foreach ($article->media as $mediaItem) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($mediaItem->file_path);
            }
            $article->media()->delete();
        });
    }
}
