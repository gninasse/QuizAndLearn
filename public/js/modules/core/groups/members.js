/**
 * Page « Membres du groupe » : liste détaillée et recherchable des
 * apprenants / formateurs, ajout, retrait, suspension temporaire.
 * Toutes les URLs sont fournies par la vue (data-*), sans Ziggy.
 */

const root = document.getElementById('group-members-app');
const urls = {
    data: root.dataset.urlData,
    candidates: root.dataset.urlCandidates,
    learners: root.dataset.urlLearners,
    trainers: root.dataset.urlTrainers,
    impersonate: root.dataset.urlImpersonate,
};
const canManageTrainers = root.dataset.canManageTrainers === '1';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

let state = { group: null, learners: [], trainers: [], content: {}, max_trainers: 3 };

// ------------------------------------------------------------------ Utils

const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const normalize = (value) =>
    String(value ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

const formatDate = (iso, withTime = false) => {
    if (!iso) return '—';
    const date = new Date(iso.length === 10 ? `${iso}T00:00:00` : iso);
    return withTime
        ? date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
        : date.toLocaleDateString('fr-FR');
};

const daysSince = (isoDate) => {
    if (!isoDate) return Infinity;
    return Math.floor((Date.now() - new Date(`${isoDate}T00:00:00`).getTime()) / 86400000);
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

async function impersonate(learner) {
    // Ouverture synchrone (activation utilisateur conservée, Safari) ; l'URL est
    // renseignée après la requête.
    const tab = window.open('', '_blank');
    try {
        const result = await request('POST', urls.impersonate.replace('__ID__', learner.learner_id));
        if (tab) {
            tab.opener = null;
            tab.location.href = result.url;
        } else {
            Swal.fire({
                icon: 'info',
                title: 'Consultation prête',
                html: `<a href="${result.url}" target="_blank" rel="noopener">Ouvrir l'espace de l'apprenant</a>`,
                showConfirmButton: false,
            });
        }
        void load();
    } catch (error) {
        tab?.close();
        fail(error);
    }
}

const impersonateButton = () =>
    state.can_impersonate
        ? '<button type="button" class="btn btn-light" data-action="impersonate" title="Voir en tant que"><i class="bi bi-person-video3"></i></button>'
        : '';

// ------------------------------------------------------------- Chargement

async function load() {
    try {
        state = await request('GET', urls.data);
        renderHeader();
        renderLearners();
        renderTrainers();
    } catch (error) {
        document.getElementById('learners-body').innerHTML =
            `<tr><td colspan="9" class="text-center text-danger py-5">${esc(error.message)}</td></tr>`;
    }
}

const STATUS_BADGES = {
    active: '<span class="badge text-bg-success">En cours</span>',
    upcoming: '<span class="badge text-bg-info">À venir</span>',
    suspended: '<span class="badge text-bg-warning">Suspendu</span>',
    closed: '<span class="badge text-bg-secondary">Clôturé</span>',
};

function renderHeader() {
    const { group, learners, trainers, content } = state;
    document.getElementById('gm-status').innerHTML = STATUS_BADGES[group.status] ?? '';
    document.getElementById('gm-dates').textContent =
        group.start_date || group.end_date
            ? `Du ${formatDate(group.start_date)} au ${formatDate(group.end_date)}`
            : 'Sans dates définies';
    document.getElementById('kpi-learners').textContent = learners.length;
    document.getElementById('kpi-trainers').textContent = trainers.length;
    document.getElementById('kpi-suspended').textContent = learners.filter((l) => l.suspended).length;
    document.getElementById('kpi-content').textContent =
        (content.quizzes ?? 0) + (content.exams ?? 0) + (content.decks ?? 0) + (content.articles ?? 0);
    document.getElementById('kpi-content').parentElement.title =
        `${content.quizzes} quiz · ${content.exams} examens · ${content.decks} decks · ${content.articles} articles`;
    document.getElementById('tab-learners-count').textContent = learners.length;
    document.getElementById('tab-trainers-count').textContent = trainers.length;
}

// -------------------------------------------------------------- Apprenants

function learnerStatus(learner) {
    if (!learner.is_active) return '<span class="badge text-bg-danger"><i class="bi bi-slash-circle me-1"></i>Compte désactivé</span>';
    if (learner.suspended) {
        const until = learner.suspended_until ? `jusqu'au ${formatDate(learner.suspended_until, true)}` : 'sans date de fin';
        return `<span class="badge text-bg-warning" title="${esc(until)}"><i class="bi bi-pause-circle me-1"></i>Suspendu</span>`;
    }
    return '<span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Actif</span>';
}

function filteredLearners() {
    const query = normalize(document.getElementById('learner-search').value.trim());
    const filter = document.getElementById('learner-filter').value;

    return state.learners.filter((l) => {
        if (filter === 'active' && (!l.is_active || l.suspended)) return false;
        if (filter === 'suspended' && !l.suspended) return false;
        if (filter === 'disabled' && l.is_active) return false;
        if (filter === 'dormant' && daysSince(l.last_activity) < 7) return false;
        if (!query) return true;
        return [l.full_name, l.email, l.user_name, l.matricule, l.phone].some((v) => normalize(v).includes(query));
    });
}

function renderLearners() {
    const body = document.getElementById('learners-body');
    const rows = filteredLearners();

    if (!state.learners.length) {
        body.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-5">
            <i class="bi bi-people fs-2 d-block mb-2"></i>Aucun apprenant dans ${T('this_group')} pour l'instant.</td></tr>`;
    } else if (!rows.length) {
        body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">Aucun apprenant ne correspond à la recherche.</td></tr>';
    } else {
        body.innerHTML = rows.map((l) => `
            <tr class="${l.suspended ? 'gm-row-suspended' : ''}" data-learner="${l.learner_id}">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <img src="${esc(l.avatar_url)}" alt="" class="gm-avatar" loading="lazy">
                        <div class="lh-sm">
                            <a href="#" class="fw-semibold text-decoration-none" data-action="details">${esc(l.full_name)}</a>
                            <div class="small text-muted">${esc(l.email)}</div>
                        </div>
                    </div>
                </td>
                <td><code>${esc(l.matricule || '—')}</code></td>
                <td class="text-center"><span class="badge rounded-pill text-bg-light border">Niv. ${l.level}</span><div class="small text-muted">${l.xp} XP</div></td>
                <td class="text-center">${l.streak > 0 ? `🔥 ${l.streak}` : '<span class="text-muted">—</span>'}</td>
                <td class="text-center">${l.quizzes_completed}${l.quiz_avg_score !== null ? `<div class="small text-muted">moy. ${l.quiz_avg_score} %</div>` : ''}</td>
                <td class="text-center">${l.exams_taken}${l.exam_avg_note !== null ? `<div class="small text-muted">moy. ${l.exam_avg_note}/20</div>` : ''}</td>
                <td>${l.last_activity ? `${formatDate(l.last_activity)}<div class="small text-muted">${relativeDays(l.last_activity)}</div>` : '<span class="text-muted">Jamais</span>'}</td>
                <td>${learnerStatus(l)}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-light" data-action="details" title="Voir la fiche"><i class="bi bi-eye"></i></button>
                        ${impersonateButton()}
                        ${l.suspended
                            ? '<button type="button" class="btn btn-light text-success" data-action="unsuspend" title="Lever la suspension"><i class="bi bi-play-circle"></i></button>'
                            : '<button type="button" class="btn btn-light text-warning" data-action="suspend" title="Suspendre temporairement"><i class="bi bi-pause-circle"></i></button>'}
                        <button type="button" class="btn btn-light text-danger" data-action="remove" title="Retirer ${T('of_group')}"><i class="bi bi-person-dash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    }

    document.getElementById('learners-footer').textContent =
        rows.length === state.learners.length
            ? `${state.learners.length} apprenant(s)`
            : `${rows.length} sur ${state.learners.length} apprenant(s)`;
}

function relativeDays(isoDate) {
    const days = daysSince(isoDate);
    if (days <= 0) return "aujourd'hui";
    if (days === 1) return 'hier';
    return `il y a ${days} j`;
}

function showDetails(learner) {
    const otherGroups = learner.other_groups.length
        ? learner.other_groups.map((g) => `<span class="badge text-bg-light border me-1">${esc(g)}</span>`).join('')
        : '<span class="text-muted">Aucun</span>';

    const suspension = learner.suspended
        ? `<div class="alert alert-warning small">
               <strong><i class="bi bi-pause-circle me-1"></i>Suspendu</strong>
               ${learner.suspended_until ? `jusqu'au ${formatDate(learner.suspended_until, true)}` : 'sans date de fin'}
               ${learner.suspension_reason ? `<br>Motif : ${esc(learner.suspension_reason)}` : ''}
           </div>`
        : '';

    document.getElementById('learner-details-title').textContent = learner.full_name;
    document.getElementById('learner-details-body').innerHTML = `
        <div class="text-center mb-3">
            <img src="${esc(learner.avatar_url)}" alt="" class="rounded-circle mb-2" style="width:84px;height:84px;object-fit:cover">
            <div>${learnerStatus(learner)}</div>
        </div>
        ${suspension}
        <dl class="gm-detail">
            <dt>E-mail</dt><dd>${esc(learner.email)}</dd>
            <dt>Identifiant</dt><dd>${esc(learner.user_name || '—')}</dd>
            <dt>Matricule</dt><dd>${esc(learner.matricule || '—')}</dd>
            <dt>Téléphone</dt><dd>${esc(learner.phone || '—')}</dd>
            <dt>Inscrit le</dt><dd>${formatDate(learner.created_at)}</dd>
            <dt>Progression</dt><dd>Niveau ${learner.level} · ${learner.xp} XP · série ${learner.streak} j</dd>
            <dt>Quiz terminés</dt><dd>${learner.quizzes_completed}${learner.quiz_avg_score !== null ? ` (moyenne ${learner.quiz_avg_score} %)` : ''}</dd>
            <dt>Examens passés</dt><dd>${learner.exams_taken}${learner.exam_avg_note !== null ? ` (moyenne ${learner.exam_avg_note}/20)` : ''}</dd>
            <dt>Dernière activité</dt><dd>${learner.last_activity ? `${formatDate(learner.last_activity)} (${relativeDays(learner.last_activity)})` : 'Jamais'}</dd>
            <dt>Autres ${T('groups')}</dt><dd>${otherGroups}</dd>
            ${state.can_impersonate ? `<dt>Dernière consultation</dt><dd>${learner.last_consultation
                ? `${formatDate(learner.last_consultation.at, true)} par ${esc(learner.last_consultation.by || '—')}`
                : 'Jamais'}</dd>` : ''}
        </dl>
        ${state.can_impersonate ? `<button type="button" class="btn btn-dark w-100" id="btn-details-impersonate"><i class="bi bi-person-video3 me-1"></i>Voir en tant que</button>` : ''}`;
    document.getElementById('btn-details-impersonate')?.addEventListener('click', () => impersonate(learner));
    bootstrap.Offcanvas.getOrCreateInstance('#learner-details').show();
}

// -------------------------------------------------------------- Formateurs

function renderTrainers() {
    const list = document.getElementById('trainers-list');
    const addButton = document.getElementById('btn-add-trainers');
    if (addButton) addButton.disabled = state.trainers.length >= state.max_trainers;

    if (!state.trainers.length) {
        list.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-person-badge fs-2 d-block mb-2"></i>Aucun formateur assigné.</div>';
        return;
    }

    list.innerHTML = state.trainers.map((t) => `
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex gap-3 align-items-center">
                    <img src="${esc(t.avatar_url)}" alt="" class="gm-avatar" style="width:52px;height:52px">
                    <div class="flex-grow-1 lh-sm overflow-hidden">
                        <div class="fw-semibold text-truncate">${esc(t.full_name)}</div>
                        <div class="small text-muted text-truncate">${esc(t.specialty || 'Formateur')}</div>
                        <div class="small text-truncate"><i class="bi bi-envelope me-1"></i>${esc(t.email)}</div>
                        ${t.phone ? `<div class="small"><i class="bi bi-telephone me-1"></i>${esc(t.phone)}</div>` : ''}
                    </div>
                    ${canManageTrainers
                        ? `<button type="button" class="btn btn-sm btn-light text-danger" data-remove-trainer="${t.trainer_id}" title="Retirer ${T('of_group')}"><i class="bi bi-person-dash"></i></button>`
                        : ''}
                </div>
            </div>
        </div>`).join('');
}

// ------------------------------------------------------------ Ajout (modale)

const addModal = () => bootstrap.Modal.getOrCreateInstance('#add-members-modal');
let candidateType = 'learner';
let selected = new Map();
let searchTimer = null;

function openAddModal(type) {
    candidateType = type;
    selected = new Map();
    document.getElementById('add-members-title').textContent =
        type === 'trainer' ? 'Ajouter un formateur' : 'Ajouter des apprenants';
    document.getElementById('candidate-search').value = '';
    updateSelectedCount();
    addModal().show();
    void searchCandidates('');
}

async function searchCandidates(query) {
    const list = document.getElementById('candidate-list');
    list.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span></div>';
    try {
        const url = `${urls.candidates}?type=${candidateType}&q=${encodeURIComponent(query)}`;
        const { rows } = await request('GET', url);
        if (!rows.length) {
            list.innerHTML = '<div class="text-center text-muted py-4">Aucun résultat.</div>';
            return;
        }
        list.innerHTML = rows.map((r) => `
            <label class="list-group-item d-flex align-items-center gap-2">
                <input class="form-check-input m-0" type="checkbox" value="${r.id}" ${selected.has(r.id) ? 'checked' : ''}>
                <img src="${esc(r.avatar_url)}" alt="" class="gm-avatar" style="width:32px;height:32px">
                <span class="lh-sm">
                    <span class="fw-semibold">${esc(r.full_name)}</span>
                    <span class="d-block small text-muted">${esc(r.email)}${r.matricule ? ` · ${esc(r.matricule)}` : ''}</span>
                </span>
            </label>`).join('');
    } catch (error) {
        list.innerHTML = `<div class="text-center text-danger py-4">${esc(error.message)}</div>`;
    }
}

function updateSelectedCount() {
    const count = selected.size;
    document.getElementById('candidate-selected').textContent = `${count} sélectionné${count > 1 ? 's' : ''}`;
    document.getElementById('btn-confirm-add').disabled = count === 0;
}

// ------------------------------------------------------------- Suspension

let suspendTarget = null;

function toLocalInput(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function applyPreset(days) {
    document.querySelectorAll('#suspend-presets button').forEach((b) => b.classList.toggle('active', Number(b.dataset.days) === days));
    if (days === 0) {
        document.getElementById('suspend-until').value = '';
        return;
    }
    const until = new Date();
    until.setDate(until.getDate() + days);
    document.getElementById('suspend-until').value = toLocalInput(until);
}

function openSuspend(learner) {
    suspendTarget = learner;
    document.getElementById('suspend-name').textContent = learner.full_name;
    document.getElementById('suspend-reason').value = '';
    document.getElementById('suspend-until').min = toLocalInput(new Date());
    applyPreset(7);
    bootstrap.Modal.getOrCreateInstance('#suspend-modal').show();
}

// ----------------------------------------------------------------- Events

document.getElementById('learner-search').addEventListener('input', renderLearners);
document.getElementById('learner-filter').addEventListener('change', renderLearners);
document.getElementById('btn-add-learners').addEventListener('click', () => openAddModal('learner'));
document.getElementById('btn-add-trainers')?.addEventListener('click', () => openAddModal('trainer'));

document.getElementById('candidate-search').addEventListener('input', (event) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchCandidates(event.target.value.trim()), 250);
});

document.getElementById('candidate-list').addEventListener('change', (event) => {
    const box = event.target.closest('input[type="checkbox"]');
    if (!box) return;
    const id = Number(box.value);
    if (box.checked) selected.set(id, true);
    else selected.delete(id);
    updateSelectedCount();
});

document.getElementById('btn-confirm-add').addEventListener('click', async () => {
    const ids = [...selected.keys()];
    try {
        const result = candidateType === 'trainer'
            ? await request('POST', urls.trainers, { trainer_ids: ids })
            : await request('POST', urls.learners, { learner_ids: ids });
        addModal().hide();
        notify(result.message);
        await load();
    } catch (error) {
        fail(error);
    }
});

document.getElementById('learners-body').addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-action]');
    if (!trigger) return;
    event.preventDefault();

    const id = Number(trigger.closest('tr').dataset.learner);
    const learner = state.learners.find((l) => l.learner_id === id);
    if (!learner) return;

    const action = trigger.dataset.action;
    if (action === 'details') {
        showDetails(learner);
    } else if (action === 'impersonate') {
        await impersonate(learner);
    } else if (action === 'suspend') {
        openSuspend(learner);
    } else if (action === 'unsuspend') {
        const { isConfirmed } = await Swal.fire({
            title: 'Lever la suspension ?',
            text: `${learner.full_name} retrouvera immédiatement l'accès au volet apprenant.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Lever la suspension',
            cancelButtonText: 'Annuler',
        });
        if (!isConfirmed) return;
        try {
            notify((await request('DELETE', `${urls.learners}/${id}/suspend`)).message);
            await load();
        } catch (error) {
            fail(error);
        }
    } else if (action === 'remove') {
        const { isConfirmed } = await Swal.fire({
            title: `Retirer ${T('of_group')} ?`,
            html: `<strong>${esc(learner.full_name)}</strong> n'aura plus accès aux contenus de ${T('this_group')}.<br>Son historique est conservé.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Retirer',
            cancelButtonText: 'Annuler',
        });
        if (!isConfirmed) return;
        try {
            notify((await request('DELETE', `${urls.learners}/${id}`)).message);
            await load();
        } catch (error) {
            fail(error);
        }
    }
});

document.getElementById('trainers-list').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-remove-trainer]');
    if (!button) return;
    const { isConfirmed } = await Swal.fire({
        title: `Retirer ce formateur ${T('of_group')} ?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Retirer',
        cancelButtonText: 'Annuler',
    });
    if (!isConfirmed) return;
    try {
        notify((await request('DELETE', `${urls.trainers}/${button.dataset.removeTrainer}`)).message);
        await load();
    } catch (error) {
        fail(error);
    }
});

document.getElementById('suspend-presets').addEventListener('click', (event) => {
    const button = event.target.closest('[data-days]');
    if (button) applyPreset(Number(button.dataset.days));
});

document.getElementById('suspend-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!suspendTarget) return;
    const untilValue = document.getElementById('suspend-until').value;
    try {
        const result = await request('POST', `${urls.learners}/${suspendTarget.learner_id}/suspend`, {
            until: untilValue ? new Date(untilValue).toISOString() : null,
            reason: document.getElementById('suspend-reason').value.trim() || null,
        });
        bootstrap.Modal.getOrCreateInstance('#suspend-modal').hide();
        notify(result.message);
        await load();
    } catch (error) {
        fail(error);
    }
});

void load();
