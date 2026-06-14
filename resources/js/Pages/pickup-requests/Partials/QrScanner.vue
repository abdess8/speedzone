<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import Swal from "sweetalert2";

const props = defineProps({
  show: { type: Boolean, default: false },
  scanTargetStatus: { type: String, default: "PICKED_UP" },
  scanMode: { type: String, default: "driver" },
});

const emit = defineEmits(["close"]);

const manualInput = ref("");
const batch = ref([]);
const scanning = ref(false);
const cameraError = ref("");
const validating = ref(false);
const submitting = ref(false);
const videoRef = ref(null);
let stream = null;
let scanInterval = null;
let lastScannedValue = "";
let lastScannedAt = 0;

const validBatch = computed(() => batch.value.filter((item) => item.valid));
const updateButtonLabel = computed(() => {
  if (props.scanTargetStatus === "IN_DEPOT") {
    return "Mark as In Depot";
  }

  return "Mark as Picked Up";
});

const statusLabel = (status) => {
  const labels = {
    CREATED: "Created",
    WAITING_PICKUP: "Waiting for Pickup",
    PICKED_UP: "Picked Up",
    IN_DEPOT: "In Depot",
    DELIVERED: "Delivered",
    RETURNED: "Returned",
  };

  return labels[status] || status || "—";
};

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
  const { data } = await axios.post(route("pickup.scan"), {
    tracking_number: tracking,
  });

  return data;
};

const addToBatch = async (rawValue = manualInput.value) => {
  const tracking = parseTrackingNumber(rawValue);
  if (!tracking) {
    Swal.fire({ icon: "warning", title: "Invalid tracking", text: "Enter a tracking number or order URL." });
    return;
  }

  if (batch.value.some((item) => item.tracking_number === tracking)) {
    return;
  }

  validating.value = true;

  try {
    const result = await scanOrder(tracking);

    batch.value.push({
      tracking_number: tracking,
      customer: result.order?.customer ?? "—",
      city: result.order?.city ?? "—",
      status: result.order?.status ?? "—",
      valid: Boolean(result.success && result.valid),
      validation_message: result.success ? "Valid" : result.message,
      scanned_at: new Date().toISOString(),
    });

    if (!result.success) {
      Swal.fire({
        toast: true,
        position: "top-end",
        icon: "error",
        title: result.message || "Scan rejected",
        timer: 2500,
        showConfirmButton: false,
      });
    }
  } catch (error) {
    const message = error.response?.data?.message || "Unable to validate this order.";
    batch.value.push({
      tracking_number: tracking,
      customer: "—",
      city: "—",
      status: "—",
      valid: false,
      validation_message: message,
      scanned_at: new Date().toISOString(),
    });

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

  if (!("BarcodeDetector" in window)) {
    cameraError.value = "Camera QR scanning is not supported in this browser. Use manual entry below.";
    return;
  }

  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
    await nextTick();
    if (videoRef.value) {
      videoRef.value.srcObject = stream;
      await videoRef.value.play();
    }

    const detector = new BarcodeDetector({ formats: ["qr_code"] });
    scanning.value = true;
    lastScannedValue = "";
    lastScannedAt = 0;

    scanInterval = setInterval(async () => {
      if (!videoRef.value || validating.value) return;
      try {
        const codes = await detector.detect(videoRef.value);
        if (codes.length === 0) return;

        const rawValue = codes[0].rawValue;
        const now = Date.now();
        if (rawValue === lastScannedValue && now - lastScannedAt < 3000) {
          return;
        }

        lastScannedValue = rawValue;
        lastScannedAt = now;
        await addToBatch(rawValue);
      } catch {
        // ignore frame errors
      }
    }, 500);
  } catch {
    cameraError.value = "Unable to access camera. Check permissions or use manual entry.";
  }
};

const submitBatch = () => {
  if (validBatch.value.length === 0) return;

  Swal.fire({
    title: `${updateButtonLabel.value} for ${validBatch.value.length} package(s)?`,
    icon: "question",
    showCancelButton: true,
    confirmButtonText: "Update Status",
    confirmButtonColor: "#0ab39c",
  }).then(async (result) => {
    if (!result.isConfirmed) return;

    submitting.value = true;

    try {
      const { data } = await axios.post(route("pickup.bulk-status-update"), {
        orders: validBatch.value.map((item) => item.tracking_number),
        status: props.scanTargetStatus,
      });

      await Swal.fire({
        icon: "success",
        title: "Status updated",
        text: `${data.updated} order(s) updated successfully.`,
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
        || "Bulk status update failed.";

      Swal.fire({ icon: "error", title: "Update failed", text: message });
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
  <BModal
    :model-value="show"
    title="QR Bulk Pickup Scan"
    size="xl"
    hide-footer
    scrollable
    @update:model-value="(v) => !v && close()"
  >
    <div class="row g-3">
      <BCol lg="5">
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

      <BCol lg="7">
        <label class="form-label">Scan or paste tracking URL / number</label>
        <div class="input-group mb-3">
          <input
            v-model="manualInput"
            type="text"
            class="form-control"
            placeholder="SPD-2026-000001 or /orders/SPD-2026-000001"
            :disabled="validating"
            @keyup.enter="addToBatch()"
          />
          <button type="button" class="btn btn-primary" :disabled="validating" @click="addToBatch()">
            <span v-if="validating" class="spinner-border spinner-border-sm"></span>
            <span v-else>Add</span>
          </button>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="mb-0">
            Scanned ({{ batch.length }})
            <span class="text-muted fw-normal">· {{ validBatch.length }} valid</span>
          </h6>
          <button v-if="batch.length" type="button" class="btn btn-link btn-sm text-danger p-0" @click="batch = []">
            Clear all
          </button>
        </div>

        <div class="border rounded" style="max-height: 320px; overflow-y: auto">
          <div v-if="batch.length === 0" class="text-muted text-center py-4">No packages scanned yet.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th>Tracking Number</th>
                  <th>Customer</th>
                  <th>City</th>
                  <th>Current Status</th>
                  <th>Validation</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in batch" :key="item.tracking_number">
                  <td class="fw-medium">{{ item.tracking_number }}</td>
                  <td>{{ item.customer }}</td>
                  <td>{{ item.city }}</td>
                  <td>{{ statusLabel(item.status) }}</td>
                  <td>
                    <span v-if="item.valid" class="badge bg-success-subtle text-success">Valid</span>
                    <span v-else class="badge bg-danger-subtle text-danger" :title="item.validation_message">
                      Rejected
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
        </div>
      </BCol>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button type="button" class="btn btn-light" @click="close">Close</button>
      <button
        type="button"
        class="btn btn-success"
        :disabled="validBatch.length === 0 || submitting"
        @click="submitBatch"
      >
        <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
        <i v-else class="ri-refresh-line me-1"></i>
        Update Status ({{ validBatch.length }})
      </button>
    </div>
  </BModal>
</template>

<style scoped>
.object-fit-cover {
  object-fit: cover;
}
</style>
