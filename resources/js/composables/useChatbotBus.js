import { getCurrentScope, onScopeDispose, readonly, ref } from 'vue';

/**
 * The bridge between the assistant and the rest of the interface.
 *
 * When the bot changes something — a status, an invoice — the screens that
 * already display that data have to catch up, and they are mounted far away
 * from the widget (the dashboard, an order table, the layout). Passing an emit
 * down through the layout would couple every one of them to the widget, so the
 * signal travels through this module-level singleton instead: the widget
 * announces, the screens subscribe, neither knows the other exists.
 *
 * A composable rather than a Vuex module because there is no state to persist
 * or to time-travel — only a notification.
 */

/** Timestamp of the last mutation, for screens that prefer a watcher. */
const lastMutationAt = ref(0);

/** Actions carried by that mutation, for screens that want the detail. */
const lastActions = ref([]);

const subscribers = new Set();

export function useChatbotBus() {
  /**
   * Announce that the assistant mutated application data.
   *
   * @param {Array<{ type: string, data: object }>} actions
   */
  const notifyDataChanged = (actions = []) => {
    lastActions.value = actions;
    lastMutationAt.value = Date.now();

    subscribers.forEach((handler) => {
      // One screen throwing must not stop the others from refreshing.
      try {
        handler({ actions, at: lastMutationAt.value });
      } catch (error) {
        console.error('[chatbot] refresh handler failed', error);
      }
    });
  };

  /**
   * Refresh whenever the assistant changes something.
   *
   * Unsubscribes itself with the calling component, so a page that mounts and
   * unmounts repeatedly cannot leak stale handlers.
   *
   * @param {(payload: { actions: Array<object>, at: number }) => void} handler
   * @returns {() => void} unsubscribe
   */
  const onDataChanged = (handler) => {
    subscribers.add(handler);

    const unsubscribe = () => subscribers.delete(handler);

    if (getCurrentScope()) {
      onScopeDispose(unsubscribe);
    }

    return unsubscribe;
  };

  return {
    lastMutationAt: readonly(lastMutationAt),
    lastActions: readonly(lastActions),
    notifyDataChanged,
    onDataChanged,
  };
}

export default useChatbotBus;
