import { ApiError, api } from '../api/client';
import { isConsultation } from '../consultation/consultation';
import { escapeHtml, html, raw } from '../core/html';
import type { Resource, ResourceView } from '../domain/types';
import { resourcesStore } from '../stores';
import { dispatch, markResourceConsulted } from '../sync/engine';
import {
  hasResourceOffline,
  type OfflineFileType,
  readResourceOffline,
  removeResourceOffline,
  saveResourceOffline,
} from '../sync/resource-cache';
import { toast } from '../ui/app-toast';
import { pathChipHtml } from '../ui/path-chip';
import type { PdfViewerHandle } from '../ui/pdf-viewer';
import { parseVideoEmbed } from '../ui/video-embed-parser';

/**
 * Lecteur de ressource `/ressources/:id`, selon la réponse de `view` :
 * PDF (document converti compris) → lecteur canvas protégé ; audio et image
 * → médias protégés de media-guard (`.rich-content`) ; vidéo → video-ref ;
 * lien → lecteur de marque si YouTube / Vimeo, sinon nouvel onglet.
 * 202 → « Document en préparation… » ; 409 → « Document indisponible ».
 */

const MSG_PENDING = 'Document en préparation…';
const MSG_FAILED = 'Document indisponible';
const MSG_FORBIDDEN = 'Ressource indisponible.';
const MSG_ONLINE_ONLY = 'Ressource disponible en ligne uniquement.';

/** Kinds dont le fichier peut être gardé hors ligne (vidéos : jamais). */
const OFFLINE_KINDS: ReadonlySet<Resource['kind']> = new Set(['pdf', 'document', 'audio', 'image']);

/** Lecteur et URL locale de la vue précédente, libérés au montage suivant. */
let activePdf: PdfViewerHandle | null = null;
let activeObjectUrl: string | null = null;

function releasePrevious(): void {
  activePdf?.destroy();
  activePdf = null;
  if (activeObjectUrl) URL.revokeObjectURL(activeObjectUrl);
  activeObjectUrl = null;
}

function messageHtml(icon: string, text: string, retry = false): string {
  return html`
    <div class="flex flex-col items-center gap-3 text-center py-12 text-zinc-500" role="status">
      <i class="bi ${icon} text-4xl" aria-hidden="true"></i>
      <p class="font-semibold">${text}</p>
      ${retry
        ? raw('<button type="button" data-role="retry" class="text-sm font-semibold text-sky-600 dark:text-sky-400">Réessayer</button>')
        : ''}
    </div>
  `;
}

function offlineButtonHtml(saved: boolean): string {
  return html`
    <i class="bi ${saved ? 'bi-check-circle-fill text-emerald-500' : 'bi-cloud-arrow-down'}" aria-hidden="true"></i>
    <span>Disponible hors ligne</span>
  `;
}

const ACTION_BUTTON =
  'inline-flex items-center gap-2 rounded-xl border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800 px-3.5 py-2 text-sm font-semibold transition-colors';

