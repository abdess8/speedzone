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
  contentTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "created"]);

const step = ref(1);
const selectedOrderIds = ref([]);
const selectedReturnIds = ref([]);
const eligibleOrders = ref([]);
const eligibleReturns = ref([]);

const steps = computed(() => [
  { number: 1, label: t("transfers.form.step_content_type") },
  { number: 2, label: t("transfers.form.step_cities") },
  { number: 3, label: t("transfers.form.step_orders") },
  { number: 4, label: t("pickups.modal.step_notes_confirm") },
]);
const loadingOrders = ref(false);

const orderFilters = reactive({
  search: "",
  customer: "",
  created_from: "",
  created_to: "",
});

const form = useForm({
  content_type: "",
  from_city_id: props.defaultFromCityId ?? "",
  to_city_id: "",
  order_ids: [],
  return_ids: [],
  assigned_to: "",
  notes: "",
});

/**
 * The two pools are picked from different tables and validated by different
 * rules, so the manifest declares up front which of them it draws from.
 */
const carriesOrders = computed(() => form.content_type !== "RETURNS");
const carriesReturns = computed(() => form.content_type !== "ORDERS");

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

import { formatMoney as money } from "@/common/formatMoney";

const empty = () => t("common.empty_value");

const totalAmount = computed(() =>
  eligibleOrders.value
    .filter((o) => selectedOrderIds.value.includes(o.id))
    .reduce((sum, o) => sum + Number(o.order_amount || 0), 0)
);

const selectedCount = computed(
  () => selectedOrderIds.value.length + selectedReturnIds.value.length
);

const allOrdersChecked = computed(
  () =>
    eligibleOrders.value.length > 0 &&
    selectedOrderIds.value.length === eligibleOrders.value.length
);

const allReturnsChecked = computed(
  () =>
    eligibleReturns.value.length > 0 &&
    selectedReturnIds.value.length === eligibleReturns.value.length
);

const toggleAllOrders = () => {
  selectedOrderIds.value = allOrdersChecked.value ? [] : eligibleOrders.value.map((o) => o.id);
};

const toggleAllReturns = () => {
  selectedReturnIds.value = allReturnsChecked.value ? [] : eligibleReturns.value.map((r) => r.id);
};

const canProceedStep1 = computed(() => form.content_type !== "");
const canProceedStep2 = computed(
  () => form.from_city_id && form.to_city_id && form.from_city_id !== form.to_city_id
);
const canProceedStep3 = computed(() => selectedCount.value > 0);

const buildParams = () => {
  const params = new URLSearchParams({
    from_city_id: String(form.from_city_id),
    to_city_id: String(form.to_city_id),
  });
  Object.entries(orderFilters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params.set(key, String(value));
  });

  return params;
};

