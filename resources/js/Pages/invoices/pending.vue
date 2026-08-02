<script setup>
import { ref, computed } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const { t } = useI18n();

const props = defineProps({
  preview: { type: Object, default: () => ({ summary: {}, lines: [] }) },
  sellerId: { type: Number, default: null },
  sellers: { type: Array, default: () => [] },
  billing: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const selectedSeller = ref(props.sellerId);
/** Line whose mobile detail sheet is open. */
const selectedLine = ref(null);

const sellerOptions = computed(() =>
  props.sellers.map((s) => ({ value: s.id, label: `${s.name} (${s.email})` }))
);

const isAdmin = computed(() => (props.sellers ?? []).length > 0);

const lines = computed(() => props.preview?.lines ?? []);
const summary = computed(() => props.preview?.summary ?? {});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : null);

const changeSeller = (value) => {
  router.get(route("invoices.pending"), value ? { seller_id: value } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const activeFilterCount = computed(() => (selectedSeller.value ? 1 : 0));

const applyFilters = () => changeSeller(selectedSeller.value);

const resetFilters = () => {
  selectedSeller.value = null;
  changeSeller(null);
};

const lineStatusColor = (line) => (line.status === "RETURNED" ? "dark" : "success");

const cardRows = (line) => [
  { label: t("invoices.columns.order_amount"), value: money(line.order_amount) },
  { label: t("invoices.columns.delivery_fee"), value: money(line.delivery_fee) },
  { label: t("invoices.columns.final_amount"), value: money(line.final_amount), emphasis: true },
];

const sheetRows = (line) => [
  { label: t("invoices.columns.city"), value: line.city },
  { label: t("invoices.columns.completed_on"), value: formatDate(line.completed_at) ?? "—" },
  { label: t("invoices.columns.return_fee"), value: money(line.return_fee) },
  ...cardRows(line),
];
</script>

<template>
  <Layout>
    <PageHeader :title="$t('invoices.pending.title')" :pageTitle="$t('invoices.pending.page_title')" />

    <BRow>
      <BCol lg="8">
        <BCard no-body>
          <FilterPanel
            v-if="isAdmin"
            :active-count="activeFilterCount"
            @apply="applyFilters"
            @reset="resetFilters"
          >
            <template #title>
              <div class="d-flex align-items-center gap-2">
                <h5 class="card-title mb-0">{{ $t('invoices.pending.orders_title') }}</h5>
                <span class="badge bg-primary-subtle text-primary">{{ summary.total_orders_count ?? 0 }}</span>
              </div>
            </template>

            <BCol md="6">
              <label class="form-label">{{ $t('invoices.filters.seller') }}</label>
              <Multiselect
                v-model="selectedSeller"
                :options="sellerOptions"
                :searchable="true"
                :close-on-select="true"
                :placeholder="$t('invoices.create.seller_placeholder')"
              />
            </BCol>
          </FilterPanel>

          <BCardHeader v-else class="d-flex align-items-center">
            <h5 class="card-title mb-0 flex-grow-1">{{ $t('invoices.pending.orders_title') }}</h5>
            <span class="badge bg-primary-subtle text-primary">{{ summary.total_orders_count ?? 0 }}</span>
          </BCardHeader>

          <BCardBody>
            <BRow v-if="(summary.total_orders_count ?? 0) > 0" class="g-3 mb-3">
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.delivered') }}</p>
                <h5 class="mb-0">{{ money(summary.delivered_amount) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.delivery_fees') }}</p>
                <h5 class="mb-0 text-danger">- {{ money(summary.delivery_fees_total) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.return_fees') }}</p>
                <h5 class="mb-0 text-danger">- {{ money(summary.return_fees_total) }}</h5>
              </BCol>
              <BCol md="3" cols="6">
                <p class="text-muted mb-1">{{ $t('invoices.summary.net') }}</p>
                <h4 class="mb-0 text-primary">{{ money(summary.net_amount) }}</h4>
              </BCol>
            </BRow>

            <div class="d-lg-none">
              <EntityCard
                v-for="line in lines"
                :key="line.id"
                :title="line.tracking_number ?? '—'"
                :subtitle="line.customer_full_name ?? '—'"
                :status-label="line.status"
                :status-color="lineStatusColor(line)"
                :rows="cardRows(line)"
                @open="selectedLine = line"
              />
              <p v-if="lines.length === 0" class="text-center text-muted py-4 mb-0">
                {{ $t('invoices.pending.empty') }}
              </p>
            </div>

            <div class="table-responsive table-card d-none d-lg-block">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('invoices.columns.order') }}</th>
                    <th>{{ $t('invoices.columns.customer') }}</th>
                    <th>{{ $t('invoices.columns.city') }}</th>
                    <th>{{ $t('invoices.columns.status') }}</th>
                    <th>{{ $t('invoices.columns.completed_on') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.order_amount') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.delivery_fee') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.return_fee') }}</th>
                    <th class="text-end">{{ $t('invoices.columns.final_amount') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="line in lines" :key="line.id">
                    <td class="fw-semibold">{{ line.tracking_number }}</td>
                    <td>{{ line.customer_full_name ?? "—" }}</td>
                    <td>{{ line.city ?? "—" }}</td>
                    <td>
                      <span class="badge" :class="line.status === 'RETURNED' ? 'bg-dark-subtle text-dark' : 'bg-success-subtle text-success'">
                        {{ line.status }}
                      </span>
                    </td>
                    <td>{{ formatDate(line.completed_at) ?? "—" }}</td>
                    <td class="text-end">{{ money(line.order_amount) }}</td>
                    <td class="text-end">{{ money(line.delivery_fee) }}</td>
                    <td class="text-end">{{ money(line.return_fee) }}</td>
                    <td class="text-end fw-semibold" :class="line.final_amount < 0 ? 'text-danger' : ''">{{ money(line.final_amount) }}</td>
                  </tr>
                  <tr v-if="lines.length === 0">
                    <td colspan="9" class="text-center text-muted py-4">{{ $t('invoices.pending.empty') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="4">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('invoices.pending.next_billing') }}</h5>
          </BCardHeader>
          <BCardBody>
            <div class="text-center mb-3">
              <div class="avatar-sm mx-auto mb-2">
                <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-22">
                  <i class="ri-calendar-event-line"></i>
                </span>
              </div>
              <h4 class="mb-0">{{ formatDate(billing.next_billing_date) ?? $t('invoices.pending.not_scheduled') }}</h4>
              <p class="text-muted mb-0">{{ $t('invoices.pending.next_date') }}</p>
            </div>
            <hr />
            <div class="d-flex justify-content-between mb-2">
              <span class="text-muted">{{ $t('invoices.pending.frequency') }}</span>
              <span class="fw-medium">{{ billing.billing_frequency_label ?? "—" }}</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">{{ $t('invoices.table.status') }}</span>
              <span v-if="billing.billing_enabled" class="badge bg-success-subtle text-success">
                {{ $t('invoices.pending.auto_enabled') }}
              </span>
              <span v-else class="badge bg-secondary-subtle text-secondary">
                {{ $t('invoices.pending.auto_disabled') }}
              </span>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <EntityDetailSheet
      :show="selectedLine !== null"
      :title="selectedLine?.tracking_number ?? ''"
      :subtitle="selectedLine?.customer_full_name ?? ''"
      :status-label="selectedLine?.status ?? ''"
      :status-color="selectedLine ? lineStatusColor(selectedLine) : 'secondary'"
      :rows="selectedLine ? sheetRows(selectedLine) : []"
      @close="selectedLine = null"
    />
  </Layout>
</template>
