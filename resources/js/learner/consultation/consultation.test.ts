// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { UserProfile } from '../domain/types';

const TOKEN = 'tok3nABC123';
const STORAGE_KEY = 'learner:consultation';

function profile(patch: Partial<UserProfile> = {}): UserProfile {
  return {
    id: 7,
    name: 'Awa',
    last_name: 'Koné',
    full_name: 'Awa Koné',
    email: 'awa@example.com',
    avatar_url: '',
    matricule: 'MAT-001',
    xp: { total_xp: 120, current_level: 2, current_streak: 1, longest_streak: 3, last_activity_date: null },
    ...patch,
  };
}

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

async function databaseNames(): Promise<string[]> {
  return (await indexedDB.databases()).map((d) => d.name ?? '');
}

async function deleteAllDatabases(): Promise<void> {
  for (const name of await databaseNames()) {
    await new Promise<void>((resolve) => {
      const request = indexedDB.deleteDatabase(name);
      request.onsuccess = () => resolve();
      request.onerror = () => resolve();
      request.onblocked = () => resolve();
    });
  }
}

/** Ouvre une consultation : jeton en sessionStorage, modules rechargés (db choisie à l'import). */
function startConsultation(): void {
  sessionStorage.setItem(STORAGE_KEY, TOKEN);
}

