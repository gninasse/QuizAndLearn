<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Core\Jobs\ConvertDocumentToPdf;
use Modules\Core\Models\FlashcardDeck;
use Modules\Core\Models\Folder;
use Modules\Core\Models\Group;
use Modules\Core\Models\Resource;
use Modules\Core\Services\ContentSharing;
use Modules\Core\Services\FolderTree;
use Modules\Core\Services\ResourceStorage;

/**
 * Back-office : arbre des contenus d'un groupe (dossiers, placement,
 * réordonnancement, ressources, rattachement de contenus existants).
 *
 * Accès : admins et super-admins ; formateurs assignés au groupe ; sinon 403.
 */
class GroupContentController extends Controller
{
    public function __construct(private FolderTree $tree) {}

    public function page(int $id): View
    {
        return view('core::groups.contents', ['group' => $this->authorizedGroup($id)]);
    }

    /**
     * GET — arbre des dossiers et éléments du groupe.
     */
    public function data(int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);

        $folders = $group->folders()
            ->orderBy('parent_id')->orderBy('position')->orderBy('id')
            ->get()
            ->map(fn (Folder $f) => [
                'id' => $f->id,
                'parent_id' => $f->parent_id,
                'name' => $f->name,
                'position' => (int) $f->position,
                'is_visible' => (bool) $f->is_visible,
                'available_from' => $f->available_from?->toIso8601String(),
                'available_until' => $f->available_until?->toIso8601String(),
            ])->values();

        $items = collect();
        foreach (ContentSharing::TYPES as $type => [$model, $pivot, $fk]) {
            $table = (new $model)->getTable();
            $titleColumn = $model === FlashcardDeck::class ? 'titre' : 'title';
            $columns = ["{$table}.id", "{$table}.{$titleColumn} as title", "{$pivot}.folder_id", "{$pivot}.position"];
            if ($type === 'resource') {
                array_push($columns, "{$table}.kind", "{$table}.conversion_status", "{$table}.conversion_error");
            }
            if ($type === 'deck') {
                $columns[] = "{$table}.is_public";
            }

            $rows = DB::table($pivot)
                ->join($table, "{$table}.id", '=', "{$pivot}.{$fk}")
                ->where("{$pivot}.group_id", $group->id)
                ->get($columns);

            foreach ($rows as $row) {
                $item = [
                    'type' => $type,
                    'id' => (int) $row->id,
                    'title' => $row->title,
                    'folder_id' => $row->folder_id !== null ? (int) $row->folder_id : null,
                    'position' => (int) $row->position,
                ];
                if ($type === 'resource') {
                    $item += [
                        'kind' => $row->kind,
                        'conversion_status' => $row->conversion_status,
                        'conversion_error' => $row->conversion_error,
                    ];
                }
                if ($type === 'deck') {
                    $item['is_public'] = (bool) $row->is_public;
                }
                $items->push($item);
            }
        }

