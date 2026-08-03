import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * A back button that never leaves the application.
 *
 * `window.history.length` cannot answer "is there somewhere of ours to go back
 * to": it counts forward entries too and never shrinks, so on the first page of
 * a session it happily reports two and the button becomes a no-op. Stamping the
 * history entries themselves does not work either — Inertia rewrites
 * `history.state` wholesale before every visit, taking any key of ours with it.
 *
 * So the trail is kept here, as the list of pages walked through in this tab. A
 * visit to the page just behind us is read as a step back and shortens it;
 * anything else lengthens it. It is mirrored in session storage, per tab, so a
 * reload — a login, an F5 — does not strand the visitor on a page with no way
 * out but the sidebar.
 */

const STORAGE_KEY = 'app:navigation-trail';

/** @type {import('vue').Ref<string[]>} */
const trail = ref([]);

/** Origin and hash are noise here: two visits to the same page must compare equal. */
function normalise(url) {
  const parsed = new URL(url, window.location.origin);

  return parsed.pathname + parsed.search;
}

function persist() {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(trail.value));
  } catch {
    // Private browsing, quota, an embedded webview: the trail simply does not
    // outlive the page, which costs nothing but the button after a reload.
  }
}

function restore() {
  try {
    const stored = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');

    return Array.isArray(stored) ? stored.filter((entry) => typeof entry === 'string') : [];
  } catch {
    return [];
  }
}

function record(url) {
  const page = normalise(url);
  const previous = trail.value[trail.value.length - 2];

  if (page === previous) {
    trail.value.pop();
  } else if (page !== trail.value[trail.value.length - 1]) {
    trail.value.push(page);
  }

  persist();
}

/**
 * Called once at boot, before the first page renders.
 */
export function initBackNavigation() {
  const current = normalise(window.location.href);
  const stored = restore();

  // Where the reloaded page sits in the trail we left behind. Searching from
  // the end keeps the shortest way out when a page has been visited twice.
  const position = stored.lastIndexOf(current);

  trail.value = position === -1 ? [current] : stored.slice(0, position + 1);
  persist();

  router.on('navigate', (event) => record(event.detail.page.url));
}

export function useBackNavigation() {
  const canGoBack = computed(() => trail.value.length > 1);

  /**
   * `history.back()` rather than a fresh visit to the remembered URL: it
   * restores the scroll position and the state the visitor left behind, which
   * is the whole point of going back rather than navigating again.
   */
  function goBack() {
    if (canGoBack.value) {
      window.history.back();
    }
  }

  return { canGoBack, goBack };
}
