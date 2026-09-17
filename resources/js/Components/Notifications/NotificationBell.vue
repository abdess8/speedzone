<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import NotificationDropdown from '@/Components/Notifications/NotificationDropdown.vue';
import NotificationOverlay from '@/Components/Notifications/NotificationOverlay.vue';
import { useNotifications } from '@/composables/useNotifications';
import { useMediaQuery } from '@/composables/useMediaQuery';

/**
 * Same split as global search: below `lg` the dropdown has no room, so the
 * bell opens a full-screen list instead.
 */
const isCompact = useMediaQuery('(max-width: 991.98px)');
const overlayOpen = ref(false);
const page = usePage();

const {
  notifications,
  unreadCount,
  loading,
  markAsRead,
  markAllAsRead,
} = useNotifications();

const handleMarkRead = async (notification) => {
  if (!notification.is_read) {
    await markAsRead(notification.id);
  }
};

async function openNotification(notification) {
  await handleMarkRead(notification);
  overlayOpen.value = false;

  if (notification.url) {
    router.visit(notification.url);
  }
}

watch(() => page.url, () => {
  overlayOpen.value = false;
});

function onKeydown(event) {
  if (event.key === 'Escape' && overlayOpen.value) {
    overlayOpen.value = false;
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <template v-if="isCompact">
    <button
      type="button"
      class="btn btn-icon btn-topbar btn-ghost-dark rounded-circle ms-1 header-item position-relative"
      :aria-label="$t('common.notifications')"
      @click="overlayOpen = true"
    >
      <i class="bx bx-bell fs-22"></i>
      <span
        v-if="unreadCount > 0"
        class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger"
      >
        <span class="notification-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        <span class="visually-hidden">{{ $t('common.unread_messages') }}</span>
      </span>
    </button>

    <NotificationOverlay
      :open="overlayOpen"
      :notifications="notifications"
      :unread-count="unreadCount"
      :loading="loading"
      @close="overlayOpen = false"
      @open="openNotification"
      @mark-all-read="markAllAsRead"
    />
  </template>

  <BDropdown
    v-else
    variant="ghost-dark"
    dropstart
    class="ms-1 dropdown"
    :offset="{ alignmentAxis: 57, crossAxis: 0, mainAxis: -42 }"
    toggle-class="btn-icon btn-topbar rounded-circle arrow-none"
    id="page-header-notifications-dropdown"
    menu-class="dropdown-menu-lg dropdown-menu-end p-0"
    auto-close="outside"
  >
    <template #button-content>
      <i class="bx bx-bell fs-22"></i>
      <span
        v-if="unreadCount > 0"
        class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger"
      >
        <span class="notification-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
        <span class="visually-hidden">{{ $t('common.unread_messages') }}</span>
      </span>
    </template>

    <NotificationDropdown
      :notifications="notifications"
      :unread-count="unreadCount"
      :loading="loading"
      @mark-read="handleMarkRead"
      @mark-all-read="markAllAsRead"
    />
  </BDropdown>
</template>
