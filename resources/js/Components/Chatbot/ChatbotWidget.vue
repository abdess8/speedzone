<script setup>
/**
 * Floating assistant, mounted once by the authenticated layout.
 *
 * It owns nothing but its own markup: the transcript lives in `useChatbot` so
 * an Inertia visit cannot wipe it, and the consequences of what the bot does
 * travel over `useChatbotBus` to whichever screen is displaying that data.
 */
import { computed, nextTick, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useChatbot } from '@/composables/useChatbot';
import ChatbotMessage from '@/Components/Chatbot/ChatbotMessage.vue';

const { t } = useI18n();
const page = usePage();

const { messages, sending, isOpen, unread, hasConversation, close, toggle, reset, send } = useChatbot();

const draft = ref('');
const scroller = ref(null);
const composer = ref(null);

/**
 * Rendered only for a signed-in user on an instance where the assistant is
 * actually configured — an unusable launcher is worse than no launcher.
 */
const isAvailable = computed(
  () => Boolean(page.props?.auth?.user) && page.props?.chatbot?.enabled === true,
);

const firstName = computed(() => (page.props?.auth?.user?.name ?? '').split(' ')[0]);

const suggestions = computed(() => [
  t('chatbot.suggestions.kpi'),
  t('chatbot.suggestions.search'),
  t('chatbot.suggestions.invoice'),
]);

const scrollToLatest = () => {
  nextTick(() => {
    const element = scroller.value;
    if (element) {
      element.scrollTop = element.scrollHeight;
    }
  });
};

watch(() => messages.value.length, scrollToLatest);
watch(sending, scrollToLatest);
watch(isOpen, (value) => {
  if (value) {
    scrollToLatest();
    nextTick(() => composer.value?.focus());
  }
});

const submit = () => {
  const text = draft.value.trim();

  if (text === '' || sending.value) {
    return;
  }

  draft.value = '';
  send(text);
};

const askSuggestion = (text) => {
  draft.value = text;
  submit();
};

/** Enter sends, Shift+Enter breaks the line. */
const onKeydown = (event) => {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    submit();
  }
};
</script>

<template>
  <div v-if="isAvailable" class="cb-widget">
    <transition name="cb-panel">
      <section v-if="isOpen" class="cb-panel" role="dialog" :aria-label="t('chatbot.title')">
        <header class="cb-header">
          <div class="cb-header-identity">
            <span class="cb-header-avatar"><i class="ri-robot-2-line" /></span>
            <div>
              <p class="cb-header-title">{{ t('chatbot.title') }}</p>
              <p class="cb-header-status">
                <span class="cb-dot" :class="{ 'cb-dot-busy': sending }" />
                {{ sending ? t('chatbot.thinking') : t('chatbot.ready') }}
              </p>
            </div>
          </div>

          <div class="cb-header-actions">
            <button
              v-if="hasConversation"
              type="button"
              class="cb-icon-btn"
              :title="t('chatbot.reset')"
              @click="reset"
            >
              <i class="ri-eraser-line" />
            </button>
            <button type="button" class="cb-icon-btn" :title="t('chatbot.close')" @click="close">
              <i class="ri-close-line" />
            </button>
          </div>
        </header>

        <div ref="scroller" class="cb-body">
          <div v-if="!hasConversation" class="cb-empty">
            <span class="cb-empty-icon"><i class="ri-sparkling-2-line" /></span>
            <p class="cb-empty-title">{{ t('chatbot.greeting', { name: firstName }) }}</p>
            <p class="cb-empty-text">{{ t('chatbot.intro') }}</p>

            <button
              v-for="suggestion in suggestions"
              :key="suggestion"
              type="button"
              class="cb-suggestion"
              @click="askSuggestion(suggestion)"
            >
              {{ suggestion }}
            </button>
          </div>

          <ChatbotMessage v-for="message in messages" :key="message.id" :message="message" />

          <div v-if="sending" class="cb-typing">
            <span class="cb-avatar-sm"><i class="ri-robot-2-line" /></span>
            <span class="cb-typing-bubble">
              <span class="cb-dot-typing" />
              <span class="cb-dot-typing" />
              <span class="cb-dot-typing" />
              <span class="cb-typing-text">{{ t('chatbot.thinking') }}</span>
            </span>
          </div>
        </div>

        <form class="cb-composer" @submit.prevent="submit">
          <textarea
            ref="composer"
            v-model="draft"
            class="cb-input"
            rows="1"
            :placeholder="t('chatbot.placeholder')"
            :disabled="sending"
            maxlength="2000"
            @keydown="onKeydown"
          />
          <button type="submit" class="cb-send" :disabled="sending || draft.trim() === ''">
            <i class="ri-send-plane-2-fill" />
          </button>
        </form>
      </section>
    </transition>

    <button
      type="button"
      class="cb-launcher"
      :class="{ 'cb-launcher-open': isOpen }"
      :aria-label="isOpen ? t('chatbot.close') : t('chatbot.open')"
      @click="toggle"
    >
      <i :class="isOpen ? 'ri-close-line' : 'ri-customer-service-2-line'" />
      <span v-if="unread > 0 && !isOpen" class="cb-launcher-badge">{{ unread }}</span>
    </button>
  </div>
