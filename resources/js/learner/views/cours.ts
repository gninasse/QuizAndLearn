import { html, raw } from '../core/html';
import { t, tc } from '../core/terms';
import { breadcrumb, childrenOf, folderProgress } from '../domain/folders';
import type { ContentType, Folder, GroupInfo, Placement, ResourceKind } from '../domain/types';
import {
  articlesStore,
  decksStore,
  examsStore,
  foldersStore,
  groupsStore,
  isConsulted,
  placementsStore,
  quizzesStore,
  resourcesStore,
} from '../stores';
import { coursPath } from '../ui/path-chip';
import { navigateTo } from './helpers';

/**
 * Onglet Cours : contenus rangés par groupe puis par dossier.
 * Les dossiers et placements reçus sont déjà filtrés par le serveur
 * (visibilité par groupe) ; la vue ne fait que parcourir l'arbre.
 */

const EMPTY_FOLDER = 'Aucun contenu dans ce dossier pour le moment.';

const TYPE_ICONS: Record<Exclude<ContentType, 'resource'>, string> = {
  article: 'bi-journal-text',
  quiz: 'bi-patch-question',
  exam: 'bi-mortarboard',
  deck: 'bi-stack',
};

const KIND_ICONS: Record<ResourceKind, string> = {
  video: 'bi-play-btn',
  audio: 'bi-music-note-beamed',
  image: 'bi-image',
  pdf: 'bi-file-earmark-pdf',
  document: 'bi-file-earmark-text',
  link: 'bi-link-45deg',
};

const TYPE_LABELS: Record<Exclude<ContentType, 'resource'>, string> = {
  article: 'Article',
  quiz: 'Quiz',
  exam: 'Examen',
  deck: 'Cartes',
};

const KIND_LABELS: Record<ResourceKind, string> = {
  video: 'Vidéo',
  audio: 'Audio',
  image: 'Image',
  pdf: 'PDF',
  document: 'Document',
  link: 'Lien',
};

interface ItemView {
  title: string;
  href: string;
  icon: string;
  label: string;
}

/** Titre, lien et icône d'un contenu placé ; null s'il n'est pas (ou plus) disponible localement. */
function resolveItem(placement: Placement): ItemView | null {
  const { type, id } = placement;
  switch (type) {
    case 'article': {
      const article = articlesStore.get().find((a) => a.id === id);
      return article ? { title: article.title, href: `/articles/${id}`, icon: TYPE_ICONS.article, label: TYPE_LABELS.article } : null;
    }
    case 'quiz': {
      const quiz = quizzesStore.get().find((q) => q.id === id);
      return quiz ? { title: quiz.title, href: `/quizzes/${id}`, icon: TYPE_ICONS.quiz, label: TYPE_LABELS.quiz } : null;
    }
    case 'exam': {
      const exam = examsStore.get().find((e) => e.id === id);
      return exam ? { title: exam.title, href: '/examens', icon: TYPE_ICONS.exam, label: TYPE_LABELS.exam } : null;
    }
    case 'deck': {
      const deck = decksStore.get().find((d) => d.id === id);
      return deck ? { title: deck.titre, href: `/reviser/${id}`, icon: TYPE_ICONS.deck, label: TYPE_LABELS.deck } : null;
    }
    case 'resource': {
      const resource = resourcesStore.get().find((r) => r.id === id);
      if (!resource) return null;
      return {
        title: resource.title,
        href: `/ressources/${id}`,
        icon: KIND_ICONS[resource.kind] ?? 'bi-file-earmark',
        label: KIND_LABELS[resource.kind] ?? 'Ressource',
      };
    }
  }
}

function progressText(done: number, total: number): string {
  return `${done}/${total} consultés`;
}

function progressBar(done: number, total: number): string {
  const percent = total ? Math.round((done / total) * 100) : 0;
  return `<div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden"><div class="h-full bg-sky-500 rounded-full" style="width:${percent}%"></div></div>`;
}

const CARD = 'rounded-2xl border border-zinc-200/70 dark:border-zinc-800/70 panel-glass backdrop-blur-xl p-4 shadow-sm hover:shadow-md transition-shadow';

// ------------------------------------------------------------- Groupes

function mountGroups(el: HTMLElement, groups: GroupInfo[]): void {
  if (!groups.length) {
    el.innerHTML = html`
      <div class="text-center py-16 text-zinc-500">
        <div class="text-4xl mb-3">📂</div>
        <p class="font-semibold">Aucun cours pour le moment</p>
        <p class="text-sm mt-1">Les contenus de vos ${t('groups')} apparaîtront ici.</p>
      </div>
    `;
    return;
  }

  const folders = foldersStore.get();
  const placements = placementsStore.get();
  const cards = [...groups]
    .sort((a, b) => a.name.localeCompare(b.name, 'fr', { sensitivity: 'base' }))
    .map((group) => {
      const { done, total } = folderProgress(group.id, null, folders, placements, isConsulted);
      return html`
        <a data-link href="${coursPath(group.id, null)}" class="${CARD} flex flex-col gap-2">
          <div class="flex items-center gap-3">
            <span class="w-11 h-11 shrink-0 rounded-xl bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg">
              <i class="bi bi-people"></i>
            </span>
            <div class="min-w-0 flex-1">
              <p class="font-bold text-sm truncate">${group.name}</p>
              <p class="text-xs text-zinc-500">${progressText(done, total)}</p>
            </div>
            <i class="bi bi-chevron-right text-zinc-400"></i>
          </div>
          ${raw(progressBar(done, total))}
        </a>
      `;
    });

  el.innerHTML = html`
    <section class="flex flex-col gap-3">
      <p class="text-sm text-zinc-500">Choisissez ${t('a_group')}.</p>
      <div class="grid gap-3 sm:grid-cols-2">${raw(cards.join(''))}</div>
    </section>
  `;
}

