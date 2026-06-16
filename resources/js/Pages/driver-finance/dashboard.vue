<script setup>
import { ref, computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const { t } = useI18n();

const props = defineProps({
  stats: { type: Object, default: () => ({ today: {}, week: {}, month: {} }) },
  driverId: { type: Number, default: null },
  drivers: { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
  invoices: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const selectedDriver = ref(props.driverId);

const driverOptions = computed(() =>
  props.drivers.map((d) => ({ value: d.id, label: `${d.name} (${d.email})` }))
);

const isAdmin = computed(() => (props.drivers ?? []).length > 0);

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");

const cards = computed(() => [
  { key: "today", label: t("driver_finance.earnings.today"), icon: "ri-calendar-todo-line", color: "primary" },
  { key: "week", label: t("driver_finance.earnings.week"), icon: "ri-calendar-week-line", color: "info" },
  { key: "month", label: t("driver_finance.earnings.month"), icon: "ri-calendar-2-line", color: "success" },
]);

const changeDriver = (value) => {
  router.get(route("driver-finance.dashboard"), value ? { driver_id: value } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const openPdf = (invoice) => window.open(route("driver-invoices.pdf", invoice.id), "_blank");
</script>

<template>
  <Layout>
    <PageHeader :title="$t('driver_finance.title')" :pageTitle="$t('driver_finance.page_title')" />

    <BRow v-if="isAdmin" class="mb-3">
      <BCol md="4">
        <label class="form-label">{{ $t('driver_finance.driver') }}</label>
        <Multiselect
          v-model="selectedDriver"
          :options="driverOptions"
          :searchable="true"
          :close-on-select="true"
          @change="changeDriver"
        />
      </BCol>
    </BRow>

    <!-- Earnings cards -->
    <BRow>
      <BCol v-for="card in cards" :key="card.key" md="4">
        <BCard no-body class="card-animate">
          <BCardBody>
            <div class="d-flex align-items-center">
              <div class="flex-grow-1">
                <p class="text-uppercase fw-medium text-muted mb-0">{{ card.label }}</p>
              </div>
              <div class="avatar-sm">
                <span class="avatar-title rounded-circle fs-22" :class="`bg-${card.color}-subtle text-${card.color}`">
                  <i :class="card.icon"></i>
                </span>
              </div>
            </div>
            <div class="d-flex align-items-end justify-content-between mt-3">
              <div>
                <h4 class="fs-22 fw-semibold mb-1">{{ money(stats[card.key]?.amount) }} </h4>
                <p class="text-muted mb-0">{{ $t('driver_finance.earnings.total_earned') }}</p>
              </div>
              <div class="text-end">
                <h5 class="mb-1">{{ stats[card.key]?.deliveries ?? 0 }}</h5>
                <p class="text-muted mb-0">{{ $t('driver_finance.earnings.deliveries') }}</p>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BRow>
      <!-- Paid orders -->
      <BCol lg="7">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('driver_finance.orders.title') }}</h5>
            <p class="text-muted mb-0 fs-13">{{ $t('driver_finance.orders.subtitle') }}</p>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive table-card">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('driver_finance.orders.order') }}</th>
                    <th>{{ $t('driver_finance.orders.date') }}</th>
                    <th>{{ $t('driver_finance.orders.customer') }}</th>
                    <th>{{ $t('driver_finance.orders.sector') }}</th>
                    <th class="text-end">{{ $t('driver_finance.orders.amount') }}</th>
                    <th>{{ $t('driver_finance.orders.payment_status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="tx in transactions" :key="tx.id">
                    <td>
                      <Link v-if="tx.order_id" :href="route('orders.show', tx.order_id)" class="fw-semibold">
                        {{ tx.tracking_number ?? "#" + tx.order_id }}
                      </Link>
                      <span v-else>—</span>
                    </td>
                    <td class="text-muted fs-13">{{ formatDate(tx.created_at) }}</td>
                    <td>{{ tx.customer_full_name ?? "—" }}</td>
                    <td>{{ tx.sector ?? "—" }}</td>
                    <td class="text-end fw-semibold">{{ money(tx.amount) }}</td>
                    <td>
                      <span class="badge" :class="`bg-${tx.status_color}-subtle text-${tx.status_color}`">{{ tx.status_label }}</span>
                    </td>
                  </tr>
                  <tr v-if="transactions.length === 0">
                    <td colspan="6" class="text-center text-muted py-4">{{ $t('driver_finance.orders.empty') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <!-- Invoices -->
      <BCol lg="5">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('driver_finance.invoices.title') }}</h5>
            <p class="text-muted mb-0 fs-13">{{ $t('driver_finance.invoices.subtitle') }}</p>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive table-card">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('driver_finance.invoices.invoice_number') }}</th>
                    <th class="text-end">{{ $t('driver_finance.invoices.amount') }}</th>
                    <th>{{ $t('driver_finance.invoices.status') }}</th>
                    <th class="text-end">{{ $t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inv in invoices" :key="inv.id">
                    <td>
                      <Link :href="route('driver-invoices.show', inv.id)" class="fw-semibold">{{ inv.invoice_number }}</Link>
                      <div class="text-muted fs-12">{{ inv.period_start ?? "…" }} → {{ inv.period_end ?? "…" }}</div>
                    </td>
                    <td class="text-end fw-semibold">{{ money(inv.total_amount) }}</td>
                    <td>
                      <span class="badge" :class="`bg-${inv.status_color}-subtle text-${inv.status_color}`">{{ inv.status_label }}</span>
                    </td>
                    <td class="text-end">
                      <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                        <li class="list-inline-item" :title="$t('driver_finance.invoices.view_detail')">
                          <Link :href="route('driver-invoices.show', inv.id)" class="text-primary"><i class="ri-eye-fill fs-16"></i></Link>
                        </li>
                        <li v-if="inv.pdf_url" class="list-inline-item" :title="$t('driver_finance.invoices.download_pdf')">
                          <BLink class="text-secondary" @click="openPdf(inv)"><i class="ri-file-pdf-2-line fs-16"></i></BLink>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  <tr v-if="invoices.length === 0">
                    <td colspan="4" class="text-center text-muted py-4">{{ $t('driver_finance.invoices.empty') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
