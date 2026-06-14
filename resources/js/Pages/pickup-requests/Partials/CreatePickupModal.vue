<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Swal from "sweetalert2";

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

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");

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

watch(
  () => props.show,
  (visible) => {
    if (!visible) reset();
  }
);

const close = () => emit("close");

const nextStep = () => {
  if (step.value === 1 && !canProceedStep1.value) return;
  if (step.value === 2 && !canProceedStep2.value) return;
  step.value = Math.min(step.value + 1, 3);
};

const prevStep = () => {
  step.value = Math.max(step.value - 1, 1);
};

const submit = () => {
  form.order_ids = [...selectedOrderIds.value];

  form.post(route("pickup-requests.store"), {
    preserveScroll: true,
    onSuccess: () => {
      emit("created");
      close();
    },
    onError: () => {
      Swal.fire({
        icon: "error",
        title: "Could not create pickup request",
        text: Object.values(form.errors).flat().join("\n"),
      });
    },
  });
};
</script>

<template>
  <BModal
    :model-value="show"
    title="Create Pickup Request"
    size="xl"
    hide-footer
    scrollable
    @update:model-value="(v) => !v && close()"
  >
    <div class="mb-4">
      <ul class="nav nav-pills nav-justified wizard-nav mb-3">
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 1, done: step > 1 }">
            <span class="step-number me-1">1</span> Select Orders
          </span>
        </li>
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 2, done: step > 2 }">
            <span class="step-number me-1">2</span> Pickup Address
          </span>
        </li>
        <li class="nav-item">
          <span class="nav-link" :class="{ active: step === 3 }">
            <span class="step-number me-1">3</span> Notes & Confirm
          </span>
        </li>
      </ul>
    </div>

    <!-- Step 1 -->
    <div v-show="step === 1">
      <div v-if="eligibleOrders.length === 0" class="alert alert-warning">
        No orders with status <strong>CREATED</strong> are available for pickup.
      </div>

      <div v-else class="table-responsive">
        <table class="table align-middle table-nowrap mb-0">
          <thead class="table-light text-muted">
            <tr>
              <th style="width: 40px">
                <input class="form-check-input" type="checkbox" :checked="allChecked" @change="toggleAll" />
              </th>
              <th>Tracking #</th>
              <th>Customer</th>
              <th>City</th>
              <th>Sector</th>
              <th class="text-end">Amount</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in eligibleOrders" :key="order.id">
              <td>
                <input class="form-check-input" type="checkbox" :value="order.id" v-model="selectedOrderIds" />
              </td>
              <td class="fw-semibold">{{ order.tracking_number }}</td>
              <td>{{ order.customer?.full_name ?? "—" }}</td>
              <td>{{ order.city?.name ?? "—" }}</td>
              <td>{{ order.sector?.name ?? "—" }}</td>
              <td class="text-end">{{ money(order.order_amount) }} MAD</td>
              <td class="text-muted fs-13">{{ formatDate(order.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="selectedOrderIds.length" class="mt-3 text-muted">
        {{ selectedOrderIds.length }} order(s) selected — Total: <strong>{{ money(totalAmount) }} MAD</strong>
      </div>
    </div>

    <!-- Step 2 -->
    <div v-show="step === 2">
      <div v-if="!hasAddresses" class="alert alert-warning">
        Configure your pickup addresses in your profile before creating a pickup request.
      </div>

      <div v-else class="row g-3">
        <div v-for="address in pickupAddresses" :key="address.key" class="col-md-6">
          <label class="card border h-100 cursor-pointer" :class="{ 'border-primary': form.pickup_address === address.value }">
            <div class="card-body">
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="radio"
                  name="pickup_address"
                  :value="address.value"
                  v-model="form.pickup_address"
                />
                <span class="form-check-label fw-semibold">{{ address.label }}</span>
              </div>
              <p class="text-muted mb-0 mt-2">{{ address.value }}</p>
            </div>
          </label>
        </div>
      </div>
      <div v-if="form.errors.pickup_address" class="text-danger mt-2">{{ form.errors.pickup_address }}</div>
    </div>

    <!-- Step 3 -->
    <div v-show="step === 3">
      <div class="mb-3">
        <label class="form-label">Notes (optional)</label>
        <textarea v-model="form.notes" class="form-control" rows="3" placeholder="Special instructions for the driver…"></textarea>
      </div>

      <BCard no-body class="border">
        <BCardBody>
          <h6 class="mb-3">Summary</h6>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Orders</span>
            <span class="fw-semibold">{{ selectedOrderIds.length }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Packages</span>
            <span class="fw-semibold">{{ selectedOrderIds.length }}</span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Total amount</span>
            <span class="fw-semibold">{{ money(totalAmount) }} MAD</span>
          </div>
          <div class="border-top pt-2 mt-2">
            <div class="text-muted fs-13">Pickup address</div>
            <div>{{ form.pickup_address }}</div>
          </div>
        </BCardBody>
      </BCard>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <div>
        <button v-if="step > 1" type="button" class="btn btn-light" @click="prevStep">Back</button>
      </div>
      <div class="hstack gap-2">
        <button type="button" class="btn btn-light" @click="close">Cancel</button>
        <button
          v-if="step < 3"
          type="button"
          class="btn btn-primary"
          :disabled="(step === 1 && !canProceedStep1) || (step === 2 && !canProceedStep2)"
          @click="nextStep"
        >
          Next
        </button>
        <button
          v-else
          type="button"
          class="btn btn-success"
          :disabled="form.processing"
          @click="submit"
        >
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
          Create Pickup Request
        </button>
      </div>
    </div>
  </BModal>
</template>

<style scoped>
.wizard-nav .nav-link {
  border-radius: 0.375rem;
  color: var(--vz-body-color);
  background: var(--vz-light);
}
.wizard-nav .nav-link.active {
  background: var(--vz-primary);
  color: #fff;
}
.wizard-nav .nav-link.done {
  background: rgba(var(--vz-success-rgb), 0.15);
  color: var(--vz-success);
}
.cursor-pointer {
  cursor: pointer;
}
</style>
