import { onBeforeUnmount, ref } from 'vue';
import { playScanTone, primeScanSound } from '@/utils/scanSound';

/**
 * The short-lived "that one went in" signal drawn over a camera preview.
 *
 * A toast in the corner of the screen is the wrong place for it: the operator is
 * looking at the parcel and the lens, not at the top of the page. The flash lands
 * where the eyes already are, and {@see ScannerViewport} renders it, with a beep
 * for the times the phone is at arm's length.
 */

/** Long enough to register out of the corner of an eye, short enough to keep up. */
const FEEDBACK_MS = 900;

export function useScanFeedback() {
  const feedback = ref(null);

  let timer = null;
  let id = 0;

  /**
   * @param {'success'|'error'|'warning'} kind
   * @param {string} [label] Reference to show under the icon.
   */
  const flash = (kind, label = '') => {
    id += 1;
    // A new id on every call is what replays the animation when two parcels in a
    // row end the same way.
    feedback.value = { kind, label, id };

    playScanTone(kind);

    // Android buzzes, iOS ignores it, which is why the flash carries the message.
    if (kind === 'success') {
      navigator.vibrate?.(40);
    }

    clearTimeout(timer);
    timer = setTimeout(() => {
      feedback.value = null;
    }, FEEDBACK_MS);
  };

  onBeforeUnmount(() => clearTimeout(timer));

  return { feedback, flash, primeSound: primeScanSound };
}
