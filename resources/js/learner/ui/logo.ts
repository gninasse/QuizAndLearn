/**
 * Logo Learn&Quiz — SVG inline (net à toutes les tailles, aucun asset externe).
 * Toque de diplômé blanche sur tuile dégradé bleu ciel → indigo.
 */

let uid = 0;

/** Tuile logo. `sizeClasses` ex. "w-9 h-9". */
export function logoMark(sizeClasses = 'w-9 h-9', rounded = 'rounded-xl'): string {
  const id = `lq-grad-${++uid}`;

  return `
    <svg viewBox="0 0 64 64" class="${sizeClasses} ${rounded} shrink-0" role="img" aria-label="Learn&Quiz">
      <defs>
        <linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#0ea5e9"/>
          <stop offset="55%" stop-color="#2563eb"/>
          <stop offset="100%" stop-color="#4f46e5"/>
        </linearGradient>
      </defs>
      <rect width="64" height="64" rx="14" fill="url(#${id})"/>
      <!-- Toque : plateau losange -->
      <path d="M32 16 L54 26 L32 36 L10 26 Z" fill="#ffffff"/>
      <!-- Calotte -->
      <path d="M20 31.5 V40 q0 5 12 5 t12 -5 V31.5 L32 37 Z" fill="#ffffff" opacity="0.92"/>
      <!-- Pompon -->
      <path d="M53 27 V38" stroke="#fbbf24" stroke-width="2.6" stroke-linecap="round"/>
      <circle cx="53" cy="40.5" r="3.2" fill="#fbbf24"/>
    </svg>`;
}

/** Marque complète : tuile + nom. */
export function logoWordmark(markSize = 'w-9 h-9', textClasses = 'text-lg'): string {
  return `
    <span class="inline-flex items-center gap-2.5">
      ${logoMark(markSize)}
      <span class="font-extrabold ${textClasses} tracking-tight">Learn<span class="text-sky-500">&</span>Quiz</span>
    </span>`;
}