const fetchJson = async (routeName, params) => {
  const response = await fetch(`${route(routeName)}?${params.toString()}`, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  if (!response.ok) throw new Error("Failed to load parcels");

  return (await response.json()).data ?? [];
};

const fetchEligibleParcels = async () => {
  if (!form.from_city_id || !form.to_city_id) {
    eligibleOrders.value = [];
    eligibleReturns.value = [];

    return;
  }

  loadingOrders.value = true;

  try {
    const params = buildParams();

    const [orders, returns] = await Promise.all([
      carriesOrders.value ? fetchJson("transfers.eligible-orders", params) : Promise.resolve([]),
      carriesReturns.value ? fetchJson("transfers.eligible-returns", params) : Promise.resolve([]),
    ]);

    eligibleOrders.value = orders;
    eligibleReturns.value = returns;

    // A narrowed filter can drop a row that was already ticked.
    selectedOrderIds.value = selectedOrderIds.value.filter((id) =>
      eligibleOrders.value.some((o) => o.id === id)
    );
    selectedReturnIds.value = selectedReturnIds.value.filter((id) =>
      eligibleReturns.value.some((r) => r.id === id)
    );
  } catch {
    eligibleOrders.value = [];
    eligibleReturns.value = [];
    Swal.fire({ icon: "error", title: t("transfers.create_failed") });
  } finally {
    loadingOrders.value = false;
  }
};

const applyOrderFilters = () => fetchEligibleParcels();

const resetOrderFilters = () => {
  Object.keys(orderFilters).forEach((key) => (orderFilters[key] = ""));
  fetchEligibleParcels();
};

const reset = () => {
  step.value = 1;
  selectedOrderIds.value = [];
  selectedReturnIds.value = [];
  eligibleOrders.value = [];
  eligibleReturns.value = [];
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

const selectContentType = (value) => {
  if (form.content_type === value) return;

  form.content_type = value;
  // Selections made under the previous type may no longer be offered.
  selectedOrderIds.value = [];
  selectedReturnIds.value = [];
};

const close = () => emit("close");

const nextStep = () => {
  if (step.value === 1 && !canProceedStep1.value) return;
  if (step.value === 2 && !canProceedStep2.value) return;
  if (step.value === 2) fetchEligibleParcels();
  if (step.value === 3 && !canProceedStep3.value) return;
  step.value = Math.min(step.value + 1, 4);
};

const prevStep = () => { step.value = Math.max(step.value - 1, 1); };

const submit = () => {
  form.order_ids = [...selectedOrderIds.value];
  form.return_ids = [...selectedReturnIds.value];
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
      <ul class="nav nav-pills wizard-nav mb-3 flex-nowrap align-items-center gap-2">
        <li v-for="s in steps" :key="s.number" class="nav-item" :class="{ 'flex-grow-1': step === s.number }">
          <span class="nav-link" :class="{ active: step === s.number, done: step > s.number }">
            <span class="step-number">{{ s.number }}</span>
            <span v-if="step === s.number" class="step-label ms-2">{{ s.label }}</span>
          </span>
        </li>
      </ul>
    </div>

    <!-- Asked before the cities, because it decides which pools the next step
         even queries: a returns manifest travels the delivery leg backwards. -->
    <div v-show="step === 1">
      <h5 class="fs-15 mb-1">{{ $t('transfers.form.content_type_question') }}</h5>
      <p class="text-muted mb-3">{{ $t('transfers.form.content_type_hint') }}</p>

      <BRow class="g-3">
        <BCol md="4" v-for="type in contentTypes" :key="type.value">
          <button
            type="button"
            class="content-type-card"
            :class="{ selected: form.content_type === type.value }"
            :aria-pressed="form.content_type === type.value"
            @click="selectContentType(type.value)"
          >
            <span class="content-type-icon" :class="`bg-${type.color}-subtle text-${type.color}`">
              <i :class="type.icon"></i>
            </span>
            <span class="content-type-title">{{ type.label }}</span>
            <span class="content-type-text">{{ type.description }}</span>
            <i v-if="form.content_type === type.value" class="ri-checkbox-circle-fill content-type-check"></i>
          </button>
        </BCol>
      </BRow>

      <div v-if="form.errors.content_type" class="text-danger mt-2">{{ form.errors.content_type }}</div>
    </div>

    <div v-show="step === 2">
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

    <div v-show="step === 3">
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

      <template v-else>
        <section v-if="carriesOrders" class="mb-4">
          <h6 class="parcel-section-title">
            <i class="ri-box-3-line me-1"></i>{{ $t('transfers.form.orders_section') }}
          </h6>
          <div class="alert alert-info py-2 mb-3">
            <i class="ri-information-line me-1"></i>
            {{ $t('transfers.form.eligible_orders_hint', { from: selectedFromCityName, to: selectedToCityName }) }}
          </div>

          <div v-if="eligibleOrders.length === 0" class="alert alert-warning mb-0">
            {{ $t('transfers.form.no_eligible_orders', { from: selectedFromCityName, to: selectedToCityName }) }}
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle table-nowrap mb-0">
              <thead class="table-light text-muted">
                <tr>
                  <th style="width: 40px">
                    <input class="form-check-input" type="checkbox" :checked="allOrdersChecked" @change="toggleAllOrders" />
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
        </section>

        <section v-if="carriesReturns">
          <h6 class="parcel-section-title">
            <i class="ri-arrow-go-back-line me-1"></i>{{ $t('transfers.form.returns_section') }}
          </h6>
          <div class="alert alert-warning py-2 mb-3">
            <i class="ri-information-line me-1"></i>
            {{ $t('transfers.form.eligible_returns_hint', { from: selectedFromCityName, to: selectedToCityName }) }}
          </div>

          <div v-if="eligibleReturns.length === 0" class="alert alert-light border mb-0">
            {{ $t('transfers.form.no_eligible_returns', { from: selectedFromCityName, to: selectedToCityName }) }}
          </div>
          <div v-else class="table-responsive">
            <table class="table align-middle table-nowrap mb-0">
              <thead class="table-light text-muted">
                <tr>
                  <th style="width: 40px">
                    <input class="form-check-input" type="checkbox" :checked="allReturnsChecked" @change="toggleAllReturns" />
                  </th>
                  <th>{{ $t('transfers.form.return_reference') }}</th>
                  <th>{{ $t('orders.table.tracking_number') }}</th>
                  <th>{{ $t('transfers.form.return_seller') }}</th>
                  <th>{{ $t('transfers.form.return_reason') }}</th>
                  <th>{{ $t('common.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="orderReturn in eligibleReturns" :key="orderReturn.id">
                  <td><input class="form-check-input" type="checkbox" :value="orderReturn.id" v-model="selectedReturnIds" /></td>
                  <td class="fw-semibold">{{ orderReturn.reference }}</td>
                  <td>{{ orderReturn.order?.tracking_number ?? empty() }}</td>
                  <td>{{ orderReturn.order?.seller?.full_name ?? orderReturn.order?.seller?.name ?? empty() }}</td>
                  <td>{{ orderReturn.reason_label }}</td>
                  <td>
                    <span class="badge" :class="`bg-${orderReturn.status_color}-subtle text-${orderReturn.status_color}`">
                      {{ orderReturn.status_label }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>

      <div v-if="selectedCount" class="mt-3 text-muted">
        {{ $t('transfers.form.selected_count', { count: selectedCount }) }} — {{ money(totalAmount) }}
      </div>
    </div>

    <div v-show="step === 4">
      <div class="mb-3">
        <label class="form-label">{{ $t('transfers.form.notes') }}</label>
        <textarea v-model="form.notes" class="form-control" rows="3" :placeholder="$t('transfers.form.notes_placeholder')"></textarea>
      </div>
      <BCard no-body class="border">
        <BCardBody>
          <div v-if="carriesOrders" class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('transfers.form.orders_section') }}</span>
            <span class="fw-semibold">{{ selectedOrderIds.length }}</span>
          </div>
          <div v-if="carriesReturns" class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('transfers.form.returns_section') }}</span>
            <span class="fw-semibold">{{ selectedReturnIds.length }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">{{ $t('transfers.table.packages') }}</span>
            <span class="fw-semibold">{{ selectedCount }}</span>
          </div>
          <div class="d-flex justify-content-between">
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
          v-if="step < 4"
          type="button"
          class="btn btn-primary"
          :disabled="
            (step === 1 && !canProceedStep1) ||
            (step === 2 && !canProceedStep2) ||
            (step === 3 && !canProceedStep3)
          "
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
.wizard-nav .nav-link {
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
  border-radius: 0.375rem;
  color: var(--vz-body-color);
  background: var(--vz-light);
}
.wizard-nav .nav-link.active { background: var(--vz-primary); color: #fff; }
.wizard-nav .nav-link.done { background: rgba(var(--vz-success-rgb), 0.15); color: var(--vz-success); }
.wizard-nav .step-label { overflow: hidden; text-overflow: ellipsis; }

.content-type-card {
  position: relative;
  display: flex;
  width: 100%;
  height: 100%;
  flex-direction: column;
  align-items: flex-start;
  padding: 1.15rem;
  border: 2px solid var(--vz-border-color);
  border-radius: 0.75rem;
  background: var(--vz-card-bg);
  text-align: start;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
}

.content-type-card:hover {
  border-color: var(--vz-primary);
  transform: translateY(-2px);
}

.content-type-card.selected {
  border-color: var(--vz-primary);
  box-shadow: 0 0 0 3px rgba(var(--vz-primary-rgb), 0.12);
}

.content-type-icon {
  display: inline-flex;
  width: 2.75rem;
  height: 2.75rem;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.75rem;
  border-radius: 0.65rem;
  font-size: 1.35rem;
}

.content-type-title {
  margin-bottom: 0.25rem;
  font-size: 0.9375rem;
  font-weight: 600;
}

.content-type-text {
  color: var(--vz-secondary-color);
  font-size: 0.8125rem;
  line-height: 1.4;
}

.content-type-check {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  color: var(--vz-primary);
  font-size: 1.15rem;
}

.parcel-section-title {
  margin-bottom: 0.65rem;
  color: var(--vz-secondary-color);
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

@media (prefers-reduced-motion: reduce) {
  .content-type-card,
  .content-type-card:hover {
    transform: none;
    transition: none;
  }
}
</style>