// ------------------------------------------------------------- Dossier

function breadcrumbHtml(group: GroupInfo, path: Folder[]): string {
  const crumbs: { label: string; href: string | null }[] = [
    { label: 'Cours', href: '/cours' },
    { label: group.name, href: coursPath(group.id, null) },
    ...path.map((f) => ({ label: f.name, href: coursPath(group.id, f.id) })),
  ];
  const last = crumbs.length - 1;

  return crumbs
    .map((crumb, i) =>
      i === last
        ? html`<span aria-current="page" class="font-bold text-zinc-800 dark:text-zinc-100 truncate">${crumb.label}</span>`
        : html`<a data-link href="${crumb.href ?? '/cours'}" class="text-sky-600 dark:text-sky-400 hover:underline truncate">${crumb.label}</a><i class="bi bi-chevron-right text-[10px] text-zinc-400" aria-hidden="true"></i>`,
    )
    .join('');
}

function mountFolder(el: HTMLElement, group: GroupInfo, folderId: number | null): void {
  const folders = foldersStore.get();
  const placements = placementsStore.get();

  if (folderId !== null && !folders.some((f) => f.id === folderId && f.group_id === group.id)) {
    el.innerHTML = html`
      <div class="text-center py-16 text-zinc-500">
        <p class="font-semibold">Dossier introuvable.</p>
        <a data-link href="${coursPath(group.id, null)}" class="text-sm font-semibold text-sky-600 dark:text-sky-400">Retour à ${group.name}</a>
      </div>
    `;
    return;
  }

  const children = childrenOf(group.id, folderId, folders, placements);
  const items = children.items
    .map((placement) => ({ placement, view: resolveItem(placement) }))
    .filter((entry): entry is { placement: Placement; view: ItemView } => entry.view !== null);
  const progress = folderProgress(group.id, folderId, folders, placements, isConsulted);

  const folderCards = children.folders.map((folder) => {
    const { done, total } = folderProgress(group.id, folder.id, folders, placements, isConsulted);
    return html`
      <a data-link href="${coursPath(group.id, folder.id)}" class="${CARD} flex items-center gap-3">
        <span class="w-10 h-10 shrink-0 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
          <i class="bi bi-folder2"></i>
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-bold text-sm truncate">${folder.name}</p>
          <p class="text-xs text-zinc-500">${progressText(done, total)}</p>
        </div>
        <i class="bi bi-chevron-right text-zinc-400"></i>
      </a>
    `;
  });

  const itemCards = items.map(({ placement, view }) => {
    const consulted = isConsulted(placement.type, placement.id);
    return html`
      <a data-link href="${view.href}" class="${CARD} flex items-center gap-3">
        <span class="w-10 h-10 shrink-0 rounded-xl bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg">
          <i class="bi ${view.icon}"></i>
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-bold text-sm leading-snug">${view.title}</p>
          <p class="text-xs text-zinc-500">${view.label}</p>
        </div>
        ${consulted
          ? raw('<i class="bi bi-check-circle-fill text-emerald-500" title="Consulté" aria-label="Consulté"></i>')
          : ''}
      </a>
    `;
  });

  const empty = folderCards.length === 0 && itemCards.length === 0;

  el.innerHTML = html`
    <section class="flex flex-col gap-4">
      <nav data-role="breadcrumb" aria-label="Fil d'Ariane" class="flex items-center gap-1.5 flex-wrap text-sm min-w-0">
        ${raw(breadcrumbHtml(group, breadcrumb(folderId, folders)))}
      </nav>

      ${folderCards.length ? raw(`<div class="grid gap-3 sm:grid-cols-2">${folderCards.join('')}</div>`) : ''}
      ${itemCards.length ? raw(`<div class="flex flex-col gap-3">${itemCards.join('')}</div>`) : ''}

      ${empty
        ? raw(html`
            <div class="text-center py-12 text-zinc-500">
              <div class="text-4xl mb-3">📂</div>
              <p class="font-semibold">${EMPTY_FOLDER}</p>
            </div>
          `)
        : raw(html`
            <div data-role="folder-progress" class="flex flex-col gap-1.5">
              <p class="text-xs font-semibold text-zinc-500">${progressText(progress.done, progress.total)}</p>
              ${raw(progressBar(progress.done, progress.total))}
            </div>
          `)}
    </section>
  `;
}

export function mount(el: HTMLElement, params: Record<string, string>): void {
  const groups = groupsStore.get();

  if (!params.groupId) {
    // Un seul groupe : on entre directement dans ses cours.
    const only = groups.length === 1 ? groups[0] : undefined;
    if (only) {
      navigateTo(coursPath(only.id, null), true);
      return;
    }
    mountGroups(el, groups);
    return;
  }

  const group = groups.find((g) => g.id === Number(params.groupId));
  if (!group) {
    el.innerHTML = html`<p class="text-zinc-500 py-10 text-center">${tc('group')} introuvable.</p>`;
    return;
  }

  mountFolder(el, group, params.folderId ? Number(params.folderId) : null);
}
