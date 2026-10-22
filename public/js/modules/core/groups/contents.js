/**
 * Page « Contenus du groupe » : arborescence de dossiers (à gauche) et éléments
 * du dossier ouvert (à droite) — création / paramètres / suppression de dossiers,
 * réordonnancement (SortableJS), déplacement par glisser-déposer sur l'arbre,
 * téléversement, liens, ajout d'un contenu existant, « Copier vers… », retrait,
 * relance de la conversion des documents.
 * Toutes les URLs sont fournies par la vue (data-*), sans Ziggy.
 */

const root = document.getElementById('group-contents-app');
const d = root.dataset;
const groupId = Number(d.groupId);
const urls = {
    data: d.urlData,
    dataAny: d.urlDataAny,
    candidates: d.urlCandidates,
    folders: d.urlFolders,
    folder: d.urlFolder,
    folderMove: d.urlFolderMove,
    place: d.urlPlace,
    reorder: d.urlReorder,
    attach: d.urlAttach,
    detach: d.urlDetach,
    copy: d.urlCopy,
    resources: d.urlResources,
    links: d.urlLinks,
    retry: d.urlRetry,
    edit: { article: d.urlEditArticle, quiz: d.urlEditQuiz, exam: d.urlEditExam, deck: d.urlEditDeck },
};
const copyTargets = JSON.parse(d.copyTargets || '[]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const T = (key) => (typeof window.T === 'function' ? window.T(key) : key);

/** Position « en fin de conteneur » (bornée côté serveur). */
const END_POSITION = 999999;

let state = { folders: [], items: [], sharing_mode: 'shared' };
let current = null; // dossier ouvert (null = racine)
const expanded = new Set();
let dragged = null; // élément en cours de glisser {type, id}
let droppedOnTarget = false;

// ------------------------------------------------------------------ Utils

const esc = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const fill = (template, values) =>
    Object.entries(values).reduce((url, [key, value]) => url.replace(`__${key}__`, encodeURIComponent(value)), template);

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '');

/** ISO 8601 → valeur d'un <input type="datetime-local"> (heure locale). */
const toLocalInput = (iso) => {
    if (!iso) return '';
    const date = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

async function request(method, url, body) {
    const isForm = body instanceof FormData;
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
    if (body && !isForm) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, { method, headers, body: body ? (isForm ? body : JSON.stringify(body)) : undefined });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.success === false) {
        const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        if (response.status === 413) throw new Error('Fichier trop volumineux.');
        throw new Error(firstError || data.message || `Erreur ${response.status}`);
    }
    return data;
}

const notify = (message, icon = 'success') =>
    Swal.fire({ toast: true, position: 'top-end', icon, title: message, showConfirmButton: false, timer: 2600 });

const fail = (error) => Swal.fire('Erreur', error.message || 'Une erreur est survenue.', 'error');

/** Exécute une mutation, recharge l'arbre, notifie. */
async function mutate(promise) {
    try {
        const result = await promise;
        if (result?.message) notify(result.message);
        await load();
        return result;
    } catch (error) {
        fail(error);
        await load();
        return null;
    }
}

// ---------------------------------------------------------------- Modèle

const TYPE_META = {
    folder: { icon: 'bi-folder-fill text-warning', label: 'Dossier' },
    quiz: { icon: 'bi-patch-question text-primary', label: 'Quiz' },
    exam: { icon: 'bi-clipboard-check text-danger', label: 'Examen' },
    article: { icon: 'bi-file-earmark-richtext text-success', label: 'Article' },
    deck: { icon: 'bi-stack text-info', label: 'Flashcards' },
    resource: { icon: 'bi-paperclip text-secondary', label: 'Ressource' },
};

const RESOURCE_KINDS = {
    pdf: { icon: 'bi-file-earmark-pdf text-danger', label: 'PDF' },
    document: { icon: 'bi-file-earmark-word text-primary', label: 'Document' },
    image: { icon: 'bi-file-earmark-image text-success', label: 'Image' },
    video: { icon: 'bi-camera-video text-danger', label: 'Vidéo' },
    audio: { icon: 'bi-music-note-beamed text-info', label: 'Audio' },
    link: { icon: 'bi-link-45deg text-primary', label: 'Lien' },
};

