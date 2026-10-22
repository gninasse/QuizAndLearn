@extends('core::layouts.master')

@section('title', 'Partage des contenus')
@section('header', 'Partage des contenus')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cores.settings.general') }}">Paramètres généraux</a></li>
    <li class="breadcrumb-item active" aria-current="page">Partage des contenus</li>
@endsection

@section('content')
<div class="card mb-3">
  <div class="card-header"><h3 class="card-title">Mode de partage</h3></div>
  <div class="card-body">
    <p class="text-muted">
      En mode <strong>partagé</strong>, un même contenu peut être attribué à plusieurs {{ term('groups') }}.
      En mode <strong>isolé</strong>, chaque contenu appartient à {{ term('a_group') }} seulement ; les autres {{ term('groups') }} en reçoivent une copie via « Copier vers… ».
    </p>
    <p>
      Mode actuel :
      @if ($mode === 'isolated')
        <span class="badge text-bg-primary">Isolé</span>
      @else
        <span class="badge text-bg-secondary">Partagé</span>
      @endif
    </p>
    @if ($mode === 'isolated')
      <button type="button" class="btn btn-outline-secondary mode-btn" data-mode="shared">Revenir au mode partagé</button>
    @else
      <button type="button" class="btn btn-primary mode-btn" data-mode="isolated" @disabled($conflicts->isNotEmpty())>Activer le mode isolé</button>
      @if ($conflicts->isNotEmpty())
        <div class="form-text">Lancez d'abord la bascule ci-dessous : {{ $conflicts->count() }} contenu(s) appartiennent encore à plusieurs {{ term('groups') }}.</div>
      @endif
    @endif
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><h3 class="card-title">Bascule vers le mode isolé</h3></div>
  <div class="card-body">
    @if ($conflicts->isEmpty())
      <p class="mb-0 text-success">Aucun contenu n'est attribué à plusieurs {{ term('groups') }}.</p>
    @else
      <p class="text-muted">
        Pour chaque contenu ci-dessous, l'original reste dans {{ term('the_group') }} qui compte le plus d'apprenants.
        Les autres {{ term('groups') }} reçoivent une copie, au même dossier et au même rang, et l'historique de leurs apprenants
        (tentatives, réponses, progression, révisions) est transféré sur la copie. Les decks publics ne sont pas concernés.
      </p>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Type</th><th>Contenu</th><th>{{ ucfirst(term('groups')) }}</th></tr></thead>
          <tbody>
            @foreach ($conflicts as $c)
              <tr>
                <td>{{ $typeLabels[$c['type']] ?? $c['type'] }}</td>
                <td>{{ $c['title'] }}</td>
                <td>{{ collect($c['group_ids'])->map(fn ($g) => $groupNames[$g] ?? "#{$g}")->join(', ') }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div id="planResult" class="mb-3"></div>
      <button type="button" class="btn btn-outline-primary" id="simulateBtn">Simuler</button>
      <button type="button" class="btn btn-danger" id="applyBtn">Lancer la bascule</button>
    @endif
  </div>
</div>
@endsection

@push('js')
<script>
  $(function () {
    var labels = @json($typeLabels);

    function fail(title) {
      return function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Erreur';
        var errors = xhr.responseJSON && xhr.responseJSON.errors;
        if (errors) { msg = Object.values(errors).flat().join('\n'); }
        Swal.fire({ icon: 'error', title: title, text: msg });
      };
    }

    $('#simulateBtn').on('click', function () {
      var $btn = $(this).prop('disabled', true);
      $.ajax({ url: @json(route('cores.settings.content-sharing.simulate')), method: 'POST', dataType: 'json' })
        .done(function (res) {
          var $list = $('<ul class="mb-0"></ul>');
          res.plan.forEach(function (p) {
            var copies = p.copies.map(function (c) {
              return (res.groups[c.group_id] || '#' + c.group_id) + ' (' + c.learners + ' apprenant(s))';
            }).join(', ');
            $('<li></li>').text(
              (labels[p.type] || p.type) + ' « ' + (res.titles[p.type + ':' + p.id] || p.id) + ' » : original conservé dans '
              + (res.groups[p.keep_group_id] || '#' + p.keep_group_id) + ' ; copie pour ' + copies
            ).appendTo($list);
          });
          var $box = $('<div class="alert alert-light border"></div>').append($list);
          if (res.multi_exemplar_learners > 0) {
            // Examens : leurs tentatives terminées sont dupliquées sur chaque exemplaire visible.
            $('<p class="mt-2 mb-0 small text-muted"></p>')
              .text(res.multi_exemplar_message + ' (tentatives d\'examen reportées sur chaque exemplaire).')
              .appendTo($box);
          }
          $('#planResult').empty().append($box);
        })
        .fail(fail('Simulation impossible'))
        .always(function () { $btn.prop('disabled', false); });
    });

    $('#applyBtn').on('click', function () {
      var $btn = $(this);
      Swal.fire({
        icon: 'warning', title: 'Lancer la bascule ?',
        text: 'Les copies seront créées et l\'historique des apprenants transféré. Cette opération ne s\'annule pas.',
        showCancelButton: true, confirmButtonText: 'Lancer', cancelButtonText: 'Annuler'
      }).then(function (r) {
        if (!r.isConfirmed) { return; }
        $btn.prop('disabled', true);
        $.ajax({ url: @json(route('cores.settings.content-sharing.apply')), method: 'POST', dataType: 'json' })
          .done(function (res) {
            Swal.fire({ icon: 'success', title: 'Bascule terminée', text: res.message })
              .then(function () { window.location.reload(); });
          })
          .fail(fail('Bascule impossible'))
          .always(function () { $btn.prop('disabled', false); });
      });
    });

    $('.mode-btn').on('click', function () {
      var $btn = $(this).prop('disabled', true);
      $.ajax({
        url: @json(route('cores.settings.content-sharing.mode')), method: 'PUT', contentType: 'application/json',
        dataType: 'json', data: JSON.stringify({ mode: $btn.data('mode') })
      }).done(function (res) {
        Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false })
          .then(function () { window.location.reload(); });
      }).fail(fail('Changement impossible'))
        .always(function () { $btn.prop('disabled', false); });
    });
  });
</script>
@endpush
