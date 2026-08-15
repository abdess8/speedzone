<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import ScannerViewport from '@/Components/ScannerViewport.vue';
import { useQrBatchScanner } from '@/composables/useQrBatchScanner';

const { t } = useI18n();

const props = defineProps({
  /** Returns parked at a vendor hub, offered as a checklist next to the scanner. */
  pending: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const cityId = ref(props.filters.city_id ?? '');
/** Pre-selected on every card that has no driver of its own yet. */
const defaultDriverId = ref('');
const comment = ref('');
const submitting = ref(false);

const {
  manualInput,
  batch,
  scanning,
  cameraError,
  validating,
  videoRef,
  feedback,
  startCamera,
  stopCamera,
  addToBatch,
  removeFromBatch,
  clear,
} = useQrBatchScanner({
  validate: async (reference) => {
    const { data } = await axios.post(route('returns.hand-back.scan'), { scan: reference });

    return {
      valid: data.valid,
      message: data.message ?? '',
      row: { ...(data.row ?? {}), driver_id: data.row?.assigned_to ?? defaultDriverId.value ?? '' },
    };
  },
  unsupportedMessage: () => t('returns.hand_back.camera_unsupported'),
  cameraErrorMessage: () => t('returns.hand_back.camera_error'),
  unreachableMessage: () => t('returns.hand_back.unreachable'),
  onUnknownCode: () => toast('warning', t('returns.hand_back.unreadable')),
  onDuplicateCode: (reference) => toast('info', t('returns.hand_back.already', { reference })),
});

const readyRows = computed(() => batch.value.filter((row) => row.valid));
const missingDriver = computed(() => readyRows.value.some((row) => !row.driver_id));
const canSubmit = computed(() => readyRows.value.length > 0 && !missingDriver.value && !submitting.value);

/** Rows already scanned should not show up as still available on the shelf. */
const shelf = computed(() => {
  const scanned = new Set(batch.value.map((row) => row.reference));

  return props.pending.filter((row) => !scanned.has(row.reference));
});

/** Changing the batch driver fills the blanks without overriding manual picks. */
watch(defaultDriverId, (value) => {
  if (!value) return;

  batch.value.forEach((row) => {
    if (!row.driver_id) row.driver_id = value;
  });
});

watch(cityId, (value) => {
  router.get(route('returns.hand-back'), value ? { city_id: value } : {}, {
    preserveState: false,
    replace: true,
  });
});

const toast = (icon, title) =>
  Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 3000 });

const submitScan = async () => {
  const result = await addToBatch();

  if (result.added && !result.valid && result.message) {
    toast('error', result.message);
  }
};

/** One tap to pull a shelf row into the batch, for hubs working off a list. */
const addPending = (row) => {
  if (batch.value.some((entry) => entry.reference === row.reference)) return;

  batch.value.push({
    ...row,
    tracking_number: row.reference,
    valid: true,
    message: '',
    driver_id: row.assigned_to ?? defaultDriverId.value ?? '',
  });
};

const dispatchBatch = () => {
  if (!canSubmit.value) {
    if (missingDriver.value) toast('warning', t('returns.hand_back.missing_driver'));

    return;
  }

  submitting.value = true;

  router.post(
    route('returns.hand-back.dispatch'),
    {
      items: readyRows.value.map((row) => ({
        id: row.id,
        reference: row.reference,
        driver_id: Number(row.driver_id),
      })),
      comment: comment.value || null,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        clear();
        comment.value = '';
      },
      onFinish: () => {
        submitting.value = false;
      },
    }
  );
};

