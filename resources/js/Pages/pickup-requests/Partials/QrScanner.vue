<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const manualInput = ref("");
const batch = ref([]);
const scanning = ref(false);
const cameraError = ref("");
const videoRef = ref(null);
let stream = null;
let scanInterval = null;

const parseTrackingNumber = (raw) => {
  const value = (raw || "").trim();
  if (!value) return null;

  const urlMatch = value.match(/\/orders\/([A-Za-z0-9]+-[0-9]{4}-[0-9]+)/i);
  if (urlMatch) return urlMatch[1];

  const directMatch = value.match(/^([A-Za-z0-9]+-[0-9]{4}-[0-9]+)$/);
  if (directMatch) return directMatch[1];

  return null;
};

const addToBatch = () => {
  const tracking = parseTrackingNumber(manualInput.value);
  if (!tracking) {
    Swal.fire({ icon: "warning", title: "Invalid tracking", text: "Enter a tracking number or order URL." });
    return;
  }

  if (batch.value.some((item) => item.tracking_number === tracking)) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "info",
      title: "Already in batch",
      timer: 2000,
      showConfirmButton: false,
    });
    manualInput.value = "";
    return;
  }

  batch.value.push({ tracking_number: tracking, scanned_at: new Date().toISOString() });
  manualInput.value = "";
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

  if (!("BarcodeDetector" in window)) {
    cameraError.value = "Camera QR scanning is not supported in this browser. Use manual entry below.";
    return;
  }

  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
    if (videoRef.value) {
      videoRef.value.srcObject = stream;
      await videoRef.value.play();
    }

    const detector = new BarcodeDetector({ formats: ["qr_code"] });
    scanning.value = true;

    scanInterval = setInterval(async () => {
      if (!videoRef.value) return;
      try {
        const codes = await detector.detect(videoRef.value);
        if (codes.length > 0) {
          manualInput.value = codes[0].rawValue;
          addToBatch();
        }
      } catch {
        // ignore frame errors
      }
    }, 500);
  } catch {
    cameraError.value = "Unable to access camera. Check permissions or use manual entry.";
  }
};

const submitBatch = () => {
  if (batch.value.length === 0) return;

  Swal.fire({
    title: `Mark ${batch.value.length} package(s) as picked up?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Confirm",
    confirmButtonColor: "#0ab39c",
  }).then((result) => {
    if (!result.isConfirmed) return;

    router.post(
      route("pickup-requests.bulk-scan"),
      { tracking_numbers: batch.value.map((item) => item.tracking_number) },
      {
        preserveScroll: true,
        onSuccess: () => {
          batch.value = [];
          emit("close");
        },
      }
    );
  });
};

const close = () => {
  stopCamera();
  emit("close");
};

watch(
  () => props.show,
  (visible) => {
    if (visible) startCamera();
    else stopCamera();
  }
);

onBeforeUnmount(stopCamera);
</script>

<template>
  <BModal
    :model-value="show"
    title="QR Bulk Pickup Scan"
    size="lg"
    hide-footer
    scrollable
    @update:model-value="(v) => !v && close()"
  >
    <div class="row g-3">
      <BCol md="6">
        <div class="ratio ratio-4x3 bg-light rounded border d-flex align-items-center justify-content-center overflow-hidden">
          <video v-show="scanning" ref="videoRef" class="w-100 h-100 object-fit-cover" playsinline muted></video>
          <div v-if="!scanning" class="text-center text-muted p-3">
            <i class="ri-qr-scan-2-line fs-1 d-block mb-2"></i>
            Camera preview
          </div>
        </div>
        <div v-if="cameraError" class="alert alert-warning mt-2 mb-0 py-2">{{ cameraError }}</div>
        <button v-if="!scanning && !cameraError" type="button" class="btn btn-sm btn-soft-primary mt-2" @click="startCamera">
          <i class="ri-camera-line me-1"></i> Start camera
        </button>
      </BCol>

      <BCol md="6">
        <label class="form-label">Scan or paste tracking URL / number</label>
        <div class="input-group mb-3">
          <input
            v-model="manualInput"
            type="text"
            class="form-control"
            placeholder="SPD-2026-000001 or /orders/SPD-2026-000001"
            @keyup.enter="addToBatch"
          />
          <button type="button" class="btn btn-primary" @click="addToBatch">Add</button>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">Batch ({{ batch.length }})</h6>
          <button v-if="batch.length" type="button" class="btn btn-link btn-sm text-danger p-0" @click="batch = []">
            Clear all
          </button>
        </div>

        <div class="border rounded" style="max-height: 220px; overflow-y: auto">
          <div v-if="batch.length === 0" class="text-muted text-center py-4">No packages scanned yet.</div>
          <ul v-else class="list-group list-group-flush">
            <li
              v-for="item in batch"
              :key="item.tracking_number"
              class="list-group-item d-flex justify-content-between align-items-center py-2"
            >
              <span class="fw-medium">{{ item.tracking_number }}</span>
              <button type="button" class="btn btn-sm btn-soft-danger" @click="removeFromBatch(item.tracking_number)">
                <i class="ri-close-line"></i>
              </button>
            </li>
          </ul>
        </div>
      </BCol>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="close">Close</button>
      <button type="button" class="btn btn-success" :disabled="batch.length === 0" @click="submitBatch">
        <i class="ri-hand-coin-line me-1"></i> Mark as Picked Up ({{ batch.length }})
      </button>
    </div>
  </BModal>
</template>

<style scoped>
.object-fit-cover {
  object-fit: cover;
}
</style>
