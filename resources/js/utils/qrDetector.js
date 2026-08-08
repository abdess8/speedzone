/**
 * A QR reader that works on every phone in the fleet.
 *
 * Chrome on Android exposes `BarcodeDetector`, which decodes in native code and
 * is the cheapest option by far. Safari on iOS and Firefox do not, and that gap
 * is what used to push warehouse staff onto manual entry. When the native API is
 * missing we grab a frame into a canvas and run jsQR over the pixels instead.
 */

/** Wide enough for a label held at arm's length, small enough to decode fast. */
const MAX_FRAME_WIDTH = 640;

/**
 * Whether the browser has a native detector that actually handles QR codes.
 *
 * The constructor exists on some browsers that only ship linear formats, so the
 * format list has to be checked rather than assumed.
 *
 * @returns {Promise<boolean>}
 */
async function supportsNativeQr() {
  if (!('BarcodeDetector' in window)) {
    return false;
  }

  try {
    const formats = await window.BarcodeDetector.getSupportedFormats();

    return formats.includes('qr_code');
  } catch {
    return false;
  }
}

/**
 * Build a detector bound to its own scratch canvas.
 *
 * @returns {Promise<{detect: (video: HTMLVideoElement) => Promise<Array<{rawValue: string}>>, native: boolean}>}
 */
export async function createQrDetector() {
  if (await supportsNativeQr()) {
    const detector = new window.BarcodeDetector({ formats: ['qr_code'] });

    return {
      native: true,
      detect: (video) => detector.detect(video),
    };
  }

  // Loaded only on the browsers that need it: the decoder is ~40 KB, and the
  // Android handsets the drivers carry all have the native one.
  const { default: jsQR } = await import('jsqr');

  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d', { willReadFrequently: true });

  return {
    native: false,
    detect: async (video) => {
      const width = video.videoWidth;
      const height = video.videoHeight;

      if (!width || !height) {
        return [];
      }

      const scale = Math.min(1, MAX_FRAME_WIDTH / width);
      canvas.width = Math.round(width * scale);
      canvas.height = Math.round(height * scale);

      context.drawImage(video, 0, 0, canvas.width, canvas.height);

      const frame = context.getImageData(0, 0, canvas.width, canvas.height);
      const code = jsQR(frame.data, frame.width, frame.height, {
        inversionAttempts: 'dontInvert',
      });

      return code?.data ? [{ rawValue: code.data }] : [];
    },
  };
}