beforeEach(async () => {
  vi.resetModules();
  vi.unstubAllGlobals();
  sessionStorage.clear();
  history.replaceState(null, '', '/');
  document.body.innerHTML = '<div id="app"></div>';
  await deleteAllDatabases();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('consultation mode', () => {
  it('reads the token from the fragment, stores it per tab and strips the URL', async () => {
    history.replaceState(null, '', `/articles?x=1#consultation=${TOKEN}`);
    const { readConsultationToken, isConsultation } = await import('./consultation');

    expect(readConsultationToken()).toBe(TOKEN);
    expect(sessionStorage.getItem(STORAGE_KEY)).toBe(TOKEN);
    expect(window.location.hash).toBe('');
    expect(window.location.pathname).toBe('/articles');
    expect(window.location.search).toBe('?x=1');

    // Rechargement de l'onglet : le jeton vient du sessionStorage.
    expect(readConsultationToken()).toBe(TOKEN);
    expect(isConsultation()).toBe(true);
  });

  it('is not a consultation without fragment nor stored token', async () => {
    const { readConsultationToken, isConsultation, consultationDbName } = await import('./consultation');
    expect(readConsultationToken()).toBeNull();
    expect(isConsultation()).toBe(false);
    expect(consultationDbName()).toBe('LearnQuizDB-v2026');
  });

  it('consultation uses its own database and never touches the learner one', async () => {
    history.replaceState(null, '', `/#consultation=${TOKEN}`);
    const { db } = await import('../db/schema');

    expect(db.name).toBe('LearnQuizDB-v2026-consultation');
    await db.profile.put({ ...profile(), _key: 'me' });

    const names = await databaseNames();
    expect(names).toContain('LearnQuizDB-v2026-consultation');
    expect(names).not.toContain('LearnQuizDB-v2026');
    db.close();
  });

  it('dispatch does nothing in consultation', async () => {
    startConsultation();
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    const { dispatch } = await import('../sync/engine');
    const { pendingCount } = await import('../db/outbox');
    const { sessionStore } = await import('../stores');
    sessionStore.set(profile());

    await dispatch('article_favorite', { article_id: 1, is_favorite: true });

    expect(await pendingCount()).toBe(0);
    expect(fetchMock).not.toHaveBeenCalled();
    (await import('../db/schema')).db.close();
  });

  it('api requests carry X-Impersonation-Token', async () => {
    startConsultation();
    const fetchMock = vi.fn(async () => jsonResponse(200, { success: true, user: profile() }));
    vi.stubGlobal('fetch', fetchMock);

    const { api } = await import('../api/client');
    await api.me();
    await api.endConsultation();

    const [meUrl, meInit] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(meUrl).toBe('/api/learner/v1/me');
    expect((meInit.headers as Record<string, string>)['X-Impersonation-Token']).toBe(TOKEN);
    // Le cookie de session du super admin doit accompagner la requête.
    expect(meInit.credentials).toBe('same-origin');

    const [endUrl, endInit] = fetchMock.mock.calls[1] as unknown as [string, RequestInit];
    expect(endUrl).toBe('/api/learner/v1/impersonation');
    expect(endInit.method).toBe('DELETE');
    expect((endInit.headers as Record<string, string>)['X-Impersonation-Token']).toBe(TOKEN);
  });

  it('api requests carry no impersonation header outside consultation', async () => {
    const fetchMock = vi.fn(async () => jsonResponse(200, { success: true, user: profile() }));
    vi.stubGlobal('fetch', fetchMock);

    const { api } = await import('../api/client');
    await api.me();

    const [, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect((init.headers as Record<string, string>)['X-Impersonation-Token']).toBeUndefined();
  });

  it('impersonation_ended response ends the consultation screen', async () => {
    startConsultation();
    const fetchMock = vi.fn(async () =>
      jsonResponse(403, { code: 'impersonation_ended', message: 'Consultation terminée.' }),
    );
    vi.stubGlobal('fetch', fetchMock);

    const { db, setMeta, META_CURSOR } = await import('../db/schema');
    const { sessionStore } = await import('../stores');
    const { sync } = await import('../sync/engine');

    await db.profile.put({ ...profile(), _key: 'me' });
    await setMeta(META_CURSOR, '2026-10-18T00:00:00Z');
    sessionStore.set(profile());

    await sync();

    const app = document.getElementById('app')!;
    expect(app.textContent).toContain('Consultation terminée');
    expect(app.textContent).toContain('Vous pouvez fermer cet onglet.');
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull();
    // La base de consultation est purgée.
    expect(await db.profile.count()).toBe(0);
    // Aucun « DELETE » : la fin vient du serveur.
    expect((fetchMock.mock.calls as unknown as [string, RequestInit][]).every(([, init]) => init.method === 'GET')).toBe(true);
    db.close();
  });

  it('quit ends the session server side then shows the end screen', async () => {
    startConsultation();
    const fetchMock = vi.fn(async () => jsonResponse(200, { success: true }));
    vi.stubGlobal('fetch', fetchMock);

    const { endConsultation } = await import('./consultation');
    await endConsultation('quit');

    const [url, init] = fetchMock.mock.calls[0] as unknown as [string, RequestInit];
    expect(url).toBe('/api/learner/v1/impersonation');
    expect(init.method).toBe('DELETE');
    expect(document.getElementById('app')!.textContent).toContain('Consultation terminée');
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull();
    (await import('../db/schema')).db.close();
  });

  it('banner shows learner name and quit button', async () => {
    startConsultation();
    const { sessionStore } = await import('../stores');
    await import('../components/app-shell');
    sessionStore.set(profile({ suspended: true, must_change_password: true }));

    const shell = document.createElement('app-shell');
    document.body.append(shell);

    const banner = shell.querySelector('[data-role="consultation-banner"]');
    expect(banner?.textContent).toContain("Consultation de l'espace de Awa Koné — lecture seule");
    const quit = banner?.querySelector('[data-role="consultation-quit"]');
    expect(quit?.textContent?.trim()).toBe('Quitter');
    expect(shell.textContent).toContain('Compte suspendu');
    expect(shell.textContent).toContain('Mot de passe provisoire non encore changé');
    // Pas d'interface du mode découverte.
    expect(shell.querySelector('[data-role="guest-banner"]')).toBeNull();

    // Bandeau de consultation et bandeau hors-ligne : un seul conteneur collant.
    const stack = shell.querySelector('[data-role="top-stack"]')!;
    expect(stack.querySelector('[data-role="consultation-banner"]')).not.toBeNull();
    expect(stack.querySelector('[data-role="conn-banner"]')).not.toBeNull();
    expect(shell.querySelectorAll('.sticky.top-0').length).toBe(1);

    // Suspension datée. (Un seul test monte le shell : l'élément personnalisé
    // reste lié au graphe de modules du premier `define`.)
    sessionStore.set(null);
    const until = new Date(2026, 9, 25, 7, 46).toISOString();
    sessionStore.set(profile({ suspended: true, suspended_until: until }));
    expect(shell.textContent).toContain("Compte suspendu jusqu'au 25/10/2026 à 07:46");
    expect(shell.textContent).not.toContain('Mot de passe provisoire');
  });

  it('watermark names the super admin, with a plain fallback', async () => {
    const { consultationWatermark } = await import('./consultation');
    expect(
      consultationWatermark(profile({ consultation: { admin_name: 'Root Admin', expires_at: '2026-10-18T15:00:00Z' } })),
    ).toBe('Consultation · Root Admin');
    expect(consultationWatermark(profile())).toBe('Consultation');
    expect(consultationWatermark(null)).toBe('Consultation');
  });

  it('keeps the token in memory when sessionStorage is unavailable', async () => {
    history.replaceState(null, '', `/#consultation=${TOKEN}`);
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('quota');
    });
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('denied');
    });
    try {
      const { readConsultationToken, isConsultation } = await import('./consultation');
      expect(readConsultationToken()).toBe(TOKEN);
      expect(window.location.hash).toBe('');
      expect(readConsultationToken()).toBe(TOKEN);
      expect(isConsultation()).toBe(true);
    } finally {
      vi.restoreAllMocks();
    }
  });

  it('notifies end listeners (periodic sync stops) when the consultation ends', async () => {
    startConsultation();
    vi.stubGlobal('fetch', vi.fn(async () => jsonResponse(200, { success: true })));
    const { endConsultation, onConsultationEnd, readConsultationToken } = await import('./consultation');
    const listener = vi.fn();
    onConsultationEnd(listener);

    await endConsultation('ended');

    expect(listener).toHaveBeenCalledOnce();
    expect(readConsultationToken()).toBeNull();
    (await import('../db/schema')).db.close();
  });

  it('blocked actions show a toast', async () => {
    const { blockedInConsultation } = await import('./consultation');
    blockedInConsultation();
    expect(document.body.textContent).toContain('Action indisponible en consultation.');
  });
});
