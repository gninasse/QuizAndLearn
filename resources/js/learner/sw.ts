/// <reference lib="WebWorker" />

/**
 * Service worker du volet apprenant (Workbox, mode injectManifest).
 *
 * - Precache des assets buildés (hash de révision injecté par vite-plugin-pwa).
 * - Navigations → réseau d'abord, repli sur le shell precaché (offline).
 * - /api/learner/* → network-only : les données vivent dans IndexedDB,
 *   jamais dans le cache HTTP (une seule source de vérité hors-ligne).
 * - /cores/* et /admin/* : jamais interceptés (back-office).
 * - Vidéos : jamais en cache — ni les sources de lecture (/api/…/videos/…/
 *   playback), ni les playlists HLS signées (/videos/…/hls/…, *.m3u8), ni
 *   les segments (*.ts), ni les lecteurs YouTube / Vimeo (en ligne uniquement).
 * - Background Sync : réveille l'app (clients ouverts) pour rejouer l'outbox.
 */

import { clientsClaim } from 'workbox-core';
import { ExpirationPlugin } from 'workbox-expiration';
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkOnly } from 'workbox-strategies';

import { pickLearnerClient } from './sw-clients';
import { isVideoStreamRequest } from './sw-video';

declare const self: ServiceWorkerGlobalScope;

self.skipWaiting();
clientsClaim();

precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();

// Le shell (/) est ajouté au precache via additionalManifestEntries.
const shellHandler = createHandlerBoundToURL('/');

registerRoute(
  new NavigationRoute(shellHandler, {
    // Le back-office et les endpoints API ne passent jamais par le shell SPA.
    denylist: [/^\/cores\//, /^\/admin\//, /^\/api\//, /^\/login/, /^\/logout/],
  }),
);

// Vidéos (en ligne uniquement) : réseau seul, jamais de cache — sources de
// lecture et playlists HLS signées (15 min : un cache servirait des URL
// expirées). Les hôtes YouTube / Vimeo / segments S3 (autre origine) ne sont
// pas routés : le navigateur va au réseau, aucune règle de cache ne les vise.
registerRoute(
  ({ url }) => url.origin === self.location.origin && isVideoStreamRequest(url),
  new NetworkOnly(),
);

// Données : réseau uniquement — IndexedDB est la seule source hors-ligne.
registerRoute(({ url }) => url.pathname.startsWith('/api/'), new NetworkOnly());

// Assets statiques hors precache (avatars, médias, plugins legacy).
// Vidéos exclues : lues en streaming (requêtes Range), trop lourdes pour le
// cache hors-ligne — elles restent accessibles en ligne uniquement.
const VIDEO_PATH = /\.(mp4|m4v|webm|ogv|mov)$/i;

registerRoute(
  ({ url, request }) =>
    url.origin === self.location.origin &&
    request.destination !== 'video' &&
    !VIDEO_PATH.test(url.pathname) &&
    !isVideoStreamRequest(url) &&
    (request.destination === 'image' ||
      request.destination === 'font' ||
      url.pathname.startsWith('/plugins/') ||
      url.pathname.startsWith('/icons/') ||
      url.pathname.startsWith('/avatars/') ||
      url.pathname.startsWith('/storage/')),
  new CacheFirst({
    cacheName: 'learner-static-v1',
    plugins: [
      // Cache-first borné : 200 entrées max, 30 jours — les médias
      // pédagogiques restent disponibles hors-ligne sans gonfler à l'infini.
      new ExpirationPlugin({ maxEntries: 200, maxAgeSeconds: 30 * 24 * 3600, purgeOnQuotaError: true }),
    ],
  }),
);

// Background Sync : préviens les clients ouverts de rejouer l'outbox.
// (SyncEvent absent des libs TS standard — typé manuellement.)
interface BackgroundSyncEvent extends ExtendableEvent {
  readonly tag: string;
}

self.addEventListener('sync', (event) => {
  const syncEvent = event as BackgroundSyncEvent;
  if (syncEvent.tag === 'learner-outbox') {
    syncEvent.waitUntil(
      self.clients.matchAll({ type: 'window' }).then((clients) => {
        clients.forEach((client) => client.postMessage('outbox-sync'));
      }),
    );
  }
});

self.addEventListener('message', (event) => {
  if (event.data === 'skip-waiting') {
    void self.skipWaiting();
  }
});

// ---------------------------------------------------------------- Web Push
// Aucun cache : le service worker se contente d'afficher la notification.

interface PushPayload {
  title: string;
  body: string;
  url: string;
  tag?: string;
  icon: string;
}

function parsePushPayload(event: PushEvent): PushPayload {
  const fallback: PushPayload = {
    title: 'Learn&Quiz',
    body: 'Nouvelle notification',
    url: '/',
    icon: '/icons/icon-192x192.png',
  };
  try {
    const data = event.data?.json() as Partial<PushPayload> | undefined;
    if (!data || typeof data.title !== 'string' || data.title === '') return fallback;
    return {
      title: data.title,
      body: typeof data.body === 'string' ? data.body : '',
      url: typeof data.url === 'string' ? data.url : '/',
      tag: typeof data.tag === 'string' ? data.tag : undefined,
      icon: typeof data.icon === 'string' ? data.icon : fallback.icon,
    };
  } catch {
    return fallback;
  }
}

/** Seules les URL de même origine sont ouvertes ; sinon l'accueil. */
function safeSameOriginUrl(raw: unknown): string {
  try {
    const url = new URL(typeof raw === 'string' ? raw : '/', self.location.origin);
    return url.origin === self.location.origin ? url.href : new URL('/', self.location.origin).href;
  } catch {
    return new URL('/', self.location.origin).href;
  }
}

self.addEventListener('push', (event) => {
  const payload = parsePushPayload(event);
  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: payload.icon,
      badge: '/icons/icon-192x192.png',
      tag: payload.tag,
      data: { url: payload.url },
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = safeSameOriginUrl((event.notification.data as { url?: string } | null)?.url);

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async (clients) => {
      // Jamais un onglet back-office : seule une fenêtre apprenant est réutilisée.
      const client = pickLearnerClient(clients as readonly WindowClient[], self.location.origin);
      if (client) {
        try {
          await client.focus();
          await client.navigate(target);
          return;
        } catch {
          /* client non contrôlé : navigate() est refusé, repli sur openWindow */
        }
      }
      await self.clients.openWindow(target);
    }),
  );
});
