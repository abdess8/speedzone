<script setup>
import { onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderTimeline from "./Partials/OrderTimeline.vue";
import OrderModificationHistory from "./Partials/OrderModificationHistory.vue";
import PaymentMethodBadge from "@/Components/PaymentMethodBadge.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
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

const displayMoney = (value) => (value != null && value !== "" ? `${money(value)} MAD` : "—");

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
          <Link v-if="can.create" :href="route('orders.create', { clone: order.id })" class="btn btn-sm btn-soft-info">
            <i class="ri-file-copy-line align-bottom me-1"></i> Clone
          </Link>
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
              <BCol md="4">
                <div class="text-muted fs-13">Seller</div>
                <UserAvatar
                  v-if="order.seller"
                  :user="order.seller"
                  :size="36"
                  clickable
                  show-name
                />
                <div v-else class="fw-semibold">—</div>
              </BCol>
              <BCol md="4"><div class="text-muted fs-13">Seller Phone</div><div class="fw-semibold">{{ order.seller?.phone ?? "—" }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <!-- Pickup Request -->
        <BCard v-if="order.pickup_request" no-body>
          <BCardHeader><h5 class="card-title mb-0">Pickup Request</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4">
                <div class="text-muted fs-13">Reference</div>
                <Link
                  :href="route('pickup-requests.show', order.pickup_request.id)"
                  class="fw-semibold text-primary text-decoration-none"
                >
                  {{ order.pickup_request.reference }}
                </Link>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">Status</div>
                <span
                  class="badge"
                  :class="`bg-${order.pickup_request.status_color}-subtle text-${order.pickup_request.status_color}`"
                >
                  {{ order.pickup_request.status_label }}
                </span>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">Created Date</div>
                <div class="fw-semibold">{{ formatDate(order.pickup_request.created_at) }}</div>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">Created By</div>
                <div class="fw-semibold">{{ order.pickup_request.created_by?.name ?? "—" }}</div>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">Assigned Driver</div>
                <div class="fw-semibold">{{ order.pickup_request.assigned_driver?.name ?? "—" }}</div>
              </BCol>
              <BCol md="12">
                <div class="text-muted fs-13">Pickup Address</div>
                <div class="fw-semibold">{{ order.pickup_request.pickup_address || "—" }}</div>
              </BCol>
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
              <BCol md="6"><div class="text-muted fs-13">Delivery Sector</div><div class="fw-semibold">{{ order.sector?.name ?? "—" }}</div></BCol>
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

        <!-- Modification History -->
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Modification History</h5></BCardHeader>
          <BCardBody>
            <OrderModificationHistory :history="order.change_history ?? []" />
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
              <PaymentMethodBadge
                :label="order.payment_method_label"
                :emoji="order.payment_method_emoji"
                :color="order.payment_method_color"
              />
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">Cash Collection</span>
              <span class="badge" :class="order.cash_collection_required ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary'">
                {{ order.cash_collection_required ? "YES" : "NO" }}
              </span>
            </div>
            <div v-if="order.order_amount != null" class="d-flex justify-content-between mb-2">
              <span class="text-muted">Amount to Collect</span>
              <span class="fw-semibold">{{ displayMoney(order.amount_to_collect) }}</span>
            </div>
            <div v-if="order.order_value != null" class="d-flex justify-content-between mb-2">
              <span class="text-muted">Order Value</span>
              <span>{{ displayMoney(order.order_value) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Delivery Price</span><span>{{ displayMoney(order.delivery_price) }}</span></div>
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
