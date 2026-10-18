// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
import { sessionStore } from '../stores';
import type { UserProfile } from '../domain/types';
import '../components/app-shell';
import { accessCtaHtml, guestBannerHtml, lockedCardHtml } from './guest-ui';

const xp = { total_xp: 0, current_level: 0, current_streak: 0, longest_streak: 0, last_activity_date: null };
const profile = (guest: boolean): UserProfile => ({
  id: guest ? 0 : 5,
  name: guest ? 'Invité' : 'Awa',
  last_name: '',
  full_name: guest ? 'Invité' : 'Awa Koné',
  email: '',
  avatar_url: '',
  matricule: null,
  ...(guest ? { is_guest: true } : {}),
  xp,
});

describe('guest ui', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  it('locked card shows feature copy and access link', () => {
    const card = lockedCardHtml('exams');
    expect(card).toContain('Examens surveillés, notés sur 20, avec classement.');
    expect(card).toContain('Disponible avec un compte');
    expect(card).toContain('href="/demande-acces"');
    expect(card).toContain('Demander un accès');
    expect(lockedCardHtml('badges')).toContain('Gagnez des badges en progressant.');
    expect(lockedCardHtml('leaderboard')).toContain('Comparez-vous aux autres apprenants de votre formation.');
    expect(lockedCardHtml('progression')).toContain('Suivez votre progression semaine après semaine.');
  });

  it('banner links to access request', () => {
    const banner = guestBannerHtml();
    expect(banner).toContain('Mode découverte —');
    expect(banner).toContain('href="/demande-acces"');
    expect(accessCtaHtml('quiz')).toContain('Vous avez aimé ? Obtenez votre accès complet.');
  });

  it('shell shows banner only for guests', () => {
    sessionStore.set(profile(true));
    const guestShell = document.createElement('app-shell');
    document.body.append(guestShell);
    expect(guestShell.querySelector('[data-role="guest-banner"]')).not.toBeNull();
    expect(guestShell.querySelector('[data-role="user-meta"]')?.textContent).toContain('Mode découverte');
    guestShell.remove();

    sessionStore.set(profile(false));
    const learnerShell = document.createElement('app-shell');
    document.body.append(learnerShell);
    expect(learnerShell.querySelector('[data-role="guest-banner"]')).toBeNull();
    expect(learnerShell.querySelector('[data-role="user-meta"]')?.textContent).toContain('Niveau');
  });
});