onMounted(() => {
  const flash = usePage().props?.flash ?? {};

  if (flash.success) toast('success', flash.success);
  if (flash.warning) toast('warning', flash.warning);
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('returns.hand_back.title')" :pageTitle="$t('returns.page_title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-end gap-3">
        <div class="flex-grow-1" style="min-width: 14rem">
          <p class="text-muted mb-0">{{ $t('returns.hand_back.intro') }}</p>
        </div>
        <div style="min-width: 12rem">
          <label class="form-label">{{ $t('returns.hand_back.city') }}</label>
          <select v-model="cityId" class="form-select">
            <option value="">{{ $t('returns.hand_back.all_cities') }}</option>
            <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
          </select>
        </div>
        <div style="min-width: 14rem">
          <label class="form-label">{{ $t('returns.hand_back.default_driver') }}</label>
          <select v-model="defaultDriverId" class="form-select" :disabled="!drivers.length">
            <option value="">{{ $t('returns.hand_back.select_driver') }}</option>
            <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
              {{ driver.name }}{{ driver.phone ? ` (${driver.phone})` : '' }}
            </option>
          </select>
        </div>
        <Link :href="route('returns.index')" class="btn btn-light">
          <i class="ri-arrow-left-line align-bottom me-1"></i>{{ $t('returns.show.back') }}
        </Link>
      </BCardBody>
      <BCardBody v-if="!drivers.length" class="pt-0">
        <div class="alert alert-warning mb-0">{{ $t('returns.hand_back.no_drivers') }}</div>
      </BCardBody>
    </BCard>

    <BRow class="g-3">
      <BCol xl="7">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">
              {{ $t('returns.hand_back.batch') }}
              <span v-if="readyRows.length" class="badge bg-primary-subtle text-primary ms-1">{{ readyRows.length }}</span>
            </h5>
            <div class="hstack gap-2">
              <button
                type="button"
                class="btn btn-sm btn-soft-primary"
                @click="scanning ? stopCamera() : startCamera()"
              >
                <i class="ri-qr-scan-2-line align-bottom me-1"></i>
                {{ scanning ? $t('returns.hand_back.stop_camera') : $t('returns.hand_back.start_camera') }}
              </button>
              <button v-if="batch.length" type="button" class="btn btn-sm btn-light" @click="clear">
                {{ $t('returns.hand_back.clear') }}
              </button>
            </div>
          </BCardHeader>

          <BCardBody>
            <ScannerViewport
              v-show="scanning"
              class="scanner-preview mb-3"
              :scanning="scanning"
              :feedback="feedback"
              :hint="$t('returns.hand_back.aim')"
            >
              <video ref="videoRef" muted playsinline></video>
            </ScannerViewport>
            <div v-if="cameraError" class="alert alert-warning py-2">{{ cameraError }}</div>

            <form class="input-group mb-3" @submit.prevent="submitScan">
              <input
                v-model="manualInput"
                type="text"
                class="form-control"
                :placeholder="$t('returns.hand_back.scan_placeholder')"
                autofocus
              />
              <button type="submit" class="btn btn-primary" :disabled="validating || !manualInput">
                <i class="ri-add-line align-bottom"></i>
                <span class="d-none d-sm-inline ms-1">{{ $t('returns.hand_back.add') }}</span>
              </button>
            </form>

            <p v-if="!batch.length" class="text-center text-muted py-4 mb-0">
              {{ $t('returns.hand_back.batch_empty') }}
            </p>

            <div class="table-responsive" v-else>
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ $t('returns.table.reference') }}</th>
                    <th>{{ $t('returns.table.seller') }}</th>
                    <th style="min-width: 13rem">{{ $t('returns.hand_back.driver') }}</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in batch" :key="row.tracking_number" :class="{ 'table-danger': !row.valid }">
                    <td>
                      <span class="fw-medium">{{ row.reference ?? row.tracking_number }}</span>
                      <div v-if="row.order_tracking" class="text-muted fs-12">{{ row.order_tracking }}</div>
                      <div v-if="!row.valid" class="text-danger fs-12">{{ row.message }}</div>
                    </td>
                    <td>
                      {{ row.seller_name ?? '—' }}
                      <div v-if="row.city_name" class="text-muted fs-12">{{ row.city_name }}</div>
                    </td>
                    <td>
                      <select
                        v-if="row.valid"
                        v-model="row.driver_id"
                        class="form-select form-select-sm"
                        :class="{ 'is-invalid': !row.driver_id }"
                      >
                        <option value="">{{ $t('returns.hand_back.select_driver') }}</option>
                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                          {{ driver.name }}
                        </option>
                      </select>
                      <span v-else class="text-muted">—</span>
                    </td>
                    <td class="text-end">
                      <button
                        type="button"
                        class="btn btn-sm btn-ghost-danger"
                        :title="$t('returns.hand_back.remove')"
                        @click="removeFromBatch(row.tracking_number)"
                      >
                        <i class="ri-close-line"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>

          <BCardBody v-if="batch.length" class="border-top">
            <label class="form-label">{{ $t('returns.swal.optional_comment') }}</label>
            <textarea v-model="comment" class="form-control mb-3" rows="2"></textarea>
            <button type="button" class="btn btn-success w-100" :disabled="!canSubmit" @click="dispatchBatch">
              <i class="ri-e-bike-2-line align-bottom me-1"></i>
              {{ $t('returns.hand_back.dispatch') }}
              <span v-if="readyRows.length">({{ readyRows.length }})</span>
            </button>
            <p v-if="missingDriver" class="text-danger fs-12 mt-2 mb-0">
              {{ $t('returns.hand_back.missing_driver') }}
            </p>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="5">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">
              {{ $t('returns.hand_back.pending') }}
              <span v-if="shelf.length" class="badge bg-info-subtle text-info ms-1">{{ shelf.length }}</span>
            </h5>
          </BCardHeader>
          <BCardBody>
            <p v-if="!shelf.length" class="text-center text-muted py-4 mb-0">
              {{ $t('returns.hand_back.pending_empty') }}
            </p>

            <div v-for="row in shelf" :key="row.id" class="d-flex align-items-center gap-2 border-bottom py-2">
              <div class="flex-grow-1 min-w-0">
                <Link :href="route('returns.show', row.id)" class="fw-medium">{{ row.reference }}</Link>
                <div class="text-muted fs-12 text-truncate">
                  {{ row.seller_name }}<span v-if="row.city_name"> · {{ row.city_name }}</span>
                </div>
                <div v-if="row.assigned_driver_name" class="fs-12 text-primary">
                  {{ $t('returns.hand_back.assigned_to') }} {{ row.assigned_driver_name }}
                </div>
              </div>
              <button
                type="button"
                class="btn btn-sm btn-soft-primary"
                :title="$t('returns.hand_back.add_to_batch')"
                @click="addPending(row)"
              >
                <i class="ri-add-line"></i>
              </button>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
/* A viewfinder wide enough to aim with, not a wall of camera on a desktop. */
.scanner-preview {
  max-width: 24rem;
}
</style>
