/**
 * Portage TypeScript de `parseVideoEmbed` (public/js/admin/video-embed.js,
 * script partagé des éditeurs). Même résultat, vérifié par
 * video-embed-parser.test.ts : {provider, id[, hash]} ou null.
 */

export interface VideoEmbed {
  provider: 'youtube' | 'vimeo';
  id: string;
  hash?: string;
}

const YT_ID = /^[A-Za-z0-9_-]{11}$/;
const VIMEO_ID = /^\d+$/;
const VIMEO_HASH = /^[a-f0-9]+$/;
const YT_HOSTS = [
  'youtube.com',
  'www.youtube.com',
  'm.youtube.com',
  'music.youtube.com',
  'youtube-nocookie.com',
  'www.youtube-nocookie.com',
];

export function parseVideoEmbed(input: unknown): VideoEmbed | null {
  if (typeof input !== 'string') return null;
  let raw = input.trim();
  if (!raw) return null;
  if (!/^https?:\/\//i.test(raw)) raw = `https://${raw}`;

  let url: URL;
  try {
    url = new URL(raw);
  } catch {
    return null;
  }
  if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;

  const host = url.hostname.toLowerCase();
  const parts = url.pathname.split('/').filter(Boolean);

  if (host === 'youtu.be' || host === 'www.youtu.be') {
    const id = parts[0];
    return id && YT_ID.test(id) ? { provider: 'youtube', id } : null;
  }
  if (YT_HOSTS.includes(host)) {
    let id: string | null | undefined = null;
    if (parts[0] === 'watch') id = url.searchParams.get('v');
    else if (parts.length >= 2 && ['shorts', 'embed', 'live', 'v'].includes(parts[0]!)) id = parts[1];
    return id && YT_ID.test(id) ? { provider: 'youtube', id } : null;
  }
  if (host === 'vimeo.com' || host === 'www.vimeo.com') {
    const id = parts[0];
    if (!id || !VIMEO_ID.test(id)) return null;
    const hash = parts[1] || url.searchParams.get('h');
    return hash && VIMEO_HASH.test(hash) ? { provider: 'vimeo', id, hash } : { provider: 'vimeo', id };
  }
  if (host === 'player.vimeo.com') {
    const id = parts[1];
    if (parts[0] !== 'video' || !id || !VIMEO_ID.test(id)) return null;
    const hash = url.searchParams.get('h');
    return hash && VIMEO_HASH.test(hash) ? { provider: 'vimeo', id, hash } : { provider: 'vimeo', id };
  }
  return null;
}
