<script setup>
import { computed, ref, reactive, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CreateReturnModal from "./Partials/CreateReturnModal.vue";
import ReturnQrScanner from "./Partials/ReturnQrScanner.vue";
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
import { useBulkStatusAccess } from "@/composables/useBulkStatusAccess";
import Swal from "sweetalert2";

const { t } = useI18n();
const { canBulkEditReturns } = useBulkStatusAccess();

const props = defineProps({
  returns: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  stats: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
  city_id: props.filters.city_id ?? "",
  seller_id: props.filters.seller_id ?? "",
  reason: props.filters.reason ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
const showCreateModal = ref(false);

useGuideSignals().mirror("returns.create_open", showCreateModal);
const showQrScanner = ref(false);
/** Row whose mobile detail sheet is open. */
const selectedReturn = ref(null);

const rows = computed(() => props.returns.data ?? []);
const meta = computed(() => props.returns.meta ?? {});

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

const customerName = (row) =>
  row.effective_customer_name || row.order?.customer?.full_name || null;

const cardRows = (row) => [
  {
    label: t("returns.table.order_tracking"),
    value: row.order?.reference ?? row.order?.tracking_number,
  },
  { label: t("returns.table.reason"), value: row.reason_label },
  { label: t("returns.table.current_city"), value: row.current_location_city?.name },
];

const sheetRows = (row) => [
  { label: t("returns.table.customer"), value: customerName(row) },
  { label: t("returns.table.seller"), value: row.order?.seller?.full_name ?? row.order?.seller?.name },
  ...cardRows(row),
  { label: t("returns.table.created"), value: formatDate(row.created_at) },
];

const query = () => {
  const params = { per_page: perPage.value, sort: sort.value, direction: direction.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("returns.index"), query(), {
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
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000 });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('returns.title')" :pageTitle="$t('returns.page_title')" />

    <StatusKpiCards
      :stats="stats"
      :model-value="filters.status"
      :all-label="$t('common.all_statuses')"
      @select="selectStatus"
    />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('returns.title') }}</h5>
        </template>

        <template #actions>
          <Link
            v-if="canBulkEditReturns"
            :href="route('bulk-status.index', { entity_type: 'RETURN' })"
            class="btn btn-soft-secondary"
            :title="$t('bulk_status.menu')"
            :aria-label="$t('bulk_status.menu')"
          >
            <i class="ri-list-check-3 align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('bulk_status.menu') }}</span>
          </Link>
          <Link v-if="can.hand_back" :href="route('returns.hand-back')" class="btn btn-soft-success">
            <i class="ri-e-bike-2-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('returns.hand_back.open') }}</span>
          </Link>
          <button v-if="can.scan" type="button" class="btn btn-soft-primary" @click="showQrScanner = true">
            <i class="ri-qr-scan-2-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('returns.qr_scan') }}</span>
          </button>
          <button
            v-if="can.create_request"
            data-guide="return-create-open"
            type="button"
            class="btn btn-success"
            @click="showCreateModal = true"
          >
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('returns.create') }}</span>
          </button>
        </template>

        <BCol md="3">
          <label class="form-label">{{ $t('returns.filters.reference') }}</label>
          <input v-model="filters.search" type="text" class="form-control" :placeholder="$t('returns.filters.reference_placeholder')" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('returns.filters.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('returns.filters.all_statuses') }}</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('returns.filters.city') }}</label>
          <select v-model="filters.city_id" class="form-select">
            <option value="">{{ $t('returns.filters.all_cities') }}</option>
            <option v-for="city in filterOptions.cities" :key="city.id" :value="city.id">{{ city.name }}</option>
          </select>
        </BCol>
        <BCol md="2" v-if="filterOptions.sellers?.length">
          <label class="form-label">{{ $t('returns.filters.seller') }}</label>
          <select v-model="filters.seller_id" class="form-select">
            <option value="">{{ $t('returns.filters.all_sellers') }}</option>
            <option v-for="seller in filterOptions.sellers" :key="seller.id" :value="seller.id">{{ seller.name }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('returns.filters.reason') }}</label>
          <select v-model="filters.reason" class="form-select">
            <option value="">{{ $t('returns.filters.all_reasons') }}</option>
            <option v-for="r in filterOptions.reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('returns.filters.created_from') }}</label>
          <input v-model="filters.created_from" type="date" class="form-control" />
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('returns.filters.created_to') }}</label>
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
            v-for="row in rows"
            :key="row.id"
            :title="row.reference"
            :subtitle="customerName(row) ?? ''"
            :status-label="row.status_label"
            :status-color="row.status_color"
            :rows="cardRows(row)"
            @open="selectedReturn = row"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('returns.empty') }}</p>
        </div>

        <div class="table-responsive d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light">
              <tr>
                <SortableTh field="reference" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.reference') }}
                </SortableTh>
                <SortableTh field="order_tracking" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.order_tracking') }}
                </SortableTh>
                <SortableTh field="customer" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.customer') }}
                </SortableTh>
                <SortableTh field="seller" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.seller') }}
                </SortableTh>
                <SortableTh field="reason" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.reason') }}
                </SortableTh>
                <SortableTh field="status" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.status') }}
                </SortableTh>
                <SortableTh field="driver" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.hand_back.driver') }}
                </SortableTh>
                <SortableTh field="current_city" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.current_city') }}
                </SortableTh>
                <SortableTh field="created_at" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('returns.table.created') }}
                </SortableTh>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="10" class="text-center text-muted py-4">{{ $t('returns.empty') }}</td>
              </tr>
              <tr v-for="row in rows" :key="row.id">
                <td><span class="fw-medium">{{ row.reference }}</span></td>
                <td>
                  <EntityLink v-if="row.order" type="order" :entity="row.order" />
                  <span v-else class="text-muted">—</span>
                </td>
                <td>{{ row.effective_customer_name || row.order?.customer?.full_name || '—' }}</td>
                <td>
                  <UserAvatar v-if="row.order?.seller" :user="row.order.seller" :size="24" clickable show-name />
                  <span v-else class="text-muted">—</span>
                </td>
                <td>{{ row.reason_label }}</td>
                <td>
                  <span class="badge" :class="`bg-${row.status_color}-subtle text-${row.status_color}`">{{ row.status_label }}</span>
                </td>
                <td>
                  <UserAvatar v-if="row.assigned_driver" :user="row.assigned_driver" :size="24" clickable show-name />
                  <span v-else class="text-muted">{{ $t('returns.hand_back.unassigned') }}</span>
                </td>
                <td>{{ row.current_location_city?.name || '—' }}</td>
                <td>{{ formatDate(row.created_at) }}</td>
                <td>
                  <Link :href="route('returns.show', row.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3" v-if="meta.total">
          <div class="text-muted">{{ meta.from }}–{{ meta.to }} / {{ meta.total }}</div>
          <ul class="pagination pagination-sm mb-0" v-if="meta.links">
            <li
              v-for="(link, i) in meta.links"
              :key="i"
              class="page-item"
              :class="{ active: link.active, disabled: !link.url }"
            >
              <button class="page-link" v-html="link.label" @click="goToPage(link.url)" :disabled="!link.url"></button>
            </li>
          </ul>
        </div>
      </BCardBody>
    </BCard>

    <CreateReturnModal v-if="can.create_request" :show="showCreateModal" :filter-options="filterOptions" @close="showCreateModal = false" />
    <ReturnQrScanner v-if="can.scan" :show="showQrScanner" @close="showQrScanner = false" />

    <EntityDetailSheet
      :show="selectedReturn !== null"
      :title="selectedReturn?.reference ?? ''"
      :subtitle="(selectedReturn ? customerName(selectedReturn) : '') ?? ''"
      :status-label="selectedReturn?.status_label ?? ''"
      :status-color="selectedReturn?.status_color ?? 'secondary'"
      :rows="selectedReturn ? sheetRows(selectedReturn) : []"
      @close="selectedReturn = null"
    >
      <template #actions>
        <Link
          :href="route('returns.show', selectedReturn?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
