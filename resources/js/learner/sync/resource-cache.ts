/**
 * « Disponible hors ligne » des ressources fichier (PDF, audio, image).
 *
 * Le fichier est téléchargé une fois par son URL signée (15 min), puis rangé
 * dans Cache Storage sous la clé stable `resource:{id}` (voir sw-video.ts) :
 * jamais sous l'URL signée, qui expire. Le cache part avec la session
 * (clearLocalData).
 */

import { RESOURCE_CACHE, resourceCacheKey } from '../sw-video';

export type OfflineFileType = 'pdf' | 'audio' | 'image';

const TYPE_HEADER = 'X-Resource-Type';
const FILE_TYPES: readonly string[] = ['pdf', 'audio', 'image'];

function cacheAvailable(): boolean {
  return typeof caches !== 'undefined';
}

/** Télécharge le fichier (URL signée) et le garde sous `resource:{id}`. */
export async function saveResourceOffline(id: number, type: OfflineFileType, signedUrl: string): Promise<void> {
  if (!cacheAvailable()) throw new Error('Stockage hors ligne indisponible sur cet appareil.');
  const response = await fetch(signedUrl, { credentials: 'same-origin' });
  if (!response.ok) throw new Error(`Erreur ${response.status}`);
  const blob = await response.blob();
  const cache = await caches.open(RESOURCE_CACHE);
  await cache.put(
    resourceCacheKey(id),
    new Response(blob, {
      headers: {
        'Content-Type': blob.type || response.headers.get('Content-Type') || 'application/octet-stream',
        [TYPE_HEADER]: type,
      },
    }),
  );
}

/** Fichier gardé hors ligne, ou null. */
export async function readResourceOffline(id: number): Promise<{ type: OfflineFileType; blob: Blob } | null> {
  if (!cacheAvailable()) return null;
  try {
    const cache = await caches.open(RESOURCE_CACHE);
    const response = await cache.match(resourceCacheKey(id));
    const type = response?.headers.get(TYPE_HEADER) ?? '';
    if (!response || !FILE_TYPES.includes(type)) return null;
    return { type: type as OfflineFileType, blob: await response.blob() };
  } catch {
    return null;
  }
}

export async function hasResourceOffline(id: number): Promise<boolean> {
  if (!cacheAvailable()) return false;
  try {
    const cache = await caches.open(RESOURCE_CACHE);
    return (await cache.match(resourceCacheKey(id))) !== undefined;
  } catch {
    return false;
  }
}

export async function removeResourceOffline(id: number): Promise<void> {
  if (!cacheAvailable()) return;
  const cache = await caches.open(RESOURCE_CACHE);
  await cache.delete(resourceCacheKey(id));
}

const OFFLINE_KEY = /\/offline\/resource:(\d+)$/;

/**
 * Supprime les fichiers hors ligne des ressources qui ne sont plus
 * autorisées (« non récupérable » : la copie ne survit pas à l'accès).
 */
export async function pruneResourceCache(keepIds: readonly number[]): Promise<void> {
  if (!cacheAvailable()) return;
  try {
    const keep = new Set(keepIds);
    const cache = await caches.open(RESOURCE_CACHE);
    for (const request of await cache.keys()) {
      const id = Number(new URL(request.url, self.location.origin).pathname.match(OFFLINE_KEY)?.[1] ?? NaN);
      if (Number.isFinite(id) && !keep.has(id)) await cache.delete(request);
    }
  } catch {
    /* stockage indisponible : nouvel essai à la prochaine synchronisation */
  }
}

/** Vide tous les fichiers gardés hors ligne (déconnexion). */
export async function clearResourceCache(): Promise<void> {
  if (!cacheAvailable()) return;
  try {
    await caches.delete(RESOURCE_CACHE);
  } catch {
    /* stockage indisponible */
  }
}
