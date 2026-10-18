// @vitest-environment happy-dom
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { submit, settingsFn, guest } = vi.hoisted(() => ({
  submit: vi.fn(),
  settingsFn: vi.fn(),
  guest: vi.fn(),
}));

vi.mock('../api/client', () => ({
  api: { submitAccessRequest: submit, showcaseSettings: settingsFn },
  ApiError: class ApiError extends Error {
    constructor(readonly status: number, message: string, readonly payload: unknown = null) {
      super(message);
    }
  },
  NetworkError: class extends Error {},
}));
vi.mock('../guest/guest-session', () => ({ guestSettings: guest }));
vi.mock('../ui/app-toast', () => ({ toast: vi.fn() }));

import { ApiError } from '../api/client';
import { mount } from './demande-acces';

const SETTINGS = {
  enabled: true,
  brand: { name: 'X', tagline: null, logo: null },
  registration: { fee: 25000, currency: 'XOF', accounts: [], reference: null, instructions: null },
  default_country_code: '225',
  privacy_url: '/confidentialite',
  groups: [{ id: 3, name: 'Promo A', start_date: null, end_date: null }],
};

async function setup(): Promise<{ el: HTMLElement; btn: HTMLButtonElement; set: (n: string, v: string) => void }> {
  const el = document.createElement('div');
  document.body.appendChild(el);
  await mount(el, {});
  const set = (name: string, value: string): void => {
    const input = el.querySelector<HTMLInputElement>(`[name="${name}"]`)!;
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };
  return { el, btn: el.querySelector<HTMLButtonElement>('button[type="submit"]')!, set };
}

function fillValid(el: HTMLElement, set: (n: string, v: string) => void): void {
  set('first_name', 'Awa');
  set('last_name', 'Kone');
  set('phone', '0700000000');
  const consent = el.querySelector<HTMLInputElement>('[name="consent"]')!;
  consent.checked = true;
  consent.dispatchEvent(new Event('input', { bubbles: true }));
}

function setOnline(value: boolean): void {
  Object.defineProperty(navigator, 'onLine', { value, configurable: true });
}

const ACCESS = {
  enabled: true,
  button_label: 'Demander un accès',
  title: 'Demander un accès',
  intro: null,
  confirmation: 'Demande envoyée.',
  channels: [{ type: 'form' }],
  fields: { whatsapp: 'optional', email: 'optional', organization: 'optional', group: 'optional', message: 'optional' },
  custom_fields: [],
};

