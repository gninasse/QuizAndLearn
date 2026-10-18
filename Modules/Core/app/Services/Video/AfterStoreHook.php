<?php

namespace Modules\Core\Services\Video;

use Modules\Core\Models\Video;

/**
 * Pilote qui doit agir une fois la ligne `videos` créée (ex. mise en file
 * d'une conversion qui a besoin du modèle persisté).
 */
interface AfterStoreHook
{
    public function afterStore(Video $video): void;
}
