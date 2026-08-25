import { preferencesStore } from '../stores';

/**
 * Sons de feedback générés en WebAudio — aucun fichier, fonctionne
 * hors-ligne, respecte la préférence « Sons » de l'apprenant.
 */

let context: AudioContext | null = null;

function ctx(): AudioContext | null {
  if (!preferencesStore.get().sound_enabled) return null;
  try {
    context ??= new AudioContext();
    if (context.state === 'suspended') void context.resume();
    return context;
  } catch {
    return null;
  }
}

function tone(
  audioContext: AudioContext,
  frequency: number,
  startAt: number,
  duration: number,
  type: OscillatorType = 'sine',
  gainValue = 0.12,
): void {
  const oscillator = audioContext.createOscillator();
  const gain = audioContext.createGain();
  oscillator.type = type;
  oscillator.frequency.value = frequency;
  gain.gain.setValueAtTime(gainValue, audioContext.currentTime + startAt);
  gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + startAt + duration);
  oscillator.connect(gain).connect(audioContext.destination);
  oscillator.start(audioContext.currentTime + startAt);
  oscillator.stop(audioContext.currentTime + startAt + duration);
}

/** Bonne réponse / évaluation positive : deux notes ascendantes. */
export function playCorrect(): void {
  const audioContext = ctx();
  if (!audioContext) return;
  tone(audioContext, 523.25, 0, 0.12); // do
  tone(audioContext, 783.99, 0.1, 0.18); // sol
}

/** Mauvaise réponse : note basse feutrée. */
export function playWrong(): void {
  const audioContext = ctx();
  if (!audioContext) return;
  tone(audioContext, 196, 0, 0.22, 'triangle', 0.1);
}

/** Réussite (quiz/examen) : arpège majeur. */
export function playSuccess(): void {
  const audioContext = ctx();
  if (!audioContext) return;
  [523.25, 659.25, 783.99, 1046.5].forEach((frequency, index) => {
    tone(audioContext, frequency, index * 0.09, 0.22);
  });
}

/** Badge débloqué : carillon. */
export function playBadge(): void {
  const audioContext = ctx();
  if (!audioContext) return;
  [783.99, 987.77, 1318.5].forEach((frequency, index) => {
    tone(audioContext, frequency, index * 0.12, 0.35, 'triangle', 0.1);
  });
}
