import { onBeforeUnmount, ref } from 'vue';

/**
 * Copy-to-clipboard with a short-lived "copied" acknowledgement.
 *
 * `navigator.clipboard` is unavailable on insecure origins, which includes the
 * plain-HTTP addresses the app is reached on in development, so a hidden
 * textarea backs it up rather than leaving the button silently dead.
 *
 * @param {number} feedbackMs how long `copied` stays true after a success
 */
export function useClipboard(feedbackMs = 1800) {
  const copied = ref(null);
  let timer = null;

  const acknowledge = (key) => {
    copied.value = key;
    clearTimeout(timer);
    timer = setTimeout(() => {
      copied.value = null;
    }, feedbackMs);
  };

  const legacyCopy = (text) => {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
      return document.execCommand('copy');
    } catch {
      return false;
    } finally {
      document.body.removeChild(textarea);
    }
  };

  /**
   * @param {string} text
   * @param {string} key identifies which button was pressed, so several copy
   *                     buttons can share one composable without all lighting up
   */
  const copy = async (text, key = 'default') => {
    if (navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(text);
        acknowledge(key);

        return true;
      } catch {
        // Permission denied or a non-secure context: fall through to the textarea.
      }
    }

    if (legacyCopy(text)) {
      acknowledge(key);

      return true;
    }

    return false;
  };

  onBeforeUnmount(() => clearTimeout(timer));

  return { copy, copied };
}