describe('demande d\'accès', () => {
  beforeEach(() => {
    window.history.replaceState(null, '', '/demande-acces');
    submit.mockReset();
    guest.mockReturnValue(SETTINGS);
    setOnline(true);
    sessionStorage.clear();
  });

  it('submit disabled until required fields and consent', async () => {
    const { el, btn, set } = await setup();
    expect(btn.disabled).toBe(true);
    expect(el.querySelector('[data-prefix]')!.textContent).toBe('+225');
    const select = el.querySelector<HTMLSelectElement>('select[name="group_id"]')!;
    expect(select.querySelectorAll('option').length).toBe(2);
    expect(select.querySelector('option[value="3"]')!.textContent).toBe('Promo A');
    expect(el.textContent).not.toContain('&lt;');
    expect(el.textContent).not.toContain('<span');
    set('first_name', 'Awa');
    set('last_name', 'Kone');
    set('phone', '0700000000');
    expect(btn.disabled).toBe(true);
    fillValid(el, set);
    expect(btn.disabled).toBe(false);
  });

  it('offline disables submit and keeps draft', async () => {
    const { el, btn, set } = await setup();
    fillValid(el, set);
    setOnline(false);
    window.dispatchEvent(new Event('offline'));
    expect(btn.disabled).toBe(true);
    expect(el.textContent).toContain('Vous êtes hors-ligne');
    expect(JSON.parse(sessionStorage.getItem('learner:access-draft')!).first_name).toBe('Awa');
    setOnline(true);
    window.dispatchEvent(new Event('online'));
    expect(btn.disabled).toBe(false);
  });

  it('success screen shows payment instructions when configured', async () => {
    submit.mockResolvedValue({
      success: true,
      message: 'Demande envoyée.',
      phone: '+2250700000000',
      registration: {
        fee: 25000,
        currency: 'XOF',
        accounts: [{ operator: 'wave', number: '0700000000', holder: 'Ecole' }],
        reference: 'REF',
        instructions: 'Indiquez votre nom.',
      },
    });
    const { el, set } = await setup();
    fillValid(el, set);
    el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await vi.waitFor(() => expect(el.textContent).toContain('Demande envoyée.'));
    expect(el.textContent).toContain('+2250700000000');
    expect(el.textContent).toContain('Frais d\'inscription');
    expect(el.querySelectorAll('li').length).toBe(1);
    expect(el.querySelector('[data-fee]')).not.toBeNull();
    expect(el.textContent).not.toContain('&lt;');
    expect(el.textContent).not.toContain('<p');
    expect(el.textContent).toContain('Wave');
    expect(el.textContent).toContain('Ecole');
    expect(el.textContent).toContain('Attendez la confirmation de notre équipe avant tout transfert.');
    expect(el.textContent).toContain('Retour à la découverte');
    expect(sessionStorage.getItem('learner:access-draft')).toBeNull();
  });

  it('sends elapsed_ms measured locally since mount (no absolute timestamp)', async () => {
    submit.mockResolvedValue({ success: true, message: 'Demande envoyée.', phone: '+2250700000000', registration: SETTINGS.registration });
    const now = vi.spyOn(performance, 'now').mockReturnValue(1000);
    const { el, set } = await setup();
    fillValid(el, set);
    now.mockReturnValue(6500);
    el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await vi.waitFor(() => expect(submit).toHaveBeenCalledOnce());
    now.mockRestore();

    const body = submit.mock.calls[0]![0] as Record<string, unknown>;
    expect(body.elapsed_ms).toBe(5500);
    expect(body).not.toHaveProperty('started_at');
  });

  it('server validation errors are shown next to fields', async () => {
    submit.mockRejectedValue(
      new ApiError(422, 'Invalide', { errors: { phone: ['Numéro invalide.'] } }),
    );
    const { el, set } = await setup();
    fillValid(el, set);
    el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await vi.waitFor(() => {
      const err = el.querySelector('[data-error="phone"]')!;
      expect(err.textContent).toBe('Numéro invalide.');
      expect(err.classList.contains('hidden')).toBe(false);
    });
  });

  it('hidden field is absent, required field is starred', async () => {
    guest.mockReturnValue({
      ...SETTINGS,
      access_request: { ...ACCESS, fields: { ...ACCESS.fields, organization: 'hidden', email: 'required' } },
    });
    const { el } = await setup();
    expect(el.querySelector('[name="organization"]')).toBeNull();
    expect(el.querySelector('[name="email"]')!.closest('label')!.textContent).toContain('*');
  });

  it('configured texts are escaped', async () => {
    guest.mockReturnValue({ ...SETTINGS, access_request: { ...ACCESS, title: '<i>Hop</i>', intro: 'Intro <b>x</b>' } });
    const { el } = await setup();
    expect(el.querySelector('h1')!.textContent).toBe('<i>Hop</i>');
    expect(el.querySelector('h1 i')).toBeNull();
    expect(el.textContent).toContain('Intro <b>x</b>');
  });

  it('custom select field is sent under extra', async () => {
    guest.mockReturnValue({
      ...SETTINGS,
      access_request: {
        ...ACCESS,
        custom_fields: [{ key: 'cf_a1', label: 'Ville', type: 'select', options: ['Abidjan', 'Bouaké'], required: true }],
      },
    });
    submit.mockResolvedValue({ success: true, message: 'ok', confirmation: 'Merci !', phone: '+2250700000000', registration: SETTINGS.registration });
    const { el, set } = await setup();
    expect(el.textContent).toContain('Ville');
    fillValid(el, set);
    set('cf_a1', 'Bouaké');
    el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await vi.waitFor(() => expect(submit).toHaveBeenCalledOnce());
    expect((submit.mock.calls[0]![0] as { extra: unknown }).extra).toEqual({ cf_a1: 'Bouaké' });
    await vi.waitFor(() => expect(el.textContent).toContain('Merci !'));
  });

  it('several channels show choice cards, form first; canal=form opens the form', async () => {
    guest.mockReturnValue({
      ...SETTINGS,
      access_request: {
        ...ACCESS,
        channels: [{ type: 'phone', number: '+2250700000000' }, { type: 'form' }],
      },
    });
    const { el } = await setup();
    expect(el.querySelector('form')).toBeNull();
    const links = [...el.querySelectorAll('a')].map((a) => a.getAttribute('href'));
    expect(links.slice(0, 2)).toEqual(['/demande-acces?canal=form', 'tel:+2250700000000']);

    window.history.replaceState(null, '', '/demande-acces?canal=form');
    const second = await setup();
    expect(second.el.querySelector('form')).not.toBeNull();
  });

  it('closed settings show the closing message', async () => {
    guest.mockReturnValue({ ...SETTINGS, access_request: { ...ACCESS, enabled: false } });
    const { el } = await setup();
    expect(el.querySelector('form')).toBeNull();
    expect(el.textContent).toContain('fermées');
  });

  it('403 access_requests_closed shows the closing message, not a generic error', async () => {
    submit.mockRejectedValue(new ApiError(403, 'x', { success: false, code: 'access_requests_closed' }));
    const { el, set } = await setup();
    fillValid(el, set);
    el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    await vi.waitFor(() => expect(el.textContent).toContain('fermées pour le moment'));
    expect(el.querySelector('form')).toBeNull();
  });
});
