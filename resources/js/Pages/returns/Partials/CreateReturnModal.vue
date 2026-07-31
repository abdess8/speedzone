<script setup>
import { ref, watch, computed } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import BottomSheet from "@/Components/BottomSheet.vue";

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
  filterOptions: { type: Object, default: () => ({}) },
  preselectedOrderId: { type: [Number, String], default: null },
  isAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const eligibleOrders = ref([]);
const loading = ref(false);

const form = useForm({
  order_id: props.preselectedOrderId ?? "",
  reason: "",
  return_notes: "",
});

const canSubmit = computed(() => {
  if (props.preselectedOrderId) return true;
  return eligibleOrders.value.length > 0;
});

watch(
  () => props.show,
  (visible) => {
    if (visible) {
      form.order_id = props.preselectedOrderId ?? "";
      loadEligibleOrders();
    }
  }
);

const loadEligibleOrders = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(route("returns.eligible-orders"));
    eligibleOrders.value = data.data ?? [];
  } catch {
    eligibleOrders.value = [];
  } finally {
    loading.value = false;
  }
};

const submit = () => {
  form.post(route("returns.store"), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      emit("close");
    },
  });
};
</script>

<template>
  <BottomSheet :show="show" :title="$t('returns.create_modal_title')" size="lg" @close="emit('close')">
    <div v-if="loading" class="text-center py-4 text-muted">…</div>

    <div v-else-if="!canSubmit" class="alert alert-warning">
      {{ $t('returns.form.no_eligible_orders') }}
    </div>

    <form v-else @submit.prevent="submit">
      <p v-if="!preselectedOrderId" class="text-muted small">{{ $t('returns.form.eligible_orders_hint') }}</p>

      <div v-if="preselectedOrderId" class="mb-3">
        <label class="form-label">{{ $t('returns.form.select_order') }}</label>
        <input
          type="text"
          class="form-control"
          disabled
          :value="eligibleOrders.find((o) => o.id == preselectedOrderId)?.tracking_number ?? `#${preselectedOrderId}`"
        />
      </div>

      <div v-else class="mb-3">
        <label class="form-label">{{ $t('returns.form.select_order') }}</label>
        <select v-model="form.order_id" class="form-select" required>
          <option value="" disabled>{{ $t('returns.form.select_order') }}</option>
          <option v-for="order in eligibleOrders" :key="order.id" :value="order.id">
            {{ order.tracking_number }} — {{ order.customer?.full_name }}
            <template v-if="isAdmin && order.seller"> ({{ order.seller.name }})</template>
            ({{ order.status_label }})
          </option>
        </select>
        <div v-if="form.errors.order_id" class="text-danger small mt-1">{{ form.errors.order_id }}</div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ $t('returns.form.reason') }}</label>
        <select v-model="form.reason" class="form-select" required>
          <option value="" disabled>{{ $t('returns.form.select_reason') }}</option>
          <option v-for="r in filterOptions.reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
        <div v-if="form.errors.reason" class="text-danger small mt-1">{{ form.errors.reason }}</div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ $t('returns.form.notes') }}</label>
        <textarea v-model="form.return_notes" class="form-control" rows="3" :placeholder="$t('returns.form.notes_placeholder')"></textarea>
      </div>

      <div class="text-end">
        <button type="button" class="btn btn-light me-2" @click="emit('close')">{{ $t('returns.modal.cancel') }}</button>
        <button type="submit" class="btn btn-success" :disabled="form.processing">{{ $t('returns.modal.submit') }}</button>
      </div>
    </form>
  </BottomSheet>
</template>
