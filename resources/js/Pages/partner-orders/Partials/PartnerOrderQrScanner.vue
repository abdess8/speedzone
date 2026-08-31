<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Swal from "sweetalert2";
import BottomSheet from "@/Components/BottomSheet.vue";
import ScannerViewport from "@/Components/ScannerViewport.vue";
import { useScanFeedback } from "@/composables/useScanFeedback";
import { QR_DETECT_INTERVAL_MS, createQrDetector, openQrCamera } from "@/utils/qrDetector";

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const manualInput = ref("");
const batch = ref([]);
const scanning = ref(false);
const cameraError = ref("");
const validating = ref(false);
const submitting = ref(false);
const videoRef = ref(null);
const { feedback, flash, primeSound } = useScanFeedback();
let stream = null;
let scanInterval = null;
let lastScannedValue = "";
let lastScannedAt = 0;

const validBatch = computed(() => batch.value.filter((item) => item.valid));

const parseTrackingNumber = (raw) => {
  const value = (raw || "").trim();
  if (!value) return null;

  const urlMatch = value.match(/\/orders\/([A-Za-z0-9]+-[0-9]{4}-[0-9]+)/i);
  if (urlMatch) return urlMatch[1];

  const directMatch = value.match(/^([A-Za-z0-9]+-[0-9]{4}-[0-9]+)$/);
  if (directMatch) return directMatch[1];

  return null;
};

const scanOrder = async (tracking) => {
  const { data } = await axios.post(route("partner-orders.scan"), {
    tracking_number: tracking,
  });

  return data;
};

const addToBatch = async (rawValue = manualInput.value) => {
  const tracking = parseTrackingNumber(rawValue);
  if (!tracking) {
    // A toast rather than a modal: the operator is mid-trolley, and a dialog
    // waiting for a tap stops the whole sweep.
    flash("warning");
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "warning",
      title: t("partners.orders.scanner.invalid_tracking"),
      text: t("partners.orders.scanner.invalid_tracking_text"),
      timer: 2500,
      showConfirmButton: false,
    });
    return;
  }

  if (batch.value.some((item) => item.tracking_number === tracking)) {
    flash("warning", tracking);
    return;
  }

  validating.value = true;

  try {
    const result = await scanOrder(tracking);

    batch.value.push({
      tracking_number: tracking,
      customer: result.order?.customer ?? "—",
      city: result.order?.city ?? "—",
      status: result.order?.status_label ?? result.order?.status ?? "—",
      partner: result.order?.partner ?? "—",
      next_status: result.order?.next_status ?? null,
      valid: Boolean(result.success && result.valid),
      validation_message: result.success ? t("partners.orders.scanner.valid") : result.message,
      scanned_at: new Date().toISOString(),
    });

    flash(result.success && result.valid ? "success" : "error", tracking);

    if (!result.success) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: result.message || t("partners.orders.scanner.scan_rejected"),
        timer: 2500,
        showConfirmButton: false,
      });
    }
  } catch (error) {
    const message = error.response?.data?.message || t("partners.orders.scanner.unable_validate");
    batch.value.push({
      tracking_number: tracking,
      customer: "—",
      city: "—",
      status: "—",
      partner: "—",
      next_status: null,
      valid: false,
      validation_message: message,
      scanned_at: new Date().toISOString(),
    });

    flash("error", tracking);

    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: message,
      timer: 2500,
      showConfirmButton: false,
    });
  } finally {
    validating.value = false;
    manualInput.value = "";
  }
};

const removeFromBatch = (tracking) => {
  batch.value = batch.value.filter((item) => item.tracking_number !== tracking);
};

const stopCamera = () => {
  scanning.value = false;
  if (scanInterval) {
    clearInterval(scanInterval);
    scanInterval = null;
  }
  if (stream) {
    stream.getTracks().forEach((track) => track.stop());
    stream = null;
  }
};

const startCamera = async () => {
  cameraError.value = "";
  stopCamera();
  primeSound();

  if (!navigator.mediaDevices?.getUserMedia) {
    cameraError.value = t("partners.orders.scanner.camera_unsupported");
    return;
  }

  try {
    await nextTick();
    stream = await openQrCamera(videoRef.value);

    const detector = await createQrDetector();
    scanning.value = true;
    lastScannedValue = "";
    lastScannedAt = 0;

    let failures = 0;

    scanInterval = setInterval(async () => {
      if (!videoRef.value || validating.value) return;

      let codes = [];

      try {
        codes = await detector.detect(videoRef.value);
        failures = 0;
      } catch {
        failures += 1;

        // A decoder that never reads a single frame is a dead end, and leaving it
        // spinning in silence looks exactly like a camera that sees nothing.
        if (failures >= 6) {
          stopCamera();
          cameraError.value = t("partners.orders.scanner.camera_error");
        }

        return;
      }

      if (codes.length === 0) return;

      const rawValue = codes[0].rawValue;
      const now = Date.now();
      if (rawValue === lastScannedValue && now - lastScannedAt < 3000) {
        return;
      }

      lastScannedValue = rawValue;
      lastScannedAt = now;
      await addToBatch(rawValue);
    }, QR_DETECT_INTERVAL_MS);
  } catch {
    cameraError.value = t("partners.orders.scanner.camera_error");
  }
};

