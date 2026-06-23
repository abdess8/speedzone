<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
  eligibleOrders: { type: Array, default: () => [] },
  pickupAddresses: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "created"]);

const step = ref(1);
const selectedOrderIds = ref([]);

const form = useForm({
  order_ids: [],
  pickup_address: "",
  notes: "",
});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : t("common.empty_value"));
const empty = () => t("common.empty_value");

const totalAmount = computed(() =>
  props.eligibleOrders
    .filter((o) => selectedOrderIds.value.includes(o.id))
    .reduce((sum, o) => sum + Number(o.order_amount || 0), 0)
);

const allChecked = computed(
  () => props.eligibleOrders.length > 0 && selectedOrderIds.value.length === props.eligibleOrders.length
);

const toggleAll = () => {
  selectedOrderIds.value = allChecked.value ? [] : props.eligibleOrders.map((o) => o.id);
};

const canProceedStep1 = computed(() => selectedOrderIds.value.length > 0);
const canProceedStep2 = computed(() => !!form.pickup_address);
const hasAddresses = computed(() => props.pickupAddresses.length > 0);

const reset = () => {
  step.value = 1;
  selectedOrderIds.value = [];
  form.reset();
  form.clearErrors();
};

watch(() => props.show, (visible) => { if (!visible) reset(); });

const close = () => emit("close");
const nextStep = () => {
  if (step.value === 1 && !canProceedStep1.value) return;
  if (step.value === 2 && !canProceedStep2.value) return;
  step.value = Math.min(step.value + 1, 3);
};
const prevStep = () => { step.value = Math.max(step.value - 1, 1); };

const submit = () => {
  form.order_ids = [...selectedOrderIds.value];
  form.post(route("pickup-requests.store"), {
    preserveScroll: true,
    onSuccess: () => { emit("created"); close(); },
    onError: () => {
      Swal.fire({
        icon: "error",
        title: t("pickups.create_failed"),
        text: Object.values(form.errors).flat().join("\n"),
      });
    },
  });
};
</script>

<template>
  <BModal
    :model-value="show"
    :title="$t('pickups.create_modal_title')"
    size="xl"
    hide-footer
    scrollable
    @update:model-value="(v) => !v && close()"
  >
    <div class="mb-4">
      <ul class="nav nav-pills nav-justified wizard-nav mb-3">
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 1, done: step > 1 }">
            <span class="step-number me-1">1</span> {{ $t('pickups.modal.step_select_orders') }}
          </span>
        </li>
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 2, done: step > 2 }">
            <span class="step-number me-1">2</span> {{ $t('pickups.modal.step_pickup_address') }}
          </span>
        </li>
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 3 }">
            <span class="step-number me-1">3</span> {{ $t('pickups.modal.step_notes_confirm') }}
          </span>
        </li>
      </ul>
    </div>

    <div v-show="step === 1">
      <div v-if="eligibleOrders.length === 0" class="alert alert-warning">
        {{ $t('pickups.modal.no_eligible_orders') }}
      </div>

      <div v-else class="table-responsive">
        <table class="table align-middle table-nowrap mb-0">
          <thead class="table-light text-muted">
            <tr>
              <th style="width: 40px">
                <input class="form-check-input" type="checkbox" :checked="allChecked" @change="toggleAll" />
              </th>
              <th>{{ $t('orders.table.tracking_number') }}</th>
              <th>{{ $t('orders.table.customer') }}</th>
              <th>{{ $t('orders.filters.city') }}</th>
              <th>{{ $t('orders.table.sector') }}</th>
              <th class="text-end">{{ $t('orders.table.amount') }}</th>
              <th>{{ $t('orders.table.created') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in eligibleOrders" :key="order.id">
              <td><input class="form-check-input" type="checkbox" :value="order.id" v-model="selectedOrderIds" /></td>
              <td class="fw-semibold">{{ order.tracking_number }}</td>
              <td>{{ order.customer?.full_name ?? empty() }}</td>
              <td>{{ order.city?.name ?? empty() }}</td>
              <td>{{ order.sector?.name ?? empty() }}</td>
              <td class="text-end">{{ money(order.order_amount) }}</td>
              <td class="text-muted fs-13">{{ formatDate(order.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="selectedOrderIds.length" class="mt-3 text-muted">
        {{ $t('pickups.modal.orders_selected', { count: selectedOrderIds.length, amount: money(totalAmount) }) }}
      </div>
    </div>

    <div v-show="step === 2">
      <div v-if="!hasAddresses" class="alert alert-warning">
        {{ $t('pickups.modal.no_addresses') }}
      </div>
      <div v-else class="row g-3">
        <div v-for="address in pickupAddresses" :key="address.key" class="col-md-6">
          <label class="card border h-100 cursor-pointer" :class="{ 'border-primary': form.pickup_address === address.value }">
            <div class="card-body">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="pickup_address" :value="address.value" v-model="form.pickup_address" />
                <span class="form-check-label fw-semibold">{{ address.label }}</span>
              </div>
              <p class="text-muted mb-0 mt-2">{{ address.value }}</p>
            </div>
          </label>
        </div>
      </div>
      <div v-if="form.errors.pickup_address" class="text-danger mt-2">{{ form.errors.pickup_address }}</div>
    </div>

    <div v-show="step === 3">
      <div class="mb-3">
        <label class="form-label">{{ $t('pickups.modal.notes_optional') }}</label>
        <textarea v-model="form.notes" class="form-control" rows="3" :placeholder="$t('pickups.form.notes_placeholder')"></textarea>
      </div>
      <BCard no-body class="border">
        <BCardBody>
          <h6 class="mb-3">{{ $t('pickups.modal.summary') }}</h6>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('orders.title') }}</span>
            <span class="fw-semibold">{{ selectedOrderIds.length }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('pickups.modal.packages') }}</span>
            <span class="fw-semibold">{{ selectedOrderIds.length }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('pickups.show.total_amount') }}</span>
            <span class="fw-semibold">{{ money(totalAmount) }}</span>
          </div>
          <div class="border-top pt-2 mt-2">
            <div class="text-muted fs-13">{{ $t('pickups.modal.pickup_address') }}</div>
            <div>{{ form.pickup_address }}</div>
          </div>
        </BCardBody>
      </BCard>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <div>
        <button v-if="step > 1" type="button" class="btn btn-light" @click="prevStep">{{ $t('pickups.modal.back') }}</button>
      </div>
      <div class="hstack gap-2">
        <button type="button" class="btn btn-light" @click="close">{{ $t('common.cancel') }}</button>
        <button
          v-if="step < 3"
          type="button"
          class="btn btn-primary"
          :disabled="(step === 1 && !canProceedStep1) || (step === 2 && !canProceedStep2)"
          @click="nextStep"
        >
          {{ $t('pickups.modal.next') }}
        </button>
        <button v-else type="button" class="btn btn-success" :disabled="form.processing" @click="submit">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
          {{ $t('pickups.create') }}
        </button>
      </div>
    </div>
  </BModal>
</template>

<style scoped>
.wizard-nav .nav-link { border-radius: 0.375rem; color: var(--vz-body-color); background: var(--vz-light); }
.wizard-nav .nav-link.active { background: var(--vz-primary); color: #fff; }
.wizard-nav .nav-link.done { background: rgba(var(--vz-success-rgb), 0.15); color: var(--vz-success); }
.cursor-pointer { cursor: pointer; }
</style>
