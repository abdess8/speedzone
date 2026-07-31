<script setup>
import { computed, ref, watch, reactive } from "vue";
import { useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Swal from "sweetalert2";
import BottomSheet from "@/Components/BottomSheet.vue";

const { t } = useI18n();

const props = defineProps({
  show: { type: Boolean, default: false },
  cities: { type: Array, default: () => [] },
  defaultFromCityId: { type: [Number, String, null], default: null },
  staff: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "created"]);

const step = ref(1);
const selectedOrderIds = ref([]);
const eligibleOrders = ref([]);
const loadingOrders = ref(false);

const orderFilters = reactive({
  search: "",
  customer: "",
  created_from: "",
  created_to: "",
});

const form = useForm({
  from_city_id: props.defaultFromCityId ?? "",
  to_city_id: "",
  order_ids: [],
  assigned_to: "",
  notes: "",
});

const cityOptions = computed(() =>
  props.cities.map((c) => ({ value: c.id, label: c.name }))
);

const toCityOptions = computed(() =>
  cityOptions.value.filter((c) => String(c.value) !== String(form.from_city_id))
);

const selectedFromCityName = computed(() =>
  props.cities.find((c) => String(c.id) === String(form.from_city_id))?.name ?? ""
);

const selectedToCityName = computed(() =>
  props.cities.find((c) => String(c.id) === String(form.to_city_id))?.name ?? ""
);

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const empty = () => t("common.empty_value");

const totalAmount = computed(() =>
  eligibleOrders.value
    .filter((o) => selectedOrderIds.value.includes(o.id))
    .reduce((sum, o) => sum + Number(o.order_amount || 0), 0)
);

const allChecked = computed(
  () => eligibleOrders.value.length > 0 && selectedOrderIds.value.length === eligibleOrders.value.length
);

const toggleAll = () => {
  selectedOrderIds.value = allChecked.value ? [] : eligibleOrders.value.map((o) => o.id);
};

const canProceedStep1 = computed(() => form.from_city_id && form.to_city_id && form.from_city_id !== form.to_city_id);
const canProceedStep2 = computed(() => selectedOrderIds.value.length > 0);

const fetchEligibleOrders = async () => {
  if (!form.from_city_id || !form.to_city_id) {
    eligibleOrders.value = [];
    return;
  }

  loadingOrders.value = true;

  try {
    const params = new URLSearchParams({
      from_city_id: String(form.from_city_id),
      to_city_id: String(form.to_city_id),
    });
    Object.entries(orderFilters).forEach(([key, value]) => {
      if (value !== "" && value !== null) params.set(key, String(value));
    });

    const response = await fetch(`${route("transfers.eligible-orders")}?${params.toString()}`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!response.ok) throw new Error("Failed to load orders");

    const json = await response.json();
    eligibleOrders.value = json.data ?? [];
    selectedOrderIds.value = selectedOrderIds.value.filter((id) =>
      eligibleOrders.value.some((o) => o.id === id)
    );
  } catch {
    eligibleOrders.value = [];
    Swal.fire({ icon: "error", title: t("transfers.create_failed") });
  } finally {
    loadingOrders.value = false;
  }
};

const applyOrderFilters = () => fetchEligibleOrders();

const resetOrderFilters = () => {
  Object.keys(orderFilters).forEach((key) => (orderFilters[key] = ""));
  fetchEligibleOrders();
};

const reset = () => {
  step.value = 1;
  selectedOrderIds.value = [];
  eligibleOrders.value = [];
  Object.keys(orderFilters).forEach((key) => (orderFilters[key] = ""));
  form.reset();
  form.from_city_id = props.defaultFromCityId ?? "";
  form.clearErrors();
};

watch(() => props.show, (visible) => {
  if (visible) {
    form.from_city_id = props.defaultFromCityId ?? "";
  } else {
    reset();
  }
});

const close = () => emit("close");

const nextStep = () => {
  if (step.value === 1 && !canProceedStep1.value) return;
  if (step.value === 1) fetchEligibleOrders();
  if (step.value === 2 && !canProceedStep2.value) return;
  step.value = Math.min(step.value + 1, 3);
};

const prevStep = () => { step.value = Math.max(step.value - 1, 1); };

const submit = () => {
  form.order_ids = [...selectedOrderIds.value];
  form.post(route("transfers.store"), {
    preserveScroll: true,
    onSuccess: () => { emit("created"); close(); },
    onError: () => {
      Swal.fire({
        icon: "error",
        title: t("transfers.create_failed"),
        text: Object.values(form.errors).flat().join("\n"),
      });
    },
  });
};
</script>

<template>
  <BottomSheet :show="show" :title="$t('transfers.create_modal_title')" size="xl" @close="close">
    <div class="mb-4">
      <ul class="nav nav-pills nav-justified wizard-nav mb-3">
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 1, done: step > 1 }">
            <span class="step-number me-1">1</span> {{ $t('transfers.form.step_cities') }}
          </span>
        </li>
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 2, done: step > 2 }">
            <span class="step-number me-1">2</span> {{ $t('transfers.form.step_orders') }}
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
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">{{ $t('transfers.form.from_city') }} <span class="text-danger">*</span></label>
          <Multiselect
            v-model="form.from_city_id"
            :options="cityOptions"
            :searchable="true"
            :close-on-select="true"
            :placeholder="$t('transfers.form.select_from_city')"
          />
          <div v-if="form.errors.from_city_id" class="text-danger mt-1">{{ form.errors.from_city_id }}</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ $t('transfers.form.to_city') }} <span class="text-danger">*</span></label>
          <Multiselect
            v-model="form.to_city_id"
            :options="toCityOptions"
            :searchable="true"
            :close-on-select="true"
            :placeholder="$t('transfers.form.select_to_city')"
          />
          <div v-if="form.errors.to_city_id" class="text-danger mt-1">{{ form.errors.to_city_id }}</div>
        </div>
        <div class="col-md-12">
          <label class="form-label">{{ $t('transfers.form.assigned_to') }}</label>
          <select v-model="form.assigned_to" class="form-select">
            <option value="">{{ $t('transfers.show.select_staff') }}</option>
            <option v-for="member in staff" :key="member.id" :value="member.id">
              {{ member.name }} {{ member.phone ? `(${member.phone})` : "" }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div v-show="step === 2">
      <div class="alert alert-info py-2 mb-3">
        <i class="ri-information-line me-1"></i>
        {{ $t('transfers.form.eligible_orders_hint', { from: selectedFromCityName, to: selectedToCityName }) }}
      </div>

      <div class="border rounded p-3 mb-3 bg-light-subtle">
        <BRow class="g-2">
          <BCol md="4">
            <label class="form-label">{{ $t('orders.table.tracking_number') }}</label>
            <input v-model="orderFilters.search" type="text" class="form-control form-control-sm" @keyup.enter="applyOrderFilters" />
          </BCol>
          <BCol md="4">
            <label class="form-label">{{ $t('orders.table.customer') }}</label>
            <input v-model="orderFilters.customer" type="text" class="form-control form-control-sm" @keyup.enter="applyOrderFilters" />
          </BCol>
          <BCol md="4">
            <label class="form-label">{{ $t('transfers.filters.created_from') }}</label>
            <input v-model="orderFilters.created_from" type="date" class="form-control form-control-sm" />
          </BCol>
          <BCol md="4">
            <label class="form-label">{{ $t('transfers.filters.created_to') }}</label>
            <input v-model="orderFilters.created_to" type="date" class="form-control form-control-sm" />
          </BCol>
          <BCol cols="12" class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-sm btn-light" @click="resetOrderFilters">{{ $t('common.reset') }}</button>
            <button type="button" class="btn btn-sm btn-primary" @click="applyOrderFilters">
              <i class="ri-search-line me-1"></i>{{ $t('common.apply_filters') }}
            </button>
          </BCol>
        </BRow>
      </div>

      <div v-if="loadingOrders" class="text-center py-4">
        <span class="spinner-border text-primary"></span>
      </div>
      <div v-else-if="eligibleOrders.length === 0" class="alert alert-warning">
        {{ $t('transfers.form.no_eligible_orders', { from: selectedFromCityName, to: selectedToCityName }) }}
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
              <th>{{ $t('orders.show.pickup_city') }}</th>
              <th>{{ $t('orders.show.delivery_city') }}</th>
              <th class="text-end">{{ $t('orders.table.amount') }}</th>
              <th>{{ $t('common.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in eligibleOrders" :key="order.id">
              <td><input class="form-check-input" type="checkbox" :value="order.id" v-model="selectedOrderIds" /></td>
              <td class="fw-semibold">{{ order.tracking_number }}</td>
              <td>{{ order.customer?.full_name ?? empty() }}</td>
              <td>{{ order.pickup_city?.name ?? order.seller?.city?.name ?? empty() }}</td>
              <td>{{ order.city?.name ?? empty() }}</td>
              <td class="text-end">{{ money(order.order_amount) }}</td>
              <td>
                <span class="badge bg-primary-subtle text-primary">{{ order.status_label }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="selectedOrderIds.length" class="mt-3 text-muted">
        {{ $t('transfers.form.selected_count', { count: selectedOrderIds.length }) }} — {{ money(totalAmount) }}
      </div>
    </div>

    <div v-show="step === 3">
      <div class="mb-3">
        <label class="form-label">{{ $t('transfers.form.notes') }}</label>
        <textarea v-model="form.notes" class="form-control" rows="3" :placeholder="$t('transfers.form.notes_placeholder')"></textarea>
      </div>
      <BCard no-body class="border">
        <BCardBody>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('transfers.table.packages') }}</span>
            <span class="fw-semibold">{{ selectedOrderIds.length }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('transfers.show.total_amount') }}</span>
            <span class="fw-semibold">{{ money(totalAmount) }}</span>
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
          {{ $t('transfers.modal.submit') }}
        </button>
      </div>
    </div>
  </BottomSheet>
</template>

<style scoped>
.wizard-nav .nav-link { border-radius: 0.375rem; color: var(--vz-body-color); background: var(--vz-light); }
.wizard-nav .nav-link.active { background: var(--vz-primary); color: #fff; }
.wizard-nav .nav-link.done { background: rgba(var(--vz-success-rgb), 0.15); color: var(--vz-success); }
</style>
