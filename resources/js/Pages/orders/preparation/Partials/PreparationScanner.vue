<script setup>
import { watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Swal from 'sweetalert2';
import BottomSheet from '@/Components/BottomSheet.vue';
import { useQrBatchScanner } from '@/composables/useQrBatchScanner';

/**
 * Sweep the labels of a packed trolley, then mark the lot prepared.
 *
 * The camera is a convenience, not the contract: the manual field accepts both a
 * hand-held wedge scanner and a typed reference, so the bench keeps working when
 * the browser has no barcode support.
 */

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

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
  validate: async (tracking) => {
    const { data } = await axios.post(route('orders.preparation.scan'), { tracking_number: tracking });

    return { valid: data.valid, message: data.message, row: data.order ?? {} };
  },
  unsupportedMessage: () => t('preparation.scanner.camera_unsupported'),
  cameraErrorMessage: () => t('preparation.scanner.camera_error'),
  unreachableMessage: () => t('preparation.scanner.unable_validate'),
});

const toast = (icon, title) =>
  Swal.fire({ toast: true, position: 'top-end', icon, title, timer: 2500, showConfirmButton: false });

const scan = async (rawValue) => {
  const result = await addToBatch(rawValue);

  if (!result.added && manualInput.value) {
    toast('warning', t('preparation.scanner.invalid_tracking'));
    manualInput.value = '';

    return;
  }

  if (result.added && !result.valid) {
    toast('error', result.message || t('preparation.scanner.rejected'));
  }
};

const close = () => {
  stopCamera();
  clear();
  emit('close');
};

const submit = () => {
  if (validBatch.value.length === 0) {
    return;
  }

  Swal.fire({
    title: t('preparation.scanner.confirm', { count: validBatch.value.length }),
    text: t('preparation.scanner.confirm_hint'),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: t('preparation.scanner.mark_prepared'),
    cancelButtonText: t('common.cancel'),
    confirmButtonColor: '#0ab39c',
  }).then(async (confirmed) => {
    if (!confirmed.isConfirmed) {
      return;
    }

    try {
      const { data } = await axios.post(route('orders.preparation.bulk-scan'), {
        orders: validBatch.value.map((row) => row.tracking_number),
      });

      await Swal.fire({
        icon: 'success',
        title: t('preparation.scanner.done', { prepared: data.prepared, skipped: data.skipped }),
        timer: 2200,
        showConfirmButton: false,
      });

      clear();
      emit('close');
      router.reload({ preserveScroll: true });
    } catch (error) {
      const message =
        error.response?.data?.errors?.orders?.[0]
        || error.response?.data?.message
        || t('preparation.scanner.bulk_failed');

      Swal.fire({ icon: 'error', title: t('preparation.scanner.bulk_failed'), text: message });
    }
  });
};

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      clear();
      startCamera();
    } else {
      stopCamera();
    }
  }
);
</script>

<template>
  <BottomSheet :show="show" :title="$t('preparation.scanner.title')" size="xl" @close="close">
    <div class="row g-3">
      <BCol lg="5">
        <div class="ratio ratio-4x3 bg-light rounded border overflow-hidden">
          <video v-show="scanning" ref="videoRef" class="w-100 h-100 scanner-video" playsinline muted></video>
          <div v-if="!scanning" class="d-flex flex-column align-items-center justify-content-center text-muted p-3">
            <i class="ri-qr-scan-2-line fs-1 mb-2"></i>
            <span class="text-center">{{ $t('preparation.scanner.camera_preview') }}</span>
          </div>
        </div>

        <div v-if="cameraError" class="alert alert-warning mt-2 mb-0 py-2">{{ cameraError }}</div>

        <button
          v-if="!scanning && !cameraError"
          type="button"
          class="btn btn-sm btn-soft-primary mt-2"
          @click="startCamera"
        >
          <i class="ri-camera-line me-1"></i> {{ $t('preparation.scanner.start_camera') }}
        </button>
      </BCol>

      <BCol lg="7">
        <label class="form-label">{{ $t('preparation.scanner.manual_label') }}</label>
        <div class="input-group mb-3">
          <input
            v-model="manualInput"
            type="text"
            class="form-control"
            :placeholder="$t('preparation.scanner.manual_placeholder')"
            :disabled="validating"
            @keyup.enter="scan()"
          />
          <button type="button" class="btn btn-primary" :disabled="validating" @click="scan()">
            <span v-if="validating" class="spinner-border spinner-border-sm"></span>
            <span v-else>{{ $t('preparation.scanner.add') }}</span>
          </button>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">
            {{ $t('preparation.scanner.scanned', { count: batch.length }) }}
            <span class="text-muted fw-normal">
              · {{ $t('preparation.scanner.valid_count', { count: validBatch.length }) }}
            </span>
          </h6>
          <button v-if="batch.length" type="button" class="btn btn-link btn-sm text-danger p-0" @click="clear">
            {{ $t('preparation.scanner.clear_all') }}
          </button>
        </div>

        <div class="border rounded scanner-batch">
          <p v-if="batch.length === 0" class="text-muted text-center py-4 mb-0">
            {{ $t('preparation.scanner.nothing_scanned') }}
          </p>

          <div v-else class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th>{{ $t('preparation.columns.tracking') }}</th>
                  <th>{{ $t('preparation.columns.customer') }}</th>
                  <th>{{ $t('preparation.columns.city') }}</th>
                  <th class="text-end">{{ $t('preparation.columns.units') }}</th>
                  <th>{{ $t('preparation.columns.check') }}</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in batch" :key="row.tracking_number">
                  <td class="fw-medium">{{ row.tracking_number }}</td>
                  <td>{{ row.customer ?? $t('common.empty_value') }}</td>
                  <td>{{ row.city ?? $t('common.empty_value') }}</td>
                  <td class="text-end">{{ row.units ?? $t('common.empty_value') }}</td>
                  <td>
                    <span v-if="row.valid" class="badge bg-success-subtle text-success">
                      {{ $t('preparation.scanner.valid') }}
                    </span>
                    <span v-else class="badge bg-danger-subtle text-danger" :title="row.message">
                      {{ $t('preparation.scanner.rejected') }}
                    </span>
                  </td>
                  <td class="text-end">
                    <button
                      type="button"
                      class="btn btn-sm btn-soft-danger"
                      @click="removeFromBatch(row.tracking_number)"
                    >
                      <i class="ri-close-line"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </BCol>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="close">{{ $t('common.close') }}</button>
      <button type="button" class="btn btn-success" :disabled="validBatch.length === 0" @click="submit">
        <i class="ri-box-3-line me-1"></i>
        {{ $t('preparation.scanner.mark_prepared_count', { count: validBatch.length }) }}
      </button>
    </div>
  </BottomSheet>
</template>

<style scoped>
.scanner-video {
  object-fit: cover;
}

.scanner-batch {
  max-height: 320px;
  overflow-y: auto;
}
</style>
