<script setup>
import NotificationDropdown from '@/Components/Notifications/NotificationDropdown.vue';
import { useNotifications } from '@/composables/useNotifications';

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
</script>

<template>
  <BDropdown
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
