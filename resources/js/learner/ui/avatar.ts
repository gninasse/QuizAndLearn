import { escapeHtml } from '../core/html';

/**
 * Avatar 100 % local : photo si elle est hébergée sur notre origine,
 * sinon initiales sur un dégradé déterministe — aucune requête externe
 * (l'ancien repli ui-avatars.com cassait le mode hors-ligne).
 */

const GRADIENTS = [
  'from-sky-500 to-blue-600',
  'from-violet-500 to-purple-600',
  'from-emerald-500 to-teal-600',
  'from-amber-500 to-orange-600',
  'from-rose-500 to-pink-600',
  'from-indigo-500 to-blue-700',
];

function initials(fullName: string): string {
  const parts = fullName.trim().split(/\s+/).filter(Boolean);
  const first = parts[0]?.[0] ?? '?';
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';
  return (first + last).toUpperCase();
}

function gradientFor(seed: string): string {
  let hash = 0;
  for (const char of seed) {
    hash = (hash * 31 + char.charCodeAt(0)) | 0;
  }
  return GRADIENTS[Math.abs(hash) % GRADIENTS.length] as string;
}

function isLocalUrl(url: string): boolean {
  return url.startsWith('/') || url.startsWith(window.location.origin);
}

/**
 * HTML d'un avatar. `sizeClasses` pilote la taille (ex. "w-9 h-9 text-xs").
 */
export function avatarHtml(
  user: { full_name: string; avatar_url: string },
  sizeClasses = 'w-9 h-9 text-xs',
  extraClasses = '',
): string {
  if (user.avatar_url && isLocalUrl(user.avatar_url)) {
    return `<img src="${escapeHtml(user.avatar_url)}" alt="" class="${sizeClasses} rounded-full object-cover ${extraClasses}" />`;
  }

  return `<span aria-hidden="true"
      class="${sizeClasses} rounded-full bg-gradient-to-br ${gradientFor(user.full_name)} text-white font-bold flex items-center justify-center select-none ${extraClasses}">${escapeHtml(initials(user.full_name))}</span>`;
}
