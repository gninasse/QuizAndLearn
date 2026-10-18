/**
 * Écran « Demandes d'accès » : liste filtrable, fiche latérale, statuts,
 * notes et paiements Mobile Money saisis à la main.
 * Les URLs viennent de la vue (data-*), sans Ziggy.
 */

const root = document.getElementById('access-requests-app');
const urls = { data: root.dataset.urlData, base: root.dataset.urlBase };
const currency = root.dataset.currency || 'XOF';
const groups = JSON.parse(root.dataset.groups || '[]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const STATUS_LABELS = {
    new: ['Nouvelle', 'danger'],
    contacted: ['Contactée', 'info'],
    payment_pending: ['Paiement en attente', 'warning'],
    paid: ['Payée', 'success'],
    account_created: ['Compte créé', 'primary'],
    rejected: ['Refusée', 'secondary'],
    abandoned: ['Abandonnée', 'secondary'],
};
const OPERATOR_LABELS = {
    orange_money: 'Orange Money',
    mtn_momo: 'MTN MoMo',
    moov_money: 'Moov Money',
    wave: 'Wave',
    other: 'Autre',
};

let state = { rows: [], counts: {} };
let filters = { status: '', q: '' };
let current = null; // fiche ouverte
let offcanvas = null;

// ------------------------------------------------------------------ Utils

const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const formatDate = (iso, withTime = false) => {
    if (!iso) return '—';
    const date = new Date(iso.length === 10 ? `${iso}T00:00:00` : iso);
    return withTime
        ? date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
        : date.toLocaleDateString('fr-FR');
};

const money = (amount) => `${Number(amount || 0).toLocaleString('fr-FR')} ${currency}`;

const statusBadge = (status) => {
    const [label, color] = STATUS_LABELS[status] ?? [status, 'secondary'];
    return `<span class="badge text-bg-${color}">${esc(label)}</span>`;
};

async function request(method, url, body) {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(firstError || data.message || `Erreur ${response.status}`);
    }
    return data;
}

const notify = (message, icon = 'success') =>
    Swal.fire({ toast: true, position: 'top-end', icon, title: message, showConfirmButton: false, timer: 2600 });

const fail = (error) => Swal.fire('Erreur', error.message || 'Une erreur est survenue.', 'error');

// ------------------------------------------------------------- Liste

async function load() {
    const params = new URLSearchParams();
    if (filters.status) params.set('status', filters.status);
    if (filters.q) params.set('q', filters.q);
    try {
        state = await request('GET', `${urls.data}?${params}`);
        renderCounts();
        renderRows();
    } catch (error) {
        document.getElementById('requests-body').innerHTML =
            `<tr><td colspan="7" class="text-center text-danger py-5">${esc(error.message)}</td></tr>`;
    }
}

function renderCounts() {
    const c = state.counts;
    const total = Object.values(c).reduce((a, b) => a + b, 0);
    document.querySelector('[data-count="all"]').textContent = total;
    Object.keys(STATUS_LABELS).forEach((status) => {
        const el = document.querySelector(`[data-count="${status}"]`);
        if (el) el.textContent = c[status] ?? 0;
    });
    document.getElementById('kpi-new').textContent = c.new ?? 0;
    document.getElementById('kpi-open').textContent = (c.new ?? 0) + (c.contacted ?? 0) + (c.payment_pending ?? 0);
    document.getElementById('kpi-paid').textContent = c.paid ?? 0;
    document.getElementById('kpi-created').textContent = c.account_created ?? 0;
}

function renderRows() {
    const body = document.getElementById('requests-body');
    if (!state.rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5">Aucune demande.</td></tr>';
        return;
    }
    body.innerHTML = state.rows.map((r) => `
        <tr data-id="${r.id}">
            <td><strong>${esc(r.full_name)}</strong>${r.organization ? `<div class="small text-muted">${esc(r.organization)}</div>` : ''}</td>
            <td>${esc(r.phone)}</td>
            <td>${esc(r.group ?? '—')}</td>
            <td>${statusBadge(r.status)}</td>
            <td class="text-end">${r.payments_count ? esc(money(r.paid_total)) : '—'}</td>
            <td>${formatDate(r.created_at)}</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-open="${r.id}"><i class="bi bi-eye"></i> Ouvrir</button></td>
        </tr>`).join('');
}

// ------------------------------------------------------------- Fiche

