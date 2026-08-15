<script setup>
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Swal from 'sweetalert2';
import BottomSheet from '@/Components/BottomSheet.vue';
import TransitionBadge from './TransitionBadge.vue';
import { useQrBatchScanner } from '@/composables/useQrBatchScanner';

/**
 * Selection by camera, for the operator whose hands are on the parcels.
 *
 * The scanner adds nothing the manual board would have refused: every code goes
 * to `bulk-status/scan`, which runs the same perimeter and transition checks as
 * the list query, and a rejected parcel is kept on screen with the reason rather
 * than silently dropped — "why is this one not in my batch?" is the question the
 * driver will otherwise ask the dispatcher.
 *
 * The camera stays on between codes: closing and reopening it for every parcel
 * is unusable with a trolley in front of you.
 */
const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
  entityType: { type: String, required: true },
  toStatus: { type: String, required: true },
  target: { type: Object, default: null },
});

const emit = defineEmits(['close', 'select']);

const toast = (icon, title) =>
  Swal.fire({ toast: true, position: 'top-end', icon, title, timer: 2600, showConfirmButton: false });

const {
  manualInput,
  batch,
  validBatch,
  scanning,
  cameraError,
  validating,
  videoRef,
  startCamera,
  stopCamera,
  addToBatch,
  removeFromBatch,
  clear,
} = useQrBatchScanner({
  validate: async (reference) => {
    const { data } = await axios.post(route('bulk-status.scan'), {
      scan: reference,
      entity_type: props.entityType,
      to_status: props.toStatus,
    });

    if (data.valid && data.item) {
      // Added as it is resolved rather than on submit: the point of scanning is
      // to watch the counter climb while the trolley empties.
      emit('select', data.item);
    }

    return { valid: data.valid, message: data.message, row: data.item ?? {} };
  },
  unsupportedMessage: () => t('bulk_status.scan.camera_unsupported'),
  cameraErrorMessage: () => t('bulk_status.scan.camera_error'),
  unreachableMessage: () => t('bulk_status.scan.unreachable'),
});

const scan = async (rawValue) => {
  const result = await addToBatch(rawValue);

  if (!result.added) {
    if (manualInput.value) {
      toast('warning', t('bulk_status.scan.unreadable'));
      manualInput.value = '';
    }

    return;
  }

  result.valid
    ? toast('success', t('bulk_status.scan.added', { reference: batch.value.at(-1).tracking_number }))
    : toast('error', result.message || t('bulk_status.scan.unreadable'));
};

const close = () => {
  stopCamera();
  emit('close');
};

watch(
  () => props.show,
  (visible) => (visible ? startCamera() : stopCamera())
);

// A change of target invalidates everything already scanned against the old one.
watch(() => props.toStatus, clear);
</script>

<template>
  <BottomSheet :show="show" :title="$t('bulk_status.scan.title')" size="xl" @close="close">
    <template #header>
      <h5 class="sheet-title mb-1">{{ $t('bulk_status.scan.title') }}</h5>
      <TransitionBadge
        v-if="target"
        :from="{ label: $t('bulk_status.selection.source_filter'), color: 'secondary', icon: '' }"
        :to="target"
      />
    </template>

    <p class="text-muted small">{{ $t('bulk_status.scan.help') }}</p>

    <div class="row g-3">
      <BCol lg="5">
        <div class="ratio ratio-4x3 bg-light rounded border overflow-hidden">
          <video v-show="scanning" ref="videoRef" class="w-100 h-100 scanner-video" playsinline muted></video>
          <div
            v-if="!scanning"
            class="d-flex flex-column align-items-center justify-content-center text-muted p-3"
          >
            <i class="ri-qr-scan-2-line fs-1 mb-2"></i>
            <span class="text-center">{{ $t('bulk_status.scan.start') }}</span>
          </div>
        </div>

        <div v-if="cameraError" class="alert alert-warning mt-2 mb-0 py-2 small">{{ cameraError }}</div>

        <button
          v-if="!scanning"
          type="button"
          class="btn btn-sm btn-soft-primary mt-2 w-100"
          @click="startCamera"
        >
          <i class="ri-camera-line me-1"></i> {{ $t('bulk_status.scan.start') }}
        </button>
        <button v-else type="button" class="btn btn-sm btn-soft-secondary mt-2 w-100" @click="stopCamera">
          <i class="ri-camera-off-line me-1"></i> {{ $t('bulk_status.scan.stop') }}
        </button>
      </BCol>

      <BCol lg="7">
        <label class="form-label">{{ $t('bulk_status.scan.manual') }}</label>
        <div class="input-group mb-3">
          <input
            v-model="manualInput"
            type="text"
            class="form-control"
            inputmode="text"
            autocomplete="off"
            :disabled="validating"
            @keyup.enter="scan()"
          />
          <button type="button" class="btn btn-primary" :disabled="validating" @click="scan()">
            <span v-if="validating" class="spinner-border spinner-border-sm"></span>
            <span v-else>{{ $t('bulk_status.scan.add') }}</span>
          </button>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">
            {{ $t('bulk_status.selection.selected', { count: validBatch.length }) }}
          </h6>
          <button v-if="batch.length" type="button" class="btn btn-link btn-sm text-danger p-0" @click="clear">
            {{ $t('bulk_status.selection.clear') }}
          </button>
        </div>

        <div class="border rounded scanner-batch">
          <p v-if="batch.length === 0" class="text-muted text-center py-4 mb-0 small">
            {{ $t('bulk_status.scan.help') }}
          </p>

          <ul v-else class="list-group list-group-flush">
            <li
              v-for="row in batch"
              :key="row.tracking_number"
              class="list-group-item d-flex align-items-start justify-content-between gap-2"
            >
              <div class="min-w-0">
                <div class="fw-medium">{{ row.tracking_number }}</div>
                <TransitionBadge
                  v-if="row.valid && row.from_status"
                  :from="row.from_status"
                  :to="row.to_status"
                  size="sm"
                />
                <div v-else class="text-danger small">{{ row.message }}</div>
              </div>
              <button
                type="button"
                class="btn btn-sm btn-ghost-danger"
                :aria-label="$t('common.delete')"
                @click="removeFromBatch(row.tracking_number)"
              >
                <i class="ri-close-line"></i>
              </button>
            </li>
          </ul>
        </div>
      </BCol>
    </div>

    <template #footer>
      <button type="button" class="btn btn-primary w-100" @click="close">
        {{ $t('common.close') }}
      </button>
    </template>
  </BottomSheet>
</template>

<style scoped>
.scanner-video {
  object-fit: cover;
}

.scanner-batch {
  max-height: 18rem;
  overflow-y: auto;
}

.min-w-0 {
  min-width: 0;
}
</style>
