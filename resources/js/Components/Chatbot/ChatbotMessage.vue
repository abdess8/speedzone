<script setup>
import { computed } from 'vue';
import ChatbotAction from '@/Components/Chatbot/ChatbotAction.vue';

const props = defineProps({
  message: { type: Object, required: true },
});

const isUser = computed(() => props.message.role === 'user');

const time = computed(() => {
  if (!props.message.at) return '';

  return new Date(props.message.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
  <div class="cb-message" :class="isUser ? 'cb-message-user' : 'cb-message-bot'">
    <div v-if="!isUser" class="cb-avatar" :class="{ 'cb-avatar-error': message.failed }">
      <i :class="message.failed ? 'ri-error-warning-line' : 'ri-robot-2-line'" />
    </div>

    <div class="cb-bubble-group">
      <!-- Model output is inserted as text, never as markup: it is the one part
           of this screen an outside system authored. -->
      <div v-if="message.content" class="cb-bubble" :class="{ 'cb-bubble-error': message.failed }">
        {{ message.content }}
      </div>

      <ChatbotAction
        v-for="(action, index) in message.actions"
        :key="`${message.id}-action-${index}`"
        :action="action"
      />

      <span v-if="time" class="cb-time">{{ time }}</span>
    </div>
  </div>
</template>

<style scoped>
.cb-message {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.cb-message-user {
  justify-content: flex-end;
}

.cb-avatar {
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

.cb-avatar-error {
  background-color: var(--vz-danger-bg-subtle, #fde8e4);
  color: var(--vz-danger, #f06548);
}

.cb-bubble-group {
  display: flex;
  max-width: 85%;
  flex-direction: column;
  align-items: flex-start;
}

.cb-message-user .cb-bubble-group {
  align-items: flex-end;
}

.cb-bubble {
  padding: 0.5rem 0.75rem;
  border-radius: 0.875rem 0.875rem 0.875rem 0.25rem;
  background-color: var(--vz-light, #f3f6f9);
  color: var(--vz-body-color, #212529);
  font-size: 0.8125rem;
  line-height: 1.5;
  /* Model answers arrive as plain text with real newlines. */
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.cb-message-user .cb-bubble {
  border-radius: 0.875rem 0.875rem 0.25rem;
  background-color: var(--vz-primary, #0d4a9d);
  color: #fff;
}

.cb-bubble-error {
  background-color: var(--vz-danger-bg-subtle, #fde8e4);
  color: var(--vz-danger-text-emphasis, #a13a29);
}

.cb-time {
  margin-top: 0.125rem;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.625rem;
}
</style>