const folderById = (id) => state.folders.find((f) => f.id === id) ?? null;

const byOrder = (a, b) =>
    a.position - b.position || (a.type === 'folder' ? 0 : 1) - (b.type === 'folder' ? 0 : 1) || a.id - b.id;

/** Enfants directs d'un conteneur (dossiers + contenus), dans l'ordre affiché. */
function children(folderId) {
    const folders = state.folders.filter((f) => f.parent_id === folderId).map((f) => ({ ...f, type: 'folder' }));
    const items = state.items.filter((i) => i.folder_id === folderId);
    return [...folders, ...items].sort(byOrder);
}

/** Identifiants du dossier et de tous ses descendants. */
function subtree(folderId) {
    const ids = new Set([folderId]);
    let grew = true;
    while (grew) {
        grew = false;
        for (const f of state.folders) {
            if (f.parent_id !== null && ids.has(f.parent_id) && !ids.has(f.id)) {
                ids.add(f.id);
                grew = true;
            }
        }
    }
    return ids;
}

const ancestors = (folderId) => {
    const chain = [];
    let folder = folderById(folderId);
    while (folder) {
        chain.unshift(folder);
        folder = folderById(folder.parent_id);
    }
    return chain;
};

/** Dossiers à plat en ordre d'arborescence (libellé indenté), hors `exclude`. */
function flatFolders(exclude = new Set()) {
    const out = [];
    const walk = (parentId, depth) => {
        state.folders
            .filter((f) => f.parent_id === parentId && !exclude.has(f.id))
            .sort((a, b) => a.position - b.position || a.id - b.id)
            .forEach((f) => {
                out.push({ id: f.id, label: `${'— '.repeat(depth)}${f.name}` });
                walk(f.id, depth + 1);
            });
    };
    walk(null, 0);
    return out;
}

function visibilityBadges(folder) {
    const badges = [];
    if (!folder.is_visible) badges.push('<span class="badge text-bg-secondary" title="Masqué aux apprenants"><i class="bi bi-eye-slash"></i></span>');
    if (folder.available_from || folder.available_until) {
        const period = [
            folder.available_from ? `à partir du ${formatDate(folder.available_from)}` : '',
            folder.available_until ? `jusqu'au ${formatDate(folder.available_until)}` : '',
        ].filter(Boolean).join(' ');
        badges.push(`<span class="badge text-bg-light border" title="Disponible ${esc(period)}"><i class="bi bi-clock"></i></span>`);
    }
    return badges.join(' ');
}

// ------------------------------------------------------------- Chargement

async function load() {
    try {
        state = await request('GET', urls.data);
        if (current !== null && !folderById(current)) current = null;
        render();
    } catch (error) {
        document.getElementById('gc-tree').innerHTML = `<p class="text-danger text-center py-4 mb-0">${esc(error.message)}</p>`;
    }
}

function render() {
    ancestors(current).forEach((f) => expanded.add(f.id));
    renderTree();
    renderBreadcrumb();
    renderList();
    const hash = current === null ? '' : `#dossier-${current}`;
    if (window.location.hash !== hash) history.replaceState(null, '', `${window.location.pathname}${window.location.search}${hash}`);
}

// ------------------------------------------------------------------ Arbre

