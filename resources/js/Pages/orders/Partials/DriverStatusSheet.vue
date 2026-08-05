<script setup>
import { computed, ref, watch } from 'vue';
import BottomSheet from '@/Components/BottomSheet.vue';

/**
 * Quick status change for the driver, presented as a bottom sheet.
 *
 * Two steps, never a nested dialog: pick the target status, then — only when the
 * status is a failed delivery — pick the reason. Going back is always possible
 * because a mis-tap on "not delivered" is expensive to undo.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  order: { type: Object, default: null },
  /** Transitions allowed from the order's current status, already permission-filtered. */
  transitions: { type: Array, default: () => [] },
  failureReasons: { type: Array, default: () => [] },
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const selectedStatus = ref(null);
const selectedReason = ref(null);
const note = ref('');

const step = computed(() => (selectedStatus.value?.requires_reason ? 'reason' : 'status'));

const canSubmit = computed(() => {
  if (!selectedStatus.value) {
    return false;
  }

  return selectedStatus.value.requires_reason ? Boolean(selectedReason.value) : true;
});

function reset() {
  selectedStatus.value = null;
  selectedReason.value = null;
  note.value = '';
}

watch(
  () => props.show,
  (visible) => {
    if (!visible) {
      reset();
    }
  }
);

function pickStatus(transition) {
  selectedStatus.value = transition;

  // A terminal-but-successful status needs no extra input, so commit immediately
  // and save the driver a second tap.
  if (!transition.requires_reason) {
    submit();
  }
}

function back() {
  selectedStatus.value = null;
  selectedReason.value = null;
}

function submit() {
  if (!canSubmit.value) {
    return;
  }

  emit('submit', {
    order: props.order,
    to_status: selectedStatus.value.value,
    failure_reason: selectedReason.value?.value ?? null,
    failure_note: note.value.trim() || null,
  });
}
</script>

<template>
  <BottomSheet
    :show="show"
    :dismissible="!processing"
    :title="step === 'reason' ? $t('orders.driver.reason_title') : $t('orders.driver.status_title')"
    :subtitle="order?.tracking_number"
    @close="emit('close')"
  >
    <!-- Step 1 — target status -->
    <div v-if="step === 'status'" class="d-grid gap-2">
      <button
        v-for="transition in transitions"
        :key="transition.value"
        type="button"
        class="btn text-start sheet-option"
        :class="`btn-soft-${transition.color}`"
        :disabled="processing"
        @click="pickStatus(transition)"
      >
        <i :class="transition.icon" class="fs-18 me-2 align-middle"></i>
        <span class="fw-medium">{{ transition.label }}</span>
        <i class="ri-arrow-right-s-line float-end fs-18"></i>
      </button>
    </div>

    <!-- Step 2 — failure reason, required before a non-delivery is recorded -->
    <div v-else class="d-grid gap-2">
      <button
        v-for="reason in failureReasons"
        :key="reason.value"
        type="button"
        class="btn text-start sheet-option"
        :class="selectedReason?.value === reason.value ? `btn-${reason.color}` : `btn-soft-${reason.color}`"
        :disabled="processing"
        @click="selectedReason = reason"
      >
        <i :class="reason.icon" class="fs-18 me-2 align-middle"></i>
        <span class="fw-medium">{{ reason.label }}</span>
        <i
          v-if="selectedReason?.value === reason.value"
          class="ri-check-line float-end fs-18"
        ></i>
      </button>

      <div class="mt-2">
        <label class="form-label fs-13">{{ $t('orders.driver.note_label') }}</label>
        <textarea
          v-model="note"
          class="form-control"
          rows="2"
          maxlength="500"
          :placeholder="$t('orders.driver.note_placeholder')"
          :disabled="processing"
        ></textarea>
      </div>
    </div>

    <template v-if="step === 'reason'" #footer>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light sheet-option" :disabled="processing" @click="back">
          <i class="ri-arrow-left-line align-bottom"></i>
        </button>
        <button
          type="button"
          class="btn btn-primary flex-fill sheet-option"
          :disabled="!canSubmit || processing"
          @click="submit"
        >
          <span v-if="processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
          {{ $t('orders.driver.confirm') }}
        </button>
      </div>
    </template>
  </BottomSheet>
</template>

<style scoped>
.sheet-option {
  min-height: 48px;
}
</style>
