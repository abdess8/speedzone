<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import StatusKpiCards from '@/Components/StatusKpiCards.vue';
import SortableTh from '@/Components/SortableTh.vue';
import { useTableSort } from '@/composables/useTableSort';
import PreparationScanner from './Partials/PreparationScanner.vue';

/**
 * The picking bench for orders served from a vendor's stock.
 *
 * These parcels are already on our shelves, so they skip the pickup flow
 * entirely: an agent reads the lines here, packs the box, and marks the order
 * prepared — by ticking rows, or by sweeping the QR scanner over a trolley.
 *
 * Each row states up front whether the box leaves on a local round or waits for
 * an inter-city transfer, because that changes where the agent puts it down.
 */

const { t } = useI18n();

const props = defineProps({
  orders: { type: Object, default: () => ({ data: [], meta: {} }) },
  stats: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  hubCities: { type: Array, default: () => [] },
});

const filters = reactive({
  search: props.filters.search ?? '',
  hub_city_id: props.filters.hub_city_id ?? '',
});

const selectedIds = ref([]);
const scannerOpen = ref(false);
const submitting = ref(false);

const rows = computed(() => props.orders.data ?? []);
const meta = computed(() => props.orders.meta ?? {});

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== '' && value !== null).length
);

const allSelected = computed(() => rows.value.length > 0 && selectedIds.value.length === rows.value.length);

const selectedUnits = computed(() =>
  rows.value.filter((row) => selectedIds.value.includes(row.id)).reduce((total, row) => total + row.units, 0)
);

const toggleAll = () => {
  selectedIds.value = allSelected.value ? [] : rows.value.map((row) => row.id);
};

const toggle = (id) => {
  selectedIds.value = selectedIds.value.includes(id)
    ? selectedIds.value.filter((current) => current !== id)
    : [...selectedIds.value, id];
};

