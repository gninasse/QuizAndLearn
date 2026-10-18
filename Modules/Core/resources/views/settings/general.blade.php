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
  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Notifications</h3></div>
    <div class="card-body">
      <p class="mb-3">
        @if ($vapidConfigured)
          <span class="badge text-bg-success">Clés VAPID configurées</span>
        @else
          <span class="badge text-bg-warning">Non configuré — voir DEPLOIEMENT.md (php artisan push:vapid)</span>
        @endif
      </p>
      @foreach (['new_content' => 'Nouveau contenu', 'cards_due' => 'Cartes à réviser', 'streak' => 'Série en danger', 'exam_open' => 'Examen ouvert'] as $type => $label)
        <div class="form-check form-switch mb-2">
          <input class="form-check-input push-type" type="checkbox" role="switch" id="push_{{ $type }}" data-type="{{ $type }}" @checked($push['types'][$type])>
          <label class="form-check-label" for="push_{{ $type }}">{{ $label }}</label>
        </div>
      @endforeach
      <div class="row mt-3">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="pushDailyHour">Heure du rappel quotidien</label>
          <input type="number" class="form-control" id="pushDailyHour" min="7" max="20" step="1" value="{{ $push['daily_hour'] }}" aria-describedby="pushDailyHourHelp">
          <div class="form-text" id="pushDailyHourHelp">Entre 7 h et 20 h : aucune notification n'est envoyée de 21 h à 7 h.</div>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="pushDailyCap">Notifications par jour et par apprenant (maximum)</label>
          <input type="number" class="form-control" id="pushDailyCap" min="1" max="5" step="1" value="{{ $push['daily_cap'] }}">
        </div>
      </div>
      <button type="button" class="btn btn-outline-secondary" id="pushTestBtn" @disabled(! $vapidConfigured || ! $canTestPush)>Envoyer une notification de test</button>
      @unless ($canTestPush)
        <div class="form-text">Abonnez-vous depuis l'espace apprenant d'un compte de test pour recevoir la notification de test.</div>
      @endunless
    </div>
  </div>
  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Vidéos</h3></div>
    <div class="card-body">
      <p class="mb-2">Pilote actif : <strong>{{ $videoDriver }}</strong> <span class="text-muted">(défini dans le fichier .env, lecture seule)</span></p>
      @if (count($videoRequirements))
        <ul class="list-unstyled mb-3">
          @foreach ($videoRequirements as $env => $ok)
            <li><code>{{ $env }}</code> :
              @if ($ok)
                <span class="badge text-bg-success">configurée</span>
              @else
                <span class="badge text-bg-warning">manquante</span>
              @endif
            </li>
          @endforeach
        </ul>
      @endif
      <button type="button" class="btn btn-outline-secondary mb-4" id="videoTestBtn">Tester la connexion</button>
      <h6>Vidéos YouTube / Vimeo dans les contenus</h6>
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" role="switch" id="videoEmbedsEnabled" @checked($video['enabled'])>
        <label class="form-check-label" for="videoEmbedsEnabled">Autoriser les vidéos YouTube / Vimeo</label>
      </div>
      @foreach (['youtube' => 'YouTube', 'vimeo' => 'Vimeo'] as $key => $label)
        <div class="form-check form-switch mb-2 ms-3">
          <input class="form-check-input video-provider" type="checkbox" role="switch" id="videoProvider_{{ $key }}" data-provider="{{ $key }}" @checked(in_array($key, $video['providers'], true))>
          <label class="form-check-label" for="videoProvider_{{ $key }}">{{ $label }}</label>
        </div>
      @endforeach
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
      data.push = { types: {}, daily_hour: parseInt($('#pushDailyHour').val(), 10), daily_cap: parseInt($('#pushDailyCap').val(), 10) };
      $('.push-type').each(function () { data.push.types[$(this).data('type')] = this.checked; });
      data.video = { embeds: { enabled: $('#videoEmbedsEnabled').is(':checked'), providers: [] } };
      $('.video-provider:checked').each(function () { data.video.embeds.providers.push($(this).data('provider')); });
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

    $('#videoTestBtn').on('click', function () {
      var $btn = $(this).prop('disabled', true);
      $.ajax({
        url: @json(route('cores.settings.video.test')), method: 'POST', dataType: 'json',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
      }).done(function (res) {
        Swal.fire({ icon: res.ok ? 'success' : 'error', title: res.ok ? 'Connexion réussie' : 'Connexion impossible', text: res.message });
      }).fail(function (xhr) {
        Swal.fire({ icon: 'error', title: 'Test impossible', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Erreur' });
      }).always(function () { $btn.prop('disabled', false); });
    });

    $('#pushTestBtn').on('click', function () {
      var $btn = $(this).prop('disabled', true);
      $.ajax({
        url: @json(route('cores.settings.push.test')), method: 'POST', dataType: 'json',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
      }).done(function (res) {
        Swal.fire({ icon: 'success', title: res.message, timer: 2000, showConfirmButton: false });
      }).fail(function (xhr) {
        Swal.fire({ icon: 'error', title: 'Envoi impossible', text: (xhr.responseJSON && xhr.responseJSON.message) || 'Erreur' });
      }).always(function () { $btn.prop('disabled', false); });
    });
  });
</script>
@endpush
