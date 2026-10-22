<?php

namespace Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Learner;

/**
 * Visibilité des placements de contenus (groupe + dossier) pour un apprenant.
 * Ne gère que la visibilité des dossiers, pas celle des contenus eux-mêmes.
 */
class ContentPlacement
{
    /** @var array<int, Collection> */
    private array $placements = [];

    /** @var array<string, array<int, true>> identifiants visibles par apprenant et type */
    private array $idSets = [];

    /**
     * Dossiers visibles parmi ceux des groupes donnés : visibles eux-mêmes
     * et dont tous les ancêtres le sont (is_visible + fenêtre de dates).
     *
     * @param  Collection<int, int>  $groupIds
     * @return array<int>
     */
    public function visibleFolderIds(Collection $groupIds, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $folders = Folder::query()->whereIn('group_id', $groupIds->all())->get()->keyBy('id');
        $memo = [];

        $visible = function (Folder $folder) use (&$visible, &$memo, $folders, $at): bool {
            if (isset($memo[$folder->id])) {
                return $memo[$folder->id];
            }
            $memo[$folder->id] = false; // protège contre un cycle éventuel

            $own = self::ownVisible($folder, $at);
            if ($own && $folder->parent_id !== null) {
                $parent = $folders->get($folder->parent_id);
                $own = $parent !== null && $visible($parent);
            }

            return $memo[$folder->id] = $own;
        };

        return $folders->filter(fn (Folder $f) => $visible($f))->keys()->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Placements des groupes courants de l'apprenant dont le dossier est null ou visible.
     *
     * @return Collection<int, array{type:string,id:int,group_id:int,folder_id:?int,position:int}>
     */
    public function placementsFor(Learner $learner): Collection
    {
        if (isset($this->placements[$learner->id])) {
            return $this->placements[$learner->id];
        }

        $groupIds = $learner->groups()->current()->pluck('groups.id')->map(fn ($id) => (int) $id);
        $placements = collect();

        if ($groupIds->isNotEmpty()) {
            $visibleFolders = array_flip($this->visibleFolderIds($groupIds));

            foreach (ContentSharing::TYPES as $type => [, $pivot, $foreignKey]) {
                $rows = DB::table($pivot)->whereIn('group_id', $groupIds->all())->get();
                foreach ($rows as $row) {
                    $folderId = $row->folder_id !== null ? (int) $row->folder_id : null;
                    if ($folderId !== null && ! isset($visibleFolders[$folderId])) {
                        continue;
                    }
                    $placements->push([
                        'type' => $type,
                        'id' => (int) $row->{$foreignKey},
                        'group_id' => (int) $row->group_id,
                        'folder_id' => $folderId,
                        'position' => (int) $row->position,
                    ]);
                }
            }
        }

        return $this->placements[$learner->id] = $placements;
    }

    /**
     * @return array<int>
     */
    public function visibleIds(Learner $learner, string $type): array
    {
        return $this->placementsFor($learner)
            ->where('type', $type)->pluck('id')->unique()->values()->all();
    }

    public function isVisible(Learner $learner, string $type, int $id): bool
    {
        $this->idSets[$learner->id.':'.$type] ??= array_fill_keys($this->visibleIds($learner, $type), true);

        return isset($this->idSets[$learner->id.':'.$type][$id]);
    }

    /**
     * Moment du masquage d'un contenu pour l'apprenant : le plus récent, parmi
     * les dossiers masqués de ses chaînes de placement (groupes courants), de
     * max(updated_at, available_until si déjà passé). Null si aucun dossier
     * masqué n'est en cause.
     */
    public function hiddenSince(Learner $learner, string $type, int $id, ?CarbonInterface $at = null): ?CarbonInterface
    {
        $at ??= now();
        [, $pivot, $foreignKey] = ContentSharing::TYPES[$type];
        $groupIds = $learner->groups()->current()->pluck('groups.id')->all();

        $folderIds = DB::table($pivot)->where($foreignKey, $id)->whereIn('group_id', $groupIds)
            ->whereNotNull('folder_id')->pluck('folder_id');
        if ($folderIds->isEmpty()) {
            return null;
        }

        $folders = Folder::query()->whereIn('group_id', $groupIds)->get()->keyBy('id');
        $latest = null;
        foreach ($folderIds as $folderId) {
            $seen = [];
            for ($folder = $folders->get($folderId); $folder !== null && ! isset($seen[$folder->id]); $folder = $folders->get($folder->parent_id)) {
                $seen[$folder->id] = true;
                if (self::ownVisible($folder, $at)) {
                    continue;
                }
                // Une fenêtre expirée masque le dossier à available_until,
                // même sans modification ultérieure.
                $hiddenAt = $folder->updated_at;
                if ($folder->available_until !== null && $folder->available_until->lt($at) && $folder->available_until->gt($hiddenAt)) {
                    $hiddenAt = $folder->available_until;
                }
                if ($latest === null || $hiddenAt->gt($latest)) {
                    $latest = $hiddenAt;
                }
            }
        }

        return $latest;
    }

    /**
     * Visibilité propre d'un dossier (sans ses ancêtres).
     */
    private static function ownVisible(Folder $folder, CarbonInterface $at): bool
    {
        return $folder->is_visible
            && ($folder->available_from === null || $folder->available_from->lte($at))
            && ($folder->available_until === null || $folder->available_until->gte($at));
    }
}