function renderTree() {
    const count = (folderId) => state.items.filter((i) => i.folder_id === folderId).length;
    const branch = (parentId) => {
        const folders = state.folders.filter((f) => f.parent_id === parentId).sort((a, b) => a.position - b.position || a.id - b.id);
        if (!folders.length) return '';
        return `<ul>${folders.map((f) => {
            const hasChildren = state.folders.some((c) => c.parent_id === f.id);
            const open = expanded.has(f.id);
            const toggle = hasChildren
                ? `<span class="gc-toggle" data-toggle="${f.id}" role="button" aria-label="${open ? 'Replier' : 'Déplier'}"><i class="bi bi-chevron-${open ? 'down' : 'right'}"></i></span>`
                : '<span class="gc-toggle"></span>';
            return `<li>
                <div class="gc-node${current === f.id ? ' active' : ''}" data-folder="${f.id}" data-drop-folder="${f.id}" tabindex="0">
                    ${toggle}<i class="bi bi-folder${open && hasChildren ? '2-open' : ''} text-warning"></i>
                    <span class="gc-name">${esc(f.name)}</span>
                    ${visibilityBadges(f)}
                    <span class="badge rounded-pill text-bg-light border">${count(f.id)}</span>
                </div>
                ${open ? branch(f.id) : ''}
            </li>`;
        }).join('')}</ul>`;
    };

    document.getElementById('gc-tree').innerHTML = `
        <ul><li>
            <div class="gc-node${current === null ? ' active' : ''}" data-folder="root" data-drop-folder="root" tabindex="0">
                <span class="gc-toggle"></span><i class="bi bi-house-door"></i>
                <span class="gc-name">Racine</span>
                <span class="badge rounded-pill text-bg-light border">${count(null)}</span>
            </div>
            ${branch(null)}
        </li></ul>
        ${state.folders.length ? '' : '<p class="small text-muted px-2 mt-2 mb-0">Aucun dossier. Créez-en un avec « Nouveau dossier ».</p>'}`;
}

function renderBreadcrumb() {
    const crumbs = [{ id: null, name: 'Racine' }, ...ancestors(current)];
    document.getElementById('gc-breadcrumb').innerHTML = crumbs.map((f, index) => {
        const key = f.id === null ? 'root' : f.id;
        const icon = f.id === null ? '<i class="bi bi-house-door me-1"></i>' : '';
        return index === crumbs.length - 1
            ? `<li class="breadcrumb-item active fw-semibold" aria-current="page">${icon}${esc(f.name)}</li>`
            : `<li class="breadcrumb-item"><a href="#" data-folder="${key}" data-drop-folder="${key}">${icon}${esc(f.name)}</a></li>`;
    }).join('');
    document.getElementById('gc-folder-actions').classList.toggle('d-none', current === null);
}

// ---------------------------------------------------------- Liste courante

function statusBadge(item) {
    if (item.type !== 'resource' || item.kind !== 'document') return '';
    if (item.conversion_status === 'pending' || item.conversion_status === 'processing') {
        return '<span class="badge text-bg-warning"><span class="spinner-border spinner-border-sm me-1" style="width:.7rem;height:.7rem"></span>En préparation</span>';
    }
    if (item.conversion_status === 'failed') {
        return `<span class="badge text-bg-danger" title="${esc(item.conversion_error || 'La conversion en PDF a échoué.')}"><i class="bi bi-exclamation-triangle me-1"></i>Échec</span>
            <button type="button" class="btn btn-sm btn-outline-danger py-0" data-action="retry"><i class="bi bi-arrow-clockwise me-1"></i>Réessayer</button>`;
    }
    return '';
}

