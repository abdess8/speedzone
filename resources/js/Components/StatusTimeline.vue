<script setup>
import { useI18n } from "vue-i18n";
import UserAvatar from "@/Components/UserAvatar.vue";
import EntityLink from "@/Components/EntityLink.vue";

const { t } = useI18n();

defineProps({
  history: { type: Array, default: () => [] },
  emptyKey: { type: String, default: "orders.timeline.empty" },
});

const formatDate = (value) => (value ? new Date(value).toLocaleString() : "");
</script>

<template>
  <div class="profile-timeline">
    <div v-if="history.length === 0" class="text-muted text-center py-4">
      {{ $t(emptyKey) }}
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

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
          <span v-if="entry.is_system" class="badge bg-secondary-subtle text-secondary">
            <i class="ri-settings-3-line me-1"></i>{{ $t("orders.timeline.system") }}
          </span>
          <UserAvatar
            v-else-if="entry.user"
            :user="entry.user"
            :size="24"
            clickable
            show-name
            show-role
          />

          <EntityLink
            v-if="entry.pickup_request"
            type="pickup"
            :entity="entry.pickup_request"
            size="sm"
          />
          <EntityLink
            v-if="entry.transfer"
            type="transfer"
            :entity="entry.transfer"
            size="sm"
          />
        </div>
      </div>
    </div>
  </div>
</template>
