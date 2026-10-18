{{-- Interrupteur « Visible en mode découverte » : admins seulement, désactivé pour les formateurs. --}}
@php($canShowcase = auth()->user()->hasAnyRole(['super-admin', 'admin', 'Admin']))
<div class="form-check form-switch px-3 py-2 ms-4"
     @unless($canShowcase) data-bs-toggle="tooltip" title="Réservé aux administrateurs" @endunless>
  <input class="form-check-input" type="checkbox" role="switch" id="showcaseSwitch"
         data-showcase-url="{{ route('cores.showcase.toggle', ['type' => $showcaseType, 'id' => $showcaseItem->id]) }}"
         {{ $showcaseItem->is_showcase ? 'checked' : '' }} {{ $canShowcase ? '' : 'disabled' }}>
  <label class="form-check-label small" for="showcaseSwitch">Visible en mode découverte</label>
</div>
@if ($canShowcase)
  @once
    @push('scripts')
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var sw = document.getElementById('showcaseSwitch');
          if (!sw) return;
          sw.addEventListener('change', function () {
            var wanted = sw.checked;
            fetch(sw.dataset.showcaseUrl, {
              method: 'PATCH',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              },
              body: JSON.stringify({ is_showcase: wanted })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                if (!res.ok || !res.d.success) throw new Error(res.d.message || 'Erreur');
                sw.checked = !!res.d.is_showcase;
                Swal.fire({ icon: 'success', title: sw.checked ? 'Visible en mode découverte' : 'Retiré du mode découverte', timer: 1500, showConfirmButton: false });
              })
              .catch(function (e) {
                sw.checked = !wanted;
                Swal.fire({ icon: 'error', title: 'Erreur', text: e.message || 'Mise à jour impossible' });
              });
          });
        });
      </script>
    @endpush
  @endonce
@endif