function rowHtml(entry) {
    const isFolder = entry.type === 'folder';
    const kind = entry.type === 'resource' ? RESOURCE_KINDS[entry.kind] : null;
    const meta = kind ?? TYPE_META[entry.type] ?? TYPE_META.resource;
    const typeLabel = isFolder ? 'Dossier' : (kind ? `Ressource · ${kind.label}` : TYPE_META[entry.type]?.label ?? entry.type);
    const editUrl = urls.edit[entry.type] ? fill(urls.edit[entry.type], { ID: entry.id }) : null;

    const title = isFolder
        ? `<span class="gc-label" data-action="open">${esc(entry.name)}</span>`
        : editUrl
            ? `<a href="${esc(editUrl)}" class="gc-label text-body">${esc(entry.title)}</a>`
            : `<span class="gc-label">${esc(entry.title)}</span>`;

    const menu = isFolder
        ? `<li><button class="dropdown-item" data-action="open"><i class="bi bi-folder2-open me-2"></i>Ouvrir</button></li>
           <li><button class="dropdown-item" data-action="edit-folder"><i class="bi bi-pencil me-2"></i>Paramètres</button></li>
           <li><button class="dropdown-item" data-action="move"><i class="bi bi-arrow-right-square me-2"></i>Déplacer vers…</button></li>
           <li><hr class="dropdown-divider"></li>
           <li><button class="dropdown-item text-danger" data-action="delete-folder"><i class="bi bi-trash me-2"></i>Supprimer</button></li>`
        : `${editUrl ? `<li><a class="dropdown-item" href="${esc(editUrl)}"><i class="bi bi-pencil-square me-2"></i>Ouvrir dans l'éditeur</a></li>` : ''}
           <li><button class="dropdown-item" data-action="move"><i class="bi bi-arrow-right-square me-2"></i>Déplacer vers…</button></li>
           <li><button class="dropdown-item" data-action="copy"><i class="bi bi-copy me-2"></i>Copier vers…</button></li>
           <li><hr class="dropdown-divider"></li>
           <li><button class="dropdown-item text-danger" data-action="detach"><i class="bi bi-x-circle me-2"></i>Retirer ${esc(T('of_group'))}</button></li>`;

    return `<li class="list-group-item gc-row${isFolder ? ' gc-folder-row' : ''}" data-type="${entry.type}" data-id="${entry.id}">
        <span class="gc-handle" title="Glisser pour réordonner ou déposer sur un dossier"><i class="bi bi-grip-vertical"></i></span>
        <i class="bi ${meta.icon} gc-icon"></i>
        <div class="gc-title">
            ${title}
            <div class="small text-muted d-flex flex-wrap align-items-center gap-1">
                <span>${esc(typeLabel)}</span>
                ${entry.is_public ? '<span class="badge text-bg-light border"><i class="bi bi-globe2 me-1"></i>Public</span>' : ''}
                ${isFolder ? visibilityBadges(entry) : ''}
                ${statusBadge(entry)}
            </div>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">${menu}</ul>
        </div>
    </li>`;
}

let sortable = null;

function renderList() {
    const list = document.getElementById('gc-list');
    const entries = children(current);
    list.innerHTML = entries.map(rowHtml).join('');
    document.getElementById('gc-empty').classList.toggle('d-none', entries.length > 0);

    sortable?.destroy();
    sortable = new Sortable(list, {
        handle: '.gc-handle',
        animation: 150,
        onStart: (evt) => {
            dragged = { type: evt.item.dataset.type, id: Number(evt.item.dataset.id) };
            droppedOnTarget = false;
        },
        onEnd: (evt) => {
            const moved = droppedOnTarget;
            dragged = null;
            droppedOnTarget = false;
            if (moved || evt.oldIndex === evt.newIndex) return;
            const items = [...list.querySelectorAll('.gc-row')].map((row) => ({ type: row.dataset.type, id: Number(row.dataset.id) }));
            mutate(request('POST', urls.reorder, { folder_id: current, items }));
        },
    });
}

// ---------------------------------------------------- Déplacements (drop)

const parseFolder = (key) => (key === 'root' || key === '' || key == null ? null : Number(key));

/** Place un dossier ou un contenu en fin du dossier `targetId` (null = racine). */
function moveTo(entry, targetId) {
    if (entry.type === 'folder') {
        const folder = folderById(entry.id);
        if (!folder) return;
        if (subtree(folder.id).has(targetId)) {
            notify('Un dossier ne peut pas être déplacé dans lui-même ou dans un de ses sous-dossiers.', 'error');
            return;
        }
        if (folder.parent_id === targetId) return;
        return mutate(request('POST', fill(urls.folderMove, { FOLDER: folder.id }), { parent_id: targetId, position: END_POSITION }));
    }
    const item = state.items.find((i) => i.type === entry.type && i.id === entry.id);
    if (!item || item.folder_id === targetId) return;
    return mutate(request('POST', urls.place, { type: entry.type, id: entry.id, folder_id: targetId, position: END_POSITION }));
}

function bindDropTargets() {
    const target = (event) => (dragged ? event.target.closest('[data-drop-folder]') : null);
    root.addEventListener('dragover', (event) => {
        const el = target(event);
        if (!el) return;
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
        el.classList.add('gc-drop-over');
    });
    root.addEventListener('dragleave', (event) => {
        const el = event.target.closest?.('[data-drop-folder]');
        if (el && !el.contains(event.relatedTarget)) el.classList.remove('gc-drop-over');
    });
    root.addEventListener('drop', (event) => {
        const el = target(event);
        if (!el) return;
        event.preventDefault();
        el.classList.remove('gc-drop-over');
        droppedOnTarget = true;
        moveTo(dragged, parseFolder(el.dataset.dropFolder));
    });
    document.addEventListener('dragend', () => {
        root.querySelectorAll('.gc-drop-over').forEach((el) => el.classList.remove('gc-drop-over'));
    });
}

// ------------------------------------------------------------- Dialogues

function folderSelectHtml(id, selected, exclude) {
    const options = [{ id: null, label: 'Racine (aucun dossier)' }, ...flatFolders(exclude)]
        .map((f) => `<option value="${f.id ?? ''}"${f.id === selected ? ' selected' : ''}>${esc(f.label)}</option>`)
        .join('');
    return `<select id="${id}" class="form-select">${options}</select>`;
}

async function folderDialog(folder = null) {
    const html = `
        <div class="text-start">
            <label class="form-label small fw-semibold" for="f-name">Nom</label>
            <input id="f-name" class="form-control mb-3" maxlength="150" value="${esc(folder?.name ?? '')}" placeholder="Ex. Chapitre 1 — Introduction">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="f-visible" ${folder?.is_visible === false ? '' : 'checked'}>
                <label class="form-check-label" for="f-visible">Visible par les apprenants</label>
            </div>
            <div class="gc-dates">
                <div>
                    <label class="form-label small fw-semibold" for="f-from">Disponible à partir du</label>
                    <input type="datetime-local" id="f-from" class="form-control" value="${toLocalInput(folder?.available_from)}">
                </div>
                <div>
                    <label class="form-label small fw-semibold" for="f-until">Jusqu'au</label>
                    <input type="datetime-local" id="f-until" class="form-control" value="${toLocalInput(folder?.available_until)}">
                </div>
            </div>
            <p class="form-text mb-0">La visibilité et les dates s'appliquent au dossier et à tout son contenu, pour ${esc(T('this_group'))} uniquement. Laissez les dates vides pour une disponibilité permanente.</p>
            ${folder ? '' : `<p class="small text-muted mt-2 mb-0">Créé dans : <strong>${esc(current === null ? 'Racine' : folderById(current)?.name)}</strong></p>`}
        </div>`;

    const result = await Swal.fire({
        title: folder ? 'Paramètres du dossier' : 'Nouveau dossier',
        html,
        showCancelButton: true,
        confirmButtonText: folder ? 'Enregistrer' : 'Créer',
        cancelButtonText: 'Annuler',
        focusConfirm: false,
        didOpen: (popup) => popup.querySelector('#f-name').focus(),
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const payload = {
                name: popup.querySelector('#f-name').value.trim(),
                is_visible: popup.querySelector('#f-visible').checked,
                available_from: popup.querySelector('#f-from').value || null,
                available_until: popup.querySelector('#f-until').value || null,
            };
            if (!payload.name) {
                Swal.showValidationMessage('Le nom est obligatoire.');
                return false;
            }
            try {
                return folder
                    ? await request('PATCH', fill(urls.folder, { FOLDER: folder.id }), payload)
                    : await request('POST', urls.folders, { ...payload, parent_id: current });
            } catch (error) {
                Swal.showValidationMessage(error.message);
                return false;
            }
        },
    });
    if (result.isConfirmed && result.value) {
        notify(result.value.message);
        if (!folder && current !== null) expanded.add(current);
        await load();
    }
}

async function deleteFolderDialog(folder) {
    const ids = subtree(folder.id);
    const contents = state.items.filter((i) => ids.has(i.folder_id)).length;
    const subfolders = ids.size - 1;
    const parentName = folder.parent_id === null ? 'la racine' : `« ${folderById(folder.parent_id)?.name} »`;
    const summary = contents || subfolders
        ? `Ce dossier contient ${contents} contenu(s) et ${subfolders} sous-dossier(s).`
        : 'Ce dossier est vide.';

    const result = await Swal.fire({
        title: `Supprimer « ${esc(folder.name)} » ?`,
        html: `<p class="mb-2">${summary}</p><p class="small text-muted mb-0">Que faire de son contenu ? Les contenus retirés ne sont pas supprimés.</p>`,
        icon: 'warning',
        input: 'radio',
        inputOptions: {
            lift: `Le remonter dans ${esc(parentName)}`,
            detach: `Le retirer ${esc(T('of_group'))}`,
        },
        inputValue: 'lift',
        inputValidator: (value) => (value ? undefined : 'Choisissez une option.'),
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Supprimer le dossier',
        cancelButtonText: 'Annuler',
    });
    if (!result.isConfirmed) return;
    if (ids.has(current)) current = folder.parent_id;
    const url = `${fill(urls.folder, { FOLDER: folder.id })}?mode=${encodeURIComponent(result.value)}`;
    await mutate(request('DELETE', url));
}

async function moveDialog(entry) {
    const isFolder = entry.type === 'folder';
    const exclude = isFolder ? subtree(entry.id) : new Set();
    const selected = isFolder ? folderById(entry.id)?.parent_id ?? null : entry.folder_id;
    const result = await Swal.fire({
        title: 'Déplacer vers…',
        html: `<div class="text-start"><label class="form-label small text-muted" for="move-target">Dossier de destination</label>${folderSelectHtml('move-target', selected, exclude)}</div>`,
        showCancelButton: true,
        confirmButtonText: 'Déplacer',
        cancelButtonText: 'Annuler',
        focusConfirm: false,
        preConfirm: () => Swal.getPopup().querySelector('#move-target').value,
    });
    if (result.isConfirmed) await moveTo(entry, parseFolder(result.value));
}

async function detachDialog(item) {
    const result = await Swal.fire({
        title: `Retirer « ${esc(item.title)} » ?`,
        text: `Le contenu est retiré ${T('of_group')}, il n'est pas supprimé.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Retirer',
        cancelButtonText: 'Annuler',
    });
    if (result.isConfirmed) await mutate(request('DELETE', fill(urls.detach, { TYPE: item.type, ID: item.id })));
}

