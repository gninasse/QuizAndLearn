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
use Modules\Core\Services\ContentCopier;
use Modules\Core\Services\ContentEditAccess;
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
        $group = $this->authorizedGroup($id);

        // Groupes cibles de « Copier vers… » : tous pour un admin, ceux qu'il anime pour un formateur
        // (revérifié côté serveur par copyItem).
        $copyTargets = $this->isAdmin()
            ? Group::query()->orderBy('name')->get(['id', 'name'])
            : (auth()->user()->trainer?->groups()->orderBy('name')->get(['groups.id', 'groups.name']) ?? collect());

        return view('core::groups.contents', [
            'group' => $group,
            'sharingMode' => ContentSharing::mode(),
            'copyTargets' => $copyTargets->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values(),
        ]);
    }

    /**
     * GET ?type=&q= — contenus existants que l'utilisateur peut rattacher au groupe :
     * ceux qu'il peut modifier (ContentEditAccess) et les decks publics, hors contenus déjà présents.
     * `locked` : en mode `isolated`, le contenu appartient déjà à un autre groupe (rattachement refusé).
     */
    public function candidates(Request $request, int $id): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentSharing::TYPES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $type = $data['type'];
        $model = ContentSharing::TYPES[$type][0];
        $isDeck = $model === FlashcardDeck::class;
        $titleColumn = $isDeck ? 'titre' : 'title';
        $user = auth()->user();

        $query = $model::query()
            ->whereDoesntHave('groups', fn ($g) => $g->where('groups.id', $group->id))
            ->withCount('groups');

        if (! $user->hasAnyRole(ContentEditAccess::ADMIN_ROLES)) {
            // scopeEditable n'ajoute aucune contrainte pour un admin : ne l'imbriquer que pour les autres.
            $query->where(function ($q) use ($user, $isDeck) {
                $q->where(fn ($editable) => ContentEditAccess::scopeEditable($editable, $user));
                if ($isDeck) {
                    $q->orWhere('is_public', true);
                }
            });
        }

        $term = trim((string) ($data['q'] ?? ''));
        if ($term !== '') {
            $query->where($titleColumn, 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        $isolated = ContentSharing::isolated();
        $items = $query->orderBy($titleColumn)->limit(50)->get()->map(function ($content) use ($type, $titleColumn, $isolated, $isDeck) {
            $public = $isDeck && $content->is_public;
            $item = [
                'type' => $type,
                'id' => $content->id,
                'title' => $content->{$titleColumn},
                'groups_count' => (int) $content->groups_count,
                'locked' => $isolated && ! $public && $content->groups_count > 0,
            ];
            if ($type === 'resource') {
                $item['kind'] = $content->kind;
            }
            if ($isDeck) {
                $item['is_public'] = (bool) $content->is_public;
            }

            return $item;
        })->values();

        return response()->json(['success' => true, 'items' => $items]);
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
                array_push($columns, "{$table}.kind", "{$table}.conversion_status", "{$table}.conversion_error",
                    "{$table}.description", "{$table}.allow_download", "{$table}.is_active");
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
                        'description' => $row->description,
                        'allow_download' => (bool) $row->allow_download,
                        'is_active' => (bool) $row->is_active,
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
     * PATCH — paramètres d'une ressource du groupe (titre, description, téléchargement, activation).
     * Il faut pouvoir modifier la ressource (ContentEditAccess::canEdit).
     */
    public function updateResource(Request $request, int $id, int $resource): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $model = $group->resources()->whereKey($resource)->firstOrFail();
        ContentEditAccess::authorize($model);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'allow_download' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('description', $data) && $data['description'] === '') {
            $data['description'] = null;
        }
        $model->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Ressource mise à jour.',
            'resource' => $this->resourcePayload($model->fresh()) + [
                'description' => $model->description,
                'allow_download' => (bool) $model->allow_download,
                'is_active' => (bool) $model->is_active,
            ],
        ]);
    }

    /**
     * DELETE — supprime définitivement une ressource (et ses fichiers s'ils ne servent plus à une copie).
     * Créateur et admins seulement (ContentEditAccess::canDelete). Refus 422 si elle est encore
     * rattachée à un autre groupe que l'utilisateur ne gère pas : on ne retire rien aux collègues.
     */
    public function destroyResource(int $id, int $resource, ResourceStorage $storage): JsonResponse
    {
        $group = $this->authorizedGroup($id);
        $model = $group->resources()->whereKey($resource)->firstOrFail();
        abort_unless(ContentEditAccess::canDelete(auth()->user(), $model), 403, 'Action non autorisée.');

        if (! $this->isAdmin()) {
            $managed = auth()->user()->trainer?->groups()->pluck('groups.id')->all() ?? [];
            $foreign = DB::table('group_resource')->where('resource_id', $model->id)
                ->whereNotIn('group_id', $managed)->exists();
            if ($foreign) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette ressource est aussi utilisée dans '.term('a_group').' que vous ne gérez pas. Retirez-la '.term('of_group').' au lieu de la supprimer.',
                ], 422);
            }
        }

        DB::transaction(function () use ($model) {
            DB::table('group_resource')->where('resource_id', $model->id)->delete();
            $model->delete();
        });
        $storage->release($model);

        return response()->json(['success' => true, 'message' => 'Ressource supprimée.']);
    }

    /**
     * POST — rattache un contenu existant (règle du mode de partage appliquée).
     * Il faut pouvoir modifier le contenu (ContentEditAccess), decks publics exceptés.
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

        $public = $content instanceof FlashcardDeck && $content->is_public;
        abort_unless($public || ContentEditAccess::canEdit(auth()->user(), $content), 403, 'Action non autorisée.');

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
     * POST — « Copier vers… » : copie profonde d'un contenu du groupe vers `target_group_id`
     * (dans `target_folder_id`, null = racine), en dernière position.
     * Il faut pouvoir modifier le contenu et gérer le groupe cible ; le groupe {id} n'est que
     * le contexte (le contenu doit y être rattaché), il n'a pas à être géré par l'utilisateur.
     */
    public function copyItem(Request $request, ContentCopier $copier, int $id, string $type, int $itemId): JsonResponse
    {
        $group = Group::findOrFail($id);
        abort_unless(isset(ContentSharing::TYPES[$type]), 404);
        [$model, $pivot, $fk] = ContentSharing::TYPES[$type];

        abort_unless(DB::table($pivot)->where('group_id', $group->id)->where($fk, $itemId)->exists(), 404);
        $content = $model::findOrFail($itemId);
        ContentEditAccess::authorize($content);

        $data = $request->validate([
            'target_group_id' => ['required', 'integer', 'exists:groups,id'],
            'target_folder_id' => ['nullable', 'integer'],
        ]);
        $target = $this->authorizedGroup((int) $data['target_group_id']);
        $folderId = isset($data['target_folder_id']) ? (int) $data['target_folder_id'] : null;

        $copy = $copier->copy($type, $content->id, $target->id, $folderId, auth()->user())['model'];

        return response()->json([
            'success' => true,
            'message' => 'Copie créée dans '.term('the_group').' « '.$target->name.' ».',
            'item' => ['type' => $type, 'id' => $copy->id, 'group_id' => $target->id, 'folder_id' => $folderId],
        ], 201);
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
