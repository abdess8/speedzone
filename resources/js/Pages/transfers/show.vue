<script setup>
import { onMounted, ref } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import TransferTimeline from "./Partials/TransferTimeline.vue";
import TransferQrScanner from "./Partials/TransferQrScanner.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import EntityLink from "@/Components/EntityLink.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  transfer: { type: Object, required: true },
  allowedTransitions: { type: Array, default: () => [] },
  qrCode: { type: String, default: null },
  staff: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const showQrScanner = ref(false);
const assignForm = useForm({
  assigned_to: props.transfer.assigned_to ?? "",
  notes: props.transfer.notes ?? "",
});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));
const empty = () => t("common.empty_value");

const dispatchLabel = () => {
  if (props.transfer.status === "CREATED") return t("transfers.actions.dispatch_waiting");
  if (props.transfer.status === "WAITING_DISPATCH") return t("transfers.actions.dispatch_transit");
  return t("transfers.show.dispatch");
};

const updateTransfer = () => {
  assignForm.put(route("transfers.update", props.transfer.id), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({ toast: true, position: "top-end", icon: "success", title: t("transfers.swal.staff_assigned"), timer: 2500, showConfirmButton: false });
    },
  });
};

const dispatchTransfer = () => {
  Swal.fire({
    title: t("transfers.swal.dispatch_confirm"),
    input: "textarea",
    inputLabel: t("transfers.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("transfers.swal.confirm"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;
    router.post(route("transfers.dispatch", props.transfer.id), { comment: result.value }, { preserveScroll: true });
  });
};

const receiveTransfer = () => {
  Swal.fire({
    title: t("transfers.swal.receive_confirm"),
    input: "textarea",
    inputLabel: t("transfers.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("transfers.swal.confirm"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;
    router.post(route("transfers.receive", props.transfer.id), { comment: result.value }, { preserveScroll: true });
  });
};

const changeStatus = (status) => {
  Swal.fire({
    title: t("transfers.swal.move_to", { label: status.label }),
    input: "textarea",
    inputLabel: t("transfers.swal.optional_comment"),
    showCancelButton: true,
    confirmButtonText: t("transfers.swal.confirm"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;
    router.post(
      route("transfers.change-status", props.transfer.id),
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
    <PageHeader :title="transfer.reference" :pageTitle="$t('transfers.page_title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="`bg-${transfer.status_color}-subtle text-${transfer.status_color}`">
          {{ transfer.status_label }}
        </span>
        <div class="vr"></div>

        <button
          v-if="can.dispatch && ['CREATED', 'WAITING_DISPATCH'].includes(transfer.status)"
          class="btn btn-sm btn-primary"
          @click="dispatchTransfer"
        >
          <i class="ri-send-plane-line align-bottom me-1"></i> {{ dispatchLabel() }}
        </button>

        <button v-if="can.receive && transfer.status === 'IN_TRANSIT'" class="btn btn-sm btn-success" @click="receiveTransfer">
          <i class="ri-checkbox-circle-line align-bottom me-1"></i> {{ $t('transfers.show.receive') }}
        </button>

        <div class="dropdown" v-if="allowedTransitions.length">
          <button class="btn btn-sm btn-soft-primary dropdown-toggle" data-bs-toggle="dropdown">
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
          <button v-if="can.scan && transfer.status === 'IN_TRANSIT'" class="btn btn-sm btn-soft-primary" @click="showQrScanner = true">
            <i class="ri-qr-scan-2-line align-bottom me-1"></i> {{ $t('transfers.qr_scan') }}
          </button>
          <Link :href="route('transfers.index')" class="btn btn-sm btn-light">
            <i class="ri-arrow-left-line align-bottom me-1"></i> {{ $t('transfers.show.back') }}
          </Link>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="8">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('transfers.show.info') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="4"><div class="text-muted fs-13">{{ $t('transfers.show.reference') }}</div><EntityLink type="transfer" :entity="transfer" :show-status="false" size="sm" /></BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('transfers.show.from_city') }}</div><div class="fw-semibold">{{ transfer.from_city?.name ?? empty() }}</div></BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('transfers.show.to_city') }}</div><div class="fw-semibold">{{ transfer.to_city?.name ?? empty() }}</div></BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('transfers.show.created_by') }}</div>
                <UserAvatar v-if="transfer.creator" :user="transfer.creator" :size="28" clickable show-name show-role />
                <div v-else class="fw-semibold">{{ empty() }}</div>
              </BCol>
              <BCol md="4">
                <div class="text-muted fs-13">{{ $t('transfers.show.assigned_to') }}</div>
                <UserAvatar v-if="transfer.assignee" :user="transfer.assignee" :size="28" clickable show-name show-role />
                <div v-else class="fw-semibold">{{ empty() }}</div>
              </BCol>
              <BCol md="4"><div class="text-muted fs-13">{{ $t('transfers.show.creation_date') }}</div><div class="fw-semibold">{{ formatDate(transfer.created_at) }}</div></BCol>
              <BCol md="12" v-if="transfer.notes"><div class="text-muted fs-13">{{ $t('transfers.show.notes') }}</div><div>{{ transfer.notes }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>

        <BCard no-body v-if="can.update">
          <BCardHeader><h5 class="card-title mb-0">{{ $t('transfers.show.assign_staff') }}</h5></BCardHeader>
          <BCardBody>
            <div class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="form-label">{{ $t('transfers.show.staff') }}</label>
                <select v-model="assignForm.assigned_to" class="form-select">
                  <option value="">{{ $t('transfers.show.select_staff') }}</option>
                  <option v-for="member in staff" :key="member.id" :value="member.id">
                    {{ member.name }} {{ member.phone ? `(${member.phone})` : "" }}
                  </option>
                </select>
              </div>
              <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" :disabled="assignForm.processing" @click="updateTransfer">
                  {{ $t('transfers.show.assign') }}
                </button>
              </div>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('transfers.show.orders_count', { count: transfer.orders?.length ?? 0 }) }}</h5></BCardHeader>
          <BCardBody>
            <div class="table-responsive">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('orders.table.tracking_number') }}</th>
                    <th>{{ $t('orders.table.customer') }}</th>
                    <th>{{ $t('orders.show.pickup_city') }}</th>
                    <th>{{ $t('orders.show.delivery_city') }}</th>
                    <th class="text-end">{{ $t('orders.table.amount') }}</th>
                    <th>{{ $t('common.status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in transfer.orders ?? []" :key="order.id">
                    <td>
                      <EntityLink type="order" :entity="order" :show-status="false" size="sm" />
                    </td>
                    <td>{{ order.customer?.full_name ?? empty() }}</td>
                    <td>{{ order.pickup_city?.name ?? order.seller?.city?.name ?? empty() }}</td>
                    <td>{{ order.city?.name ?? empty() }}</td>
                    <td class="text-end">{{ money(order.order_amount) }}</td>
                    <td>
                      <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                        {{ order.status_label }}
                      </span>
                    </td>
                  </tr>
                  <tr v-if="!(transfer.orders?.length)">
                    <td colspan="6" class="text-center text-muted py-3">{{ $t('orders.no_orders_linked') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="4">
        <BCard no-body v-if="can.print_qr && qrCode">
          <BCardHeader><h5 class="card-title mb-0">{{ $t('transfers.show.qr_code') }}</h5></BCardHeader>
          <BCardBody class="text-center">
            <img :src="qrCode" alt="Transfer QR" class="img-fluid" style="max-width: 220px" />
            <p class="text-muted fs-13 mt-2 mb-0">{{ $t('transfers.show.qr_hint') }}</p>
            <code class="fs-12">{{ transfer.scan_url }}</code>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('transfers.show.statistics') }}</h5></BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('transfers.show.number_of_packages') }}</span>
              <span class="fw-semibold">{{ transfer.number_of_packages }}</span>
            </div>
            <div class="d-flex justify-content-between border-top pt-2 mt-2">
              <span class="fw-semibold">{{ $t('transfers.show.total_amount') }}</span>
              <span class="fw-bold fs-16 text-primary">{{ money(transfer.total_amount) }}</span>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('transfers.show.status_history') }}</h5></BCardHeader>
          <BCardBody>
            <TransferTimeline :history="transfer.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <TransferQrScanner
      :show="showQrScanner"
      :transfer-id="transfer.id"
      @close="showQrScanner = false"
    />
  </Layout>
</template>
