/**
 * Requêtes vidéo que le service worker ne doit JAMAIS mettre en cache
 * (module séparé de sw.ts pour être testable sous vitest).
 *
 * - Sources de lecture : `/api/learner/v1/videos/{ref}/playback`,
 *   `/api/showcase/v1/videos/{ref}/playback` (URL signées 15 min).
 * - Playlists HLS signées `/api/learner/v1/videos/{ref}/hls/*.m3u8` et
 *   segments `*.ts` (S3 / CDN) : couverts par l'extension (HLS_PATH).
 * - Lecteurs et flux YouTube / Vimeo : en ligne uniquement.
 */

const VIDEO_STREAM_HOSTS =
  /(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|ytimg\.com|googlevideo\.com|vimeo\.com|vimeocdn\.com)$/i;
const HLS_PATH = /\.(m3u8|ts)$/i;
const PLAYBACK_PATH = /^\/api\/[^/]+\/v\d+\/videos\/[^/]+\/playback$/;

export function isVideoStreamRequest(url: URL): boolean {
  return (
    VIDEO_STREAM_HOSTS.test(url.hostname) ||
    HLS_PATH.test(url.pathname) ||
    PLAYBACK_PATH.test(url.pathname)
  );
}

// ------------------------------------------------ Ressources hors ligne

/**
 * Fichiers de ressources gardés pour le hors-ligne (« Disponible hors ligne »).
 * L'URL de fichier est signée et expire après 15 min : elle n'est jamais une
 * clé de cache. Le fichier est rangé sous une clé stable par id.
 */
export const RESOURCE_CACHE = 'learner-resources-v1';

const RESOURCE_FILE_PATH = /^\/api\/[^/]+\/v\d+\/resources\/(\d+)\/file$/;

/** Clé de cache d'une ressource : `resource:{id}` (chemin même origine, jamais servi par le réseau). */
export function resourceCacheKey(id: number): string {
  return `/offline/resource:${id}`;
}

/** Id de ressource d'une URL de fichier signée, ou null. */
export function resourceFileId(url: URL): number | null {
  const match = url.pathname.match(RESOURCE_FILE_PATH);
  return match ? Number(match[1]) : null;
}
