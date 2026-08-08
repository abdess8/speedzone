import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Re-fetch the current page's data without reloading the document.
 *
 * `router.reload()` re-issues the current URL as an Inertia request: the
 * controller runs again and the new props are patched into the live component
 * tree. Filters, scroll position and open panels survive, and none of the
 * JS/CSS bundle is downloaded a second time — which is the difference between
 * this and the browser's own refresh button.
 */
export function usePageRefresh() {
  const refreshing = ref(false);

  function refresh() {
    // A double click would otherwise queue a second request behind the first
    // and leave the spinner running after the earlier one resolves.
    if (refreshing.value) {
      return;
    }

    refreshing.value = true;

    router.reload({
      preserveScroll: true,
      preserveState: true,
      onFinish: () => {
        refreshing.value = false;
      },
    });
  }

  return { refreshing, refresh };
}
