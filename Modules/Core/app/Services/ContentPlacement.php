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

            $own = $folder->is_visible
                && ($folder->available_from === null || $folder->available_from->lte($at))
                && ($folder->available_until === null || $folder->available_until->gte($at));
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
        return in_array($id, $this->visibleIds($learner, $type), true);
    }
}
