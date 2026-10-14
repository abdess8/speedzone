<script setup>
import { watch, onBeforeUnmount, onMounted } from 'vue';
import { formatNotificationDate, notificationIcon } from '@/composables/useNotifications';

/**
 * Notifications on a phone: a trigger in the topbar that opens a full-screen
 * list, the way the Facebook app (and this app's own global search) does it.
 *
 * The desktop dropdown cannot simply be stretched. Its panel sits under the
 * topbar, the bottom navigation covers the last rows, and a tap that should
 * open a record instead fights the page scrolling underneath. So the whole
 * screen becomes the inbox: back control on top, list filling the rest.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  notifications: { type: Array, required: true },
  unreadCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'open', 'mark-all-read']);

function setBodyLock(locked) {
  document.body.style.overflow = locked ? 'hidden' : '';
}

watch(() => props.open, setBodyLock);

function onKeydown(event) {
  if (event.key === 'Escape' && props.open) {
    emit('close');
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
  setBodyLock(false);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="search-view">
      <div
        v-if="open"
        class="search-view"
        role="dialog"
        aria-modal="true"
        :aria-label="$t('notifications.center.open')"
      >
        <div class="search-view-bar notif-view-bar">
          <div class="notif-view-top">
            <button
              type="button"
              class="search-view-back"
              :aria-label="$t('notifications.center.close')"
              @click="emit('close')"
            >
              <i class="ri-arrow-left-line"></i>
            </button>

            <h2 class="search-view-title notif-view-title mb-0">{{ $t('common.notifications') }}</h2>
            <span v-if="unreadCount > 0" class="badge bg-primary-subtle text-primary notif-view-count">
              {{ $t('common.new_count', { count: unreadCount }) }}
            </span>
          </div>

          <button
            v-if="unreadCount > 0"
            type="button"
            class="notif-view-mark"
            @click="emit('mark-all-read')"
          >
            <i class="ri-check-double-line" aria-hidden="true"></i>
            {{ $t('notifications.center.mark_all_read') }}
          </button>
        </div>

        <div class="search-view-body">
          <div v-if="loading && notifications.length === 0" aria-hidden="true">
            <div v-for="n in 5" :key="n" class="search-view-skeleton placeholder-glow">
              <span class="search-view-skeleton-figure placeholder"></span>
              <span class="d-block flex-grow-1">
                <span class="placeholder col-7 d-block mb-1"></span>
                <span class="placeholder col-4 d-block"></span>
              </span>
            </div>
          </div>

          <div v-else-if="notifications.length === 0" class="search-view-state">
            <span class="search-view-state-icon"><i class="ri-notification-3-line"></i></span>
            <p class="search-view-state-title">{{ $t('notifications.center.empty_title') }}</p>
            <p class="search-view-state-text">{{ $t('notifications.center.no_notifications_hint') }}</p>
          </div>

          <template v-else>
            <div
              v-for="notification in notifications"
              :key="notification.id"
              class="search-view-row"
              :class="{ 'is-unread': !notification.is_read }"
            >
              <button
                type="button"
                class="search-view-row-main"
                @click="emit('open', notification)"
              >
                <span
                  class="search-view-row-figure"
                  :class="notification.is_read ? 'text-muted' : 'text-primary'"
                >
                  <i :class="['bx', notificationIcon(notification.type)]"></i>
                </span>
                <span class="search-view-row-body">
                  <span class="search-view-row-title">{{ notification.title }}</span>
                  <span v-if="notification.message" class="search-view-row-subtitle">
                    {{ notification.message }}
                  </span>
                  <span class="search-view-row-meta">
                    {{ formatNotificationDate(notification.created_at) }}
                  </span>
                </span>
                <span v-if="!notification.is_read" class="search-view-unread" aria-hidden="true"></span>
                <i v-if="notification.url" class="ri-arrow-right-s-line search-view-row-chevron"></i>
              </button>
            </div>
          </template>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* The title and the "mark all read" action share one bar. On a phone the
   action is a whole sentence, so it takes the row under the title instead of
   squeezing "Notifications" down to a few letters. */
.notif-view-bar {
  flex-direction: column;
  align-items: stretch;
  gap: 0;
  padding-bottom: 0.375rem;
}

.notif-view-top {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 0.375rem;
}

.notif-view-title {
  flex: 0 1 auto;
  overflow: visible;
  text-overflow: unset;
}

.notif-view-count {
  flex: 0 0 auto;
}

.notif-view-mark {
  display: inline-flex;
  align-items: center;
  align-self: flex-end;
  gap: 0.35rem;
  margin: 0 0.375rem 0.125rem;
  padding: 0.375rem 0.5rem;
  border: 0;
  background: transparent;
  color: var(--vz-primary, #0d4a9d);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  -webkit-tap-highlight-color: transparent;
}

.notif-view-mark:active {
  opacity: 0.7;
}

@media (prefers-reduced-motion: reduce) {
  .notif-view-mark:active {
    opacity: 1;
  }
}
</style>
