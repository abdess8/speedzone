<script setup>
/**
 * Announcements that open over the interface on the first page of a session.
 *
 * They are shown one at a time, oldest queued behind newest, and each is
 * recorded as read the moment it is acknowledged so the next page load moves on
 * to the following one. Signing in again replays them, because the record lives
 * in the session.
 */
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const acknowledged = ref([]);

const queue = computed(() =>
  (page.props.announcements?.modals ?? []).filter((alert) => !acknowledged.value.includes(alert.id)),
);

const current = computed(() => queue.value[0] ?? null);

// The dialog itself is always mounted, so it is never asked to appear and open
// in the same tick. `shown` lags one step behind on the way out, which keeps the
// text on screen while the dialog fades rather than emptying it mid-animation.
const shown = ref(current.value);
const open = ref(Boolean(current.value));

watch(current, (alert) => {
  if (alert) {
    shown.value = alert;
  }

  open.value = Boolean(alert);
});

const acknowledge = () => {
  const alert = current.value;

  if (!alert) {
    return;
  }

  acknowledged.value.push(alert.id);
  open.value = false;

  router.post(
    route('alerts.dismiss', alert.id),
    {},
    { preserveScroll: true, preserveState: true, only: ['announcements'] },
  );
};
</script>

<template>
  <!-- The button is deliberately the only way out: with no header cross, no
       backdrop click and no escape key, acknowledging cannot be skipped, and
       the handler cannot fire twice and swallow the next queued announcement. -->
  <BModal v-model="open" centered hide-footer hide-header no-close-on-backdrop no-close-on-esc>
    <div v-if="shown" class="text-center">
      <div class="app-alert-modal__icon" :class="`text-${shown.type} bg-${shown.type}-subtle`">
        <i :class="shown.icon"></i>
      </div>

      <h5 class="mt-3 mb-2">{{ shown.title }}</h5>

      <!-- Sanitised server-side by App\Support\AlertHtml before it is stored. -->
      <div class="text-muted app-alert-modal__body" v-html="shown.message"></div>

      <BButton :variant="shown.type" class="mt-4 px-4" @click="acknowledge">
        {{ $t('alerts.actions.understood') }}
      </BButton>
    </div>
  </BModal>
</template>

<style lang="scss" scoped>
.app-alert-modal__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  font-size: 1.9rem;
}

.app-alert-modal__body {
  overflow-wrap: anywhere;

  :deep(p:last-child) {
    margin-bottom: 0;
  }

  :deep(ul),
  :deep(ol) {
    display: inline-block;
    text-align: start;
    margin-bottom: 0;
  }

  :deep(a) {
    text-decoration: underline;
  }
}
</style>
