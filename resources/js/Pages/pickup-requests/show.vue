<script setup>
import { onMounted, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import PickupTimeline from "./Partials/PickupTimeline.vue";
import QrScanner from "./Partials/QrScanner.vue";
import Swal from "sweetalert2";

const props = defineProps({
  pickup: { type: Object, required: true },
  allowedTransitions: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const showQrScanner = ref(false);
const assignForm = useForm({ driver_id: props.pickup.assigned_to ?? "" });

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const formatDate = (value) => (value ? new Date(value).toLocaleString() : "—");

const openPdf = () => window.open(route("pickup-requests.pdf", props.pickup.id), "_blank");
const downloadPdf = () => window.open(route("pickup-requests.pdf", { pickupRequest: props.pickup.id, download: 1 }), "_blank");

const assignDriver = () => {
  if (!assignForm.driver_id) return;

  assignForm.post(route("pickup-requests.assign-driver", props.pickup.id), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({ toast: true, position: "top-end", icon: "success", title: "Driver assigned", timer: 2500, showConfirmButton: false });
    },
  });
};

const changeStatus = (status) => {
  Swal.fire({
    title: `Move to "${status.label}"?`,
    input: "textarea",
    inputLabel: "Optional comment",
    showCancelButton: true,
    confirmButtonText: "Confirm",
    confirmButtonColor: "#0ab39c",
  }).then((result) => {
    if (!result.isConfirmed) return;

    router.post(
      route("pickup-requests.change-status", props.pickup.id),
      { status: status.value, comment: result.value },
      { preserveScroll: true }
    );
  });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: success,
      showConfirmButton: false,
      timer: 3000,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="pickup.reference" pageTitle="Pickup Management" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="`bg-${pickup.status_color}-subtle text-${pickup.status_color}`">
          {{ pickup.status_label }}
        </span>
        <div class="vr"></div>

        <div class="dropdown" v-if="allowedTransitions.length">
          <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="ri-exchange-line align-bottom me-1"></i> Update Status
          </button>
          <ul class="dropdown-menu">
            <li v-for="t in allowedTransitions" :key="t.value">
              <button class="dropdown-item" @click="changeStatus(t)">
                <i :class="`ri-circle-fill text-${t.color} me-2 fs-10`"></i>{{ t.label }}
              </button>
            </li>
          </ul>
        </div>

        <div class="ms-auto hstack gap-2">
          <button v-if="can.scan" class="btn btn-sm btn-soft-primary" @click="showQrScanner = true">
            <i class="ri-qr-scan-2-line align-bottom me-1"></i> QR Scan
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="openPdf">
            <i class="ri-printer-line align-bottom me-1"></i> Delivery Note
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="downloadPdf">
            <i class="ri-download-2-line align-bottom me-1"></i> Download PDF
          </button>
          <Link :href="route('pickup-requests.index')" class="btn btn-sm btn-light">
            <i class="ri-arrow-left-line align-bottom me-1"></i> Back
          </Link>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="8">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Pickup Information</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4"><div class="text-muted fs-13">Reference</div><div class="fw-semibold">{{ pickup.reference }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">Created By</div><div class="fw-semibold">{{ pickup.creator?.name ?? "—" }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">Assigned Driver</div><div class="fw-semibold">{{ pickup.assignee?.name ?? "—" }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">Status</div><span class="badge" :class="`bg-${pickup.status_color}-subtle text-${pickup.status_color}`">{{ pickup.status_label }}</span></BCol>
              <BCol md="4"><div class="text-muted fs-13">Creation Date</div><div class="fw-semibold">{{ formatDate(pickup.created_at) }}</div></BCol>
              <BCol md="12"><div class="text-muted fs-13">Pickup Address</div><div class="fw-semibold">{{ pickup.pickup_address }}</div></BCol>
              <BCol md="12" v-if="pickup.notes"><div class="text-muted fs-13">Notes</div><div>{{ pickup.notes }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard no-body v-if="can.assign">
          <BCardHeader><h5 class="card-title mb-0">Assign Driver</h5></BCardHeader>
          <BCardBody>
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="form-label">Driver</label>
                <select v-model="assignForm.driver_id" class="form-select">
                  <option value="">Select a driver…</option>
                  <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                    {{ driver.name }} {{ driver.phone ? `(${driver.phone})` : "" }}
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" :disabled="!assignForm.driver_id || assignForm.processing" @click="assignDriver">
                  Assign
                </button>
              </div>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Orders ({{ pickup.orders?.length ?? 0 }})</h5></BCardHeader>
          <BCardBody>
            <div class="table-responsive">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>Tracking #</th>
                    <th>Customer</th>
                    <th>City</th>
                    <th>Sector</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in pickup.orders ?? []" :key="order.id">
                    <td>
                      <Link :href="route('orders.show', order.id)" class="fw-semibold">{{ order.tracking_number }}</Link>
                    </td>
                    <td>{{ order.customer?.full_name ?? "—" }}</td>
                    <td>{{ order.city?.name ?? "—" }}</td>
                    <td>{{ order.sector?.name ?? "—" }}</td>
                    <td class="text-end">{{ money(order.order_amount) }} MAD</td>
                    <td>
                      <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                        {{ order.status_label }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!(pickup.orders?.length)">
                    <td colspan="6" class="text-center text-muted py-3">No orders linked.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Statistics</h5></BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Number of packages</span>
              <span class="fw-semibold">{{ pickup.number_of_packages }}</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2">
              <span class="fw-semibold">Total amount</span>
              <span class="fw-bold fs-16 text-primary">{{ money(pickup.total_orders_amount) }} MAD</span>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Status History</h5></BCardHeader>
          <BCardBody>
            <PickupTimeline :history="pickup.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <QrScanner :show="showQrScanner" @close="showQrScanner = false" />
  </Layout>
</template>
