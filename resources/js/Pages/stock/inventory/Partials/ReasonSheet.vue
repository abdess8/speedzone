<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from '@/Components/BottomSheet.vue';

/**
 * Mandatory motive behind one stock discrepancy.
 *
 * A gap without a reason is indistinguishable from a typing mistake, and
 * shrinkage nobody categorises is shrinkage nobody can act on — so this sheet is
 * not skippable, and the two reasons a vendor will later be asked to justify
 * (theft, "other") additionally demand a written note.
 *
 * Rendered as a bottom sheet on a phone and as a centered card from tablets up,
 * which is the same component doing both jobs rather than two dialogs to keep
 * in sync.
 */

const props = defineProps({
  show: { type: Boolean, default: false },
  /** The line being explained, or null when the sheet is closed. */
  line: { type: Object, default: null },
  /** @type {Array<{value: string, label: string, color: string, icon: string, requires_note: boolean}>} */
  reasons: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'confirm']);

const { t } = useI18n();

const reason = ref('');
const note = ref('');
const submitted = ref(false);

const selectedReason = computed(() => props.reasons.find((item) => item.value === reason.value) ?? null);

const noteRequired = computed(() => selectedReason.value?.requires_note === true);

const noteMissing = computed(() => noteRequired.value && note.value.trim() === '');

const canConfirm = computed(() => reason.value !== '' && !noteMissing.value);

const delta = computed(() => props.line?.delta ?? 0);

const signedDelta = computed(() => (delta.value > 0 ? `+${delta.value}` : String(delta.value)));

// Reopening the sheet on the same line has to show what was already chosen:
// re-picking a reason from scratch on every visit is how a counter ends up
// mislabelling a line he only meant to reread.
watch(
  () => [props.show, props.line?.product_id],
  () => {
    if (props.show) {
      reason.value = props.line?.reason ?? '';
      note.value = props.line?.note ?? '';
      submitted.value = false;
    }
  },
  { immediate: true }
);

function confirm() {
  submitted.value = true;

  if (!canConfirm.value) {
    return;
  }

  emit('confirm', { reason: reason.value, note: note.value.trim() });
}
</script>

<template>
  <BottomSheet
    :show="show"
    size="lg"
    :title="$t('stock.inventory.reason_modal.title')"
    :subtitle="line ? $t('stock.inventory.reason_modal.subtitle', { product: line.name, delta: signedDelta }) : ''"
    @close="emit('close')"
  >
    <div class="d-flex justify-content-between align-items-center bg-light rounded p-3 mb-3">
      <div>
        <p class="text-muted fs-12 mb-0">{{ $t('stock.inventory.columns.recorded') }}</p>
        <span class="fw-semibold">{{ line?.recorded ?? 0 }}</span>
      </div>
      <i class="ri-arrow-right-line text-muted"></i>
      <div>
        <p class="text-muted fs-12 mb-0">{{ $t('stock.inventory.columns.counted') }}</p>
        <span class="fw-semibold">{{ line?.counted ?? 0 }}</span>
      </div>
      <div class="text-end">
        <p class="text-muted fs-12 mb-0">{{ $t('stock.inventory.columns.delta') }}</p>
        <span class="fw-bold fs-16" :class="delta < 0 ? 'text-danger' : 'text-success'">{{ signedDelta }}</span>
      </div>
    </div>

    <label class="form-label">
      {{ $t('stock.inventory.reason_modal.reason') }} <span class="text-danger">*</span>
    </label>
    <div class="vstack gap-2 mb-3">
      <div v-for="option in reasons" :key="option.value" class="form-check card-radio mb-0">
        <input
          :id="`reason-${option.value}`"
          v-model="reason"
          class="form-check-input"
          type="radio"
          :value="option.value"
        />
        <label class="form-check-label w-100 d-flex align-items-center gap-2 py-2" :for="`reason-${option.value}`">
          <span class="avatar-xs flex-shrink-0">
            <span class="avatar-title rounded" :class="`bg-${option.color}-subtle text-${option.color}`">
              <i :class="option.icon"></i>
            </span>
          </span>
          <span class="fs-14">{{ option.label }}</span>
          <span v-if="option.requires_note" class="badge bg-light text-body border ms-auto">
            {{ $t('stock.inventory.reason_modal.note') }}
          </span>
        </label>
      </div>
    </div>
    <p v-if="submitted && reason === ''" class="text-danger fs-13">
      {{ $t('stock.inventory.errors.reason_required') }}
    </p>

    <div>
      <label class="form-label" for="reason-note">
        {{ $t('stock.inventory.reason_modal.note') }}
        <span v-if="noteRequired" class="text-danger">*</span>
      </label>
      <textarea
        id="reason-note"
        v-model="note"
        class="form-control"
        :class="{ 'is-invalid': submitted && noteMissing }"
        rows="3"
        :placeholder="$t('stock.inventory.reason_modal.note_placeholder')"
      ></textarea>
      <div v-if="submitted && noteMissing" class="invalid-feedback d-block">
        {{ $t('stock.inventory.reason_modal.note_required') }}
      </div>
    </div>

    <template #footer>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light sheet-action" @click="emit('close')">
          {{ $t('common.cancel') }}
        </button>
        <button type="button" class="btn btn-primary flex-fill sheet-action" @click="confirm">
          <i class="ri-check-line align-bottom me-1"></i>
          {{ $t('stock.inventory.reason_modal.confirm') }}
        </button>
      </div>
    </template>
  </BottomSheet>
</template>

<style scoped>
.sheet-action {
  min-height: 48px;
}
</style>
