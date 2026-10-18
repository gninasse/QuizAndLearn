/**
 * Notifications Web Push (opt-in depuis le profil).
 *
 * La permission du navigateur n'est demandée qu'au clic de l'apprenant
 * (`enablePush`), jamais au chargement. Aucune mise en cache : le service
 * worker affiche simplement les notifications reçues.
 */

import { api } from '../api/client';
import type { UserProfile } from '../domain/types';
import { isStandalone } from '../ui/install';

export type PushSupport = 'supported' | 'ios-install-needed' | 'unsupported';

/** Décode une clé publique VAPID (base64 url-safe, sans padding). */
export function urlBase64ToUint8Array(s: string): Uint8Array {
  const padded = s + '='.repeat((4 - (s.length % 4)) % 4);
  const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return out;
}

function isIos(): boolean {
  return (
    /iphone|ipad|ipod/i.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
  );
}

export function pushSupport(): PushSupport {
  const ok = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  if (ok) return 'supported';
  // iOS n'expose Web Push qu'aux applications ajoutées à l'écran d'accueil.
  if (isIos() && !isStandalone()) return 'ios-install-needed';
  return 'unsupported';
}

async function currentSubscription(): Promise<PushSubscription | null> {
  const registration = await navigator.serviceWorker.ready;
  return registration.pushManager.getSubscription();
}

function subscriptionBody(subscription: PushSubscription): { endpoint: string; keys: { p256dh: string; auth: string } } {
  const json = subscription.toJSON() as { endpoint?: string; keys?: { p256dh?: string; auth?: string } };
  return {
    endpoint: json.endpoint ?? subscription.endpoint,
    keys: { p256dh: json.keys?.p256dh ?? '', auth: json.keys?.auth ?? '' },
  };
}

const RESYNC_TIMEOUT_MS = 5000;

/**
 * Navigateur partagé : après une connexion (ou au démarrage avec une session),
 * l'abonnement déjà présent dans ce navigateur est renvoyé au serveur, qui le
 * réaffecte à l'apprenant connecté. Ainsi, après une session expirée sans
 * déconnexion explicite, l'apprenant précédent ne reçoit plus rien ici.
 *
 * Jamais en mode découverte, en consultation ni avec un mot de passe provisoire
 * (l'API le refuserait). À appeler sans attendre : ne lève jamais, ne bloque rien.
 */
export async function resyncPushSubscription(user: UserProfile | null, consultation: boolean): Promise<void> {
  try {
    if (!user || user.is_guest || user.must_change_password || consultation) return;
    if (pushSupport() !== 'supported' || Notification.permission !== 'granted') return;
    const subscription = await Promise.race([
      currentSubscription(),
      new Promise<null>((resolve) => setTimeout(() => resolve(null), RESYNC_TIMEOUT_MS)),
    ]);
    if (!subscription) return;
    await api.subscribePush(subscriptionBody(subscription));
  } catch {
    /* jamais bloquant : l'état serveur sera rattrapé à la prochaine connexion */
  }
}

export async function pushState(): Promise<'on' | 'off' | 'denied'> {
  if (pushSupport() !== 'supported') return 'off';
  if (Notification.permission === 'denied') return 'denied';
  try {
    return (await currentSubscription()) ? 'on' : 'off';
  } catch {
    return 'off';
  }
}

export async function enablePush(publicKey: string): Promise<'on' | 'denied' | 'error'> {
  if (pushSupport() !== 'supported') return 'error';
  try {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return 'denied';

    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey) as BufferSource,
    });
    try {
      await api.subscribePush(subscriptionBody(subscription));
    } catch (e) {
      // Le serveur ne connaît pas cet abonnement : ne pas le garder côté navigateur.
      await subscription.unsubscribe().catch(() => undefined);
      throw e;
    }
    return 'on';
  } catch {
    return 'error';
  }
}

/** Désabonne le navigateur et le serveur. Ne lève jamais. */
export async function disablePush(): Promise<void> {
  try {
    if (pushSupport() !== 'supported') return;
    const subscription = await currentSubscription();
    if (!subscription) return;
    const endpoint = subscription.endpoint;
    await subscription.unsubscribe().catch(() => undefined);
    await api.unsubscribePush(endpoint);
  } catch {
    /* jamais bloquant */
  }
}
