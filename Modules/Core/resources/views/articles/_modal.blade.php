<!-- Modal -->
<div class="modal fade" id="articleModal" tabindex="-1" role="dialog" aria-labelledby="articleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Créer un article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <form id="articleForm">
                @csrf
                <input type="hidden" id="article_id" name="article_id">
                
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="title">Titre de l'article <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="group_ids">{{ term('Groups') }} assigné{{ term('fem_e') }}s</label>
                        @php($singleGroup = \Modules\Core\Services\ContentSharing::isolated())
                        <select id="group_ids" name="group_ids[]" class="form-select select2" @unless($singleGroup) multiple @endunless data-placeholder="{{ $singleGroup ? 'Sélectionner '.term('a_group') : 'Sélectionner les '.term('groups') }}" style="width: 100%;">
                            @if($singleGroup)
                                <option value=""></option>
                            @endif
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @if($singleGroup)
                            <div class="form-text">Un contenu n'appartient qu'à un{{ term('fem_e') }} seul{{ term('fem_e') }} {{ term('group') }}.</div>
                        @endif
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="is_active">Actif / Publié</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="btn-save">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
