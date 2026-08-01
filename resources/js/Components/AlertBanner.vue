<script setup>
/**
 * Announcements pinned to the top of the content area.
 *
 * The list comes from the shared Inertia props, so it follows the reader from
 * page to page without any page having to ask for it. A banner the reader is
 * allowed to close disappears for the rest of their session; a permanent one
 * has no close button at all.
 */
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

/** Hidden the moment the reader clicks, rather than waiting for the round trip. */
const closing = ref([]);

const banners = computed(() =>
  (page.props.announcements?.banners ?? []).filter((alert) => !closing.value.includes(alert.id)),
);

const dismiss = (alert) => {
  closing.value.push(alert.id);

  // The server owns the session record, so a reload keeps the banner hidden.
  router.post(
    route('alerts.dismiss', alert.id),
    {},
    { preserveScroll: true, preserveState: true, only: ['announcements'] },
  );
};
</script>

<template>
  <div v-if="banners.length" class="app-alerts">
    <div
      v-for="alert in banners"
      :key="alert.id"
      class="alert app-alert d-flex align-items-start gap-2"
      :class="[`alert-${alert.type}`, { 'alert-dismissible': alert.is_dismissible }]"
      role="alert"
    >
      <i :class="alert.icon" class="app-alert__icon"></i>

      <div class="flex-grow-1 min-width-0">
        <h6 class="app-alert__title mb-1">{{ alert.title }}</h6>
        <!-- Sanitised server-side by App\Support\AlertHtml before it is stored. -->
        <div class="app-alert__body" v-html="alert.message"></div>
      </div>

      <button
        v-if="alert.is_dismissible"
        type="button"
        class="btn-close"
        :aria-label="$t('alerts.actions.dismiss')"
        @click="dismiss(alert)"
      ></button>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.app-alerts {
  margin-bottom: 1rem;
}

.app-alert {
  margin-bottom: 0.75rem;

  &:last-child {
    margin-bottom: 0;
  }
}

.app-alert__icon {
  font-size: 1.15rem;
  line-height: 1.4;
}

.app-alert__title {
  font-weight: 600;
}

.app-alert__body {
  // Author-supplied markup: keep it from breaking the layout it sits in.
  overflow-wrap: anywhere;

  :deep(p:last-child) {
    margin-bottom: 0;
  }

  :deep(ul),
  :deep(ol) {
    margin-bottom: 0;
    padding-left: 1.25rem;
  }

  :deep(a) {
    text-decoration: underline;
  }
}

.min-width-0 {
  min-width: 0;
}
</style>
