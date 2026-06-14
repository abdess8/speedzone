<script setup>
import { onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderTimeline from "./Partials/OrderTimeline.vue";
import OrderModificationHistory from "./Partials/OrderModificationHistory.vue";
import PaymentMethodBadge from "@/Components/PaymentMethodBadge.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  order: { type: Object, required: true },
  allowedTransitions: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const money = (value) =>
  new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const displayMoney = (value) => (value != null && value !== "" ? `${money(value)} MAD` : t("common.empty_value"));
const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));
const empty = () => t("common.empty_value");

const openPdf = () => window.open(route("orders.pdf", props.order.id), "_blank");
const downloadPdf = () => window.open(route("orders.pdf", { order: props.order.id, download: 1 }), "_blank");

const changeStatus = (status) => {
  Swal.fire({
    title: t("orders.swal.move_to", { label: status.label }),
    input: "textarea",
    inputLabel: t("orders.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("orders.swal.confirm"),
    cancelButtonText: t("common.cancel"),
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
    title: t("orders.swal.delete_title"),
    text: t("orders.swal.delete_text", { tracking: props.order.tracking_number }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_delete"),
    cancelButtonText: t("common.cancel"),
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
    <PageHeader :title="order.tracking_number" :pageTitle="$t('orders.page_title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
          {{ order.status_label }}
        </span>
        <div class="vr"></div>

        <div class="dropdown" v-if="allowedTransitions.length">
          <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="ri-exchange-line align-bottom me-1"></i> {{ $t('orders.actions.update_status') }}
          </button>
          <ul class="dropdown-menu">
            <li v-for="trans in allowedTransitions" :key="trans.value">
              <button class="dropdown-item" @click="changeStatus(trans)">
                <i :class="`ri-circle-fill text-${trans.color} me-2 fs-10`"></i>{{ trans.label }}
              </button>
            </li>
          </ul>
        </div>

        <div class="ms-auto hstack gap-2">
          <Link v-if="can.create" :href="route('orders.create', { clone: order.id })" class="btn btn-sm btn-soft-info">
            <i class="ri-file-copy-line align-bottom me-1"></i> {{ $t('orders.actions.clone') }}
          </Link>
          <Link v-if="can.update" :href="route('orders.edit', order.id)" class="btn btn-sm btn-soft-warning">
            <i class="ri-pencil-line align-bottom me-1"></i> {{ $t('common.edit') }}
          </Link>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="openPdf">
            <i class="ri-printer-line align-bottom me-1"></i> {{ $t('orders.actions.print_ticket') }}
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="downloadPdf">
            <i class="ri-download-2-line align-bottom me-1"></i> {{ $t('orders.actions.download_pdf') }}
          </button>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" @click="confirmDelete">
            <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('common.delete') }}
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="8">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('orders.show.general') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4"><div class="text-muted fs-13">{{ $t('orders.table.tracking_number') }}</div><div class="fw-semibold">{{ order.tracking_number }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('common.status') }}</div><span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">{{ order.status_label }}</span></BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('orders.table.created') }}</div><div class="fw-semibold">{{ formatDate(order.created_at) }}</div></BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('orders.filters.seller') }}</div>
                <UserAvatar v-if="order.seller" :user="order.seller" :size="36" clickable show-name />
                <div v-else class="fw-semibold">{{ empty() }}</div>
              </BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('orders.show.seller_phone') }}</div><div class="fw-semibold">{{ order.seller?.phone ?? empty() }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard v-if="order.pickup_request" no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('orders.show.pickup_request') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('orders.show.reference') }}</div>
                <Link :href="route('pickup-requests.show', order.pickup_request.id)" class="fw-semibold text-primary text-decoration-none">
                  {{ order.pickup_request.reference }}
                </Link>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('common.status') }}</div>
                <span class="badge" :class="`bg-${order.pickup_request.status_color}-subtle text-${order.pickup_request.status_color}`">
                  {{ order.pickup_request.status_label }}
                </span>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('orders.show.created_date') }}</div>
                <div class="fw-semibold">{{ formatDate(order.pickup_request.created_at) }}</div>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('orders.show.created_by') }}</div>
                <div class="fw-semibold">{{ order.pickup_request.created_by?.name ?? empty() }}</div>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('orders.show.assigned_driver') }}</div>
                <div class="fw-semibold">{{ order.pickup_request.assigned_driver?.name ?? empty() }}</div>
              </BCol>
              <BCol md="12">
                <div class="text-muted fs-13">{{ $t('orders.show.pickup_address') }}</div>
                <div class="fw-semibold">{{ order.pickup_request.pickup_address || empty() }}</div>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('orders.show.customer') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="6"><div class="text-muted fs-13">{{ $t('orders.show.first_name') }}</div><div class="fw-semibold">{{ order.customer.first_name }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">{{ $t('orders.show.last_name') }}</div><div class="fw-semibold">{{ order.customer.last_name }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">{{ $t('orders.show.phone') }}</div><div class="fw-semibold">{{ order.customer.phone }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">{{ $t('orders.show.delivery_city') }}</div><div class="fw-semibold">{{ order.city?.name ?? empty() }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">{{ $t('orders.show.delivery_sector') }}</div><div class="fw-semibold">{{ order.sector?.name ?? empty() }}</div></BCol>
              <BCol md="12"><div class="text-muted fs-13">{{ $t('orders.show.address') }}</div><div class="fw-semibold">{{ order.customer.address }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('orders.show.package') }}</h5></BCardHeader>
          <BCardBody>
            <div class="hstack gap-2 mb-3">
              <span class="badge" :class="order.is_fragile ? 'bg-danger-subtle text-danger' : 'bg-light text-muted'">
                <i class="ri-alarm-warning-line me-1"></i>{{ order.is_fragile ? $t('orders.badges.fragile') : $t('orders.badges.not_fragile') }}
              </span>
              <span class="badge" :class="order.can_be_opened ? 'bg-info-subtle text-info' : 'bg-light text-muted'">
                <i class="ri-box-3-line me-1"></i>{{ order.can_be_opened ? $t('orders.badges.openable') : $t('orders.badges.do_not_open') }}
              </span>
            </div>
            <div class="text-muted fs-13">{{ $t('orders.show.notes') }}</div>
            <div>{{ order.notes || empty() }}</div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('orders.show.modification_history') }}</h5></BCardHeader>
          <BCardBody>
            <OrderModificationHistory :history="order.change_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('orders.show.financial') }}</h5></BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('orders.filters.payment_method') }}</span>
              <PaymentMethodBadge
                :label="order.payment_method_label"
                :emoji="order.payment_method_emoji"
                :color="order.payment_method_color"
              />
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('orders.show.cash_collection') }}</span>
              <span class="badge" :class="order.cash_collection_required ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary'">
                {{ order.cash_collection_required ? $t('common.yes') : $t('common.no') }}
              </span>
            </div>
            <div v-if="order.order_amount != null" class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('orders.show.amount_to_collect') }}</span>
              <span class="fw-semibold">{{ displayMoney(order.amount_to_collect) }}</span>
            </div>
            <div v-if="order.order_value != null" class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('orders.table.order_value') }}</span>
              <span>{{ displayMoney(order.order_value) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ $t('orders.show.delivery_price') }}</span><span>{{ displayMoney(order.delivery_price) }}</span></div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2">
              <span class="fw-semibold">{{ $t('orders.show.total_amount') }}</span>
              <span class="fw-bold fs-16 text-primary">{{ money(order.total_amount) }} MAD</span>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">{{ $t('orders.show.tracking_timeline') }}</h5>
            <Link :href="route('orders.track', order.tracking_number)" class="btn btn-sm btn-link p-0">{{ $t('orders.show.open_full_view') }}</Link>
          </BCardHeader>
          <BCardBody>
            <OrderTimeline :history="order.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
