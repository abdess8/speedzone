import { ref } from 'vue';

/**
 * The scanner's beep, synthesised rather than loaded.
 *
 * A depot floor is loud and an operator's eyes are on the parcel, so the sound
 * often lands before the flash does. Two short tones through the Web Audio API
 * cost nothing to ship and, unlike an <audio> file, never miss because a request
 * is still in flight.
 */

/** Muting has to survive a page reload: it is a preference, not a session quirk. */
const STORAGE_KEY = 'scan-sound-muted';

export const scanSoundMuted = ref(localStorage.getItem(STORAGE_KEY) === '1');

export function toggleScanSound() {
  scanSoundMuted.value = !scanSoundMuted.value;
  localStorage.setItem(STORAGE_KEY, scanSoundMuted.value ? '1' : '0');
}

/**
 * A rising blip for an accepted parcel, a low buzz for a refusal, and a flat
 * middle note for "read, but not one of ours" — three sounds an operator can
 * tell apart without looking up.
 */
const TONES = {
  success: [
    { frequency: 990, duration: 0.07, at: 0 },
    { frequency: 1480, duration: 0.09, at: 0.07 },
  ],
  error: [
    { frequency: 240, duration: 0.13, at: 0 },
    { frequency: 180, duration: 0.18, at: 0.15 },
  ],
  warning: [{ frequency: 620, duration: 0.14, at: 0 }],
};

const PEAK_GAIN = 0.18;

let context = null;

function audioContext() {
  const Ctor = window.AudioContext || window.webkitAudioContext;

  if (!Ctor) {
    return null;
  }

  if (!context) {
    context = new Ctor();
  }

  if (context.state === 'suspended') {
    context.resume().catch(() => {});
  }

  return context;
}

/**
 * Wake the audio hardware while a tap is still in hand.
 *
 * WebKit only lets a context start from a user gesture, and the first scan
 * happens long after the one that opened the camera. Called from the button that
 * starts the camera, the beeps that follow are already allowed.
 */
export function primeScanSound() {
  audioContext();
}

/**
 * @param {'success'|'error'|'warning'} kind
 */
export function playScanTone(kind) {
  if (scanSoundMuted.value) {
    return;
  }

  const ctx = audioContext();
  const tones = TONES[kind];

  if (!ctx || !tones) {
    return;
  }

  tones.forEach(({ frequency, duration, at }) => {
    const start = ctx.currentTime + at;
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.value = frequency;

    // Ramped rather than switched on: a square edge on the gain is heard as a
    // click, which on a warehouse phone speaker is louder than the note itself.
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(PEAK_GAIN, start + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

    oscillator.connect(gain).connect(ctx.destination);
    oscillator.start(start);
    oscillator.stop(start + duration + 0.02);
  });
}
