import type { AccessChannel, AccessRequestConfig, ShowcaseSettings } from '../domain/types';

/** Page interne de demande d'accès. */
export const ACCESS_PAGE = '/demande-acces';

export const DEFAULT_ACCESS_CONFIG: AccessRequestConfig = {
  enabled: true,
  button_label: 'Demander un accès',
  title: 'Demander un accès',
  intro: 'Laissez-nous vos coordonnées : notre équipe vous recontacte pour ouvrir votre compte.',
  confirmation: 'Demande envoyée.',
  channels: [{ type: 'form' }],
  fields: { whatsapp: 'optional', email: 'optional', organization: 'optional', group: 'optional', message: 'optional' },
  custom_fields: [],
};

/** Réglages effectifs ; un ancien cache sans `access_request` se comporte comme les défauts. */
export function resolveAccessConfig(settings: Pick<ShowcaseSettings, 'access_request'> | null | undefined): AccessRequestConfig {
  const c = settings?.access_request;
  if (!c) return DEFAULT_ACCESS_CONFIG;
  // Défense en profondeur : un lien non https n'est jamais proposé.
  const usable = (c.channels ?? []).filter((ch) => ch.type !== 'link' || isSafeLink(ch.url));
  return {
    enabled: c.enabled !== false,
    button_label: c.button_label || DEFAULT_ACCESS_CONFIG.button_label,
    title: c.title || DEFAULT_ACCESS_CONFIG.title,
    intro: c.intro || DEFAULT_ACCESS_CONFIG.intro,
    confirmation: c.confirmation || DEFAULT_ACCESS_CONFIG.confirmation,
    channels: usable.length ? usable : DEFAULT_ACCESS_CONFIG.channels,
    fields: { ...DEFAULT_ACCESS_CONFIG.fields, ...c.fields },
    custom_fields: c.custom_fields ?? [],
  };
}

export const DEFAULT_LINK_LABEL = 'Ouvrir le lien';

function isSafeLink(url: unknown): boolean {
  return typeof url === 'string' && url.startsWith('https://');
}

/** Ajoute `?key=valeur` seulement si la valeur n'est pas vide (les vides arrivent parfois à null). */
function withParam(base: string, key: string, value: string | null | undefined): string {
  const v = value?.trim() ?? '';
  return v === '' ? base : `${base}?${key}=${encodeURIComponent(v)}`;
}

export function channelHref(c: AccessChannel): string {
  switch (c.type) {
    case 'form':
      return `${ACCESS_PAGE}?canal=form`;
    case 'whatsapp':
      return withParam(`https://wa.me/${c.number.replace(/\D/g, '')}`, 'text', c.message);
    case 'phone':
      return `tel:${c.number}`;
    case 'email':
      return withParam(`mailto:${c.address}`, 'subject', c.subject);
    case 'link':
      return c.url;
  }
}

export function channelLabel(c: AccessChannel): string {
  switch (c.type) {
    case 'form':
      return 'Remplir le formulaire';
    case 'whatsapp':
      return 'Écrire sur WhatsApp';
    case 'phone':
      return 'Appeler';
    case 'email':
      return 'Envoyer un e-mail';
    case 'link':
      return c.label?.trim() || DEFAULT_LINK_LABEL;
  }
}

/** Destination du bouton « demander un accès » ; null si les demandes sont fermées. */
export function accessEntryHref(cfg: AccessRequestConfig): string | null {
  if (!cfg.enabled) return null;
  const only = cfg.channels.length === 1 ? cfg.channels[0] : undefined;
  if (!only) return ACCESS_PAGE;
  return only.type === 'form' ? ACCESS_PAGE : channelHref(only);
}

/** Lien interne à la PWA (routeur, attribut `data-link`). */
export function isInternalHref(href: string): boolean {
  return href.startsWith('/');
}

/** Lien externe https : ouvert dans un nouvel onglet. */
export function opensNewTab(href: string): boolean {
  return href.startsWith('https://');
}