export function mount(el: HTMLElement, params: Record<string, string>): void {
  releasePrevious();
  const id = Number(params.id);
  const resource = resourcesStore.get().find((r) => r.id === id);

  if (!resource) {
    el.innerHTML = html`
      <div class="text-center py-16 text-zinc-500">
        <p class="font-semibold">Ressource introuvable.</p>
        <a data-link href="/cours" class="text-sm font-semibold text-sky-600 dark:text-sky-400">Retour aux cours</a>
      </div>
    `;
    return;
  }

  // Consultation : lecture seule, rien à télécharger ni à garder sur l'appareil.
  const consultation = isConsultation();
  const canKeepOffline = !consultation && OFFLINE_KINDS.has(resource.kind);
  // Vidéo : lecture en streaming uniquement, `download` répond 404 côté serveur.
  const canDownload = !consultation && resource.allow_download && resource.kind !== 'video';
  const chip = pathChipHtml('resource', id);

  el.innerHTML = html`
    <article class="max-w-3xl mx-auto flex flex-col gap-4">
      <div class="flex items-center gap-3 flex-wrap">
        <a data-link href="/cours" class="text-sm font-semibold text-sky-600 dark:text-sky-400">
          <i class="bi bi-arrow-left"></i> Cours
        </a>
        ${raw(chip)}
      </div>

      <header class="flex flex-col gap-1.5">
        <h2 class="text-2xl font-extrabold leading-tight">${resource.title}</h2>
        ${resource.description ? raw(`<p class="text-sm text-zinc-500">${escapeHtml(resource.description)}</p>`) : ''}
      </header>

      ${canKeepOffline || canDownload
        ? raw(html`
            <div data-role="resource-actions" class="flex items-center gap-2 flex-wrap">
              ${canKeepOffline
                ? raw(`<button type="button" data-role="offline" aria-pressed="false" class="${ACTION_BUTTON}">${offlineButtonHtml(false)}</button>`)
                : ''}
              ${canDownload
                ? raw(`<button type="button" data-role="download" class="${ACTION_BUTTON}"><i class="bi bi-download" aria-hidden="true"></i><span>Télécharger</span></button>`)
                : ''}
            </div>
          `)
        : ''}

      <div data-role="resource-body" class="min-h-40">
        ${raw(messageHtml('bi-hourglass', 'Chargement…'))}
      </div>
    </article>
  `;

  const body = el.querySelector<HTMLElement>('[data-role="resource-body"]')!;

  body.addEventListener('click', (event) => {
    if ((event.target as HTMLElement).closest('[data-role="retry"]')) void load(resource, body);
  });

  el.querySelector('[data-role="download"]')?.addEventListener('click', () => {
    // Fichier d'origine servi en pièce jointe : pas de lien de téléchargement dans la page.
    window.location.assign(api.resourceDownloadUrl(id));
  });

  const offlineButton = el.querySelector<HTMLButtonElement>('[data-role="offline"]');
  if (offlineButton) bindOfflineButton(offlineButton, resource);

  void load(resource, body);
}

// ----------------------------------------------------------- Chargement

/**
 * Consultée seulement une fois affichée (200, ou copie hors ligne) :
 * localement (onglet Cours) et côté serveur. 202 / 409 / 403 / hors ligne
 * sans copie ne comptent pas ; la consultation « Voir en tant que » non plus.
 */
function markConsulted(resource: Resource): void {
  if (isConsultation()) return;
  void markResourceConsulted(resource.id);
  void dispatch('resource_view', { resource_id: resource.id });
}

async function load(resource: Resource, body: HTMLElement): Promise<void> {
  body.innerHTML = messageHtml('bi-hourglass', 'Chargement…');

  if (!navigator.onLine) {
    await showOffline(resource, body);
    return;
  }

  let view;
  try {
    view = await api.resourceView(resource.id);
  } catch (e) {
    if (e instanceof ApiError) {
      body.innerHTML =
        e.status === 409
          ? messageHtml('bi-file-earmark-x', MSG_FAILED)
          : messageHtml('bi-lock', MSG_FORBIDDEN);
    } else {
      await showOffline(resource, body);
    }
    return;
  }

  if ('status' in view) {
    body.innerHTML = messageHtml('bi-hourglass-split', MSG_PENDING, true);
    return;
  }

  markConsulted(resource);
  await show(view, resource, body);
}

async function showOffline(resource: Resource, body: HTMLElement): Promise<void> {
  const cached = await readResourceOffline(resource.id);
  if (!cached) {
    body.innerHTML = messageHtml('bi-wifi-off', MSG_ONLINE_ONLY, true);
    return;
  }
  markConsulted(resource);
  if (cached.type === 'pdf') {
    await showPdf(body, () => cached.blob.arrayBuffer());
    return;
  }
  activeObjectUrl = URL.createObjectURL(cached.blob);
  showMedia(cached.type, activeObjectUrl, resource, body);
}

