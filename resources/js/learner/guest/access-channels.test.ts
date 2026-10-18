import { describe, expect, it } from 'vitest';
import type { AccessRequestConfig } from '../domain/types';
import { accessEntryHref, channelHref, channelLabel, resolveAccessConfig } from './access-channels';

const cfg = (over: Partial<AccessRequestConfig> = {}): AccessRequestConfig => ({
  ...resolveAccessConfig(null),
  ...over,
});

describe('access channels', () => {
  it('builds the href of each channel type', () => {
    expect(channelHref({ type: 'form' })).toBe('/demande-acces?canal=form');
    expect(channelHref({ type: 'whatsapp', number: '+2250700000000', message: 'Bonjour & merci ? é' })).toBe(
      `https://wa.me/2250700000000?text=${encodeURIComponent('Bonjour & merci ? é')}`,
    );
    expect(channelHref({ type: 'phone', number: '+2250700000000' })).toBe('tel:+2250700000000');
    expect(channelHref({ type: 'email', address: 'a@b.ci', subject: "Demande d'accès" })).toBe(
      `mailto:a@b.ci?subject=${encodeURIComponent("Demande d'accès")}`,
    );
    expect(channelHref({ type: 'link', url: 'https://forms.gle/x', label: 'L' })).toBe('https://forms.gle/x');
  });

  it('labels channels', () => {
    expect(channelLabel({ type: 'form' })).toBe('Remplir le formulaire');
    expect(channelLabel({ type: 'whatsapp', number: '+1', message: '' })).toBe('Écrire sur WhatsApp');
    expect(channelLabel({ type: 'phone', number: '+1' })).toBe('Appeler');
    expect(channelLabel({ type: 'email', address: 'a@b.ci', subject: '' })).toBe('Envoyer un e-mail');
    expect(channelLabel({ type: 'link', url: 'https://x.ci', label: 'Formulaire en ligne' })).toBe('Formulaire en ligne');
  });

  it('entry href: closed, single channel, several channels', () => {
    expect(accessEntryHref(cfg({ enabled: false }))).toBeNull();
    expect(accessEntryHref(cfg())).toBe('/demande-acces');
    expect(accessEntryHref(cfg({ channels: [{ type: 'phone', number: '+2250700000000' }] }))).toBe('tel:+2250700000000');
    expect(
      accessEntryHref(cfg({ channels: [{ type: 'form' }, { type: 'phone', number: '+2250700000000' }] })),
    ).toBe('/demande-acces');
  });

  it('missing settings behave as defaults (form only)', () => {
    const d = resolveAccessConfig(null);
    expect(d.enabled).toBe(true);
    expect(d.button_label).toBe('Demander un accès');
    expect(d.channels).toEqual([{ type: 'form' }]);
    expect(resolveAccessConfig({} as never)).toEqual(d);
  });
});