async function copyDialog(item) {
    if (!window.GroupFolderPicker?.copyDialog) {
        fail(new Error('Copie indisponible.'));
        return;
    }
    const url = urls.copy.replace('__TYPE__', encodeURIComponent(item.type)).replace('__ID__', encodeURIComponent(item.id));
    const copied = await window.GroupFolderPicker.copyDialog({ urlCopy: url, urlData: urls.dataAny, sourceId: groupId, targets: copyTargets });
    if (copied?.item?.group_id === groupId) await load();
}

async function uploadDialog() {
    const result = await Swal.fire({
        title: 'Téléverser une ressource',
        html: `
            <div class="text-start">
                <label class="form-label small fw-semibold" for="r-file">Fichier</label>
                <input type="file" id="r-file" class="form-control mb-1"
                       accept=".pdf,.doc,.docx,.odt,.ppt,.pptx,.odp,image/*,audio/*,video/*">
                <p class="form-text mt-0 mb-3">PDF, Word, PowerPoint, OpenDocument, image, audio ou vidéo. Les documents bureautiques sont convertis en PDF.</p>
                <label class="form-label small fw-semibold" for="r-title">Titre <span class="text-muted fw-normal">(facultatif)</span></label>
                <input id="r-title" class="form-control mb-3" maxlength="255" placeholder="Par défaut : nom du fichier">
                <label class="form-label small fw-semibold" for="r-description">Description <span class="text-muted fw-normal">(facultatif)</span></label>
                <textarea id="r-description" class="form-control mb-3" rows="2" maxlength="5000"></textarea>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="r-download">
                    <label class="form-check-label" for="r-download">Autoriser le téléchargement</label>
                </div>
                <p class="small text-muted mt-2 mb-0">Ajouté dans : <strong>${esc(current === null ? 'Racine' : folderById(current)?.name)}</strong></p>
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Téléverser',
        cancelButtonText: 'Annuler',
        focusConfirm: false,
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const file = popup.querySelector('#r-file').files[0];
            if (!file) {
                Swal.showValidationMessage('Choisissez un fichier.');
                return false;
            }
            const form = new FormData();
            form.append('file', file);
            const title = popup.querySelector('#r-title').value.trim();
            const description = popup.querySelector('#r-description').value.trim();
            if (title) form.append('title', title);
            if (description) form.append('description', description);
            form.append('allow_download', popup.querySelector('#r-download').checked ? '1' : '0');
            if (current !== null) form.append('folder_id', current);
            try {
                return await request('POST', urls.resources, form);
            } catch (error) {
                Swal.showValidationMessage(error.message);
                return false;
            }
        },
    });
    if (result.isConfirmed && result.value) {
        notify(result.value.message);
        await load();
    }
}

async function linkDialog() {
    const result = await Swal.fire({
        title: 'Ajouter un lien',
        html: `
            <div class="text-start">
                <label class="form-label small fw-semibold" for="l-url">Adresse</label>
                <input type="url" id="l-url" class="form-control mb-3" maxlength="2048" placeholder="https://…">
                <label class="form-label small fw-semibold" for="l-title">Titre</label>
                <input id="l-title" class="form-control" maxlength="255">
                <p class="small text-muted mt-2 mb-0">Ajouté dans : <strong>${esc(current === null ? 'Racine' : folderById(current)?.name)}</strong></p>
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Ajouter',
        cancelButtonText: 'Annuler',
        focusConfirm: false,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const payload = { url: popup.querySelector('#l-url').value.trim(), title: popup.querySelector('#l-title').value.trim(), folder_id: current };
            if (!payload.url || !payload.title) {
                Swal.showValidationMessage("L'adresse et le titre sont obligatoires.");
                return false;
            }
            try {
                return await request('POST', urls.links, payload);
            } catch (error) {
                Swal.showValidationMessage(error.message);
                return false;
            }
        },
    });
    if (result.isConfirmed && result.value) {
        notify(result.value.message);
        await load();
    }
}

