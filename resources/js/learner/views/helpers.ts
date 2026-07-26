import type { XpSnapshot } from '../domain/types';
import { GAMIFICATION } from '../domain/gamification';

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  });
}

export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatDuration(totalSeconds: number): string {
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

/** Progression vers le prochain niveau — formule unifiée (100 XP / niveau). */
export function levelProgress(xp: XpSnapshot): { percent: number; intoLevel: number; perLevel: number } {
  const perLevel = GAMIFICATION.XP_PER_LEVEL;
  const intoLevel = xp.total_xp % perLevel;
  return { percent: Math.round((intoLevel / perLevel) * 100), intoLevel, perLevel };
}

export function shuffleArray<T>(items: T[]): T[] {
  const copy = [...items];
  for (let i = copy.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    const a = copy[i] as T;
    copy[i] = copy[j] as T;
    copy[j] = a;
  }
  return copy;
}

export function pluralize(count: number, singular: string, plural?: string): string {
  return count <= 1 ? singular : (plural ?? `${singular}s`);
}
