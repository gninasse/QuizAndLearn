<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;

/**
 * Droit de modifier un contenu (article, quiz, examen, deck, ressource).
 *
 *  - `shared`   : le créateur et les admins ;
 *  - `isolated` : aussi les formateurs d'un groupe auquel le contenu appartient.
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
