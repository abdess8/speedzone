<script setup>
import { ref, computed, onMounted } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import InputError from "@/Components/InputError.vue";
import SupportTicketsPanel from "@/Components/SupportTicketsPanel.vue";
import BottomSheet from "@/Components/BottomSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  invoice: { type: Object, required: true },
  can: { type: Object, default: () => ({}) },
});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : "—");
const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");

const inv = computed(() => props.invoice);
const lines = computed(() => props.invoice.lines ?? []);
const logs = computed(() => props.invoice.logs ?? []);
const billing = computed(() => props.invoice.seller_billing ?? {});

const showPayModal = ref(false);

const payForm = useForm({
  paid_at: new Date().toISOString().slice(0, 10),
  payment_receipt: null,
});

const onReceiptChange = (event) => {
  payForm.payment_receipt = event.target.files[0] || null;
};

const submitPay = () => {
  payForm.post(route("invoices.pay", inv.value.id), {
    forceFormData: true,
    onSuccess: () => {
      showPayModal.value = false;
      payForm.reset();
    },
  });
};

const cancelInvoice = () => {
  Swal.fire({
    title: t("invoices.confirms.cancel_title"),
    text: t("invoices.confirms.cancel_text"),
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: t("invoices.confirms.confirm"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#f06548",
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route("invoices.cancel", inv.value.id), {}, { preserveScroll: true });
    }
  });
};

const deleteInvoice = () => {
  Swal.fire({
    title: t("invoices.confirms.delete_title"),
    text: t("invoices.confirms.delete_text"),
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: t("invoices.confirms.confirm"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#f06548",
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("invoices.destroy", inv.value.id));
    }
  });
};

