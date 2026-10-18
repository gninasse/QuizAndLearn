/**
 * Requêtes vidéo que le service worker ne doit JAMAIS mettre en cache
 * (module séparé de sw.ts pour être testable sous vitest).
 *
 * - Sources de lecture : `/api/learner/v1/videos/{ref}/playback`,
 *   `/api/showcase/v1/videos/{ref}/playback` (URL signées 15 min).
 * - Playlists HLS signées `/videos/{ref}/hls/*.m3u8` et segments `*.ts`.
 * - Lecteurs et flux YouTube / Vimeo : en ligne uniquement.
 */

const VIDEO_STREAM_HOSTS =
  /(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be|ytimg\.com|googlevideo\.com|vimeo\.com|vimeocdn\.com)$/i;
const HLS_PATH = /\.(m3u8|ts)$/i;
const PLAYBACK_PATH = /^\/api\/[^/]+\/v\d+\/videos\/[^/]+\/playback$/;
const HLS_ROUTE = /^\/videos\/[^/]+\/hls\//;

export function isVideoStreamRequest(url: URL): boolean {
  return (
    VIDEO_STREAM_HOSTS.test(url.hostname) ||
    HLS_PATH.test(url.pathname) ||
    PLAYBACK_PATH.test(url.pathname) ||
    HLS_ROUTE.test(url.pathname)
  );
}
