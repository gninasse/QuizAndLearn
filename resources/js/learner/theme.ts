import type { Preferences } from './domain/types';

/**
 * Application du thème et de la taille de police sur <html>.
 * Avant login : localStorage / prefers-color-scheme. Après : préférences serveur.
 */

const THEME_KEY = 'learner-theme';

export function applyTheme(dark: boolean): void {
  document.documentElement.classList.toggle('dark', dark);
  localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light');

  const meta = document.querySelector<HTMLMetaElement>('meta[name="theme-color"]');
  if (meta) meta.content = dark ? '#09090b' : '#0284c7';
}

export function initialTheme(): boolean {
  const stored = localStorage.getItem(THEME_KEY);
  if (stored) return stored === 'dark';
  return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

export function applyPreferences(preferences: Preferences): void {
  applyTheme(preferences.theme === 'dark');
  document.documentElement.dataset.fontSize = preferences.font_size;
}

export function toggleTheme(): boolean {
  const dark = !document.documentElement.classList.contains('dark');
  applyTheme(dark);
  return dark;
}
