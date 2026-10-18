// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { guest } = vi.hoisted(() => ({ guest: vi.fn() }));
vi.mock('./guest-session', () => ({ guestSettings: guest }));
import { sessionStore } from '../stores';
import type { UserProfile } from '../domain/types';
import '../components/app-shell';
import { accessCtaHtml, guestBannerHtml, lockedCardHtml, patchGuestBanner } from './guest-ui';

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
    guest.mockReturnValue(null);
  });

  const withAccess = (over: Record<string, unknown>): void => {
    guest.mockReturnValue({
      access_request: {
        enabled: true,
        button_label: 'Demander un accès',
        title: 'T',
        intro: null,
        confirmation: 'C',
        channels: [{ type: 'form' }],
        fields: {},
        custom_fields: [],
        ...over,
      },
    });
  };

  it('closed requests: banner text without link, no CTA', () => {
    withAccess({ enabled: false });
    const banner = guestBannerHtml();
    expect(banner).toContain('data-role="guest-banner"');
    expect(banner).toContain('Mode découverte');
    expect(banner).not.toContain('—');
    expect(banner).not.toContain('<a');
    expect(lockedCardHtml('exams')).toContain('Disponible avec un compte');
    expect(lockedCardHtml('exams')).not.toContain('<a');
    expect(accessCtaHtml('quiz')).toBe('');
  });

  it('patchGuestBanner re-renders the displayed banner from current settings', () => {
    document.body.innerHTML = guestBannerHtml();
    expect(document.querySelector('[data-role="guest-banner"] a')).not.toBeNull();
    withAccess({ enabled: false });
    patchGuestBanner();
    const banner = document.querySelector('[data-role="guest-banner"]')!;
    expect(banner.textContent?.trim()).toBe('Mode découverte');
    expect(banner.querySelector('a')).toBeNull();
  });

  it('uses the custom label escaped', () => {
    withAccess({ button_label: '<b>Go</b>' });
    const banner = guestBannerHtml();
    expect(banner).toContain('&lt;b&gt;Go&lt;/b&gt;');
    expect(banner).not.toContain('<b>');
    expect(lockedCardHtml('badges')).toContain('&lt;b&gt;Go&lt;/b&gt;');
  });

  it('single external channel links directly with target blank', () => {
    withAccess({ channels: [{ type: 'whatsapp', number: '+2250700000000', message: 'Salut' }] });
    const banner = guestBannerHtml();
    expect(banner).toContain('href="https://wa.me/2250700000000?text=Salut"');
    expect(banner).toContain('target="_blank" rel="noopener"');
    expect(banner).not.toContain('data-link');
  });

  it('tel link has no target', () => {
    withAccess({ channels: [{ type: 'phone', number: '+2250700000000' }] });
    const banner = guestBannerHtml();
    expect(banner).toContain('href="tel:+2250700000000"');
    expect(banner).not.toContain('target=');
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
