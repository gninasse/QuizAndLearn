import { createStore } from '../core/store';
import type {
  ArticleItem,
  GroupInfo,
  BadgeItem,
  DeckItem,
  ContentType,
  ExamItem,
  Folder,
  Placement,
  Preferences,
  QuizItem,
  Resource,
  UserProfile,
} from '../domain/types';

/** Session courante : null = non connecté (ou profil pas encore chargé). */
export const sessionStore = createStore<UserProfile | null>(null);

export const articlesStore = createStore<ArticleItem[]>([]);
export const quizzesStore = createStore<QuizItem[]>([]);
export const decksStore = createStore<DeckItem[]>([]);
export const examsStore = createStore<ExamItem[]>([]);
export const badgesStore = createStore<BadgeItem[]>([]);
export const groupsStore = createStore<GroupInfo[]>([]);
export const foldersStore = createStore<Folder[]>([]);
export const resourcesStore = createStore<Resource[]>([]);
export const placementsStore = createStore<Placement[]>([]);
/** Ressources ouvertes sur cet appareil (voir `markResourceConsulted`). */
export const consultedResourcesStore = createStore<number[]>([]);

/**
 * Contenu déjà consulté, d'après les seules données locales :
 * article et quiz → progression ; ressource → ouverture locale ;
 * examen → au moins une tentative ; deck → au moins une carte révisée
 * (les sessions de révision ne sont pas conservées côté client).
 */
export function isConsulted(type: ContentType, id: number): boolean {
  switch (type) {
    case 'article': {
      const article = articlesStore.get().find((a) => a.id === id);
      return !!article && (article.status !== 'unread' || article.progress_percentage > 0);
    }
    case 'quiz': {
      const quiz = quizzesStore.get().find((q) => q.id === id);
      return !!quiz && (quiz.status !== 'unread' || quiz.attempts.length > 0);
    }
    case 'exam':
      return (examsStore.get().find((e) => e.id === id)?.attempts.length ?? 0) > 0;
    case 'deck':
      return decksStore.get().find((d) => d.id === id)?.cards.some((c) => c.review !== null) ?? false;
    case 'resource':
      return consultedResourcesStore.get().includes(id);
  }
}

export const preferencesStore = createStore<Preferences>({
  locale: 'fr',
  theme: 'light',
  font_size: 'medium',
  sound_enabled: true,
  notifications_enabled: null,
});

export interface SyncState {
  online: boolean;
  syncing: boolean;
  pendingActions: number;
  lastSyncAt: string | null;
}

export const syncStore = createStore<SyncState>({
  online: navigator.onLine,
  syncing: false,
  pendingActions: 0,
  lastSyncAt: null,
});
