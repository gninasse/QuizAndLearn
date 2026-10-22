{{--
  Placement du contenu dans les dossiers de ses groupes (sélecteur par groupe,
  voir public/js/admin/group-folder-picker.js) et règles du mode de partage.

  Paramètres : $type (article|quiz|exam|deck), $content (modèle).
  En mode `isolated` (decks publics exceptés) : un seul groupe par contenu, « Copier vers… » est
  l'action principale. En mode `shared`, « Copier vers… » est aussi proposé (action secondaire).
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
        @include('core::admin.partials.group-copy-button', ['primary' => true])
      @endif
    </div>
  @elseif(Route::has('cores.groups.contents.items.copy'))
    @include('core::admin.partials.group-copy-button', ['primary' => false])
  @endif
</div>

@once
  @push('scripts')
    <script src="{{ asset('js/admin/group-folder-picker.js') }}"></script>
  @endpush
@endonce
