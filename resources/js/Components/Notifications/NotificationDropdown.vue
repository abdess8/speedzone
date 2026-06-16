<script setup>
import { Link } from '@inertiajs/vue3';
import simplebar from 'simplebar-vue';
import { formatNotificationDate, notificationIcon } from '@/composables/useNotifications';

defineProps({
    notifications: {
        type: Array,
        required: true,
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['mark-read', 'mark-all-read']);

const handleClick = (notification) => {
    emit('mark-read', notification);
};
</script>

<template>
  <div class="dropdown-head bg-primary bg-pattern rounded-top dropdown-menu-lg">
    <div class="p-3">
      <BRow class="align-items-center">
        <BCol>
          <h6 class="m-0 fs-16 fw-semibold text-white">{{ $t('common.notifications') }}</h6>
        </BCol>
        <BCol cols="auto" class="dropdown-tabs">
          <BBadge variant="light-subtle" class="bg-light-subtle text-body fs-13">
            {{ $t('common.new_count', { count: unreadCount }) }}
          </BBadge>
        </BCol>
      </BRow>
      <div v-if="unreadCount > 0" class="mt-2">
        <BButton size="sm" variant="light" class="w-100" @click.stop="emit('mark-all-read')">
          {{ $t('notifications.center.mark_all_read') }}
        </BButton>
      </div>
    </div>
  </div>

  <simplebar data-simplebar style="max-height: 360px" class="pe-2 py-2">
    <div v-if="loading" class="text-center py-4 text-muted">
      <div class="spinner-border spinner-border-sm" role="status"></div>
    </div>

    <div v-else-if="notifications.length === 0" class="text-center py-4 text-muted">
      <i class="bx bx-bell fs-1 d-block mb-2"></i>
      <p class="mb-0">{{ $t('notifications.center.no_notifications') }}</p>
    </div>

    <template v-else>
      <Link
        v-for="notification in notifications"
        :key="notification.id"
        :href="notification.url || '#'"
        class="dropdown-item notify-item py-2 px-3 d-flex gap-3 align-items-start"
        :class="{ 'bg-light-subtle': !notification.is_read }"
        @click="handleClick(notification)"
      >
        <div class="avatar-xs flex-shrink-0">
          <span
            class="avatar-title rounded-circle fs-16"
            :class="notification.is_read ? 'bg-light text-muted' : 'bg-primary-subtle text-primary'"
          >
            <i :class="['bx', notificationIcon(notification.type)]"></i>
          </span>
        </div>
        <div class="flex-grow-1 overflow-hidden">
          <h6 class="mb-1 fs-13" :class="{ 'fw-semibold': !notification.is_read }">
            {{ notification.title }}
          </h6>
          <p class="mb-1 fs-12 text-muted text-truncate">{{ notification.message }}</p>
          <small class="text-muted">{{ formatNotificationDate(notification.created_at) }}</small>
        </div>
        <span v-if="!notification.is_read" class="flex-shrink-0 mt-1">
          <span class="badge bg-primary rounded-circle p-1">&nbsp;</span>
        </span>
      </Link>
    </template>
  </simplebar>
</template>

<script>
import simplebar from 'simplebar-vue';

export default {
  components: { simplebar },
};
</script>