async function show(view: ResourceView, resource: Resource, body: HTMLElement): Promise<void> {
  switch (view.type) {
    case 'pdf':
      await showPdf(body, async () => {
        const response = await fetch(view.url, { credentials: 'same-origin' });
        if (!response.ok) throw new Error(`Erreur ${response.status}`);
        return response.arrayBuffer();
      });
      return;
    case 'audio':
    case 'image':
      showMedia(view.type, view.url, resource, body);
      return;
    case 'video':
      // video-ref (via media-guard) demande la source de lecture au serveur.
      body.innerHTML = html`<div class="rich-content"><video controls data-video-ref="${view.video_ref}"></video></div>`;
      return;
    case 'link':
      showLink(view.url, body);
      return;
  }
}

async function showPdf(body: HTMLElement, data: () => Promise<ArrayBuffer>): Promise<void> {
  try {
    const [{ renderPdf }, buffer] = await Promise.all([import('../ui/pdf-viewer'), data()]);
    if (!body.isConnected) return;
    activePdf = await renderPdf(body, buffer);
  } catch {
    body.innerHTML = messageHtml('bi-file-earmark-x', MSG_FAILED);
  }
}

/** Médias protégés existants : media-guard encadre `.rich-content img/audio` (filigrane, gestes bloqués). */
function showMedia(type: 'audio' | 'image', url: string, resource: Resource, body: HTMLElement): void {
  body.innerHTML =
    type === 'image'
      ? html`<div class="rich-content text-center"><img src="${url}" alt="${resource.title}"></div>`
      : html`<div class="rich-content"><audio controls preload="metadata" src="${url}" class="w-full"></audio></div>`;
}

function showLink(url: string, body: HTMLElement): void {
  const embed = parseVideoEmbed(url);
  if (embed) {
    // Lecteur de marque monté par media-guard (filigrane, sans commandes du fournisseur).
    body.innerHTML = html`
      <div class="rich-content">
        <div class="brand-video" data-embed="${embed.provider}" data-id="${embed.id}" ${raw(embed.hash ? `data-hash="${escapeHtml(embed.hash)}"` : '')}></div>
      </div>
    `;
    return;
  }

  let host = url;
  try {
    host = new URL(url).hostname;
  } catch {
    /* URL affichée telle quelle */
  }
  body.innerHTML = html`
    <div class="flex flex-col items-center gap-3 text-center py-10">
      <i class="bi bi-box-arrow-up-right text-4xl text-sky-500" aria-hidden="true"></i>
      <p class="text-sm text-zinc-500">Cette ressource s'ouvre sur un site externe : <span class="font-semibold">${host}</span></p>
      <button type="button" data-role="open-link"
              class="rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-sm px-5 py-2.5">Ouvrir le lien</button>
    </div>
  `;
  body.querySelector('[data-role="open-link"]')?.addEventListener('click', () => {
    window.open(url, '_blank', 'noopener');
  });
}

// ------------------------------------------------------- Hors ligne

function bindOfflineButton(button: HTMLButtonElement, resource: Resource): void {
  let saved = false;
  const paint = (): void => {
    button.innerHTML = offlineButtonHtml(saved);
    button.setAttribute('aria-pressed', String(saved));
    button.title = saved ? 'Retirer du hors-ligne' : 'Garder sur cet appareil';
  };

  void hasResourceOffline(resource.id).then((value) => {
    saved = value;
    paint();
  });

  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      if (saved) {
        await removeResourceOffline(resource.id);
        saved = false;
        toast('Ressource retirée du hors-ligne.', 'info');
      } else {
        const view = await api.resourceView(resource.id);
        if ('status' in view) {
          toast(MSG_PENDING, 'info');
          return;
        }
        if (view.type !== 'pdf' && view.type !== 'audio' && view.type !== 'image') return;
        await saveResourceOffline(resource.id, view.type satisfies OfflineFileType, view.url);
        saved = true;
        toast('Ressource disponible hors ligne.', 'success');
      }
    } catch {
      toast("La ressource n'a pas pu être gardée hors ligne.", 'warning');
    } finally {
      button.disabled = false;
      paint();
    }
  });
}
