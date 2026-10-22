{{--
  Bouton « Copier vers… » (POST cores.groups.contents.items.copy, voir public/js/admin/group-folder-picker.js).
  Paramètres : $type, $content, $primary (action principale du mode `isolated`, sinon secondaire).
  Groupes cibles proposés : tous pour un admin, ceux qu'il anime pour un formateur (revérifié côté serveur).
--}}
@php($copyTargets = auth()->user()->hasAnyRole(\Modules\Core\Services\ContentEditAccess::ADMIN_ROLES) ? \Modules\Core\Models\Group::query()->orderBy('name')->get(['id', 'name']) : (auth()->user()->trainer?->groups()->orderBy('name')->get(['groups.id', 'groups.name']) ?? collect()))
<button type="button" class="btn {{ $primary ? 'btn-outline-primary' : 'btn-outline-secondary' }} btn-sm w-100 mt-2 btn-copy-to-group"
        data-type="{{ $type }}" data-id="{{ $content->id }}"
        data-url-copy="{{ route('cores.groups.contents.items.copy', ['id' => '__GROUP__', 'type' => $type, 'itemId' => $content->id]) }}"
        data-targets='@json($copyTargets->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values())'
        title="Créer une copie indépendante dans {{ term('a_group') }} que vous gérez"
        style="border-radius: 8px;">
  <i class="bi bi-copy me-1"></i>Copier vers…
</button>