        return response()->json([
            'success' => true,
            'group' => ['id' => $group->id, 'name' => $group->name],
            'sharing_mode' => ContentSharing::mode(),
            'folders' => $folders,
            'items' => $items->sortBy([['folder_id', 'asc'], ['position', 'asc']])->values(),
        ]);
    }

    public function storeFolder(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate($this->folderRules(true) + [
            'parent_id' => ['nullable', 'integer'],
        ]);
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        $this->assertWindow($data['available_from'] ?? null, $data['available_until'] ?? null);
        $this->tree->assertFolderOfGroup($group->id, $parentId);

        $folder = Folder::create([
            'group_id' => $group->id,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'position' => $this->tree->nextPosition($group->id, $parentId),
            'is_visible' => $data['is_visible'] ?? true,
            'available_from' => $data['available_from'] ?? null,
            'available_until' => $data['available_until'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Dossier créé.', 'folder' => $folder->fresh()], 201);
    }

    /**
     * PATCH — nom, visibilité, fenêtre de disponibilité.
     */
    public function updateFolder(Request $request, int $id, int $folder): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $model = $this->groupFolder($group, $folder);
        $data = $request->validate($this->folderRules(false));

        $from = array_key_exists('available_from', $data) ? $data['available_from'] : $model->available_from;
        $until = array_key_exists('available_until', $data) ? $data['available_until'] : $model->available_until;
        $this->assertWindow($from, $until);

        $model->update($data);

        return response()->json(['success' => true, 'message' => 'Dossier mis à jour.', 'folder' => $model->fresh()]);
    }

    public function moveFolder(Request $request, int $id, int $folder): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $model = $this->groupFolder($group, $folder);
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $this->tree->move($model, isset($data['parent_id']) ? (int) $data['parent_id'] : null, (int) $data['position']);

        return response()->json(['success' => true, 'message' => 'Dossier déplacé.']);
    }

    /**
     * DELETE ?mode=lift|detach — sans mode valide : 422, rien n'est supprimé.
     */
    public function destroyFolder(Request $request, int $id, int $folder): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $model = $this->groupFolder($group, $folder);

        $this->tree->delete($model, (string) $request->query('mode', ''));

        return response()->json(['success' => true, 'message' => 'Dossier supprimé.']);
    }

    public function placeItem(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentSharing::TYPES))],
            'id' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $this->tree->place($group, $data['type'], (int) $data['id'], $this->folderId($data), (int) $data['position']);

        return response()->json(['success' => true, 'message' => 'Élément déplacé.']);
    }

    public function reorder(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate([
            'folder_id' => ['nullable', 'integer'],
            'items' => ['present', 'array'],
            'items.*.type' => ['required', Rule::in(['folder', ...array_keys(ContentSharing::TYPES)])],
            'items.*.id' => ['required', 'integer'],
        ]);

        $this->tree->reorder($group, $this->folderId($data), $data['items']);

        return response()->json(['success' => true, 'message' => 'Ordre enregistré.']);
    }

    /**
     * POST multipart — téléversement d'une ressource, rattachée au groupe
     * dans `folder_id` (null = racine), en dernière position.
     */
    public function storeResource(Request $request, int $id, ResourceStorage $storage): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate([
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'allow_download' => ['nullable', 'boolean'],
            'folder_id' => ['nullable', 'integer'],
        ]);
        $folderId = $this->folderId($data);
        $this->tree->assertFolderOfGroup($group->id, $folderId);

        $attrs = array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $attrs['allow_download'] = (bool) ($data['allow_download'] ?? false);

        $resource = $storage->store($request->file('file'), $attrs, auth()->user());
        $this->tree->attach($group, 'resource', $resource->id, $folderId);

        return response()->json(['success' => true, 'message' => 'Ressource ajoutée.', 'resource' => $this->resourcePayload($resource)], 201);
    }

    public function storeLink(Request $request, int $id, ResourceStorage $storage): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['required', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer'],
        ]);
        $folderId = $this->folderId($data);
        $this->tree->assertFolderOfGroup($group->id, $folderId);

        $resource = $storage->createLink($data['url'], $data['title'], auth()->user());
        $this->tree->attach($group, 'resource', $resource->id, $folderId);

        return response()->json(['success' => true, 'message' => 'Lien ajouté.', 'resource' => $this->resourcePayload($resource)], 201);
    }

    /**
     * POST — rattache un contenu existant (règle du mode de partage appliquée).
     * Hors admins, seul le créateur du contenu peut le rattacher (decks publics exceptés).
     */
    public function attachItem(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentSharing::TYPES))],
            'id' => ['required', 'integer'],
            'folder_id' => ['nullable', 'integer'],
        ]);
        $type = $data['type'];
        $content = ContentSharing::TYPES[$type][0]::findOrFail((int) $data['id']);

        if (! $this->isAdmin()) {
            $public = $content instanceof FlashcardDeck && $content->is_public;
            abort_unless($public || (int) $content->created_by === (int) auth()->id(), 403, 'Action non autorisée.');
        }

        ContentSharing::assertCanAttach($type, $content->id, $group->id);
        $this->tree->attach($group, $type, $content->id, $this->folderId($data));

        return response()->json(['success' => true, 'message' => 'Contenu ajouté '.term('to_group').'.']);
    }

    public function detachItem(int $id, string $type, int $itemId): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        abort_unless(isset(ContentSharing::TYPES[$type]), 404);

        $this->tree->detach($group, $type, $itemId);

        return response()->json(['success' => true, 'message' => 'Contenu retiré '.term('of_group').'.']);
    }

    /**
     * POST — relance la conversion PDF d'un document (statut remis à « pending »).
     * Le job ne s'exécute jamais deux fois en parallèle pour une même ressource.
     */
    public function retryConversion(int $id, int $resource): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $model = $group->resources()->whereKey($resource)->firstOrFail();

        if ($model->kind !== 'document') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les documents Word, PowerPoint ou OpenDocument sont convertis.',
            ], 422);
        }

        $model->update(['conversion_status' => 'pending', 'conversion_error' => null]);
        ConvertDocumentToPdf::dispatch($model->id);

        return response()->json(['success' => true, 'message' => 'Conversion relancée.']);
    }

    // ------------------------------------------------------------------

    /**
     * @return array<string, array<int, mixed>>
     */
    private function folderRules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'is_visible' => ['sometimes', 'boolean'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date'],
        ];
    }

    private function assertWindow(mixed $from, mixed $until): void
    {
        if ($from && $until && Carbon::parse($until)->lt(Carbon::parse($from))) {
            throw ValidationException::withMessages([
                'available_until' => 'La date de fin doit être postérieure à la date de début.',
            ]);
        }
    }

    private function folderId(array $data): ?int
    {
        return isset($data['folder_id']) ? (int) $data['folder_id'] : null;
    }

    private function resourcePayload(Resource $resource): array
    {
        return [
            'id' => $resource->id,
            'kind' => $resource->kind,
            'title' => $resource->title,
            'conversion_status' => $resource->conversion_status,
        ];
    }

    private function groupFolder(Group $group, int $folderId): Folder
    {
        return Folder::query()->whereKey($folderId)->where('group_id', $group->id)->firstOrFail();
    }

    private function isAdmin(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'admin', 'Admin']);
    }

    private function authorizedGroup(int $id): Group
    {
        $group = Group::findOrFail($id);

        if (! $this->isAdmin()) {
            $trainer = auth()->user()->trainer;
            $assigned = $trainer && $trainer->groups()->where('groups.id', $group->id)->exists();
            abort_unless($assigned, 403, 'Action non autorisée.');
        }

        return $group;
    }
}
