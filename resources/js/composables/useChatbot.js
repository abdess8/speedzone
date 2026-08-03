import { computed, ref } from 'vue';
import { abortChatbotMessage, isCancelled, sendChatbotMessage } from '@/services/ChatbotService';
import { useChatbotBus } from '@/composables/useChatbotBus';

/**
 * Conversation state for the assistant widget.
 *
 * Module-level rather than per-component on purpose: pages import their own
 * layout, so an Inertia visit tears the widget down and builds a new one. State
 * living inside the component would drop the transcript every time the user
 * navigated. It is deliberately not persisted to storage — the transcript can
 * quote customer names and phone numbers, and it has no business outliving the
 * tab.
 */

let nextId = 0;
const makeId = () => `msg-${++nextId}`;

const messages = ref([]);
const sending = ref(false);
const error = ref(null);
const isOpen = ref(false);
/** Unread assistant answers while the panel is closed. */
const unread = ref(0);

export function useChatbot() {
  const { notifyDataChanged } = useChatbotBus();

  const hasConversation = computed(() => messages.value.length > 0);

  /** What the backend replays to the model: plain user/assistant turns only. */
  const transcript = () => messages.value
    .filter((message) => !message.failed)
    .map(({ role, content }) => ({ role, content }));

  const open = () => {
    isOpen.value = true;
    unread.value = 0;
  };

  const close = () => {
    isOpen.value = false;
  };

  const toggle = () => (isOpen.value ? close() : open());

  const reset = () => {
    abortChatbotMessage();
    messages.value = [];
    error.value = null;
    sending.value = false;
    unread.value = 0;
  };

  /**
   * Send a message and append the assistant's answer.
   *
   * @param {string} text
   */
  const send = async (text) => {
    const content = (text ?? '').trim();

    if (content === '' || sending.value) {
      return;
    }

    const history = transcript();

    messages.value.push({
      id: makeId(),
      role: 'user',
      content,
      actions: [],
      at: new Date().toISOString(),
    });

    sending.value = true;
    error.value = null;

    try {
      const reply = await sendChatbotMessage(content, history);

      messages.value.push({
        id: makeId(),
        role: 'assistant',
        content: reply?.message ?? '',
        actions: Array.isArray(reply?.actions) ? reply.actions : [],
        at: new Date().toISOString(),
      });

      if (!isOpen.value) {
        unread.value += 1;
      }

      // Tell the dashboard and any open table to re-read the data the bot
      // just changed, so the user never sees a stale row next to a bot
      // message saying it moved.
      if (reply?.refresh) {
        notifyDataChanged(reply.actions ?? []);
      }
    } catch (e) {
      // A superseded request is not a failure: the newer turn owns the state.
      if (isCancelled(e)) {
        return;
      }

      error.value = e?.response?.data?.message ?? e?.message ?? null;

      messages.value.push({
        id: makeId(),
        role: 'assistant',
        content: error.value ?? '',
        actions: [],
        at: new Date().toISOString(),
        failed: true,
      });
    } finally {
      sending.value = false;
    }
  };

  return {
    messages,
    sending,
    error,
    isOpen,
    unread,
    hasConversation,
    open,
    close,
    toggle,
    reset,
    send,
  };
}

export default useChatbot;
