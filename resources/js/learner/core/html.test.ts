import { describe, expect, it } from 'vitest';
import { escapeHtml, html, raw } from './html';

describe('html tagged template', () => {
  it('échappe toute interpolation par défaut', () => {
    const evil = '<script>alert("xss")</script>';
    expect(html`<p>${evil}</p>`).toBe(
      '<p>&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;</p>',
    );
  });

  it("n'échappe pas le HTML marqué raw()", () => {
    expect(html`<div>${raw('<b>gras</b>')}</div>`).toBe('<div><b>gras</b></div>');
  });

  it('aplati les tableaux (map de listes)', () => {
    const items = ['a', '<b>'];
    expect(html`<ul>${items.map((i) => html`<li>${i}</li>`).map(raw)}</ul>`).toBe(
      '<ul><li>a</li><li>&lt;b&gt;</li></ul>',
    );
  });

  it('ignore null/undefined/false, garde 0', () => {
    expect(html`<p>${null}${undefined}${false}${0}</p>`).toBe('<p>0</p>');
  });

  it('escapeHtml couvre les 5 caractères sensibles', () => {
    expect(escapeHtml(`&<>"'`)).toBe('&amp;&lt;&gt;&quot;&#39;');
  });
});
