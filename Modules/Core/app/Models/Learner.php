<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Core\Database\Factories\LearnerFactory;

class Learner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matricule',
    ];

    /**
     * Obtenir l'utilisateur associé à l'apprenant.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir les groupes d'appartenance de l'apprenant.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_learner', 'learner_id', 'group_id');
    }
}
