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
        retention_months: $('#retention_months').val()
      };

      $('#saveBtn').prop('disabled', true);
      $.ajax({
        url: this.action, method: 'PUT', contentType: 'application/json', dataType: 'json',
        data: JSON.stringify(data)
      }).done(function (res) {
        Swal.fire({ icon: 'success', title: res.message, timer: 1800, showConfirmButton: false });
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
