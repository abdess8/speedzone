<script setup>
import { onMounted, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import PickupTimeline from "./Partials/PickupTimeline.vue";
import QrScanner from "./Partials/QrScanner.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import EntityLink from "@/Components/EntityLink.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  pickup: { type: Object, required: true },
  allowedTransitions: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const showQrScanner = ref(false);
const assignForm = useForm({ driver_id: props.pickup.assigned_to ?? "" });

const money = (value) =>
  new Intl.NumberFormat("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));
const empty = () => t("common.empty_value");

const openPdf = () => window.open(route("pickup-requests.pdf", props.pickup.id), "_blank");
const downloadPdf = () => window.open(route("pickup-requests.pdf", { pickupRequest: props.pickup.id, download: 1 }), "_blank");

const assignDriver = () => {
  if (!assignForm.driver_id) return;
  assignForm.post(route("pickup-requests.assign-driver", props.pickup.id), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({ toast: true, position: "top-end", icon: "success", title: t("pickups.swal.driver_assigned"), timer: 2500, showConfirmButton: false });
    },
  });
};

const changeStatus = (status) => {
  Swal.fire({
    title: t("pickups.swal.move_to", { label: status.label }),
    input: "textarea",
    inputLabel: t("pickups.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("pickups.swal.confirm"),
    cancelButtonText: t("common.cancel"),
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
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000 });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="pickup.reference" :pageTitle="$t('pickups.page_title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="`bg-${pickup.status_color}-subtle text-${pickup.status_color}`">
          {{ pickup.status_label }}
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
          <button v-if="can.scan" class="btn btn-sm btn-soft-primary" @click="showQrScanner = true">
            <i class="ri-qr-scan-2-line align-bottom me-1"></i> {{ $t('pickups.qr_scan') }}
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="openPdf">
            <i class="ri-printer-line align-bottom me-1"></i> {{ $t('pickups.show.delivery_note') }}
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="downloadPdf">
            <i class="ri-download-2-line align-bottom me-1"></i> {{ $t('orders.actions.download_pdf') }}
          </button>
          <Link :href="route('pickup-requests.index')" class="btn btn-sm btn-light">
            <i class="ri-arrow-left-line align-bottom me-1"></i> {{ $t('pickups.show.back') }}
          </Link>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="8">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('pickups.show.info') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4"><div class="text-muted fs-13">{{ $t('pickups.show.reference') }}</div><EntityLink type="pickup" :entity="pickup" :show-status="false" size="sm" /></BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('pickups.show.created_by') }}</div>
                <UserAvatar v-if="pickup.creator" :user="pickup.creator" :size="28" clickable show-name show-role />
                <div v-else class="fw-semibold">{{ empty() }}</div>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('pickups.show.assigned_driver') }}</div>
                <UserAvatar v-if="pickup.assignee" :user="pickup.assignee" :size="28" clickable show-name show-role />
                <div v-else class="fw-semibold">{{ empty() }}</div>
              </BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('common.status') }}</div><span class="badge" :class="`bg-${pickup.status_color}-subtle text-${pickup.status_color}`">{{ pickup.status_label }}</span></BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('pickups.show.creation_date') }}</div><div class="fw-semibold">{{ formatDate(pickup.created_at) }}</div></BCol>
              <BCol md="12"><div class="text-muted fs-13">{{ $t('pickups.show.pickup_address') }}</div><div class="fw-semibold">{{ pickup.pickup_address }}</div></BCol>
              <BCol md="12" v-if="pickup.notes"><div class="text-muted fs-13">{{ $t('pickups.show.notes') }}</div><div>{{ pickup.notes }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard no-body v-if="can.assign">
          <BCardHeader><h5 class="card-title mb-0">{{ $t('pickups.show.assign_driver') }}</h5></BCardHeader>
          <BCardBody>
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="form-label">{{ $t('pickups.show.driver') }}</label>
                <select v-model="assignForm.driver_id" class="form-select">
                  <option value="">{{ $t('pickups.show.select_driver') }}</option>
                  <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                    {{ driver.name }} {{ driver.phone ? `(${driver.phone})` : "" }}
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" :disabled="!assignForm.driver_id || assignForm.processing" @click="assignDriver">
                  {{ $t('pickups.show.assign') }}
                </button>
              </div>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('pickups.show.orders_count', { count: pickup.orders?.length ?? 0 }) }}</h5></BCardHeader>
          <BCardBody>
            <div class="table-responsive">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('orders.table.tracking_number') }}</th>
                    <th>{{ $t('orders.table.customer') }}</th>
                    <th>{{ $t('orders.filters.city') }}</th>
                    <th>{{ $t('orders.table.sector') }}</th>
                    <th class="text-end">{{ $t('orders.table.amount') }}</th>
                    <th>{{ $t('common.status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in pickup.orders ?? []" :key="order.id">
                    <td>
                      <EntityLink type="order" :entity="order" :show-status="false" size="sm" />
                    </td>
                    <td>{{ order.customer?.full_name ?? empty() }}</td>
                    <td>{{ order.city?.name ?? empty() }}</td>
                    <td>{{ order.sector?.name ?? empty() }}</td>
                    <td class="text-end">{{ money(order.order_amount) }} MAD</td>
                    <td>
                      <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                        {{ order.status_label }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!(pickup.orders?.length)">
                    <td colspan="6" class="text-center text-muted py-3">{{ $t('orders.no_orders_linked') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('pickups.show.statistics') }}</h5></BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('pickups.show.number_of_packages') }}</span>
              <span class="fw-semibold">{{ pickup.number_of_packages }}</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2">
              <span class="fw-semibold">{{ $t('pickups.show.total_amount') }}</span>
              <span class="fw-bold fs-16 text-primary">{{ money(pickup.total_orders_amount) }} MAD</span>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('pickups.show.status_history') }}</h5></BCardHeader>
          <BCardBody>
            <PickupTimeline :history="pickup.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <QrScanner
      :show="showQrScanner"
      :scan-target-status="can.scan_target_status || 'PICKED_UP'"
      :scan-mode="can.scan_mode || 'driver'"
      @close="showQrScanner = false"
    />
  </Layout>
</template>
