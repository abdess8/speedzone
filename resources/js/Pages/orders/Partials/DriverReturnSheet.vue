<script setup>
import { ref, watch } from 'vue';
import BottomSheet from '@/Components/BottomSheet.vue';

/**
 * Return initiation for the driver, presented as a bottom sheet.
 *
 * A driver who could not hand over a parcel has to open a return without ever
 * reaching the order detail screen, so the reason picker lives next to the card
 * that triggered it.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  order: { type: Object, default: null },
  reasons: { type: Array, default: () => [] },
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);

const selectedReason = ref(null);
const notes = ref('');

watch(
  () => props.show,
  (visible) => {
    if (!visible) {
      selectedReason.value = null;
      notes.value = '';
    }
  }
);

function submit() {
  if (!selectedReason.value) {
    return;
  }

  emit('submit', {
    order: props.order,
    reason: selectedReason.value.value,
    return_notes: notes.value.trim() || null,
  });
}
</script>

<template>
  <BottomSheet
    :show="show"
    :dismissible="!processing"
    :title="$t('orders.driver.return_title')"
    :subtitle="order?.tracking_number"
    @close="emit('close')"
  >
    <div class="d-grid gap-2">
      <button
        v-for="reason in reasons"
        :key="reason.value"
        type="button"
        class="btn text-start sheet-option"
        :class="selectedReason?.value === reason.value ? 'btn-warning' : 'btn-soft-warning'"
        :disabled="processing"
        @click="selectedReason = reason"
      >
        <span class="fw-medium">{{ reason.label }}</span>
        <i v-if="selectedReason?.value === reason.value" class="ri-check-line float-end fs-18"></i>
      </button>

      <div class="mt-2">
        <label class="form-label fs-13">{{ $t('orders.driver.note_label') }}</label>
        <textarea
          v-model="notes"
          class="form-control"
          rows="2"
          maxlength="2000"
          :placeholder="$t('orders.driver.note_placeholder')"
          :disabled="processing"
        ></textarea>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="btn btn-primary w-100 sheet-option"
        :disabled="!selectedReason || processing"
        @click="submit"
      >
        <span v-if="processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
        {{ $t('orders.driver.confirm') }}
      </button>
    </template>
  </BottomSheet>
</template>

<style scoped>
.sheet-option {
  min-height: 48px;
}
</style>
