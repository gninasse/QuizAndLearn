<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\TrainerFactory;

class Trainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',
        'biography',
    ];

    /**
     * Obtenir l'utilisateur associé au formateur.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir les groupes assignés au formateur.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_trainer', 'trainer_id', 'group_id');
    }
}
