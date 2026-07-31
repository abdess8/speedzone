<script setup>
import { ref, computed, onMounted } from "vue";
import { Link, router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import InputError from "@/Components/InputError.vue";
import BottomSheet from "@/Components/BottomSheet.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  invoices: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  can: { type: Object, default: () => ({}) },
});

const rows = computed(() => props.invoices.data ?? []);
const meta = computed(() => props.invoices.meta ?? {});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const period = (inv) => {
  if (!inv.period_start && !inv.period_end) return "—";
  return `${inv.period_start ?? "…"} → ${inv.period_end ?? "…"}`;
};

/* --- Mobile cards --- */
const selectedInvoice = ref(null);

const driverName = (inv) => inv.driver?.full_name ?? inv.driver?.name ?? "—";

const cardRows = (inv) => [
  { label: t("driver_invoices.table.total"), value: money(inv.total_amount), emphasis: true },
  { label: t("driver_invoices.table.deliveries"), value: inv.deliveries_count },
  { label: t("driver_invoices.table.period"), value: period(inv) },
];

const sheetRows = (inv) => [
  ...cardRows(inv),
  { label: t("driver_invoices.table.driver"), value: driverName(inv) },
];

const showPayModal = ref(false);
const activeInvoice = ref(null);

const payForm = useForm({
  paid_at: new Date().toISOString().slice(0, 10),
  payment_receipt: null,
});

const openPay = (invoice) => {
  // Reachable from the detail sheet, which must give way rather than stack.
  selectedInvoice.value = null;
  activeInvoice.value = invoice;
  payForm.reset();
  payForm.paid_at = new Date().toISOString().slice(0, 10);
  showPayModal.value = true;
};

const onReceiptChange = (event) => {
  payForm.payment_receipt = event.target.files[0] || null;
};

const submitPay = () => {
  payForm.post(route("driver-invoices.pay", activeInvoice.value.id), {
    forceFormData: true,
    onSuccess: () => {
      showPayModal.value = false;
      payForm.reset();
      router.reload({ only: ["invoices"] });
    },
  });
};

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