const submitBatch = () => {
  if (validBatch.value.length === 0) return;

  Swal.fire({
    title: t("partners.orders.scanner.bulk_confirm", { count: validBatch.value.length }),
    icon: "question",
    showCancelButton: true,
    confirmButtonText: t("partners.orders.scanner.advance_status"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#0ab39c",
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    submitting.value = true;

    try {
      const { data } = await axios.post(route("partner-orders.bulk-scan"), {
        orders: validBatch.value.map((item) => item.tracking_number),
      });

      await Swal.fire({
        icon: "success",
        title: t("partners.orders.scanner.status_updated"),
        text: t("partners.orders.scanner.status_updated_text", { count: data.updated }),
        timer: 2200,
        showConfirmButton: false,
      });

      batch.value = [];
      emit("close");
      router.reload({ preserveScroll: true });
    } catch (error) {
      const message =
        error.response?.data?.errors?.orders?.[0]
        || error.response?.data?.message
        || t("partners.orders.scanner.bulk_failed");

      Swal.fire({ icon: "error", title: t("partners.orders.scanner.update_failed"), text: message });
    } finally {
      submitting.value = false;
    }
  });
};

const close = () => {
  stopCamera();
  batch.value = [];
  emit("close");
};

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      batch.value = [];
      nextTick(() => startCamera());
    } else {
      stopCamera();
    }
  }
);

onBeforeUnmount(stopCamera);
</script>

<template>
  <BottomSheet :show="show" :title="$t('partners.orders.scanner.title')" size="xl" @close="close">
    <div class="row g-3">
      <BCol lg="5">
        <ScannerViewport :scanning="scanning" :feedback="feedback" :hint="$t('partners.orders.scanner.aim')">
          <video v-show="scanning" ref="videoRef" playsinline muted></video>

          <template #idle>
            <i class="ri-qr-scan-2-line fs-1 mb-2"></i>
            <span>{{ $t('partners.orders.scanner.camera_preview') }}</span>
          </template>
        </ScannerViewport>
        <div v-if="cameraError" class="alert alert-warning mt-2 mb-0 py-2">{{ cameraError }}</div>
        <button v-if="!scanning && !cameraError" type="button" class="btn btn-sm btn-soft-primary mt-2" @click="startCamera">
          <i class="ri-camera-line me-1"></i> {{ $t('partners.orders.scanner.start_camera') }}
        </button>
      </BCol>

      <BCol lg="7">
        <label class="form-label">{{ $t('partners.orders.scanner.scan_manual_label') }}</label>
        <div class="input-group mb-3">
          <input
            v-model="manualInput"
            type="text"
            class="form-control"
            :placeholder="$t('partners.orders.scanner.scan_placeholder')"
            :disabled="validating"
            @keyup.enter="addToBatch()"
          />
          <button type="button" class="btn btn-primary" :disabled="validating" @click="addToBatch()">
            <span v-if="validating" class="spinner-border spinner-border-sm"></span>
            <span v-else>{{ $t('partners.orders.scanner.add') }}</span>
          </button>
        </div>

        <div v-if="batch.length" class="table-responsive border rounded">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ $t('orders.table.tracking_number') }}</th>
                <th>{{ $t('orders.table.customer') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in batch" :key="item.tracking_number">
                <td><code>{{ item.tracking_number }}</code></td>
                <td>
                  <div>{{ item.customer }}</div>
                  <div class="text-muted fs-12">{{ item.partner }}</div>
                </td>
                <td>
                  <span class="badge" :class="item.valid ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ item.valid ? item.status : item.validation_message }}
                  </span>
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-soft-danger" @click="removeFromBatch(item.tracking_number)">
                    <i class="ri-close-line"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-muted text-center py-4">{{ $t('partners.orders.scanner.empty_batch') }}</div>

        <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="button" class="btn btn-light" @click="close">{{ $t('common.cancel') }}</button>
          <button
            type="button"
            class="btn btn-success"
            :disabled="validBatch.length === 0 || submitting"
            @click="submitBatch"
          >
            <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
            {{ $t('partners.orders.scanner.advance_status') }} ({{ validBatch.length }})
          </button>
        </div>
      </BCol>
    </div>
  </BottomSheet>
</template>
