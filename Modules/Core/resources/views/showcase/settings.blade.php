@extends('core::layouts.master')

@section('title', 'Mode découverte')
@section('header', 'Mode découverte')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Mode découverte</li>
@endsection

@section('content')
<form id="showcaseSettingsForm" action="{{ route('cores.showcase.settings.save') }}" novalidate>
  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Général</h3></div>
    <div class="card-body">
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" {{ $settings->enabled() ? 'checked' : '' }}>
        <label class="form-check-label" for="enabled">Activer le mode découverte</label>
      </div>
      <div class="mb-3">
        <label class="form-label" for="tagline">Accroche</label>
        <input type="text" class="form-control" id="tagline" name="tagline" maxlength="160" value="{{ $settings->tagline() }}">
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="default_country_code">Indicatif téléphonique par défaut</label>
          <div class="input-group"><span class="input-group-text">+</span>
            <input type="text" class="form-control" id="default_country_code" name="default_country_code" value="{{ $settings->defaultCountryCode() }}"></div>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="retention_months">Conservation des demandes refusées ou abandonnées (mois)</label>
          <input type="number" min="1" max="120" class="form-control" id="retention_months" name="retention_months" value="{{ $settings->retentionMonths() }}">
        </div>
      </div>
      <div class="mb-0">
        <label class="form-label" for="notification_emails">E-mails de notification</label>
        <textarea class="form-control" id="notification_emails" name="notification_emails" rows="2" placeholder="Un par ligne">{{ implode("\n", $settings->notificationEmails()) }}</textarea>
        <div class="form-text">Ces adresses reçoivent un message à chaque nouvelle demande d'accès.</div>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Instructions d'inscription</h3></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="fee">Montant</label>
          <input type="number" min="0" class="form-control" id="fee" name="fee" value="{{ $settings->fee() }}">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="currency">Devise</label>
          <input type="text" class="form-control text-uppercase" id="currency" name="currency" maxlength="3" value="{{ $settings->currency() }}">
          <div class="form-text">Code ISO à 3 lettres, ex. XOF</div>
        </div>
      </div>

      <label class="form-label">Comptes de paiement</label>
      <div id="accounts"></div>
      <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addAccount"><i class="bi bi-plus-lg"></i> Ajouter un compte</button>

      <div class="mb-3">
        <label class="form-label" for="payment_reference">Mention à indiquer dans le transfert</label>
        <input type="text" class="form-control" id="payment_reference" name="payment_reference" maxlength="160" value="{{ $settings->paymentReference() }}">
      </div>
      <div class="mb-0">
        <label class="form-label" for="instructions">Instructions</label>
        <textarea class="form-control" id="instructions" name="instructions" rows="3" maxlength="2000">{{ $settings->instructions() }}</textarea>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><h3 class="card-title">Confidentialité</h3></div>
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label" for="privacy_url">Lien externe (prioritaire s'il est renseigné)</label>
        <input type="url" class="form-control" id="privacy_url" name="privacy_url" placeholder="https://" value="{{ $settings->privacyUrl() }}">
      </div>
      <div class="mb-0">
        <label class="form-label" for="privacy_text">Texte de la politique</label>
        <textarea class="form-control" id="privacy_text" name="privacy_text" rows="8">{{ $settings->privacyText() }}</textarea>
        <div class="form-text">Laissez vide pour afficher le texte par défaut. Page publique : <a href="{{ route('privacy') }}" target="_blank" rel="noopener">{{ route('privacy') }}</a></div>
      </div>
    </div>
  </div>

  <div class="card mb-3" id="accessRequestCard">
    <div class="card-header"><h3 class="card-title">Demande d'accès</h3></div>
    <div class="card-body">
      <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" role="switch" id="ar_enabled">
        <label class="form-check-label" for="ar_enabled">Accepter les demandes d'accès</label>
      </div>

      <h5 class="mb-1">Canaux de contact</h5>
      <p class="form-text mt-0">Cochez les moyens proposés aux visiteurs et ordonnez-les avec les flèches. Seul le formulaire alimente le suivi des demandes.</p>
      <div id="ar_channels" class="mb-4">
        @foreach ([
          'form' => 'Formulaire de demande',
          'whatsapp' => 'WhatsApp',
          'phone' => 'Appel téléphonique',
          'email' => 'E-mail',
          'link' => 'Lien externe',
        ] as $type => $label)
          <div class="border rounded p-2 mb-2 ar-channel" data-type="{{ $type }}">
            <div class="d-flex align-items-center gap-2">
              <div class="form-check flex-grow-1 mb-0">
                <input class="form-check-input ar-channel-on" type="checkbox" id="ar_ch_{{ $type }}">
                <label class="form-check-label" for="ar_ch_{{ $type }}">{{ $label }}</label>
              </div>
              <button type="button" class="btn btn-outline-secondary btn-sm ar-up" title="Monter" aria-label="Monter"><i class="bi bi-arrow-up"></i></button>
              <button type="button" class="btn btn-outline-secondary btn-sm ar-down" title="Descendre" aria-label="Descendre"><i class="bi bi-arrow-down"></i></button>
            </div>
            @if ($type !== 'form')
            <div class="row g-2 mt-1 ar-sub d-none">
              @if ($type === 'whatsapp')
                <div class="col-md-4"><input type="text" class="form-control" data-sub="number" placeholder="Numéro WhatsApp" maxlength="30"></div>
                <div class="col-md-8"><input type="text" class="form-control" data-sub="message" placeholder="Message pré-rempli" maxlength="500"></div>
              @elseif ($type === 'phone')
                <div class="col-md-4"><input type="text" class="form-control" data-sub="number" placeholder="Numéro de téléphone" maxlength="30"></div>
              @elseif ($type === 'email')
                <div class="col-md-5"><input type="email" class="form-control" data-sub="address" placeholder="Adresse e-mail" maxlength="190"></div>
                <div class="col-md-7"><input type="text" class="form-control" data-sub="subject" placeholder="Objet" maxlength="150"></div>
              @else
                <div class="col-md-7"><input type="url" class="form-control" data-sub="url" placeholder="https://" maxlength="500"></div>
                <div class="col-md-5"><input type="text" class="form-control" data-sub="label" placeholder="Libellé du lien" maxlength="60"></div>
              @endif
            </div>
            @endif
          </div>
        @endforeach
        <div class="text-danger small ar-err" data-path="channels"></div>
      </div>

      <h5 class="mb-2">Champs du formulaire</h5>
      <div class="table-responsive mb-4">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Champ</th><th>Affichage</th></tr></thead>
          <tbody>
            @foreach (['first_name' => 'Prénom', 'last_name' => 'Nom', 'phone' => 'Téléphone', 'consent' => 'Consentement'] as $key => $label)
              <tr class="table-secondary text-muted"><td>{{ $label }}</td><td>Obligatoire (non modifiable)</td></tr>
            @endforeach
            @foreach (['whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'organization' => 'Organisation', 'group' => term('Group'), 'message' => 'Message'] as $key => $label)
              <tr class="ar-field" data-field="{{ $key }}">
                <td>{{ $label }}</td>
                <td>
                  <div class="btn-group btn-group-sm" role="group" aria-label="{{ $label }}">
                    @foreach (['hidden' => 'Masqué', 'optional' => 'Facultatif', 'required' => 'Obligatoire'] as $mode => $modeLabel)
                      <input type="radio" class="btn-check" name="ar_field_{{ $key }}" id="ar_f_{{ $key }}_{{ $mode }}" value="{{ $mode }}" autocomplete="off">
                      <label class="btn btn-outline-primary" for="ar_f_{{ $key }}_{{ $mode }}">{{ $modeLabel }}</label>
                    @endforeach
                  </div>
                  @if ($key === 'group' && ! ($hasOpenGroups ?? true))
                    <div class="form-text text-warning" data-role="no-open-group">{{ ucfirst(term('no_group')) }} n'est ouvert{{ term('fem_e') }} aux demandes : ce champ n'est pas affiché et n'est jamais obligatoire.</div>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <h5 class="mb-1">Champs personnalisés</h5>
      <p class="form-text mt-0">Jusqu'à 5 champs supplémentaires.</p>
      <div id="ar_custom"></div>
      <div class="text-danger small ar-err mb-2" data-path="custom_fields"></div>
      <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="ar_addCustom"><i class="bi bi-plus-lg"></i> Ajouter un champ</button>

      <h5 class="mb-2">Textes</h5>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="ar_button_label">Libellé du bouton</label>
          <input type="text" class="form-control" id="ar_button_label" maxlength="40">
          <div class="text-danger small ar-err" data-path="button_label"></div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="ar_title">Titre</label>
          <input type="text" class="form-control" id="ar_title" maxlength="80">
          <div class="text-danger small ar-err" data-path="title"></div>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="ar_intro">Introduction</label>
          <textarea class="form-control" id="ar_intro" rows="2" maxlength="1000"></textarea>
          <div class="text-danger small ar-err" data-path="intro"></div>
        </div>
        <div class="col-12 mb-0">
          <label class="form-label" for="ar_confirmation">Message de confirmation</label>
          <textarea class="form-control" id="ar_confirmation" rows="2" maxlength="500"></textarea>
          <div class="text-danger small ar-err" data-path="confirmation"></div>
          <div class="form-text">Laissez un texte vide pour afficher le texte par défaut (indiqué en grisé).</div>
        </div>
      </div>
    </div>
  </div>

  <template id="ar_customRow">
    <div class="border rounded p-2 mb-2 ar-custom-row">
      <div class="row g-2">
        <div class="col-md-5"><input type="text" class="form-control" data-cf="label" placeholder="Libellé" maxlength="80"></div>
        <div class="col-6 col-md-3">
          <select class="form-select" data-cf="type">
            <option value="text">Texte court</option>
            <option value="textarea">Texte long</option>
            <option value="select">Liste de choix</option>
          </select>
        </div>
        <div class="col-4 col-md-2 d-flex align-items-center">
          <div class="form-check mb-0"><input class="form-check-input" type="checkbox" data-cf="required"><label class="form-check-label">Obligatoire</label></div>
        </div>
        <div class="col-2 col-md-2"><button type="button" class="btn btn-outline-danger w-100 ar-remove-custom" title="Supprimer" aria-label="Supprimer"><i class="bi bi-trash"></i></button></div>
        <div class="col-12 d-none ar-options"><textarea class="form-control" rows="3" data-cf="options" placeholder="Un choix par ligne (2 à 20)"></textarea></div>
      </div>
      <div class="text-danger small ar-err" data-cf-err></div>
    </div>
  </template>

  <button type="submit" class="btn btn-primary" id="saveBtn"><i class="bi bi-check-lg"></i> Enregistrer</button>
</form>

<template id="accountRow">
  <div class="row g-2 mb-2 account-row">
    <div class="col-md-3">
      <select class="form-select" data-field="operator">
        @foreach ($operators as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3"><input type="text" class="form-control" data-field="number" placeholder="Numéro" maxlength="30"></div>
    <div class="col-md-5"><input type="text" class="form-control" data-field="holder" placeholder="Bénéficiaire" maxlength="120"></div>
    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-account" title="Retirer"><i class="bi bi-trash"></i></button></div>
  </div>
</template>
@endsection

@push('js')
<script>
  window.ACCESS_REQUEST = {
    config: @json($settings->accessRequest()),
    defaults: @json(\Modules\Core\Services\ShowcaseSettings::ACCESS_DEFAULTS)
  };
</script>
<script src="{{ asset('js/modules/core/showcase/access-request.js') }}"></script>
<script>
  $(function () {
    var initial = @json($settings->paymentAccounts());
    var $list = $('#accounts');

    function addRow(a) {
      var $row = $($('#accountRow').html());
      if (a) {
        $row.find('[data-field=operator]').val(a.operator);
        $row.find('[data-field=number]').val(a.number);
        $row.find('[data-field=holder]').val(a.holder);
      }
      $list.append($row);
    }

    initial.forEach(addRow);
    $('#addAccount').on('click', function () { addRow(null); });
    $list.on('click', '.remove-account', function () { $(this).closest('.account-row').remove(); });

    $('#showcaseSettingsForm').on('submit', function (e) {
      e.preventDefault();
      var accounts = $list.find('.account-row').map(function () {
        return {
          operator: $(this).find('[data-field=operator]').val(),
          number: $.trim($(this).find('[data-field=number]').val()),
          holder: $.trim($(this).find('[data-field=holder]').val())
        };
      }).get();

      var data = {
        enabled: $('#enabled').is(':checked'),
        tagline: $('#tagline').val(),
        default_country_code: $('#default_country_code').val(),
        currency: $('#currency').val(),
        fee: $('#fee').val() === '' ? null : $('#fee').val(),
        payment_accounts: accounts,
        payment_reference: $('#payment_reference').val(),
        instructions: $('#instructions').val(),
        notification_emails: $('#notification_emails').val(),
        privacy_text: $('#privacy_text').val(),
        privacy_url: $('#privacy_url').val(),
        retention_months: $('#retention_months').val(),
        access_request: window.AccessRequestForm.collect()
      };

      window.AccessRequestForm.clearErrors();
      $('#saveBtn').prop('disabled', true);
      $.ajax({
        url: this.action, method: 'PUT', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify(data)
      }).done(function (res) {
        Swal.fire({ icon: 'success', title: res.message, timer: 1800, showConfirmButton: false });
      }).fail(function (xhr) {
        var msg = (xhr.responseJSON && (xhr.responseJSON.message || 'Erreur')) || 'Erreur';
        var errors = xhr.responseJSON && xhr.responseJSON.errors;
        if (errors) { window.AccessRequestForm.showErrors(errors); }
        if (errors) { msg = Object.values(errors).flat().join('\n'); }
        Swal.fire({ icon: 'error', title: 'Enregistrement impossible', text: msg });
      }).always(function () { $('#saveBtn').prop('disabled', false); });
    });
  });
</script>
@endpush
