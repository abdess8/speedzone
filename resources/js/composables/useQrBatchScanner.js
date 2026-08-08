import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import { createQrDetector } from '@/utils/qrDetector';

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

const DETECT_INTERVAL_MS = 500;

/**
 * Pull a reference out of a scanned string.
 *
 * Shipping labels encode the public tracking URL, while a hand-held wedge
 * scanner types the bare reference, so both shapes are accepted. Return labels
 * carry the same shape under `/returns/`, which is why both paths are read.
 *
 * @param {string} raw
 * @returns {string|null}
 */
export function parseTrackingNumber(raw) {
  const value = (raw || '').trim();

  if (!value) {
    return null;
  }

  const fromUrl = value.match(/\/(?:orders|returns)\/([A-Za-z0-9]+-[0-9]{4}-[0-9]+)/i);

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
 */
export function useQrBatchScanner({
  validate,
  unsupportedMessage,
  cameraErrorMessage,
  unreachableMessage,
}) {
  const manualInput = ref('');
  const batch = ref([]);
  const scanning = ref(false);
  const cameraError = ref('');
  const validating = ref(false);
  const videoRef = ref(null);

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
      return { added: false, valid: false, message: '' };
    }

    if (batch.value.some((row) => row.tracking_number === tracking)) {
      return { added: false, valid: false, message: '' };
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

      return { added: true, valid: Boolean(result.valid), message: result.message ?? '' };
    } catch (error) {
      const message =
        error.response?.data?.errors?.tracking_number?.[0]
        || error.response?.data?.message
        || unreachableMessage();

      batch.value.push({ tracking_number: tracking, valid: false, message });

      return { added: true, valid: false, message };
    } finally {
      validating.value = false;
      manualInput.value = '';
    }
  };

  const startCamera = async () => {
    cameraError.value = '';
    stopCamera();

    // Only reached on a device with no camera API at all: a plain HTTP origin,
    // or a browser too old for getUserMedia.
    if (!navigator.mediaDevices?.getUserMedia) {
      cameraError.value = unsupportedMessage();

      return;
    }

    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
      await nextTick();

      if (videoRef.value) {
        videoRef.value.srcObject = stream;
        await videoRef.value.play();
      }

      const detector = await createQrDetector();
      scanning.value = true;
      lastValue = '';
      lastAt = 0;

      detectInterval = setInterval(async () => {
        if (!videoRef.value || validating.value) {
          return;
        }

        try {
          const codes = await detector.detect(videoRef.value);

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
          await addToBatch(rawValue);
        } catch {
          // A frame the detector cannot read is not an error worth reporting.
        }
      }, DETECT_INTERVAL_MS);
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
    startCamera,
    stopCamera,
    addToBatch,
    removeFromBatch,
    clear,
  };
}
