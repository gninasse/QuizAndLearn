<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;

/**
 * Arborescence des contenus d'un groupe : dossiers et contenus partagent,
 * dans un même conteneur (dossier ou racine), une seule suite de positions.
 *
 * Les positions sont écrites sans toucher `updated_at` (le rang n'affecte
 * pas la visibilité) ; un changement de parent, lui, passe par Eloquent.
 */
class FolderTree
{
    public const DELETE_MODES = ['lift', 'detach'];

    /** @var array<string, bool> */
    private static array $pivotTimestamps = [];

    /**
     * Déplace un dossier sous $parentId (null = racine) au rang $position.
     *
     * @throws ValidationException cycle ou dossier parent d'un autre groupe
     */
    public function move(Folder $folder, ?int $parentId, int $position): void
    {
        if ($parentId !== null) {
            $this->groupFolder($folder->group_id, $parentId, 'parent_id');
            if (in_array($parentId, $folder->descendantIds(), true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Un dossier ne peut pas être déplacé dans lui-même ou dans un de ses sous-dossiers.',
                ]);
            }
        }

        DB::transaction(function () use ($folder, $parentId, $position) {
            if ($folder->parent_id !== $parentId) {
                $folder->parent_id = $parentId;
                $folder->save();
            }

            $entries = $this->entries($folder->group_id, $parentId)
                ->reject(fn ($e) => $e['type'] === 'folder' && $e['id'] === $folder->id)
                ->values()->all();
            $this->writePositions($folder->group_id, $parentId, $this->insertAt($entries, ['type' => 'folder', 'id' => $folder->id], $position));
        });
    }

    /**
     * Place un contenu déjà attribué au groupe dans $folderId (null = racine) au rang $position.
     *
     * @throws ValidationException dossier d'un autre groupe, deck public dans un dossier
     * @throws ModelNotFoundException contenu non attribué au groupe
     */
    public function place(Group $group, string $type, int $id, ?int $folderId, int $position): void
    {
        $this->type($type);
        $this->assertFolderTarget($group->id, $type, $id, $folderId);

        if (! $this->pivotRow($group->id, $type, $id)->exists()) {
            throw (new ModelNotFoundException)->setModel(ContentSharing::TYPES[$type][0], [$id]);
        }

        DB::transaction(function () use ($group, $type, $id, $folderId, $position) {
            $this->pivotRow($group->id, $type, $id)->update(['folder_id' => $folderId]);

            $entries = $this->entries($group->id, $folderId)
                ->reject(fn ($e) => $e['type'] === $type && $e['id'] === $id)
                ->values()->all();
            $this->writePositions($group->id, $folderId, $this->insertAt($entries, ['type' => $type, 'id' => $id], $position));
        });
    }

    /**
     * Rattache un contenu au groupe, dans $folderId (null = racine), en dernière position.
     * La règle du mode de partage (ContentSharing::assertCanAttach) est à appliquer par l'appelant.
     *
     * @throws ValidationException dossier d'un autre groupe, contenu déjà présent
     */
    public function attach(Group $group, string $type, int $id, ?int $folderId): void
    {
        [, $pivot, $fk] = $this->type($type);
        $this->assertFolderTarget($group->id, $type, $id, $folderId);

        if ($this->pivotRow($group->id, $type, $id)->exists()) {
            throw ValidationException::withMessages(['id' => 'Ce contenu est déjà présent dans '.term('the_group').'.']);
        }

        $row = [
            'group_id' => $group->id,
            $fk => $id,
            'folder_id' => $folderId,
            'position' => $this->nextPosition($group->id, $folderId),
        ];
        if ($this->hasTimestamps($pivot)) {
            $row += ['created_at' => now(), 'updated_at' => now()];
        }
        DB::table($pivot)->insert($row);
    }

    /**
     * Retire un contenu du groupe (le contenu lui-même est conservé).
     *
     * @throws ModelNotFoundException contenu non attribué au groupe
     */
    public function detach(Group $group, string $type, int $id): void
    {
        $this->type($type);
        if ($this->pivotRow($group->id, $type, $id)->delete() === 0) {
            throw (new ModelNotFoundException)->setModel(ContentSharing::TYPES[$type][0], [$id]);
        }
    }

    /**
     * Supprime un dossier.
     *  - `lift`   : sous-dossiers et contenus directs remontent dans le parent, à la suite ;
     *  - `detach` : les contenus de tout le sous-arbre sont retirés du groupe, les dossiers supprimés.
     *
     * @throws ValidationException mode absent ou inconnu (rien n'est supprimé)
     */
    public function delete(Folder $folder, string $mode): void
    {
        if (! in_array($mode, self::DELETE_MODES, true)) {
            throw ValidationException::withMessages([
                'mode' => 'Précisez le sort du contenu : « lift » (remonter dans le dossier parent) ou « detach » (retirer '.term('of_group').').',
            ]);
        }

        DB::transaction(function () use ($folder, $mode) {
            $groupId = $folder->group_id;

            if ($mode === 'lift') {
                $parentId = $folder->parent_id;
                $next = $this->nextPosition($groupId, $parentId, exceptFolderId: $folder->id);
                $moving = $this->entries($groupId, $folder->id)->all();

                // Contenus et sous-dossiers d'abord rattachés au parent, puis numérotés à la suite.
                Folder::query()->where('parent_id', $folder->id)->toBase()->update(['parent_id' => $parentId]);
                foreach (ContentSharing::TYPES as [, $pivot]) {
                    DB::table($pivot)->where('group_id', $groupId)->where('folder_id', $folder->id)->update(['folder_id' => $parentId]);
                }
                foreach ($moving as $i => $entry) {
                    $this->writePosition($groupId, $entry, $next + $i);
                }

                $folder->delete();

                return;
            }

            $ids = $folder->descendantIds();
            foreach (ContentSharing::TYPES as [, $pivot]) {
                DB::table($pivot)->where('group_id', $groupId)->whereIn('folder_id', $ids)->delete();
            }
            // Feuilles d'abord : pas de dépendance au ON DELETE CASCADE du SGBD.
            foreach (array_reverse($ids) as $id) {
                Folder::query()->whereKey($id)->delete();
            }
        });
    }

    /**
     * Fixe l'ordre d'un conteneur : positions 0..n dans l'ordre donné.
     *
     * @param  array<int, array{type:string, id:int}>  $items  type = folder|article|quiz|exam|deck|resource
     *
     * @throws ValidationException élément absent de ce conteneur, doublon, dossier d'un autre groupe
     */
    public function reorder(Group $group, ?int $folderId, array $items): void
    {
        if ($folderId !== null) {
            $this->groupFolder($group->id, $folderId, 'folder_id');
        }

        $present = $this->entries($group->id, $folderId)
            ->map(fn ($e) => $e['type'].':'.$e['id'])
            ->flip();

        $seen = [];
        $ordered = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            $id = (int) ($item['id'] ?? 0);
            $key = "{$type}:{$id}";
            if (! $present->has($key) || isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'items' => 'La liste contient un élément absent de ce dossier ou en double.',
                ]);
            }
            $seen[$key] = true;
            $ordered[] = ['type' => $type, 'id' => $id];
        }

        DB::transaction(fn () => $this->writePositions($group->id, $folderId, $ordered));
    }

    /**
     * Rang suivant le dernier élément du conteneur (dossiers et contenus confondus).
     */
    public function nextPosition(int $groupId, ?int $folderId, ?int $exceptFolderId = null): int
    {
        $max = $this->entries($groupId, $folderId)
            ->reject(fn ($e) => $e['type'] === 'folder' && $e['id'] === $exceptFolderId)
            ->max('position');

        return $max === null ? 0 : $max + 1;
    }

    /**
     * Vérifie que $folderId est un dossier de ce groupe (null = racine, toujours valide).
     *
     * @throws ValidationException
     */
    public function assertFolderOfGroup(int $groupId, ?int $folderId): void
    {
        if ($folderId !== null) {
            $this->groupFolder($groupId, $folderId, 'folder_id');
        }
    }

    // ------------------------------------------------------------------

    /**
     * Éléments directs d'un conteneur, triés (position, dossiers d'abord, id).
     *
     * @return \Illuminate\Support\Collection<int, array{type:string, id:int, position:int}>
     */
    private function entries(int $groupId, ?int $folderId)
    {
        $rows = Folder::query()
            ->where('group_id', $groupId)
            ->when($folderId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $folderId))
            ->get(['id', 'position'])
            ->map(fn ($f) => ['type' => 'folder', 'id' => (int) $f->id, 'position' => (int) $f->position, 'rank' => 0]);

        $rank = 1;
        foreach (ContentSharing::TYPES as $type => [, $pivot, $fk]) {
            $r = $rank++;
            $rows = $rows->concat(
                DB::table($pivot)
                    ->where('group_id', $groupId)
                    ->when($folderId === null, fn ($q) => $q->whereNull('folder_id'), fn ($q) => $q->where('folder_id', $folderId))
                    ->get([$fk, 'position'])
                    ->map(fn ($p) => ['type' => $type, 'id' => (int) $p->{$fk}, 'position' => (int) $p->position, 'rank' => $r])
            );
        }

        return $rows
            ->sortBy([['position', 'asc'], ['rank', 'asc'], ['id', 'asc']])
            ->map(fn ($e) => ['type' => $e['type'], 'id' => $e['id'], 'position' => $e['position']])
            ->values();
    }

    /**
     * @param  array<int, array{type:string, id:int}>  $entries
     * @param  array{type:string, id:int}  $entry
     * @return array<int, array{type:string, id:int}>
     */
    private function insertAt(array $entries, array $entry, int $position): array
    {
        $position = max(0, min($position, count($entries)));
        array_splice($entries, $position, 0, [$entry]);

        return $entries;
    }

    /**
     * @param  array<int, array{type:string, id:int}>  $entries
     */
    private function writePositions(int $groupId, ?int $folderId, array $entries): void
    {
        foreach (array_values($entries) as $i => $entry) {
            $this->writePosition($groupId, $entry, $i);
        }
    }

    /**
     * @param  array{type:string, id:int}  $entry
     */
    private function writePosition(int $groupId, array $entry, int $position): void
    {
        if ($entry['type'] === 'folder') {
            Folder::query()->whereKey($entry['id'])->where('group_id', $groupId)->toBase()->update(['position' => $position]);

            return;
        }

        $this->pivotRow($groupId, $entry['type'], $entry['id'])->update(['position' => $position]);
    }

    private function pivotRow(int $groupId, string $type, int $id): Builder
    {
        [, $pivot, $fk] = $this->type($type);

        return DB::table($pivot)->where('group_id', $groupId)->where($fk, $id);
    }

    /**
     * Cible d'un placement : dossier du groupe, et jamais un dossier pour un deck public.
     */
    private function assertFolderTarget(int $groupId, string $type, int $id, ?int $folderId): void
    {
        if ($folderId === null) {
            return;
        }
        $this->groupFolder($groupId, $folderId, 'folder_id');

        if ($type === 'deck' && FlashcardDeck::whereKey($id)->where('is_public', true)->exists()) {
            throw ValidationException::withMessages(['folder_id' => 'Un deck public reste hors des dossiers.']);
        }
    }

    private function groupFolder(int $groupId, int $folderId, string $field): Folder
    {
        $folder = Folder::query()->whereKey($folderId)->where('group_id', $groupId)->first();
        if (! $folder) {
            throw ValidationException::withMessages([$field => 'Ce dossier n\'appartient pas '.term('to_group').'.']);
        }

        return $folder;
    }

    /**
     * @return array{0: class-string, 1: string, 2: string}
     */
    private function type(string $type): array
    {
        if (! isset(ContentSharing::TYPES[$type])) {
            throw new InvalidArgumentException("Type de contenu inconnu : {$type}");
        }

        return ContentSharing::TYPES[$type];
    }

    private function hasTimestamps(string $pivot): bool
    {
        return self::$pivotTimestamps[$pivot] ??= Schema::hasColumn($pivot, 'created_at');
    }
}