// ------------------------------------------------- Contenu existant (modal)

const attachModalEl = document.getElementById('attach-modal');
const attachModal = new bootstrap.Modal(attachModalEl);
let attachTimer = null;
let attachedSomething = false;

async function loadCandidates() {
    const list = document.getElementById('attach-list');
    const type = document.getElementById('attach-type').value;
    const q = document.getElementById('attach-search').value.trim();
    list.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Recherche…</div>';
    try {
        const { items } = await request('GET', `${urls.candidates}?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`);
        if (!items.length) {
            list.innerHTML = '<p class="text-center text-muted py-4 mb-0">Aucun contenu disponible.</p>';
            return;
        }
        list.innerHTML = items.map((item) => {
            const meta = (item.type === 'resource' ? RESOURCE_KINDS[item.kind] : null) ?? TYPE_META[item.type];
            const lockedHint = `Ce contenu appartient déjà à ${T('a_group')}. Utilisez « Copier vers… » depuis ${T('this_group')}.`;
            return `<div class="list-group-item" data-type="${item.type}" data-id="${item.id}">
                <i class="bi ${meta.icon} fs-5"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">${esc(item.title)}</div>
                    <div class="small text-muted">
                        ${item.is_public ? '<i class="bi bi-globe2 me-1"></i>Public · ' : ''}
                        ${item.groups_count ? `Déjà dans ${item.groups_count} ${esc(item.groups_count > 1 ? T('groups') : T('group'))}` : `Dans aucun${T('fem_e') === 'e' ? 'e' : ''} ${esc(T('group'))}`}
                    </div>
                </div>
                ${item.locked
                    ? `<span class="badge text-bg-secondary" title="${esc(lockedHint)}"><i class="bi bi-lock-fill me-1"></i>Réservé</span>`
                    : '<button type="button" class="btn btn-sm btn-primary" data-action="attach"><i class="bi bi-plus-lg me-1"></i>Ajouter</button>'}
            </div>`;
        }).join('');
    } catch (error) {
        list.innerHTML = `<p class="text-center text-danger py-4 mb-0">${esc(error.message)}</p>`;
    }
}