const openPdf = () => window.open(route("invoices.pdf", inv.value.id), "_blank");
const downloadPdf = () => (window.location = route("invoices.pdf", { invoice: inv.value.id, download: 1 }));

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000, timerProgressBar: true });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('invoices.detail.title', { number: inv.invoice_number })" :pageTitle="$t('invoices.detail.page_title')" />

    <BRow>
      <BCol lg="8">
        <!-- Summary -->
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center">
            <h5 class="card-title mb-0 flex-grow-1">
              {{ inv.invoice_number }}
              <span class="badge ms-2" :class="`bg-${inv.status_color}-subtle text-${inv.status_color}`">
                <i :class="inv.status_icon" class="align-bottom me-1"></i>{{ inv.status_label }}
              </span>
            </h5>
            <div class="hstack gap-2">
              <BButton v-if="inv.pdf_url || true" variant="soft-secondary" size="sm" @click="openPdf">
                <i class="ri-eye-line align-bottom me-1"></i> {{ $t('invoices.actions.view_pdf') }}
              </BButton>
              <BButton variant="soft-primary" size="sm" @click="downloadPdf">
                <i class="ri-download-2-line align-bottom me-1"></i> {{ $t('invoices.actions.download_pdf') }}
              </BButton>
            </div>
          </BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.total_orders') }}</p>
                <h5 class="mb-0">{{ inv.total_orders_count }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.delivered') }}</p>
                <h5 class="mb-0">{{ money(inv.delivered_amount) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.delivery_fees') }}</p>
                <h5 class="mb-0 text-danger">- {{ money(inv.delivery_fees_total) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.return_fees') }}</p>
                <h5 class="mb-0 text-danger">- {{ money(inv.return_fees_total) }}</h5>
              </BCol>
            </BRow>
            <hr />
            <div class="d-flex justify-content-end align-items-center gap-3">
              <span class="text-muted">{{ $t('invoices.summary.net') }}</span>
              <h3 class="mb-0 text-primary">{{ money(inv.net_amount) }}</h3>
            </div>
          </BCardBody>
        </BCard>

        <!-- Lines -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.detail.orders_title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive table-card">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('invoices.columns.order') }}</th>
                    <th>{{ $t('invoices.columns.customer') }}</th>
                    <th>{{ $t('invoices.columns.city') }}</th>
                    <th>{{ $t('invoices.columns.status') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.order_amount') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.delivery_fee') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.return_fee') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.final_amount') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in lines" :key="line.id">
                    <td>
                      <Link v-if="line.order_id" :href="route('orders.show', line.order_id)" class="fw-semibold">
                        {{ line.tracking_number ?? "#" + line.order_id }}
                      </Link>
                      <span v-else>{{ line.tracking_number }}</span>
                    </td>
                    <td>{{ line.customer_full_name ?? "—" }}</td>
                    <td>{{ line.city ?? "—" }}</td>
                    <td>
                      <span class="badge" :class="line.order_status_at_invoice === 'RETURNED' ? 'bg-dark-subtle text-dark' : 'bg-success-subtle text-success'">
                        {{ line.order_status_label ?? line.order_status_at_invoice }}
                      </span>
                    </td>
                    <td class="text-end">{{ money(line.order_amount) }}</td>
                    <td class="text-end">{{ money(line.delivery_fee) }}</td>
                    <td class="text-end">{{ money(line.return_fee) }}</td>
                    <td class="text-end fw-semibold" :class="line.final_amount < 0 ? 'text-danger' : ''">
                      {{ money(line.final_amount) }}
                    </td>
                  </tr>
                  <tr v-if="lines.length === 0">
                    <td colspan="8" class="text-center text-muted py-4">{{ $t('invoices.detail.no_orders') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>

        <!-- Audit trail -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.detail.history_title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="logs.length === 0" class="text-muted">{{ $t('invoices.detail.no_history') }}</div>
            <ul v-else class="list-unstyled mb-0">
              <li v-for="log in logs" :key="log.id" class="d-flex gap-2 mb-3">
                <i class="ri-history-line text-muted mt-1"></i>
                <div>
                  <div class="fw-medium text-capitalize">{{ log.action.replace('_', ' ') }}</div>
                  <div class="text-muted fs-12">
                    {{ formatDateTime(log.created_at) }}
                    <span v-if="log.user"> · {{ log.user.full_name ?? log.user.name }}</span>
                  </div>
                </div>
              </li>
            </ul>
          </BCardBody>
        </BCard>

        <SupportTicketsPanel object-type="INVOICE" :object-id="inv.id" />
      </BCol>

      <BCol lg="4">
        <!-- Actions -->
        <BCard no-body>
          <BCardBody>
            <div class="d-grid gap-2">
              <BButton v-if="can.pay" variant="success" @click="showPayModal = true">
                <i class="ri-money-dollar-circle-line align-bottom me-1"></i> {{ $t('invoices.actions.mark_paid') }}
              </BButton>
              <BButton v-if="can.cancel" variant="soft-danger" @click="cancelInvoice">
                <i class="ri-close-circle-line align-bottom me-1"></i> {{ $t('invoices.actions.cancel') }}
              </BButton>
              <BButton v-if="can.delete" variant="outline-danger" @click="deleteInvoice">
                <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('invoices.actions.delete') }}
              </BButton>
              <Link :href="route('invoices.index')" class="btn btn-light">
                <i class="ri-arrow-left-line align-bottom me-1"></i> {{ $t('invoices.actions.back_to_list') }}
              </Link>
            </div>
          </BCardBody>
        </BCard>

        <!-- Seller info -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.detail.seller_info') }}</h5>
          </BCardHeader>
          <BCardBody>
            <p class="mb-1 fw-semibold">{{ inv.seller?.full_name ?? inv.seller?.name }}</p>
            <p class="text-muted mb-1">{{ inv.seller?.email }}</p>
            <p v-if="billing.phone_number" class="text-muted mb-1">{{ billing.phone_number }}</p>
            <p v-if="billing.ice_number" class="text-muted mb-1">ICE: {{ billing.ice_number }}</p>
            <p v-if="billing.address" class="text-muted mb-0">{{ billing.address }}</p>
          </BCardBody>
        </BCard>

        <!-- Payment info -->
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.detail.payment_info') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('invoices.detail.bank') }}</span>
              <span>{{ billing.bank_name ?? "—" }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('invoices.detail.rib') }}</span>
              <span>{{ billing.rib ?? "—" }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('invoices.detail.period') }}</span>
              <span>{{ inv.period_start ?? "…" }} → {{ inv.period_end ?? "…" }}</span>
            </div>
            <template v-if="inv.paid_at">
              <hr />
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">{{ $t('invoices.detail.paid_by') }}</span>
                <span>{{ inv.paid_by?.full_name ?? inv.paid_by?.name ?? "—" }}</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">{{ $t('invoices.table.paid_at') }}</span>
                <span>{{ formatDate(inv.paid_at) }}</span>
              </div>
              <a v-if="inv.payment_receipt_url" :href="inv.payment_receipt_url" target="_blank" class="btn btn-soft-secondary btn-sm w-100">
                <i class="ri-attachment-2 align-bottom me-1"></i> {{ $t('invoices.detail.view_receipt') }}
              </a>
            </template>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BottomSheet :show="showPayModal" :title="$t('invoices.pay.title')" @close="showPayModal = false">
      <form @submit.prevent="submitPay">
        <div class="mb-3">
          <label class="form-label">{{ $t('invoices.pay.paid_at') }} <span class="text-danger">*</span></label>
          <input type="date" class="form-control" v-model="payForm.paid_at" :class="{ 'is-invalid': payForm.errors.paid_at }" />
          <InputError :message="payForm.errors.paid_at" />
        </div>
        <div class="mb-3">
          <label class="form-label">{{ $t('invoices.pay.receipt') }} <span class="text-danger">*</span></label>
          <input type="file" class="form-control" accept=".pdf,image/*" @change="onReceiptChange" :class="{ 'is-invalid': payForm.errors.payment_receipt }" />
          <div class="form-text">{{ $t('invoices.pay.receipt_hint') }}</div>
          <InputError :message="payForm.errors.payment_receipt" />
        </div>
        <div class="hstack gap-2 justify-content-end">
          <BButton variant="light" type="button" @click="showPayModal = false">{{ $t('common.cancel') }}</BButton>
          <BButton variant="success" type="submit" :disabled="payForm.processing">
            <i class="ri-check-line align-bottom me-1"></i> {{ $t('invoices.pay.submit') }}
          </BButton>
        </div>
      </form>
    </BottomSheet>
  </Layout>
</template>
