<script setup>
import { ref, watch, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Swal from "sweetalert2";

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
  if (stream.value) {
    stream.value.getTracks().forEach((track) => track.stop());
    stream.value = null;
  }
  scanning.value = false;
};

const startCamera = async () => {
  if (!("BarcodeDetector" in window)) return;

  try {
    stream.value = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
    scanning.value = true;
  } catch {
    /* camera unavailable */
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
  <BModal
    :model-value="show"
    :title="$t('transfers.scanner.title')"
    size="lg"
    hide-footer
    @update:model-value="(v) => !v && close()"
  >
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
  </BModal>
</template>
