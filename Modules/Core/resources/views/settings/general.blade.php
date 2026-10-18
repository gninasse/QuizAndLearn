@extends('core::layouts.master')

@section('title', 'Paramètres généraux')
@section('header', 'Paramètres généraux')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Paramètres généraux</li>
@endsection

@section('content')
<form id="generalSettingsForm" action="{{ route('cores.settings.general.save') }}" novalidate>
  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Terminologie</h3></div>
    <div class="card-body">
      <p class="text-muted">Choisissez le terme utilisé dans toute la plateforme (administration, application apprenant, e-mails) pour désigner un groupe d'apprenants.</p>
      <div class="mb-3" style="max-width: 320px">
        <label class="form-label" for="preset">Terme</label>
        <select class="form-select" id="preset" name="preset">
          @foreach ($presets as $key => $p)
            <option value="{{ $key }}" @selected($config['preset'] === $key)>{{ ucfirst($p['singular']) }}</option>
          @endforeach
          <option value="custom" @selected($config['preset'] === 'custom')>Personnalisé…</option>
        </select>
      </div>
      <div id="customFields" class="row {{ $config['preset'] === 'custom' ? '' : 'd-none' }}">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="singular">Singulier</label>
          <input type="text" class="form-control" id="singular" name="singular" maxlength="30" value="{{ $config['preset'] === 'custom' ? $config['singular'] : '' }}">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="plural">Pluriel</label>
          <input type="text" class="form-control" id="plural" name="plural" maxlength="30" value="{{ $config['preset'] === 'custom' ? $config['plural'] : '' }}">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label d-block">Genre</label>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="feminine" id="genderM" value="0" {{ ! $config['feminine'] ? 'checked' : '' }}>
            <label class="form-check-label" for="genderM">Masculin</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="feminine" id="genderF" value="1" {{ $config['feminine'] ? 'checked' : '' }}>
            <label class="form-check-label" for="genderF">Féminin</label>
          </div>
        </div>
      </div>
      <div class="alert alert-light border mb-0" id="termPreview"></div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary" id="saveBtn">Enregistrer</button>
</form>
@endsection

@push('js')
<script>
  $(function () {
    var presets = @json($presets);

    function current() {
      var preset = $('#preset').val();
      if (preset === 'custom') {
        return { singular: $.trim($('#singular').val()), plural: $.trim($('#plural').val()), feminine: $('#genderF').is(':checked') };
      }
      return presets[preset];
    }

    function articles(t) {
      var s = t.singular;
      var vowel = /^[aeiouyhàâäéèêëîïôöûùü]/i.test(s);
      return {
        a: (t.feminine ? 'une ' : 'un ') + s,
        the: vowel ? "l'" + s : (t.feminine ? 'la ' : 'le ') + s
      };
    }

    function refresh() {
      $('#customFields').toggleClass('d-none', $('#preset').val() !== 'custom');
      var t = current();
      if (!t.singular || !t.plural) { $('#termPreview').text('Exemple : —'); return; }
      var a = articles(t);
      var cap = t.plural.charAt(0).toUpperCase() + t.plural.slice(1);
      $('#termPreview').text('Exemple : Gérer les ' + t.plural + ' · ' + a.a + ' · ' + a.the + ' · ' + cap);
    }

    $('#preset, #singular, #plural, input[name=feminine]').on('input change', refresh);
    refresh();

    $('#generalSettingsForm').on('submit', function (e) {
      e.preventDefault();
      var data = { preset: $('#preset').val() };
      if (data.preset === 'custom') {
        data.singular = $.trim($('#singular').val());
        data.plural = $.trim($('#plural').val());
        data.feminine = $('#genderF').is(':checked');
      }
      $('#saveBtn').prop('disabled', true);
      $.ajax({
        url: this.action, method: 'PUT', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify(data)
      }).done(function (res) {
        Swal.fire({ icon: 'success', title: res.message, timer: 1500, showConfirmButton: false })
          .then(function () { window.location.reload(); });
      }).fail(function (xhr) {
        var msg = (xhr.responseJSON && (xhr.responseJSON.message || 'Erreur')) || 'Erreur';
        var errors = xhr.responseJSON && xhr.responseJSON.errors;
        if (errors) { msg = Object.values(errors).flat().join('\n'); }
        Swal.fire({ icon: 'error', title: 'Enregistrement impossible', text: msg });
      }).always(function () { $('#saveBtn').prop('disabled', false); });
    });
  });
</script>
@endpush