const reload = () => {
  const params = { sort: sort.value, direction: direction.value };

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null) {
      params[key] = value;
    }
  });

  selectedIds.value = [];

  router.get(route('orders.preparation.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const { sort, direction, sortBy } = useTableSort(props.filters, reload);

const resetFilters = () => {
  Object.keys(filters).forEach((key) => {
    filters[key] = '';
  });
  reload();
};

const goToPage = (url) => {
  if (url) {
    selectedIds.value = [];
    router.visit(url, { preserveState: true, preserveScroll: true });
  }
};

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : t('common.empty_value'));

const prepare = (ids) => {
  if (ids.length === 0) {
    return;
  }

  Swal.fire({
    title: t('preparation.confirm.title', { count: ids.length }),
    text: t('preparation.confirm.text'),
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: t('preparation.actions.mark_prepared'),
    cancelButtonText: t('common.cancel'),
    confirmButtonColor: '#0ab39c',
  }).then((confirmed) => {
    if (!confirmed.isConfirmed) {
      return;
    }

    submitting.value = true;

    router.post(
      route('orders.preparation.prepare'),
      { ids },
      {
        preserveScroll: true,
        onFinish: () => {
          submitting.value = false;
          selectedIds.value = [];
        },
      }
    );
  });
};

onMounted(() => {
  const flash = usePage().props?.flash ?? {};
  const message = flash.success ?? flash.error;

  if (message) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: flash.success ? 'success' : 'error',
      title: message,
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('preparation.title')" :pageTitle="$t('orders.page_title')" />

    <!-- The queue is pinned to one status, so these report on the bench rather
         than filtering it. -->
    <StatusKpiCards :stats="stats" show-empty :clickable="false" />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="reload" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">
            {{ $t('preparation.queue_title') }}
            <span v-if="meta.total" class="badge bg-info-subtle text-info ms-1">{{ meta.total }}</span>
          </h5>
        </template>

        <template #actions>
          <button type="button" class="btn btn-primary" @click="scannerOpen = true">
            <i class="ri-qr-scan-2-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('preparation.actions.scan') }}</span>
          </button>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('preparation.columns.tracking') }}</label>
          <input
            v-model="filters.search"
            type="search"
            class="form-control"
            :placeholder="$t('preparation.filters.search')"
            @keyup.enter="reload"
          />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('preparation.columns.hub') }}</label>
          <select v-model="filters.hub_city_id" class="form-select">
            <option value="">{{ $t('preparation.filters.all_hubs') }}</option>
            <option v-for="city in hubCities" :key="city.id" :value="city.id">{{ city.name }}</option>
          </select>
        </BCol>
      </FilterPanel>

      <div v-if="selectedIds.length" class="bg-light border-top border-bottom px-3 py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <span class="fw-medium">
            {{ $t('preparation.selection.count', { count: selectedIds.length }) }}
            <span class="text-muted fw-normal">
              · {{ $t('preparation.selection.units', { count: selectedUnits }) }}
            </span>
          </span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-light" @click="selectedIds = []">
              {{ $t('common.cancel') }}
            </button>
            <button
              type="button"
              class="btn btn-sm btn-success"
              :disabled="submitting"
              @click="prepare(selectedIds)"
            >
              <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="ri-box-3-line align-bottom me-1"></i>
              {{ $t('preparation.actions.mark_prepared') }}
            </button>
          </div>
        </div>
      </div>

      <BCardBody>
        <div class="d-lg-none">
          <div v-for="row in rows" :key="row.id" class="border rounded p-3 mb-2">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div class="form-check">
                <input
                  :id="`pick-${row.id}`"
                  class="form-check-input"
                  type="checkbox"
                  :checked="selectedIds.includes(row.id)"
                  @change="toggle(row.id)"
                />
                <label class="form-check-label fw-medium" :for="`pick-${row.id}`">{{ row.tracking_number }}</label>
              </div>
              <span
                class="badge"
                :class="row.same_city ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'"
              >
                {{ row.same_city ? $t('preparation.routing.local') : $t('preparation.routing.transfer') }}
              </span>
            </div>

            <p class="text-muted fs-13 mb-2 mt-1">
              {{ row.customer }} · {{ row.city ?? $t('common.empty_value') }}
            </p>

            <ul class="list-unstyled mb-2">
              <li v-for="item in row.items" :key="item.id" class="fs-13">
                <span class="fw-medium">{{ item.quantity }} ×</span> {{ item.product_name }}
                <span class="text-muted">· {{ item.sku }}</span>
              </li>
            </ul>

            <div class="d-flex align-items-center justify-content-between">
              <span class="text-muted fs-13">
                <i class="ri-map-pin-line align-bottom me-1"></i>{{ row.hub_city ?? $t('common.empty_value') }}
              </span>
              <button type="button" class="btn btn-sm btn-soft-success" @click="prepare([row.id])">
                <i class="ri-box-3-line align-bottom me-1"></i>{{ $t('preparation.actions.mark_prepared') }}
              </button>
            </div>
          </div>

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('preparation.empty') }}</p>
        </div>

        <div class="table-responsive d-none d-lg-block">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 1%">
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="allSelected"
                    :disabled="rows.length === 0"
                    @change="toggleAll"
                  />
                </th>
                <SortableTh field="tracking_number" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('preparation.columns.tracking') }}
                </SortableTh>
                <SortableTh field="created_at" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('preparation.columns.created') }}
                </SortableTh>
                <SortableTh field="store" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('preparation.columns.store') }}
                </SortableTh>
                <SortableTh field="customer" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('preparation.columns.customer') }}
                </SortableTh>
                <th>{{ $t('preparation.columns.lines') }}</th>
                <th>{{ $t('preparation.columns.routing') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="8" class="text-center text-muted py-5">
                  <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-inbox-line"></i></div>
                  <p class="mb-1">{{ $t('preparation.empty') }}</p>
                  <p class="fs-13 mb-0">{{ $t('preparation.empty_hint') }}</p>
                </td>
              </tr>

              <tr v-for="row in rows" :key="row.id" :class="{ 'table-active': selectedIds.includes(row.id) }">
                <td>
                  <input
                    class="form-check-input"
                    type="checkbox"
                    :checked="selectedIds.includes(row.id)"
                    @change="toggle(row.id)"
                  />
                </td>
                <td>
                  <Link :href="route('orders.show', row.id)" class="fw-medium text-body">
                    {{ row.tracking_number }}
                  </Link>
                </td>
                <td class="text-muted">{{ formatDate(row.created_at) }}</td>
                <td>
                  <span class="d-block">{{ row.store ?? $t('common.empty_value') }}</span>
                  <span class="text-muted fs-13">{{ row.seller ?? '' }}</span>
                </td>
                <td>
                  <span class="d-block">{{ row.customer }}</span>
                  <span class="text-muted fs-13">
                    <i class="ri-map-pin-line align-bottom me-1"></i>{{ row.city ?? $t('common.empty_value') }}
                  </span>
                </td>
                <td>
                  <ul class="list-unstyled mb-0">
                    <li v-for="item in row.items" :key="item.id" class="fs-13">
                      <span class="fw-medium">{{ item.quantity }} ×</span> {{ item.product_name }}
                      <span class="text-muted">· {{ item.sku }}</span>
                    </li>
                  </ul>
                </td>
                <td>
                  <span
                    class="badge"
                    :class="row.same_city ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'"
                  >
                    {{ row.same_city ? $t('preparation.routing.local') : $t('preparation.routing.transfer') }}
                  </span>
                  <span class="d-block text-muted fs-13 mt-1">{{ row.hub_city ?? $t('common.empty_value') }}</span>
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-soft-success" @click="prepare([row.id])">
                    <i class="ri-box-3-line align-bottom me-1"></i>{{ $t('preparation.actions.prepare_short') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="meta.total" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <div class="text-muted">{{ meta.from }}–{{ meta.to }} / {{ meta.total }}</div>
          <ul v-if="meta.links" class="pagination pagination-sm mb-0">
            <li
              v-for="(link, index) in meta.links"
              :key="index"
              class="page-item"
              :class="{ active: link.active, disabled: !link.url }"
            >
              <button class="page-link" :disabled="!link.url" @click="goToPage(link.url)" v-html="link.label"></button>
            </li>
          </ul>
        </div>
      </BCardBody>
    </BCard>

    <PreparationScanner :show="scannerOpen" @close="scannerOpen = false" />
  </Layout>
</template>
