/**
 * A QR reader that works on every phone in the fleet.
 *
 * Chrome on Android exposes `BarcodeDetector`, which decodes in native code and
 * is the cheapest option by far. Safari and Chrome on iOS share the same WebKit
 * engine and ship neither, and that gap is what used to push warehouse staff
 * onto manual entry. When the native API is missing we grab a frame into a
 * canvas and run jsQR over the pixels instead.
 */

/**
 * Big enough to keep a label's modules apart, small enough that jsQR finishes
 * well inside one scan tick on the oldest handset in the fleet.
 */
const MAX_FULL_FRAME_WIDTH = 960;

/** Share of the frame kept by the close-up pass. */
const CENTER_CROP_FRACTION = 0.6;

/** A tick fast enough to feel instant, slow enough not to queue decodes. */
export const QR_DETECT_INTERVAL_MS = 250;

/**
 * 720p rather than the browser default, which is 480p on iOS: a label filmed at
 * arm's length covers a third of the frame, and at 480p that leaves too few
 * pixels per module for any decoder to recover.
 */
export const QR_CAMERA_CONSTRAINTS = {
  audio: false,
  video: {
    facingMode: { ideal: 'environment' },
    width: { ideal: 1280 },
    height: { ideal: 720 },
  },
};

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
 * Load jsQR whatever shape the bundler hands it over in.
 *
 * The package is UMD, so depending on how it is pre-bundled the callable ends up
 * on the namespace itself, on `default`, or on `default.default`. Picking the
 * wrong one throws on every frame, and a decoder that throws on every frame
 * looks exactly like a camera that sees nothing.
 *
 * @returns {Promise<Function>}
 */
async function loadJsQr() {
  // Loaded only on the browsers that need it: the decoder is ~40 KB, and the
  // Android handsets the drivers carry all have the native one.
  const module = await import('jsqr');

  const candidates = [module, module?.default, module?.default?.default];
  const decode = candidates.find((candidate) => typeof candidate === 'function');

  if (!decode) {
    throw new Error('jsQR decoder unavailable');
  }

  return decode;
}

/**
 * Ask the camera to keep focusing on its own.
 *
 * Android exposes this; WebKit does not and focuses continuously anyway, so a
 * refusal is not worth reporting.
 *
 * @param {MediaStream} stream
 */
async function preferContinuousFocus(stream) {
  const [track] = stream.getVideoTracks();
  const capabilities = track?.getCapabilities?.();

  if (!capabilities?.focusMode?.includes('continuous')) {
    return;
  }

  try {
    await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
  } catch {
    // Focus is a preference, not a requirement.
  }
}

/**
 * Resolve once the video reports a frame size, or give up and let the scan loop
 * pick the stream up on a later tick.
 *
 * @param {HTMLVideoElement} video
 * @returns {Promise<void>}
 */
function waitForFirstFrame(video, timeoutMs = 3000) {
  if (video.videoWidth) {
    return Promise.resolve();
  }

  return new Promise((resolve) => {
    const timer = setTimeout(resolve, timeoutMs);

    video.addEventListener(
      'loadedmetadata',
      () => {
        clearTimeout(timer);
        resolve();
      },
      { once: true }
    );
  });
}

/**
 * Restart playback whenever the element is paused from under us.
 *
 * WebKit pauses a preview when the surrounding list re-renders or the tab loses
 * focus, and a paused element keeps handing the decoder the very same stale
 * frame — a scanner that shows a picture and reads nothing.
 *
 * @param {HTMLVideoElement} video
 */
function keepPlaying(video) {
  if (video.dataset.qrResume) {
    return;
  }

  video.dataset.qrResume = '1';
  video.addEventListener('pause', () => {
    if (video.srcObject) {
      video.play().catch(() => {});
    }
  });
}

/**
 * Open the back camera and wire it into a preview element.
 *
 * The attributes are forced here rather than left to the template: WebKit
 * refuses to render a stream inline without them, and an element that never
 * renders hands the decoder blank frames while showing the operator a picture.
 *
 * @param {HTMLVideoElement|null} video
 * @returns {Promise<MediaStream>}
 */
export async function openQrCamera(video) {
  const stream = await navigator.mediaDevices.getUserMedia(QR_CAMERA_CONSTRAINTS);

  await preferContinuousFocus(stream);

  if (video) {
    video.setAttribute('playsinline', '');
    video.setAttribute('autoplay', '');
    video.muted = true;
    video.srcObject = stream;

    keepPlaying(video);

    // A rejected play() does not mean a dead stream: WebKit aborts the promise
    // when the element is re-rendered mid-start, and the frames still arrive.
    await video.play().catch(() => {});
    await waitForFirstFrame(video);
  }

  return stream;
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

  const jsQR = await loadJsQr();

  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d', { willReadFrequently: true });

  /** Copy a region of the frame into the scratch canvas and read it back. */
  const readRegion = (video, { x, y, width, height, maxWidth }) => {
    const scale = Math.min(1, maxWidth / width);

    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);

    context.drawImage(video, x, y, width, height, 0, 0, canvas.width, canvas.height);

    return context.getImageData(0, 0, canvas.width, canvas.height);
  };

  // setInterval does not wait for the previous tick, and a decode that runs long
  // on a cheap phone must not stack up behind itself.
  let decoding = false;

  return {
    native: false,
    detect: async (video) => {
      const width = video.videoWidth;
      const height = video.videoHeight;

      if (!width || !height || decoding) {
        return [];
      }

      // Two looks at the same frame: the whole picture, then the middle at full
      // sensor resolution. The close-up pass is what catches a code the operator
      // held too far back, since downscaling the whole frame is exactly what
      // destroys its smallest modules.
      const regions = [{ x: 0, y: 0, width, height, maxWidth: MAX_FULL_FRAME_WIDTH }];

      if (width > MAX_FULL_FRAME_WIDTH) {
        const side = Math.round(Math.min(width, height) * CENTER_CROP_FRACTION);

        regions.push({
          x: Math.round((width - side) / 2),
          y: Math.round((height - side) / 2),
          width: side,
          height: side,
          maxWidth: side,
        });
      }

      decoding = true;

      try {
        for (const region of regions) {
          const frame = readRegion(video, region);
          const code = jsQR(frame.data, frame.width, frame.height, {
            inversionAttempts: 'dontInvert',
          });

          if (code?.data) {
            return [{ rawValue: code.data }];
          }
        }

        return [];
      } finally {
        decoding = false;
      }
    },
  };
}
