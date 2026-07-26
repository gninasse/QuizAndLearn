/**
 * Tagged template literal `html` : toute interpolation est échappée par
 * défaut. Le HTML riche venant du serveur (contenu d'article, recto/verso
 * de carte) doit passer explicitement par `raw()`.
 *
 * Testé dans core/html.test.ts.
 */

const ESCAPE_MAP: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
};

export function escapeHtml(value: unknown): string {
  return String(value).replace(/[&<>"']/g, (ch) => ESCAPE_MAP[ch] ?? ch);
}

/** Marqueur de HTML de confiance (déjà échappé ou venant du serveur). */
export class RawHtml {
  constructor(readonly value: string) {}
}

/** Injecte du HTML de confiance sans échappement. À réserver au contenu serveur. */
export function raw(value: string | null | undefined): RawHtml {
  return new RawHtml(value ?? '');
}

type Interpolation =
  | string
  | number
  | boolean
  | null
  | undefined
  | RawHtml
  | Interpolation[];

function stringify(value: Interpolation): string {
  if (value === null || value === undefined || value === false) {
    return '';
  }
  if (value instanceof RawHtml) {
    return value.value;
  }
  if (Array.isArray(value)) {
    return value.map(stringify).join('');
  }
  if (value === true) {
    return '';
  }
  return escapeHtml(value);
}

export function html(strings: TemplateStringsArray, ...values: Interpolation[]): string {
  let out = '';
  strings.forEach((chunk, i) => {
    out += chunk;
    if (i < values.length) {
      out += stringify(values[i]);
    }
  });
  return out;
}
