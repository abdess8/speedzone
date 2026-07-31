<script setup>
import { ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Swal from "sweetalert2";
import BottomSheet from "@/Components/BottomSheet.vue";

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const scanInput = ref("");
const validating = ref(false);
const lastValidation = ref(null);

watch(
  () => props.show,
  (visible) => {
    if (!visible) {
      scanInput.value = "";
      lastValidation.value = null;
    }
  }
);

const parseScan = (raw) => raw.trim();

const validateScan = async () => {
  const value = parseScan(scanInput.value);
  if (!value) return;

  validating.value = true;
  try {
    const { data } = await axios.post(route("returns.scan"), { scan: value });
    lastValidation.value = data;
  } catch (error) {
    lastValidation.value = null;
    Swal.fire({ icon: "error", title: t("returns.scanner.invalid"), text: error.response?.data?.message || error.response?.data?.errors?.scan?.[0] });
  } finally {
    validating.value = false;
  }
};

const processScan = () => {
  const value = parseScan(scanInput.value);
  if (!value) return;

  router.post(
    route("returns.process-scan"),
    { scan: value },
    {
      preserveScroll: true,
      onSuccess: () => emit("close"),
    }
  );
};
</script>

<template>
  <BottomSheet :show="show" :title="$t('returns.scanner.title')" size="lg" @close="emit('close')">
    <p class="text-muted">{{ $t('returns.scanner.hint') }}</p>

    <div class="input-group mb-3">
      <input
        v-model="scanInput"
        type="text"
        class="form-control"
        :placeholder="$t('returns.scanner.manual_placeholder')"
        @keyup.enter="validateScan"
      />
      <button class="btn btn-primary" :disabled="validating" @click="validateScan">
        {{ $t('returns.scanner.validate') }}
      </button>
    </div>

    <div v-if="lastValidation?.valid" class="alert alert-success">
      <div class="fw-medium">{{ lastValidation.return.reference }}</div>
      <div class="small">{{ lastValidation.return.status_label }} — {{ lastValidation.return.order_tracking }}</div>
    </div>

    <div class="text-end mt-3">
      <button class="btn btn-light me-2" @click="emit('close')">{{ $t('common.cancel') }}</button>
      <button class="btn btn-success" :disabled="!scanInput" @click="processScan">{{ $t('returns.scanner.submit') }}</button>
    </div>
  </BottomSheet>
</template>