onMounted(() => {
  const flash = usePage().props?.flash ?? {};
  if (flash.success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: flash.success, showConfirmButton: false, timer: 3000, timerProgressBar: true });
  }
  if (flash.error) {
    Swal.fire({ toast: true, position: "top-end", icon: "error", title: flash.error, showConfirmButton: false, timer: 4000, timerProgressBar: true });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('driver_invoices.payments.title')" :pageTitle="$t('driver_invoices.payments.page_title')" />

    <BCard no-body>
      <BCardHeader>
        <h5 class="card-title mb-0">{{ $t('driver_invoices.payments.subtitle') }}</h5>
      </BCardHeader>
      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="inv in rows"
            :key="inv.id"
            :title="inv.invoice_number"
            :subtitle="driverName(inv)"
            :status-label="inv.status_label"
            :status-color="inv.status_color"
            :rows="cardRows(inv)"
            @open="selectedInvoice = inv"
          >
            <template v-if="can.pay" #actions>
              <button class="btn btn-sm btn-success flex-fill" @click="openPay(inv)">
                <i class="ri-money-dollar-circle-line align-bottom me-1"></i> {{ $t('driver_invoices.actions.mark_paid') }}
              </button>
            </template>
          </EntityCard>

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('driver_invoices.payments.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>{{ $t('driver_invoices.table.invoice_number') }}</th>
                <th>{{ $t('driver_invoices.table.driver') }}</th>
                <th>{{ $t('driver_invoices.table.period') }}</th>
                <th class="text-end">{{ $t('driver_invoices.table.deliveries') }}</th>
                <th class="text-end">{{ $t('driver_invoices.table.total') }}</th>
                <th>{{ $t('driver_invoices.table.status') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in rows" :key="inv.id">
                <td>
                  <Link :href="route('driver-invoices.show', inv.id)" class="fw-semibold">{{ inv.invoice_number }}</Link>
                </td>
                <td>
                  <div class="fw-medium">{{ inv.driver?.full_name ?? inv.driver?.name ?? "—" }}</div>
                  <div class="text-muted fs-12">{{ inv.driver?.email }}</div>
                </td>
                <td class="text-muted fs-13">{{ period(inv) }}</td>
                <td class="text-end">{{ inv.deliveries_count }}</td>
                <td class="text-end fw-semibold">{{ money(inv.total_amount) }}</td>
                <td>
                  <span class="badge" :class="`bg-${inv.status_color}-subtle text-${inv.status_color}`">
                    <i :class="inv.status_icon" class="align-bottom me-1"></i>{{ inv.status_label }}
                  </span>
                </td>
                <td class="text-end">
                  <BButton v-if="can.pay" variant="success" size="sm" @click="openPay(inv)">
                    <i class="ri-money-dollar-circle-line align-bottom me-1"></i> {{ $t('driver_invoices.actions.mark_paid') }}
                  </BButton>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="7" class="text-center text-muted py-4">{{ $t('driver_invoices.payments.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm class="d-flex justify-content-sm-end">
            <ul class="pagination pagination-sm mb-0" v-if="meta.links">
              <li v-for="(link, i) in meta.links" :key="i" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                <button class="page-link" @click="goToPage(link.url)" v-html="link.label"></button>
              </li>
            </ul>
          </BCol>
        </BRow>
      </BCardBody>
    </BCard>

    <EntityDetailSheet
      :show="selectedInvoice !== null"
      :title="selectedInvoice?.invoice_number ?? ''"
      :subtitle="selectedInvoice ? driverName(selectedInvoice) : ''"
      :status-label="selectedInvoice?.status_label ?? ''"
      :status-color="selectedInvoice?.status_color ?? 'secondary'"
      :rows="selectedInvoice ? sheetRows(selectedInvoice) : []"
      @close="selectedInvoice = null"
    >
      <template #actions>
        <Link
          :href="route('driver-invoices.show', selectedInvoice?.id)"
          class="btn btn-light flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
        <button v-if="can.pay" class="btn btn-success flex-fill sheet-action" @click="openPay(selectedInvoice)">
          <i class="ri-money-dollar-circle-line align-bottom me-1"></i> {{ $t('driver_invoices.actions.mark_paid') }}
        </button>
      </template>
    </EntityDetailSheet>

    <BottomSheet :show="showPayModal" :title="$t('driver_invoices.pay.title')" @close="showPayModal = false">
      <form @submit.prevent="submitPay">
        <div class="mb-3" v-if="activeInvoice">
          <div class="alert alert-light border">
            <div class="fw-semibold">{{ activeInvoice.invoice_number }}</div>
            <div class="text-muted">{{ activeInvoice.driver?.full_name ?? activeInvoice.driver?.name }} · {{ money(activeInvoice.total_amount) }}</div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ $t('driver_invoices.pay.paid_at') }} <span class="text-danger">*</span></label>
          <input type="date" class="form-control" v-model="payForm.paid_at" :class="{ 'is-invalid': payForm.errors.paid_at }" />
          <InputError :message="payForm.errors.paid_at" />
        </div>
        <div class="mb-3">
          <label class="form-label">{{ $t('driver_invoices.pay.receipt') }} <span class="text-danger">*</span></label>
          <input type="file" class="form-control" accept=".pdf,image/*" @change="onReceiptChange" :class="{ 'is-invalid': payForm.errors.payment_receipt }" />
          <div class="form-text">{{ $t('driver_invoices.pay.receipt_hint') }}</div>
          <InputError :message="payForm.errors.payment_receipt" />
        </div>
        <div class="hstack gap-2 justify-content-end">
          <BButton variant="light" type="button" @click="showPayModal = false">{{ $t('common.cancel') }}</BButton>
          <BButton variant="success" type="submit" :disabled="payForm.processing">
            <i class="ri-check-line align-bottom me-1"></i> {{ $t('driver_invoices.pay.submit') }}
          </BButton>
        </div>
      </form>
    </BottomSheet>
  </Layout>
</template>
