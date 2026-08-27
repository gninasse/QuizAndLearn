import { createStore } from '../core/store';
import type {
  ArticleItem,
  GroupInfo,
  BadgeItem,
  DeckItem,
  ExamItem,
  Preferences,
  QuizItem,
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
