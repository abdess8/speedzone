<script setup>
import { computed, ref, watch } from 'vue';
import BottomSheet from '@/Components/BottomSheet.vue';

/**
 * Closing a delivery attempt, as a bottom sheet.
 *
 * The driver reports what happened rather than picking a status, because where
 * the order lands is not his call: a refusal or a cancellation takes the parcel
 * off the round, everything else leaves it out for delivery for another try.
 * The sheet says which of the two a reason will trigger *before* he confirms,
 * so the consequence is never a surprise.
 *
 * Two steps, never a nested dialog, and going back is always possible: a
 * mis-tap on "not delivered" is expensive to undo.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  order: { type: Object, default: null },
  outcomes: { type: Array, default: () => [] },
  failureReasons: { type: Array, default: () => [] },
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const FAILED = 'FAILED';

const selectedOutcome = ref(null);
const selectedReason = ref(null);
const note = ref('');
const attachment = ref(null);
const fileInput = ref(null);

const step = computed(() => (selectedOutcome.value?.value === FAILED ? 'reason' : 'outcome'));

const canSubmit = computed(() => {
  if (!selectedOutcome.value) {
    return false;
  }

  return selectedOutcome.value.value === FAILED ? Boolean(selectedReason.value) : true;
});

const attemptCount = computed(() => props.order?.failed_attempts_count ?? 0);

function reset() {
  selectedOutcome.value = null;
  selectedReason.value = null;
  note.value = '';
  clearAttachment();
}

watch(
  () => props.show,
  (visible) => {
    if (!visible) {
      reset();
    }
  }
);

function pickOutcome(outcome) {
  selectedOutcome.value = outcome;

  // A successful hand-over needs no extra input, so commit immediately and save
  // the driver a second tap.
  if (outcome.value !== FAILED) {
    submit();
  }
}

function back() {
  selectedOutcome.value = null;
  selectedReason.value = null;
}

function onFileChange(event) {
  attachment.value = event.target.files?.[0] ?? null;
}

function clearAttachment() {
  attachment.value = null;

  if (fileInput.value) {
    fileInput.value.value = '';
  }
}

function submit() {
  if (!canSubmit.value) {
    return;
  }

  emit('submit', {
    order: props.order,
    outcome: selectedOutcome.value.value,
    failure_reason: selectedReason.value?.value ?? null,
    note: note.value.trim() || null,
    attachment: attachment.value,
  });
}
</script>

<template>
  <BottomSheet
    :show="show"
    :dismissible="!processing"
    :title="step === 'reason' ? $t('orders.driver.reason_title') : $t('orders.delivery_outcome.title')"
    :subtitle="order?.tracking_number"
    @close="emit('close')"
  >
    <!-- Step 1 — delivered, or not -->
    <div v-if="step === 'outcome'" class="d-grid gap-2">
      <p class="text-muted text-center fs-13 mb-1">{{ $t('orders.delivery_outcome.question') }}</p>

      <div v-if="attemptCount > 0" class="alert alert-warning py-2 px-3 fs-13 mb-1" role="status">
        <i class="ri-history-line align-bottom me-1"></i>
        {{ $t('orders.delivery_outcome.attempts_badge', { count: attemptCount }) }}
      </div>

      <button
        v-for="outcome in outcomes"
        :key="outcome.value"
        type="button"
        class="btn text-start sheet-option"
        :class="`btn-soft-${outcome.color}`"
        :disabled="processing"
        @click="pickOutcome(outcome)"
      >
        <i :class="outcome.icon" class="fs-18 me-2 align-middle"></i>
        <span class="fw-medium">{{ outcome.label }}</span>
        <i class="ri-arrow-right-s-line float-end fs-18"></i>
      </button>
    </div>

    <!-- Step 2 — why, plus the optional evidence that goes with it -->
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
        <i v-if="selectedReason?.value === reason.value" class="ri-check-line float-end fs-18"></i>
      </button>

      <p v-if="selectedReason" class="text-muted fs-12 mb-0 mt-1">
        <i
          class="align-bottom me-1"
          :class="selectedReason.ends_delivery ? 'ri-arrow-go-back-line' : 'ri-refresh-line'"
        ></i>
        {{
          selectedReason.ends_delivery
            ? $t('orders.delivery_outcome.ends_delivery_hint')
            : $t('orders.delivery_outcome.retry_hint')
        }}
      </p>

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

      <div>
        <label class="form-label fs-13" for="delivery-attachment">
          {{ $t('orders.delivery_outcome.attachment_label') }}
        </label>
        <input
          id="delivery-attachment"
          ref="fileInput"
          type="file"
          class="form-control"
          accept="image/*,application/pdf"
          :disabled="processing"
          @change="onFileChange"
        />
        <div class="d-flex justify-content-between align-items-center mt-1">
          <small class="text-muted">{{ $t('orders.delivery_outcome.attachment_hint') }}</small>
          <button
            v-if="attachment"
            type="button"
            class="btn btn-link btn-sm p-0 text-danger"
            :disabled="processing"
            @click="clearAttachment"
          >
            {{ $t('orders.delivery_outcome.attachment_remove') }}
          </button>
        </div>
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
