/// <reference lib="WebWorker" />

/**
 * Service worker du volet apprenant (Workbox, mode injectManifest).
 *
 * - Precache des assets buildés (hash de révision injecté par vite-plugin-pwa).
 * - Navigations → réseau d'abord, repli sur le shell precaché (offline).
 * - /api/learner/* → network-only : les données vivent dans IndexedDB,
 *   jamais dans le cache HTTP (une seule source de vérité hors-ligne).
 * - /cores/* et /admin/* : jamais interceptés (back-office).
 * - Background Sync : réveille l'app (clients ouverts) pour rejouer l'outbox.
 */

import { clientsClaim } from 'workbox-core';
import { ExpirationPlugin } from 'workbox-expiration';
import { cleanupOutdatedCaches, createHandlerBoundToURL, precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkOnly } from 'workbox-strategies';

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

// Données : réseau uniquement — IndexedDB est la seule source hors-ligne.
registerRoute(({ url }) => url.pathname.startsWith('/api/'), new NetworkOnly());

// Assets statiques hors precache (avatars, médias, plugins legacy).
registerRoute(
  ({ url, request }) =>
    url.origin === self.location.origin &&
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