function openAttach() {
    attachedSomething = false;
    document.getElementById('attach-folder-name').textContent = current === null ? 'Racine' : folderById(current)?.name ?? '';
    attachModal.show();
    loadCandidates();
}

document.getElementById('attach-type').addEventListener('change', loadCandidates);
document.getElementById('attach-search').addEventListener('input', () => {
    clearTimeout(attachTimer);
    attachTimer = setTimeout(loadCandidates, 300);
});
document.getElementById('attach-list').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action="attach"]');
    if (!button) return;
    const row = button.closest('[data-type]');
    button.disabled = true;
    try {
        const result = await request('POST', urls.attach, { type: row.dataset.type, id: Number(row.dataset.id), folder_id: current });
        notify(result.message);
        attachedSomething = true;
        row.remove();
    } catch (error) {
        button.disabled = false;
        notify(error.message, 'error');
    }
});
attachModalEl.addEventListener('hidden.bs.modal', () => {
    if (attachedSomething) load();
});

// ----------------------------------------------------------- Événements

function open(folderId) {
    current = folderId;
    render();
}

document.getElementById('gc-tree').addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-toggle]');
    if (toggle) {
        const id = Number(toggle.dataset.toggle);
        expanded.has(id) ? expanded.delete(id) : expanded.add(id);
        renderTree();
        return;
    }
    const node = event.target.closest('[data-folder]');
    if (node) open(parseFolder(node.dataset.folder));
});
document.getElementById('gc-tree').addEventListener('keydown', (event) => {
    const node = event.target.closest('[data-folder]');
    if (node && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        open(parseFolder(node.dataset.folder));
    }
});

