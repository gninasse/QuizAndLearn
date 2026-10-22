<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;

/**
 * Droit de modifier un contenu (article, quiz, examen, deck, ressource).
 *
 *  - `shared`   : le créateur et les admins ;
 *  - `isolated` : aussi les formateurs d'un groupe auquel le contenu appartient.
 *
 * La suppression reste réservée au créateur et aux admins, quel que soit le mode.
 */
class ContentEditAccess
{
    public const ADMIN_ROLES = ['super-admin', 'Admin', 'admin'];

    public static function canEdit(User $user, Model $content): bool
    {
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return true;
        }
        if ($content->created_by !== null && (int) $content->created_by === (int) $user->id) {
            return true;
        }
        if (! ContentSharing::isolated() || ! $user->trainer) {
            return false;
        }

        $type = self::typeOf($content);
        if ($type === null) {
            return false;
        }
        [, $pivot, $fk] = ContentSharing::TYPES[$type];

        return DB::table($pivot)
            ->join('group_trainer', 'group_trainer.group_id', '=', "{$pivot}.group_id")
            ->where("{$pivot}.{$fk}", $content->getKey())
            ->where('group_trainer.trainer_id', $user->trainer->id)
            ->exists();
    }

    /**
     * Suppression : créateur et admins seulement (les co-formateurs du mode `isolated` modifient sans supprimer).
     */
    public static function canDelete(User $user, Model $content): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES)
            || ($content->created_by !== null && (int) $content->created_by === (int) $user->id);
    }

    /**
     * Restreint une requête de liste aux contenus que l'utilisateur peut modifier :
     * les siens et, en mode `isolated`, ceux des groupes qu'il anime. Sans effet pour un admin.
     * Le modèle interrogé doit avoir une relation `groups`.
     */
    public static function scopeEditable(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            return $query;
        }

        $trainerId = ContentSharing::isolated() ? $user->trainer?->id : null;

        return $query->where(function (Builder $q) use ($user, $trainerId) {
            $q->where('created_by', $user->id);
            if ($trainerId !== null) {
                $q->orWhereHas('groups', fn (Builder $g) => $g->whereIn(
                    'groups.id', DB::table('group_trainer')->where('trainer_id', $trainerId)->select('group_id')
                ));
            }
        });
    }

    /**
     * 403 si l'utilisateur courant ne peut pas modifier le contenu.
     */
    public static function authorize(Model $content): void
    {
        abort_unless(self::canEdit(auth()->user(), $content), 403, 'Action non autorisée.');
    }

    /**
     * Type de ContentSharing::TYPES correspondant au modèle (null si inconnu).
     */
    public static function typeOf(Model $content): ?string
    {
        foreach (ContentSharing::TYPES as $type => [$class]) {
            if ($content instanceof $class) {
                return $type;
            }
        }

        return null;
    }
}
