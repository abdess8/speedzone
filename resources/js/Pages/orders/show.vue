<script setup>
import { onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderTimeline from "./Partials/OrderTimeline.vue";
import Swal from "sweetalert2";

const props = defineProps({
  order: { type: Object, required: true },
  allowedTransitions: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const formatDate = (value) => (value ? new Date(value).toLocaleString() : "—");

const openPdf = () => window.open(route("orders.pdf", props.order.id), "_blank");
const downloadPdf = () => window.open(route("orders.pdf", { order: props.order.id, download: 1 }), "_blank");

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
      route("orders.bulk-status"),
      { ids: [props.order.id], to_status: status.value, comment: result.value },
      { preserveScroll: true }
    );
  });
};

const confirmDelete = () => {
  Swal.fire({
    title: "Delete this order?",
    text: `Order ${props.order.tracking_number} will be permanently removed.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("orders.destroy", props.order.id));
    }
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
    <PageHeader :title="order.tracking_number" pageTitle="Order Management" />

    <!-- Action bar -->
    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
          {{ order.status_label }}
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
          <Link v-if="can.update" :href="route('orders.edit', order.id)" class="btn btn-sm btn-soft-warning">
            <i class="ri-pencil-line align-bottom me-1"></i> Edit
          </Link>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="openPdf">
            <i class="ri-printer-line align-bottom me-1"></i> Print Ticket
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="downloadPdf">
            <i class="ri-download-2-line align-bottom me-1"></i> Download PDF
          </button>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" @click="confirmDelete">
            <i class="ri-delete-bin-line align-bottom me-1"></i> Delete
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="8">
        <!-- General -->
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">General Information</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4"><div class="text-muted fs-13">Order / Tracking #</div><div class="fw-semibold">{{ order.tracking_number }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">Status</div><span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">{{ order.status_label }}</span></BCol>
              <BCol md="4"><div class="text-muted fs-13">Created</div><div class="fw-semibold">{{ formatDate(order.created_at) }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">Seller</div><div class="fw-semibold">{{ order.seller?.name ?? "—" }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">Seller Phone</div><div class="fw-semibold">{{ order.seller?.phone ?? "—" }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <!-- Customer -->
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Customer Information</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="6"><div class="text-muted fs-13">First Name</div><div class="fw-semibold">{{ order.customer.first_name }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">Last Name</div><div class="fw-semibold">{{ order.customer.last_name }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">Phone</div><div class="fw-semibold">{{ order.customer.phone }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">Delivery City</div><div class="fw-semibold">{{ order.city?.name ?? "—" }}</div></BCol>
              <BCol md="12"><div class="text-muted fs-13">Address</div><div class="fw-semibold">{{ order.customer.address }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <!-- Package -->
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Package Information</h5></BCardHeader>
          <BCardBody>
            <div class="hstack gap-2 mb-3">
              <span class="badge" :class="order.is_fragile ? 'bg-danger-subtle text-danger' : 'bg-light text-muted'">
                <i class="ri-alarm-warning-line me-1"></i>{{ order.is_fragile ? "Fragile" : "Not fragile" }}
              </span>
              <span class="badge" :class="order.can_be_opened ? 'bg-info-subtle text-info' : 'bg-light text-muted'">
                <i class="ri-box-3-line me-1"></i>{{ order.can_be_opened ? "Can be opened" : "Do not open" }}
              </span>
            </div>
            <div class="text-muted fs-13">Notes</div>
            <div>{{ order.notes || "—" }}</div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <!-- Financial -->
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Financial Information</h5></BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Payment Method</span>
              <span class="badge" :class="order.payment_method === 'COD' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'">{{ order.payment_method_label }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Order Amount</span><span>{{ money(order.order_amount) }} MAD</span></div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Delivery Price</span><span>{{ money(order.delivery_price) }} MAD</span></div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2">
              <span class="fw-semibold">Total Amount</span>
              <span class="fw-bold fs-16 text-primary">{{ money(order.total_amount) }} MAD</span>
            </div>
          </BCardBody>
        </BCard>

        <!-- Timeline -->
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Tracking Timeline</h5>
            <Link :href="route('orders.track', order.tracking_number)" class="btn btn-sm btn-link p-0">Open full view</Link>
          </BCardHeader>
          <BCardBody>
            <OrderTimeline :history="order.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
