import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Reactive `window.matchMedia` result.
 *
 * A CSS-only split (`d-md-none` / `d-none d-md-block`) still mounts both trees,
 * which on the dashboard means a phone paying for eight hidden ApexCharts it
 * will never show. Rendering off a reactive query lets `v-if` drop the branch
 * that does not apply.
 *
 * @param {string} query a media query, e.g. `(max-width: 767.98px)`
 * @returns {import('vue').Ref<boolean>}
 */
export function useMediaQuery(query) {
  const supported = typeof window !== 'undefined' && typeof window.matchMedia === 'function';
  const matches = ref(supported ? window.matchMedia(query).matches : false);

  let mediaQueryList = null;
  const sync = (event) => {
    matches.value = event.matches;
  };

  onMounted(() => {
    if (!supported) {
      return;
    }

    mediaQueryList = window.matchMedia(query);
    // Re-read on mount: the viewport can have changed between setup and paint.
    matches.value = mediaQueryList.matches;
    mediaQueryList.addEventListener('change', sync);
  });

  onBeforeUnmount(() => {
    mediaQueryList?.removeEventListener('change', sync);
  });

  return matches;
}

/**
 * True below Bootstrap's `md` breakpoint — the same boundary at which the
 * sidebar is already off screen and `BottomNav` takes over, so "mobile" means
 * one thing across the app.
 *
 * @returns {import('vue').Ref<boolean>}
 */
export function useIsMobile() {
  return useMediaQuery('(max-width: 767.98px)');
}
