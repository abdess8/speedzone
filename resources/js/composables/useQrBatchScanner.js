import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { QR_DETECT_INTERVAL_MS, createQrDetector, openQrCamera } from '@/utils/qrDetector';
import { useScanFeedback } from '@/composables/useScanFeedback';

/**
 * Camera and batch mechanics behind a "scan a pile of parcels, then act on them"
 * screen.
 *
 * Decoding goes through {@see createQrDetector}, which uses the browser's native
 * BarcodeDetector where it exists and falls back to jsQR everywhere else. Only a
 * device that refuses the camera outright reaches `cameraError`, so the caller
 * must still surface it and keep the manual input reachable.
 *
 * The caller owns what a code *means*: it passes a `validate` callback that
 * turns a tracking number into a row, and submits `validBatch` itself.
 */

/** Frames are cheap; a code that stays in view must not be added ten times. */
const REPEAT_GUARD_MS = 3000;

/**
 * A decoder that throws on every frame is indistinguishable from a camera that
 * sees nothing, so after this many failures in a row the operator is told.
 */
const MAX_DECODE_FAILURES = 6;

/**
 * Pull a reference out of a scanned string.
 *
 * Shipping labels encode the public tracking URL, while a hand-held wedge
 * scanner types the bare reference, so both shapes are accepted. Returns and
 * transfers carry the same shape under their own path, which is why all three
 * are read.
 *
 * @param {string} raw
 * @returns {string|null}
 */
export function parseTrackingNumber(raw) {
  const value = (raw || '').trim();

  if (!value) {
    return null;
  }

  const fromUrl = value.match(/\/(?:orders|returns|transfers)\/([A-Za-z0-9]+-[0-9]{4}-[0-9]+)/i);

  if (fromUrl) {
    return fromUrl[1];
  }

  const bare = value.match(/^([A-Za-z0-9]+-[0-9]{4}-[0-9]+)$/);

  return bare ? bare[1] : null;
}

/**
 * @param {Object} options
 * @param {(tracking: string) => Promise<{valid: boolean, message?: string, row?: Object}>} options.validate
 * @param {() => string} options.unsupportedMessage
 * @param {() => string} options.cameraErrorMessage
 * @param {() => string} options.unreachableMessage
 * @param {(raw: string) => void} [options.onUnknownCode] Called when the camera
 *   reads a code that is not one of ours, so the operator learns the scan
 *   worked and the sticker was wrong rather than seeing nothing happen.
 * @param {(tracking: string) => void} [options.onDuplicateCode] Called when the
 *   camera reads a code already in the batch. Without it, re-pointing the lens
 *   at a parcel just scanned looks like a camera that stopped working.
 */
export function useQrBatchScanner({
  validate,
  unsupportedMessage,
  cameraErrorMessage,
  unreachableMessage,
  onUnknownCode,
  onDuplicateCode,
}) {
  const manualInput = ref('');
  const batch = ref([]);
  const scanning = ref(false);
  const cameraError = ref('');
  const validating = ref(false);
  const videoRef = ref(null);
  const { feedback, flash, primeSound } = useScanFeedback();

  let stream = null;
  let detectInterval = null;
  let lastValue = '';
  let lastAt = 0;

  const validBatch = computed(() => batch.value.filter((row) => row.valid));

  const stopCamera = () => {
    scanning.value = false;

    if (detectInterval) {
      clearInterval(detectInterval);
      detectInterval = null;
    }

    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
  };

  /**
   * @returns {{added: boolean, valid: boolean, message: string}} so the caller
   *   can toast a rejection without re-reading the batch.
   */
  const addToBatch = async (rawValue = manualInput.value) => {
    const tracking = parseTrackingNumber(rawValue);

    if (!tracking) {
      flash('warning');

      return { added: false, valid: false, message: '', unknown: true };
    }

    if (batch.value.some((row) => row.tracking_number === tracking)) {
      flash('warning', tracking);

      return { added: false, valid: false, message: '', duplicate: true, tracking };
    }

    validating.value = true;

    try {
      const result = await validate(tracking);

      batch.value.push({
        tracking_number: tracking,
        valid: Boolean(result.valid),
        message: result.message ?? '',
        ...(result.row ?? {}),
      });

      flash(result.valid ? 'success' : 'error', tracking);

      return { added: true, valid: Boolean(result.valid), message: result.message ?? '' };
    } catch (error) {
      const message =
        error.response?.data?.errors?.tracking_number?.[0]
        || error.response?.data?.message
        || unreachableMessage();

      batch.value.push({ tracking_number: tracking, valid: false, message });
      flash('error', tracking);

      return { added: true, valid: false, message };
    } finally {
      validating.value = false;
      manualInput.value = '';
    }
  };

  const startCamera = async () => {
    cameraError.value = '';
    stopCamera();
    primeSound();

    // Only reached on a device with no camera API at all: a plain HTTP origin,
    // or a browser too old for getUserMedia.
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraError.value = unsupportedMessage();

      return;
    }

    try {
      await nextTick();
      stream = await openQrCamera(videoRef.value);

      const detector = await createQrDetector();
      scanning.value = true;
      lastValue = '';
      lastAt = 0;

      let failures = 0;

      detectInterval = setInterval(async () => {
        if (!videoRef.value || validating.value) {
          return;
        }

        let codes = [];

        try {
          codes = await detector.detect(videoRef.value);
          failures = 0;
        } catch {
          failures += 1;

          // One unreadable frame is normal; a decoder that never manages one is
          // a dead end, and leaving it spinning silently is what sends the
          // operator looking for a supervisor instead of the manual field.
          if (failures >= MAX_DECODE_FAILURES) {
            stopCamera();
            cameraError.value = cameraErrorMessage();
          }

          return;
        }

        if (codes.length === 0) {
          return;
        }

        const rawValue = codes[0].rawValue;
        const now = Date.now();

        if (rawValue === lastValue && now - lastAt < REPEAT_GUARD_MS) {
          return;
        }

        lastValue = rawValue;
        lastAt = now;

        const result = await addToBatch(rawValue);

        if (result.unknown) {
          onUnknownCode?.(rawValue);
        }

        if (result.duplicate) {
          onDuplicateCode?.(result.tracking);
        }
      }, QR_DETECT_INTERVAL_MS);
    } catch {
      cameraError.value = cameraErrorMessage();
    }
  };

  const removeFromBatch = (tracking) => {
    batch.value = batch.value.filter((row) => row.tracking_number !== tracking);
  };

  const clear = () => {
    batch.value = [];
  };

  onBeforeUnmount(stopCamera);

  return {
    manualInput,
    batch,
    validBatch,
    scanning,
    cameraError,
    validating,
    videoRef,
    feedback,
    startCamera,
    stopCamera,
    addToBatch,
    removeFromBatch,
    clear,
  };
}