document.getElementById('gc-breadcrumb').addEventListener('click', (event) => {
    const link = event.target.closest('[data-folder]');
    if (!link) return;
    event.preventDefault();
    open(parseFolder(link.dataset.folder));
});

document.getElementById('gc-folder-actions').addEventListener('click', (event) => {
    const button = event.target.closest('[data-folder-action]');
    const folder = folderById(current);
    if (!button || !folder) return;
    if (button.dataset.folderAction === 'edit') folderDialog(folder);
    if (button.dataset.folderAction === 'delete') deleteFolderDialog(folder);
});

document.getElementById('gc-list').addEventListener('click', async (event) => {
    const actionEl = event.target.closest('[data-action]');
    const row = event.target.closest('.gc-row');
    if (!actionEl || !row) return;
    const type = row.dataset.type;
    const id = Number(row.dataset.id);
    const entry = type === 'folder' ? { ...folderById(id), type: 'folder' } : state.items.find((i) => i.type === type && i.id === id);
    if (!entry) return;

    switch (actionEl.dataset.action) {
        case 'open': open(id); break;
        case 'edit-folder': folderDialog(folderById(id)); break;
        case 'delete-folder': deleteFolderDialog(folderById(id)); break;
        case 'move': moveDialog(entry); break;
        case 'copy': copyDialog(entry); break;
        case 'detach': detachDialog(entry); break;
        case 'retry':
            actionEl.disabled = true;
            await mutate(request('POST', fill(urls.retry, { ID: id })));
            break;
    }
});

document.getElementById('btn-new-folder').addEventListener('click', () => folderDialog());
document.getElementById('btn-upload').addEventListener('click', uploadDialog);
document.getElementById('btn-link').addEventListener('click', linkDialog);
document.getElementById('btn-attach').addEventListener('click', openAttach);

// Documents en préparation : rafraîchissement périodique tant qu'une conversion est en cours.
setInterval(() => {
    const pending = state.items.some((i) => i.kind === 'document' && ['pending', 'processing'].includes(i.conversion_status));
    if (pending && !dragged && !document.querySelector('.swal2-container, .modal.show')) load();
}, 10000);

const initial = /^#dossier-(\d+)$/.exec(window.location.hash);
current = initial ? Number(initial[1]) : null;
bindDropTargets();
load();
