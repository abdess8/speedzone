<script setup>
defineProps({
  history: { type: Array, default: () => [] },
});

const formatDate = (value) => (value ? new Date(value).toLocaleString() : "");
</script>

<template>
  <div class="profile-timeline">
    <div v-if="history.length === 0" class="text-muted text-center py-4">
      No status history yet.
    </div>

    <div
      v-for="(entry, index) in history"
      :key="entry.id"
      class="d-flex align-items-start mb-3 position-relative"
    >
      <div class="flex-shrink-0">
        <div
          class="avatar-xs d-flex align-items-center justify-content-center rounded-circle"
          :class="`bg-${entry.status_color}-subtle text-${entry.status_color}`"
        >
          <i :class="entry.status_icon"></i>
        </div>
        <div
          v-if="index !== history.length - 1"
          class="position-absolute bg-border"
          style="width: 2px; left: 11px; top: 28px; bottom: -8px; background: var(--vz-border-color)"
        ></div>
      </div>
      <div class="flex-grow-1 ms-3">
        <div class="d-flex justify-content-between align-items-center">
          <h6 class="mb-0">
            <span class="badge" :class="`bg-${entry.status_color}-subtle text-${entry.status_color}`">
              {{ entry.status_label }}
            </span>
          </h6>
          <small class="text-muted">{{ formatDate(entry.created_at) }}</small>
        </div>
        <p class="text-muted mb-0 mt-1" v-if="entry.comment">{{ entry.comment }}</p>
        <small class="text-muted" v-if="entry.user">by {{ entry.user.name }}</small>
      </div>
    </div>
  </div>
</template>
