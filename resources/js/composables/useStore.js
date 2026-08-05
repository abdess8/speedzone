import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Client-side view of the active shop.
 *
 * Mirrors the `store` prop shared by HandleInertiaRequests. Purely a rendering
 * concern: the data a page receives is already filtered server side by the
 * `store` global scope, so nothing here can widen what the user sees.
 *
 * Null for staff accounts and for vendors owning a single shop — the switcher
 * simply does not render in that case.
 */
export function useStore() {
  const page = usePage();

  const context = computed(() => page.props.store ?? null);
  const activeStore = computed(() => context.value?.active ?? null);
  const availableStores = computed(() => context.value?.available ?? []);

  /** More than one shop and no explicit pick yet: show the login picker. */
  const mustChooseStore = computed(() => context.value?.must_choose === true);

  /** A single shop needs no switcher, and no picker either. */
  const hasMultipleStores = computed(() => availableStores.value.length > 1);

  /**
   * Switch shop, then reload so every listing is re-queried under the new
   * boundary. A client-side filter would be a lie: the rows for the other store
   * were never sent to the browser.
   */
  function switchStore(storeId, options = {}) {
    if (storeId === activeStore.value?.id) {
      options.onFinish?.();

      return;
    }

    router.put(
      route('stores.active.update'),
      { store_id: storeId },
      {
        preserveScroll: true,
        onSuccess: () => router.reload(),
        ...options,
      }
    );
  }

  return {
    context,
    activeStore,
    availableStores,
    mustChooseStore,
    hasMultipleStores,
    switchStore,
  };
}
