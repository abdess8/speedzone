<script setup>
import { computed, ref, reactive, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CreateTransferModal from "./Partials/CreateTransferModal.vue";
import EntityLink from "@/Components/EntityLink.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import StatusPills from "@/Components/StatusPills.vue";
import StatusKpiCards from "@/Components/StatusKpiCards.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  transfers: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  stats: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  eligibleOrders: { type: Array, default: () => [] },
  staff: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
  from_city_id: props.filters.from_city_id ?? "",
  to_city_id: props.filters.to_city_id ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
const showCreateModal = ref(false);
/** Row whose mobile detail sheet is open. */
const selectedTransfer = ref(null);

const rows = computed(() => props.transfers.data ?? []);
const meta = computed(() => props.transfers.meta ?? {});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

/** The leg is the identity of a transfer, so it heads the card rather than sitting in the grid. */
const leg = (transfer) =>
  `${transfer.from_city?.name ?? t("common.empty_value")} → ${transfer.to_city?.name ?? t("common.empty_value")}`;

const cardRows = (transfer) => [
  { label: t("common.type"), value: transfer.content_type_label },
  { label: t("transfers.table.packages"), value: transfer.number_of_packages },
  { label: t("transfers.table.total_amount"), value: money(transfer.total_amount), emphasis: true },
  { label: t("transfers.table.created"), value: formatDate(transfer.created_at) },
];

const sheetRows = (transfer) => [
  { label: t("transfers.table.from_city"), value: transfer.from_city?.name },
  { label: t("transfers.table.to_city"), value: transfer.to_city?.name },
  ...cardRows(transfer),
];

const query = () => {
  const params = { per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("transfers.index"), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const applyFilters = () => reload();
const resetFilters = () => {
  Object.keys(filters).forEach((key) => (filters[key] = ""));
  reload();
};

const selectStatus = (value) => {
  filters.status = value;
  reload();
};

watch(perPage, reload);

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
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
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('transfers.title')" :pageTitle="$t('transfers.page_title')" />

    <StatusKpiCards
      :stats="stats"
      :model-value="filters.status"
      :all-label="$t('common.all_statuses')"
      @select="selectStatus"
    />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('transfers.title') }}</h5>
        </template>

        <template #actions>
          <button v-if="can.create" type="button" class="btn btn-success" @click="showCreateModal = true">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('transfers.create') }}</span>
          </button>
        </template>

        <BCol md="3">
          <label class="form-label">{{ $t('transfers.filters.reference') }}</label>
          <input v-model="filters.search" type="text" class="form-control" :placeholder="$t('transfers.filters.reference_placeholder')" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('transfers.filters.from_city') }}</label>
          <select v-model="filters.from_city_id" class="form-select">
            <option value="">{{ $t('transfers.filters.all_cities') }}</option>
            <option v-for="city in filterOptions.cities" :key="city.id" :value="city.id">{{ city.name }}</option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('transfers.filters.to_city') }}</label>
          <select v-model="filters.to_city_id" class="form-select">
            <option value="">{{ $t('transfers.filters.all_cities') }}</option>
            <option v-for="city in filterOptions.cities" :key="`to-${city.id}`" :value="city.id">{{ city.name }}</option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('common.all_statuses') }}</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('transfers.filters.created_from') }}</label>
          <input v-model="filters.created_from" type="date" class="form-control" />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('transfers.filters.created_to') }}</label>
          <input v-model="filters.created_to" type="date" class="form-control" />
        </BCol>
      </FilterPanel>

      <!-- One tap to the filter that matters most; the sheet keeps the rest. -->
      <StatusPills
        class="d-lg-none"
        :model-value="filters.status"
        :options="filterOptions.statuses ?? []"
        :all-label="$t('common.all_statuses')"
        :label="$t('common.status')"
        @change="selectStatus"
      />

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="transfer in rows"
            :key="transfer.id"
            :title="transfer.reference"
            :subtitle="leg(transfer)"
            :status-label="transfer.status_label"
            :status-color="transfer.status_color"
            :rows="cardRows(transfer)"
            @open="selectedTransfer = transfer"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('transfers.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>{{ $t('transfers.filters.reference') }}</th>
                <th>{{ $t('transfers.table.from_city') }}</th>
                <th>{{ $t('transfers.table.to_city') }}</th>
                <th class="text-center">{{ $t('transfers.table.packages') }}</th>
                <th class="text-end">{{ $t('transfers.table.total_amount') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th>{{ $t('transfers.table.created') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transfer in rows" :key="transfer.id">
                <td>
                  <EntityLink type="transfer" :entity="transfer" :show-status="false" size="sm" />
                  <span
                    class="badge ms-1"
                    :class="`bg-${transfer.content_type_color}-subtle text-${transfer.content_type_color}`"
                    :title="transfer.content_type_label"
                  >
                    <i :class="transfer.content_type_icon"></i>
                  </span>
                </td>
                <td>{{ transfer.from_city?.name ?? $t('common.empty_value') }}</td>
                <td>{{ transfer.to_city?.name ?? $t('common.empty_value') }}</td>
                <td class="text-center">{{ transfer.number_of_packages }}</td>
                <td class="text-end fw-semibold">{{ money(transfer.total_amount) }}</td>
                <td>
                  <span class="badge" :class="`bg-${transfer.status_color}-subtle text-${transfer.status_color}`">
                    {{ transfer.status_label }}
                  </span>
                </td>
                <td class="text-muted fs-13">{{ formatDate(transfer.created_at) }}</td>
                <td class="text-end">
                  <Link :href="route('transfers.show', transfer.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line"></i>
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="8" class="text-center text-muted py-4">{{ $t('transfers.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">{{ $t('common.rows_per_page') }}</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in filterOptions.pageSizes" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">{{ $t('common.pagination_range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total ?? 0 }) }}</span>
            </div>
          </BCol>
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

    <CreateTransferModal
      :show="showCreateModal"
      :cities="filterOptions.cities ?? []"
      :content-types="filterOptions.contentTypes ?? []"
      :default-from-city-id="filterOptions.defaultFromCityId"
      :staff="staff"
      @close="showCreateModal = false"
      @created="showCreateModal = false"
    />

    <EntityDetailSheet
      :show="selectedTransfer !== null"
      :title="selectedTransfer?.reference ?? ''"
      :subtitle="selectedTransfer ? leg(selectedTransfer) : ''"
      :status-label="selectedTransfer?.status_label ?? ''"
      :status-color="selectedTransfer?.status_color ?? 'secondary'"
      :rows="selectedTransfer ? sheetRows(selectedTransfer) : []"
      @close="selectedTransfer = null"
    >
      <template #actions>
        <Link
          :href="route('transfers.show', selectedTransfer?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