</template>

<style scoped>
/* Above the sidebar and the bottom nav (1040), below Bootstrap modals (1055),
   so a confirmation dialog is never trapped behind the panel. */
.cb-widget {
  position: fixed;
  right: 1.5rem;
  bottom: 1.5rem;
  z-index: 1045;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.75rem;
}

.cb-launcher {
  position: relative;
  display: flex;
  width: 3.25rem;
  height: 3.25rem;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 999px;
  background-color: var(--vz-primary, #0d4a9d);
  box-shadow: 0 8px 24px rgba(13, 74, 157, 0.35);
  color: #fff;
  font-size: 1.5rem;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.cb-launcher:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(13, 74, 157, 0.42);
}

.cb-launcher-open {
  background-color: var(--vz-secondary-color, #878a99);
  box-shadow: 0 6px 18px rgba(56, 65, 74, 0.28);
}

.cb-launcher-badge {
  position: absolute;
  top: -0.125rem;
  right: -0.125rem;
  min-width: 1.125rem;
  padding: 0 0.25rem;
  border-radius: 999px;
  background-color: var(--vz-danger, #f06548);
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.125rem;
}

.cb-panel {
  display: flex;
  overflow: hidden;
  width: min(23.75rem, calc(100vw - 3rem));
  height: min(34rem, calc(100vh - 8rem));
  flex-direction: column;
  border: 1px solid var(--vz-border-color, #e9ebec);
  border-radius: 1rem;
  background-color: var(--vz-card-bg, #fff);
  box-shadow: 0 18px 50px rgba(13, 42, 77, 0.22);
}

.cb-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.75rem 0.875rem;
  border-bottom: 1px solid var(--vz-border-color, #e9ebec);
}

.cb-header-identity {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.cb-header-avatar {
  display: flex;
  width: 2.25rem;
  height: 2.25rem;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background-color: var(--vz-primary-bg-subtle, #e3ebf7);
  color: var(--vz-primary, #0d4a9d);
  font-size: 1.125rem;
}

.cb-header-title {
  margin: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 0.875rem;
  font-weight: 600;
}

.cb-header-status {
  display: flex;
  align-items: center;
  gap: 0.3125rem;
  margin: 0;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.6875rem;
}

.cb-dot {
  width: 0.4375rem;
  height: 0.4375rem;
  border-radius: 999px;
  background-color: var(--vz-success, #0ab39c);
}

.cb-dot-busy {
  background-color: var(--vz-warning, #f7b84b);
  animation: cb-pulse 1s ease-in-out infinite;
}

.cb-header-actions {
  display: flex;
  gap: 0.25rem;
}

.cb-icon-btn {
  display: flex;
  width: 1.875rem;
  height: 1.875rem;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 0.5rem;
  background: transparent;
  color: var(--vz-secondary-color, #878a99);
  font-size: 1rem;
}

.cb-icon-btn:hover {
  background-color: var(--vz-light, #f3f6f9);
  color: var(--vz-body-color, #212529);
}

.cb-body {
  display: flex;
  overflow-y: auto;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.875rem;
}

.cb-empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.375rem;
  padding: 0.5rem 0;
}

.cb-empty-icon {
  display: flex;
  width: 2.5rem;
  height: 2.5rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  background-color: var(--vz-primary-bg-subtle, #e3ebf7);
  color: var(--vz-primary, #0d4a9d);
  font-size: 1.25rem;
}

.cb-empty-title {
  margin: 0.25rem 0 0;
  color: var(--vz-heading-color, #495057);
  font-size: 0.9375rem;
  font-weight: 600;
}

.cb-empty-text {
  margin: 0 0 0.375rem;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.75rem;
  line-height: 1.5;
}

.cb-suggestion {
  width: 100%;
  padding: 0.5rem 0.625rem;
  border: 1px solid var(--vz-border-color, #e9ebec);
  border-radius: 0.625rem;
  background-color: transparent;
  color: var(--vz-body-color, #212529);
  font-size: 0.75rem;
  text-align: left;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.cb-suggestion:hover {
  border-color: var(--vz-primary, #0d4a9d);
  background-color: var(--vz-primary-bg-subtle, #e3ebf7);
}

.cb-typing {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.cb-avatar-sm {
  display: flex;
  width: 1.75rem;
  height: 1.75rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background-color: var(--vz-primary-bg-subtle, #e3ebf7);
  color: var(--vz-primary, #0d4a9d);
  font-size: 0.875rem;
}

.cb-typing-bubble {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.5rem 0.75rem;
  border-radius: 0.875rem 0.875rem 0.875rem 0.25rem;
  background-color: var(--vz-light, #f3f6f9);
}

.cb-dot-typing {
  width: 0.3125rem;
  height: 0.3125rem;
  border-radius: 999px;
  background-color: var(--vz-secondary-color, #878a99);
  animation: cb-pulse 1.1s ease-in-out infinite;
}

.cb-dot-typing:nth-child(2) { animation-delay: 0.16s; }
.cb-dot-typing:nth-child(3) { animation-delay: 0.32s; }

.cb-typing-text {
  margin-left: 0.25rem;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.75rem;
}

.cb-composer {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  padding: 0.625rem 0.75rem;
  border-top: 1px solid var(--vz-border-color, #e9ebec);
}

.cb-input {
  max-height: 6rem;
  flex: 1 1 auto;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--vz-border-color, #e9ebec);
  border-radius: 0.75rem;
  background-color: var(--vz-input-bg-custom);
  color: var(--vz-body-color, #212529);
  font-size: 0.8125rem;
  resize: none;
}

.cb-input:focus {
  border-color: var(--vz-primary, #0d4a9d);
  outline: none;
}

.cb-send {
  display: flex;
  width: 2.25rem;
  height: 2.25rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 0.75rem;
  background-color: var(--vz-primary, #0d4a9d);
  color: #fff;
  font-size: 1rem;
}

.cb-send:disabled {
  opacity: 0.45;
}

.cb-panel-enter-active,
.cb-panel-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.cb-panel-enter-from,
.cb-panel-leave-to {
  opacity: 0;
  transform: translateY(0.75rem) scale(0.98);
}

@keyframes cb-pulse {
  0%, 100% { opacity: 0.35; }
  50% { opacity: 1; }
}

/* The mobile bottom nav owns the bottom edge, so the launcher sits above it
   and the panel gives itself the whole width it can get. */
@media (max-width: 767.98px) {
  .cb-widget {
    right: 1rem;
    bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
  }

  .cb-panel {
    width: calc(100vw - 2rem);
    height: min(30rem, calc(100vh - 12rem));
  }
}

@media (prefers-reduced-motion: reduce) {
  .cb-launcher,
  .cb-panel-enter-active,
  .cb-panel-leave-active {
    transition: none;
  }

  .cb-dot-busy,
  .cb-dot-typing {
    animation: none;
  }
}
</style>