async function openDetails(id) {
    try {
        const data = await request('GET', `${urls.base}/${id}`);
        current = data.request;
        renderDetails();
        offcanvas ??= new bootstrap.Offcanvas(document.getElementById('request-details'));
        offcanvas.show();
    } catch (error) {
        fail(error);
    }
}

function renderDetails() {
    const r = current;
    const waDigits = String(r.whatsapp || r.phone || '').replace(/\D+/g, '');
    const today = new Date().toISOString().slice(0, 10);

    document.getElementById('request-details-title').textContent = r.full_name;
    document.getElementById('request-details-body').innerHTML = `
        <div class="ar-detail">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                ${statusBadge(r.status)}
                <span class="text-muted small">Reçue le ${formatDate(r.created_at, true)}</span>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <a class="btn btn-sm btn-outline-secondary" href="tel:${esc(r.phone)}"><i class="bi bi-telephone me-1"></i>${esc(r.phone)}</a>
                ${waDigits ? `<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/${waDigits}"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>` : ''}
                ${r.email ? `<a class="btn btn-sm btn-outline-secondary" href="mailto:${esc(r.email)}"><i class="bi bi-envelope me-1"></i>${esc(r.email)}</a>` : ''}
            </div>
            <dl class="row small mb-0">
                <dt class="col-4">Organisation</dt><dd class="col-8">${esc(r.organization ?? '—')}</dd>
                <dt class="col-4">${T('Group')} souhaité${T('fem_e')}</dt><dd class="col-8">${esc(r.group ?? '—')}</dd>
                <dt class="col-4">Consentement</dt><dd class="col-8">${formatDate(r.consent_at, true)}</dd>
                <dt class="col-4">Traitée par</dt><dd class="col-8">${esc(r.handled_by ?? '—')}</dd>
                <dt class="col-4">Message</dt><dd class="col-8">${esc(r.message ?? '—')}</dd>
                ${(r.extra_fields ?? []).map((f) => `<dt class="col-4">${esc(f.label)}</dt><dd class="col-8">${esc(f.value ?? '—')}</dd>`).join('')}
            </dl>

            <h6>Statut</h6>
            <div class="d-flex flex-wrap gap-1" id="status-buttons">
                ${Object.entries(STATUS_LABELS).filter(([s]) => s !== 'account_created').map(([s, [label]]) =>
                    `<button type="button" class="btn btn-sm ${s === r.status ? 'btn-primary' : 'btn-outline-secondary'}" data-set-status="${s}" ${s === r.status ? 'disabled' : ''}>${esc(label)}</button>`).join('')}
            </div>
            <div class="mt-2">
                <span data-hook="create-account">
                    ${r.learner_id
                        ? '<span class="badge text-bg-primary"><i class="bi bi-person-check me-1"></i>Compte déjà créé</span>'
                        : `<button type="button" class="btn btn-sm btn-success" id="btn-create-account">
                        <i class="bi bi-person-plus me-1"></i> Créer le compte
                    </button>`}
                </span>
            </div>

            <h6>Notes internes</h6>
            <textarea class="form-control form-control-sm" id="request-notes" rows="3" maxlength="5000">${esc(r.notes ?? '')}</textarea>
            <button type="button" class="btn btn-sm btn-light mt-2" id="btn-save-notes"><i class="bi bi-save me-1"></i> Enregistrer les notes</button>

            <h6>Paiements (${r.payments.length})</h6>
            ${r.payments.length ? `<ul class="list-group mb-3">${r.payments.map((p) => `
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div class="small">
                        <strong>${esc(money(p.amount))}</strong> — ${esc(OPERATOR_LABELS[p.operator] ?? p.operator)}
                        <div class="text-muted">Réf. ${esc(p.reference)} · ${formatDate(p.paid_at)}${p.payer_phone ? ` · ${esc(p.payer_phone)}` : ''}</div>
                        ${p.note ? `<div class="text-muted">${esc(p.note)}</div>` : ''}
                        ${p.recorded_by ? `<div class="text-muted">Saisi par ${esc(p.recorded_by)}</div>` : ''}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-delete-payment="${p.id}" title="Supprimer ce paiement"><i class="bi bi-trash"></i></button>
                </li>`).join('')}</ul>` : '<p class="small text-muted">Aucun paiement enregistré.</p>'}

            <form id="payment-form" class="border rounded p-3 bg-body-tertiary">
                <div class="fw-semibold mb-2"><i class="bi bi-cash-coin me-1"></i> Enregistrer un paiement</div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small mb-1" for="pay-amount">Montant (${esc(currency)})</label>
                        <input type="number" min="1" step="1" class="form-control form-control-sm" id="pay-amount" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="pay-operator">Opérateur</label>
                        <select class="form-select form-select-sm" id="pay-operator" required>
                            ${Object.entries(OPERATOR_LABELS).map(([v, l]) => `<option value="${v}">${esc(l)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="pay-reference">Référence de transaction</label>
                        <input type="text" class="form-control form-control-sm" id="pay-reference" maxlength="100" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-1" for="pay-date">Date</label>
                        <input type="date" class="form-control form-control-sm" id="pay-date" value="${today}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="pay-phone">Numéro du payeur</label>
                        <input type="tel" class="form-control form-control-sm" id="pay-phone" value="${esc(r.phone)}">
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="pay-note">Note</label>
                        <input type="text" class="form-control form-control-sm" id="pay-note" maxlength="500">
                    </div>
                </div>
                <div class="form-text">Saisie manuelle : ne notez jamais de code PIN.</div>
                <button type="submit" class="btn btn-sm btn-primary mt-2"><i class="bi bi-check2 me-1"></i> Enregistrer le paiement</button>
            </form>

            <h6>Historique</h6>
            <ul class="ar-timeline">
                ${r.events.map((e) => `
                    <li>
                        ${e.from_status ? `${statusBadge(e.from_status)} <i class="bi bi-arrow-right mx-1"></i>` : ''}${statusBadge(e.to_status)}
                        <div class="text-muted small">${formatDate(e.created_at, true)}${e.user ? ` · ${esc(e.user)}` : ''}</div>
                        ${e.comment ? `<div class="small">${esc(e.comment)}</div>` : ''}
                    </li>`).join('') || '<li class="text-muted">Aucun événement.</li>'}
            </ul>
        </div>`;
}

async function applyDetail(data) {
    current = data.request;
    renderDetails();
    await load();
}

async function setStatus(status) {
    const { value: comment, isConfirmed } = await Swal.fire({
        title: `Passer à « ${STATUS_LABELS[status][0]} » ?`,
        input: 'text',
        inputPlaceholder: 'Commentaire (facultatif)',
        showCancelButton: true,
        confirmButtonText: 'Confirmer',
        cancelButtonText: 'Annuler',
    });
    if (!isConfirmed) return;
    try {
        const data = await request('POST', `${urls.base}/${current.id}/status`, { status, comment: comment || null });
        await applyDetail(data);
        notify(data.message);
    } catch (error) {
        fail(error);
    }
}

async function createAccount() {
    const r = current;
    const options = [`<option value="">${T('no_group').charAt(0).toUpperCase() + T('no_group').slice(1)}</option>`]
        .concat(groups.map((g) => `<option value="${g.id}">${esc(g.name)}</option>`)).join('');
    const { value: form, isConfirmed } = await Swal.fire({
        title: 'Créer le compte apprenant',
        html: `
            <div class="text-start">
                <label class="form-label small mb-1" for="acc-name">Prénom</label>
                <input class="form-control form-control-sm mb-2" id="acc-name" value="${esc(r.first_name)}">
                <label class="form-label small mb-1" for="acc-last">Nom</label>
                <input class="form-control form-control-sm mb-2" id="acc-last" value="${esc(r.last_name)}">
                <label class="form-label small mb-1" for="acc-phone">Téléphone</label>
                <input class="form-control form-control-sm mb-2" id="acc-phone" value="${esc(r.phone)}">
                <label class="form-label small mb-1" for="acc-email">E-mail (facultatif)</label>
                <input class="form-control form-control-sm mb-2" id="acc-email" type="email" value="${esc(r.email ?? '')}">
                <label class="form-label small mb-1" for="acc-group">${T('Group')}</label>
                <select class="form-select form-select-sm mb-2" id="acc-group">${options}</select>
                <label class="form-label small mb-1" for="acc-matricule">Matricule (facultatif)</label>
                <input class="form-control form-control-sm" id="acc-matricule">
            </div>`,
        didOpen: () => {
            const group = groups.find((g) => g.name === r.group);
            if (group) document.getElementById('acc-group').value = group.id;
        },
        showCancelButton: true,
        confirmButtonText: 'Créer le compte',
        cancelButtonText: 'Annuler',
        preConfirm: () => ({
            name: document.getElementById('acc-name').value.trim(),
            last_name: document.getElementById('acc-last').value.trim(),
            phone: document.getElementById('acc-phone').value.trim() || null,
            email: document.getElementById('acc-email').value.trim() || null,
            group_id: document.getElementById('acc-group').value || null,
            matricule: document.getElementById('acc-matricule').value.trim() || null,
        }),
    });
    if (!isConfirmed) return;

    try {
        const data = await request('POST', `${urls.base}/${r.id}/account`, form);
        await applyDetail(data);
        showCredentials(data);
    } catch (error) {
        fail(error);
    }
}

function showCredentials(data) {
    Swal.fire({
        title: 'Identifiants à transmettre',
        icon: 'success',
        html: `
            <div class="text-start small">
                <p class="text-muted">Le mot de passe n'est affiché qu'une seule fois. L'apprenant le changera à sa première connexion.</p>
                <div><strong>Identifiant :</strong> <code>${esc(data.login)}</code></div>
                <div class="mb-2"><strong>Mot de passe provisoire :</strong> <code>${esc(data.temporary_password)}</code></div>
                <textarea class="form-control form-control-sm" rows="6" readonly>${esc(data.whatsapp_text)}</textarea>
            </div>`,
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-clipboard"></i> Copier',
        denyButtonText: '<i class="bi bi-whatsapp"></i> Ouvrir WhatsApp',
        cancelButtonText: 'Fermer',
        denyButtonColor: '#25d366',
        allowOutsideClick: false,
        preConfirm: async () => {
            try {
                await navigator.clipboard.writeText(data.whatsapp_text);
                notify('Message copié.');
            } catch {
                notify('Copie impossible : sélectionnez le texte.', 'warning');
            }
            return false; // garde la modale ouverte
        },
        preDeny: () => {
            window.open(data.whatsapp_url, '_blank', 'noopener');
            return false;
        },
    });
}

async function saveNotes() {
    try {
        const data = await request('PUT', `${urls.base}/${current.id}/notes`, { notes: document.getElementById('request-notes').value });
        current.notes = document.getElementById('request-notes').value;
        notify(data.message);
    } catch (error) {
        fail(error);
    }
}

async function submitPayment(event) {
    event.preventDefault();
    try {
        const data = await request('POST', `${urls.base}/${current.id}/payments`, {
            amount: Number(document.getElementById('pay-amount').value),
            operator: document.getElementById('pay-operator').value,
            reference: document.getElementById('pay-reference').value.trim(),
            paid_at: document.getElementById('pay-date').value,
            payer_phone: document.getElementById('pay-phone').value.trim() || null,
            note: document.getElementById('pay-note').value.trim() || null,
        });
        await applyDetail(data);
        notify(data.message);
    } catch (error) {
        fail(error);
    }
}

async function deletePayment(paymentId) {
    const { isConfirmed } = await Swal.fire({
        title: 'Supprimer ce paiement ?',
        text: 'Le statut de la demande ne sera pas modifié.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Supprimer',
        cancelButtonText: 'Annuler',
    });
    if (!isConfirmed) return;
    try {
        const data = await request('DELETE', `${urls.base}/${current.id}/payments/${paymentId}`);
        await applyDetail(data);
        notify(data.message);
    } catch (error) {
        fail(error);
    }
}

// ----------------------------------------------------------- Événements

document.getElementById('status-tabs').addEventListener('click', (event) => {
    const button = event.target.closest('[data-status]');
    if (!button) return;
    document.querySelectorAll('#status-tabs .nav-link').forEach((b) => b.classList.toggle('active', b === button));
    filters.status = button.dataset.status;
    load();
});

let searchTimer;
document.getElementById('request-search').addEventListener('input', (event) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        filters.q = event.target.value.trim();
        load();
    }, 250);
});

document.getElementById('requests-body').addEventListener('click', (event) => {
    const row = event.target.closest('tr[data-id]');
    if (row) openDetails(row.dataset.id);
});

document.getElementById('request-details-body').addEventListener('click', (event) => {
    const statusBtn = event.target.closest('[data-set-status]');
    if (statusBtn) return setStatus(statusBtn.dataset.setStatus);
    if (event.target.closest('#btn-save-notes')) return saveNotes();
    if (event.target.closest('#btn-create-account')) return createAccount();
    const del = event.target.closest('[data-delete-payment]');
    if (del) return deletePayment(del.dataset.deletePayment);
});

document.getElementById('request-details-body').addEventListener('submit', (event) => {
    if (event.target.id === 'payment-form') submitPayment(event);
});

load();
