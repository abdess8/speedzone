<script setup>
import { ref, watch, nextTick, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Swal from "sweetalert2";
import BottomSheet from "@/Components/BottomSheet.vue";
import ScannerViewport from "@/Components/ScannerViewport.vue";
import { useScanFeedback } from "@/composables/useScanFeedback";
import { QR_DETECT_INTERVAL_MS, createQrDetector, openQrCamera } from "@/utils/qrDetector";

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
  transferId: { type: [Number, String], required: true },
});

const emit = defineEmits(["close"]);

const manualInput = ref("");
const scannedOrders = ref([]);
const scanning = ref(false);
const stream = ref(null);
const videoRef = ref(null);
const cameraError = ref("");
const { feedback, flash, primeSound } = useScanFeedback();

/** Frames are cheap; a code left in front of the lens must not be added twice. */
const REPEAT_GUARD_MS = 3000;

/** A decoder that never manages one frame is broken, not merely unlucky. */
const MAX_DECODE_FAILURES = 6;

let detectInterval = null;
let lastValue = "";
let lastAt = 0;

const parseTrackingNumber = (input) => {
  const trimmed = input.trim();
  const match = trimmed.match(/\/orders\/([A-Za-z0-9-]+)/);
  return match ? match[1] : trimmed;
};

const validateScan = async (trackingNumber) => {
  const response = await fetch(route("transfers.scan", props.transferId), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "",
    },
    body: JSON.stringify({ tracking_number: trackingNumber }),
  });

  return response.json();
};

const addScan = async (raw) => {
  const tracking = parseTrackingNumber(raw);
  if (!tracking) {
    flash("warning");
    return;
  }

  if (scannedOrders.value.includes(tracking)) {
    flash("warning", tracking);
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "info",
      title: t("transfers.scanner.already", { reference: tracking }),
      timer: 2000,
      showConfirmButton: false,
    });
    return;
  }

  try {
    const result = await validateScan(tracking);
    if (!result.valid) {
      flash("error", tracking);
      Swal.fire({ toast: true, position: "top-end", icon: "error", title: result.message || t("transfers.scanner.invalid"), timer: 3000, showConfirmButton: false });
      return;
    }
    scannedOrders.value.push(tracking);
    manualInput.value = "";
    flash("success", tracking);
  } catch {
    flash("error", tracking);
    Swal.fire({ icon: "error", title: t("transfers.scanner.invalid") });
  }
};

const submitBulk = () => {
  if (scannedOrders.value.length === 0) return;

  router.post(
    route("transfers.bulk-receive", props.transferId),
    { orders: scannedOrders.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        scannedOrders.value = [];
        emit("close");
      },
    }
  );
};

const stopCamera = () => {
  if (detectInterval) {
    clearInterval(detectInterval);
    detectInterval = null;
  }
  if (stream.value) {
    stream.value.getTracks().forEach((track) => track.stop());
    stream.value = null;
  }
  scanning.value = false;
};

const startCamera = async () => {
  cameraError.value = "";
  stopCamera();
  primeSound();

  if (!navigator.mediaDevices?.getUserMedia) {
    cameraError.value = t("transfers.scanner.camera_unsupported");
    return;
  }

  try {
    await nextTick();
    stream.value = await openQrCamera(videoRef.value);

    const detector = await createQrDetector();
    scanning.value = true;
    lastValue = "";
    lastAt = 0;

    let failures = 0;

    detectInterval = setInterval(async () => {
      if (!videoRef.value) return;

      let codes = [];

      try {
        codes = await detector.detect(videoRef.value);
        failures = 0;
      } catch {
        failures += 1;

        if (failures >= MAX_DECODE_FAILURES) {
          stopCamera();
          cameraError.value = t("transfers.scanner.camera_error");
        }

        return;
      }

      if (codes.length === 0) return;

      const rawValue = codes[0].rawValue;
      const now = Date.now();
      if (rawValue === lastValue && now - lastAt < REPEAT_GUARD_MS) return;

      lastValue = rawValue;
      lastAt = now;
      await addScan(rawValue);
    }, QR_DETECT_INTERVAL_MS);
  } catch {
    cameraError.value = t("transfers.scanner.camera_error");
  }
};

watch(() => props.show, (visible) => {
  if (visible) {
    startCamera();
  } else {
    stopCamera();
    scannedOrders.value = [];
    manualInput.value = "";
  }
});

onUnmounted(stopCamera);

const close = () => emit("close");
</script>

<template>
  <BottomSheet :show="show" :title="$t('transfers.scanner.title')" size="lg" @close="close">
    <p class="text-muted">{{ $t('transfers.scanner.hint') }}</p>

    <div class="input-group mb-3">
      <input
        v-model="manualInput"
        type="text"
        class="form-control"
        :placeholder="$t('transfers.scanner.manual_placeholder')"
        @keyup.enter="addScan(manualInput)"
      />
      <button type="button" class="btn btn-primary" @click="addScan(manualInput)">
        {{ $t('transfers.scanner.validate') }}
      </button>
    </div>

    <div v-if="cameraError" class="alert alert-warning">
      <i class="ri-camera-off-line align-bottom me-1"></i>
      {{ cameraError }}
    </div>

    <ScannerViewport
      v-show="scanning"
      class="scanner-preview mb-3"
      :scanning="scanning"
      :feedback="feedback"
      :hint="$t('transfers.scanner.aim')"
    >
      <video ref="videoRef" playsinline muted></video>
    </ScannerViewport>

    <div v-if="scannedOrders.length" class="mb-3">
      <div class="text-muted mb-2">{{ $t('transfers.scanner.scanned_count', { count: scannedOrders.length }) }}</div>
      <div class="d-flex flex-wrap gap-1">
        <span v-for="tn in scannedOrders" :key="tn" class="badge bg-success-subtle text-success">{{ tn }}</span>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <button type="button" class="btn btn-light" @click="close">{{ $t('common.cancel') }}</button>
      <button type="button" class="btn btn-success" :disabled="scannedOrders.length === 0" @click="submitBulk">
        {{ $t('transfers.scanner.submit') }}
      </button>
    </div>
  </BottomSheet>
</template>

<style scoped>
/* A viewfinder wide enough to aim with, not a wall of camera on a desktop. */
.scanner-preview {
  max-width: 24rem;
}
</style>
