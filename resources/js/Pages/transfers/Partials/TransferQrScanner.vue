<script setup>
import { ref, watch, nextTick, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Swal from "sweetalert2";
import BottomSheet from "@/Components/BottomSheet.vue";
import { createQrDetector } from "@/utils/qrDetector";

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

/** Frames are cheap; a code left in front of the lens must not be added twice. */
const REPEAT_GUARD_MS = 3000;
const DETECT_INTERVAL_MS = 500;

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
  if (!tracking) return;

  if (scannedOrders.value.includes(tracking)) {
    Swal.fire({ toast: true, position: "top-end", icon: "info", title: "Already scanned", timer: 2000, showConfirmButton: false });
    return;
  }

  try {
    const result = await validateScan(tracking);
    if (!result.valid) {
      Swal.fire({ toast: true, position: "top-end", icon: "error", title: result.message || t("transfers.scanner.invalid"), timer: 3000, showConfirmButton: false });
      return;
    }
    scannedOrders.value.push(tracking);
    manualInput.value = "";
  } catch {
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

  if (!navigator.mediaDevices?.getUserMedia) {
    cameraError.value = t("transfers.scanner.camera_unsupported");
    return;
  }

  try {
    stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
    await nextTick();

    if (videoRef.value) {
      videoRef.value.srcObject = stream.value;
      await videoRef.value.play();
    }

    const detector = await createQrDetector();
    scanning.value = true;
    lastValue = "";
    lastAt = 0;

    detectInterval = setInterval(async () => {
      if (!videoRef.value) return;

      try {
        const codes = await detector.detect(videoRef.value);
        if (codes.length === 0) return;

        const rawValue = codes[0].rawValue;
        const now = Date.now();
        if (rawValue === lastValue && now - lastAt < REPEAT_GUARD_MS) return;

        lastValue = rawValue;
        lastAt = now;
        await addScan(rawValue);
      } catch {
        // A frame the detector cannot read is not an error worth reporting.
      }
    }, DETECT_INTERVAL_MS);
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

    <div v-show="scanning" class="scanner-viewport mb-3">
      <video ref="videoRef" class="w-100 rounded" playsinline muted></video>
      <div class="scanner-frame"></div>
    </div>

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
.scanner-viewport {
  position: relative;
  overflow: hidden;
  border-radius: var(--vz-border-radius, 0.375rem);
  background-color: #000;
}

.scanner-viewport video {
  display: block;
  max-height: 18rem;
  object-fit: cover;
}

/* Tells the operator where to hold the label, which is the difference between
   a viewfinder and a video of the warehouse floor. */
.scanner-frame {
  position: absolute;
  inset: 15% 25%;
  border: 2px solid rgba(255, 255, 255, 0.85);
  border-radius: 0.5rem;
  pointer-events: none;
}
</style>
