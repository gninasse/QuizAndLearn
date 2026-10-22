{{--
  Placement du contenu dans les dossiers de ses groupes (sélecteur par groupe,
  voir public/js/admin/group-folder-picker.js) et règles du mode de partage.

  Paramètres : $type (article|quiz|exam|deck), $content (modèle).
  En mode `isolated` (decks publics exceptés) : un seul groupe par contenu ; « Copier vers… »
  copie le contenu dans un autre groupe géré par l'utilisateur (POST items.copy, voir le JS).
--}}
@php($isolated = \Modules\Core\Services\ContentSharing::isolated() && ! ($type === 'deck' && $content->is_public))
<div data-group-placement
     data-type="{{ $type }}"
     data-id="{{ $content->id }}"
     data-isolated="{{ $isolated ? 1 : 0 }}"
     data-url-data="{{ route('cores.groups.contents.data', ['id' => '__GROUP__']) }}"
     data-url-place="{{ route('cores.groups.contents.items.place', ['id' => '__GROUP__']) }}">
  @if($isolated)
    <div class="isolated-owned small text-muted mt-2" @if($content->groups->isEmpty()) style="display: none;" @endif>
      <i class="bi bi-lock-fill me-1"></i>Ce contenu appartient à un{{ term('fem_e') }} seul{{ term('fem_e') }} {{ term('group') }}.
      Pour le proposer à {{ term('a_group') }} de plus, utilisez « Copier vers… ».
      @if(Route::has('cores.groups.contents.items.copy'))
        {{-- Groupes cibles proposés : tous pour un admin, ceux qu'il anime pour un formateur (revérifié côté serveur). --}}
        @php($copyTargets = auth()->user()->hasAnyRole(\Modules\Core\Services\ContentEditAccess::ADMIN_ROLES) ? \Modules\Core\Models\Group::query()->orderBy('name')->get(['id', 'name']) : (auth()->user()->trainer?->groups()->orderBy('name')->get(['groups.id', 'groups.name']) ?? collect()))
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-2 btn-copy-to-group"
                data-type="{{ $type }}" data-id="{{ $content->id }}"
                data-url-copy="{{ route('cores.groups.contents.items.copy', ['id' => '__GROUP__', 'type' => $type, 'itemId' => $content->id]) }}"
                data-targets='@json($copyTargets->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values())'
                style="border-radius: 8px;">
          <i class="bi bi-copy me-1"></i>Copier vers…
        </button>
      @endif
    </div>
  @endif
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin/group-folder-picker.js') }}"></script>
  @endpush
@endonce
