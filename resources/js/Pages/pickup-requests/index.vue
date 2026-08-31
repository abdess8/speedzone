<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CreatePickupModal from "./Partials/CreatePickupModal.vue";
import QrScanner from "./Partials/QrScanner.vue";
import EntityLink from "@/Components/EntityLink.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import StatusPills from "@/Components/StatusPills.vue";
import StatusKpiCards from "@/Components/StatusKpiCards.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import SortableTh from "@/Components/SortableTh.vue";
import { useGuideSignals } from "@/composables/useGuideSignals";
import { useTableSort } from "@/composables/useTableSort";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  pickups: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  stats: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  eligibleOrders: { type: Array, default: () => [] },
  pickupAddresses: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
  seller_id: props.filters.seller_id ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
const showCreateModal = ref(false);

useGuideSignals().mirror("pickups.create_open", showCreateModal);
const showQrScanner = ref(false);
/** Row whose mobile detail sheet is open. */
const selectedPickup = ref(null);

const rows = computed(() => props.pickups.data ?? []);
const meta = computed(() => props.pickups.meta ?? {});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

/** Detail lines shared by the mobile card and its sheet. */
const cardRows = (pickup) => [
  { label: t("pickups.table.packages"), value: pickup.number_of_packages },
  { label: t("pickups.table.total_amount"), value: money(pickup.total_orders_amount), emphasis: true },
  { label: t("pickups.table.driver"), value: pickup.assignee?.full_name ?? pickup.assignee?.name },
];

const sheetRows = (pickup) => [
  ...(props.can.read_all
    ? [{ label: t("pickups.filters.seller"), value: pickup.creator?.full_name ?? pickup.creator?.name }]
    : []),
  { label: t("pickups.table.address"), value: pickup.pickup_address },
  ...cardRows(pickup),
  { label: t("pickups.table.created"), value: formatDate(pickup.created_at) },
];

const query = () => {
  const params = { per_page: perPage.value, sort: sort.value, direction: direction.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("pickup-requests.index"), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const { sort, direction, sortBy } = useTableSort(props.filters, reload);

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
    <PageHeader :title="$t('pickups.title')" :pageTitle="$t('pickups.page_title')" />

    <StatusKpiCards
      :stats="stats"
      :model-value="filters.status"
      :all-label="$t('common.all_statuses')"
      @select="selectStatus"
    />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('pickups.title') }}</h5>
        </template>

        <template #actions>
          <button v-if="can.scan" type="button" class="btn btn-soft-primary" @click="showQrScanner = true">
            <i class="ri-qr-scan-2-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('pickups.qr_scan') }}</span>
          </button>
          <button
            v-if="can.create"
            data-guide="pickup-create-open"
            type="button"
            class="btn btn-success"
            @click="showCreateModal = true"
          >
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('pickups.create') }}</span>
          </button>
        </template>

        <BCol md="3">
          <label class="form-label">{{ $t('pickups.filters.reference') }}</label>
          <input v-model="filters.search" type="text" class="form-control" :placeholder="$t('pickups.filters.reference_placeholder')" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('common.all_statuses') }}</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </BCol>
        <BCol v-if="can.read_all" md="3">
          <label class="form-label">{{ $t('pickups.filters.seller') }}</label>
          <select v-model="filters.seller_id" class="form-select">
            <option value="">{{ $t('pickups.filters.all_sellers') }}</option>
            <option v-for="seller in filterOptions.sellers" :key="seller.id" :value="seller.id">{{ seller.name }}</option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('pickups.filters.created_from') }}</label>
          <input v-model="filters.created_from" type="date" class="form-control" />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('pickups.filters.created_to') }}</label>
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
        <!-- Mobile: cards. A nine-column table on a phone puts the action button
             off screen, so the list becomes tappable rows opening a sheet. -->
        <div class="d-lg-none">
          <EntityCard
            v-for="pickup in rows"
            :key="pickup.id"
            :title="pickup.reference"
            :subtitle="pickup.pickup_address"
            :status-label="pickup.status_label"
            :status-color="pickup.status_color"
            :rows="cardRows(pickup)"
            @open="selectedPickup = pickup"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('pickups.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <SortableTh field="reference" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.filters.reference') }}
                </SortableTh>
                <SortableTh v-if="can.read_all" field="seller" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.filters.seller') }}
                </SortableTh>
                <SortableTh field="pickup_address" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.table.address') }}
                </SortableTh>
                <SortableTh field="number_of_packages" align="center" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.table.packages') }}
                </SortableTh>
                <SortableTh field="total_orders_amount" align="end" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.table.total_amount') }}
                </SortableTh>
                <SortableTh field="driver" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.table.driver') }}
                </SortableTh>
                <SortableTh field="status" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('common.status') }}
                </SortableTh>
                <SortableTh field="created_at" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('pickups.table.created') }}
                </SortableTh>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pickup in rows" :key="pickup.id">
                <td>
                  <EntityLink type="pickup" :entity="pickup" :show-status="false" size="sm" />
                </td>
                <td v-if="can.read_all">
                  <UserAvatar v-if="pickup.creator" :user="pickup.creator" :size="24" clickable show-name />
                  <span v-else>{{ $t('common.empty_value') }}</span>
                </td>
                <td>
                  <span class="text-truncate d-inline-block" style="max-width: 220px" :title="pickup.pickup_address">
                    {{ pickup.pickup_address }}
                  </span>
                </td>
                <td class="text-center">{{ pickup.number_of_packages }}</td>
                <td class="text-end fw-semibold">{{ money(pickup.total_orders_amount) }}</td>
                <td>
                  <UserAvatar v-if="pickup.assignee" :user="pickup.assignee" :size="24" clickable show-name show-role />
                  <span v-else>{{ $t('common.empty_value') }}</span>
                </td>
                <td>
                  <span class="badge" :class="`bg-${pickup.status_color}-subtle text-${pickup.status_color}`">
                    {{ pickup.status_label }}
                  </span>
                </td>
                <td class="text-muted fs-13">{{ formatDate(pickup.created_at) }}</td>
                <td class="text-end">
                  <Link :href="route('pickup-requests.show', pickup.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line"></i>
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td :colspan="can.read_all ? 9 : 8" class="text-center text-muted py-4">{{ $t('pickups.empty') }}</td>
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
              <li
                v-for="(link, i) in meta.links"
                :key="i"
                class="page-item"
                :class="{ active: link.active, disabled: !link.url }"
              >
                <button class="page-link" @click="goToPage(link.url)" v-html="link.label"></button>
              </li>
            </ul>
          </BCol>
        </BRow>
      </BCardBody>
    </BCard>

    <CreatePickupModal
      :show="showCreateModal"
      :eligible-orders="eligibleOrders"
      :pickup-addresses="pickupAddresses"
      @close="showCreateModal = false"
      @created="showCreateModal = false"
    />

    <QrScanner
      :show="showQrScanner"
      :scan-target-status="can.scan_target_status || 'PICKED_UP'"
      :scan-mode="can.scan_mode || 'driver'"
      @close="showQrScanner = false"
    />

    <EntityDetailSheet
      :show="selectedPickup !== null"
      :title="selectedPickup?.reference ?? ''"
      :subtitle="selectedPickup?.pickup_address ?? ''"
      :status-label="selectedPickup?.status_label ?? ''"
      :status-color="selectedPickup?.status_color ?? 'secondary'"
      :rows="selectedPickup ? sheetRows(selectedPickup) : []"
      @close="selectedPickup = null"
    >
      <template #actions>
        <Link
          :href="route('pickup-requests.show', selectedPickup?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
