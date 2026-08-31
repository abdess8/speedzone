<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import EntityCard from '@/Components/EntityCard.vue';
import EntityDetailSheet from '@/Components/EntityDetailSheet.vue';
import SortableTh from '@/Components/SortableTh.vue';
import ProductThumb from '../Partials/ProductThumb.vue';
import StockLevel from '../Partials/StockLevel.vue';
import { formatMoney as money, formatMoneyRounded } from '@/common/formatMoney';
import { useTableSort } from '@/composables/useTableSort';

/**
 * Vendor product catalog.
 *
 * The headline row answers the question a seller opens this page with — how many
 * references, how many units, what is about to run out — before the table asks
 * him to read anything.
 */

const { t } = useI18n();

const props = defineProps({
  products: { type: Object, default: () => ({ data: [], meta: {} }) },
  filters: { type: Object, default: () => ({}) },
  categories: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? '',
  category: props.filters.category ?? '',
  stock_status: props.filters.stock_status ?? '',
  status: props.filters.status ?? '',
});

const perPage = ref(props.filters.per_page ?? 25);
const selected = ref(null);

const rows = computed(() => props.products.data ?? []);
const meta = computed(() => props.products.meta ?? {});

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== '' && value !== null).length
);

const stats = computed(() => [
  {
    key: 'products',
    label: t('stock.products.summary.products'),
    value: props.summary.products ?? 0,
    icon: 'ri-price-tag-3-line',
    tone: 'primary',
  },
  {
    key: 'units',
    label: t('stock.products.summary.units'),
    value: props.summary.units ?? 0,
    icon: 'ri-stack-line',
    tone: 'info',
  },
  {
    key: 'out_of_stock',
    label: t('stock.products.summary.out_of_stock'),
    value: props.summary.out_of_stock ?? 0,
    icon: 'ri-error-warning-line',
    tone: 'danger',
  },
  {
    key: 'low_stock',
    label: t('stock.products.summary.low_stock'),
    value: props.summary.low_stock ?? 0,
    icon: 'ri-alert-line',
    tone: 'warning',
  },
  {
    key: 'stock_value',
    label: t('stock.products.summary.stock_value'),
    value: `${formatMoneyRounded(props.summary.stock_value ?? 0)} ${t('common.currency_mad')}`,
    icon: 'ri-wallet-3-line',
    tone: 'success',
  },
]);

const query = () => {
  const params = { per_page: perPage.value, sort: sort.value, direction: direction.value };

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null) {
      params[key] = value;
    }
  });

  return params;
};

const reload = () =>
  router.get(route('products.index'), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });

const { sort, direction, sortBy } = useTableSort(props.filters, reload);

const applyFilters = () => reload();

const resetFilters = () => {
  Object.keys(filters).forEach((key) => {
    filters[key] = '';
  });
  reload();
};

watch(perPage, reload);

const goToPage = (url) => {
  if (url) {
    router.visit(url, { preserveState: true, preserveScroll: true });
  }
};

const cardRows = (row) => [
  { label: t('stock.products.columns.sku'), value: row.sku },
  { label: t('stock.products.columns.unit_price'), value: `${money(row.unit_price)} ${t('common.currency_mad')}` },
  { label: t('stock.products.columns.stock'), value: String(row.stock_quantity), emphasis: true },
];

const sheetRows = (row) => [
  ...cardRows(row),
  { label: t('stock.products.columns.barcode'), value: row.barcode },
  { label: t('stock.products.columns.category'), value: row.category ?? t('stock.products.no_category') },
  {
    label: t('stock.products.columns.cost_price'),
    value: row.cost_price === null ? null : `${money(row.cost_price)} ${t('common.currency_mad')}`,
  },
  {
    label: t('stock.products.columns.margin'),
    value: row.margin === null ? null : `${money(row.margin)} ${t('common.currency_mad')}`,
  },
];

/** Colour and wording of the status column, worst state first. */
const statusBadge = (row) => {
  if (row.is_blocked) {
    return { label: t('stock.products.badges.blocked'), tone: 'danger' };
  }
  if (!row.is_active) {
    return { label: t('stock.products.badges.archived'), tone: 'secondary' };
  }
  if (row.is_out_of_stock) {
    return { label: t('stock.products.badges.out_of_stock'), tone: 'danger' };
  }
  if (row.is_low_stock) {
    return { label: t('stock.products.badges.low_stock'), tone: 'warning' };
  }

  return { label: t('stock.products.filters.in_stock'), tone: 'success' };
};

async function archive(row) {
  const confirmation = await Swal.fire({
    icon: 'warning',
    title: t('stock.products.archive.title'),
    text: t('stock.products.archive.text'),
    showCancelButton: true,
    confirmButtonText: t('stock.products.archive.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (confirmation.isConfirmed) {
    router.delete(route('products.destroy', row.id), { preserveScroll: true });
  }
}

onMounted(() => {
  const success = usePage().props?.flash?.success;

  if (success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: success,
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.products.title')" :pageTitle="$t('stock.page_title')" />

    <BRow data-guide="stock-summary" class="g-2 g-lg-3 mb-1">
      <BCol v-for="stat in stats" :key="stat.key" cols="6" lg>
        <BCard no-body class="h-100">
          <BCardBody class="p-3">
            <div class="d-flex align-items-center gap-2">
              <span class="avatar-xs flex-shrink-0">
                <span class="avatar-title rounded" :class="`bg-${stat.tone}-subtle text-${stat.tone}`">
                  <i :class="stat.icon"></i>
                </span>
              </span>
              <div class="min-w-0">
                <p class="text-muted text-truncate fs-12 mb-0">{{ stat.label }}</p>
                <h5 class="mb-0 fs-16">{{ stat.value }}</h5>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard no-body>
      <FilterPanel
        guide="stock-list"
        :active-count="activeFilterCount"
        @apply="applyFilters"
        @reset="resetFilters"
      >
        <template #title>
          <h5 class="card-title mb-0">{{ $t('stock.products.list_title') }}</h5>
        </template>

        <template #actions>
          <Link
            v-if="can.audit"
            :href="route('stock.movements')"
            class="btn btn-soft-secondary"
            :title="$t('stock.movements.title')"
          >
            <i class="ri-history-line align-bottom"></i>
            <span class="d-none d-xl-inline ms-1">{{ $t('stock.movements.title') }}</span>
          </Link>
          <Link :href="route('stock.inventory')" class="btn btn-soft-warning">
            <i class="ri-list-check-2 align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('stock.inventory.title') }}</span>
          </Link>
          <Link
            v-if="can.import"
            data-guide="stock-import"
            :href="route('products.import')"
            class="btn btn-soft-primary"
          >
            <i class="ri-file-excel-2-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('stock.products.import_button') }}</span>
          </Link>
          <Link v-if="can.create" data-guide="stock-create" :href="route('products.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('stock.products.create_button') }}</span>
          </Link>
        </template>

        <BCol md="4">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="search"
            class="form-control"
            :placeholder="$t('stock.products.filters.search')"
            @keyup.enter="applyFilters"
          />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('stock.products.columns.category') }}</label>
          <select v-model="filters.category" class="form-select">
            <option value="">{{ $t('stock.products.filters.category') }}</option>
            <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('stock.products.columns.stock') }}</label>
          <select v-model="filters.stock_status" class="form-select">
            <option value="">{{ $t('stock.products.filters.stock_status') }}</option>
            <option value="in">{{ $t('stock.products.filters.in_stock') }}</option>
            <option value="low">{{ $t('stock.products.filters.low_stock') }}</option>
            <option value="out">{{ $t('stock.products.filters.out_of_stock') }}</option>
            <option value="blocked">{{ $t('stock.products.filters.blocked') }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('stock.products.filters.status') }}</option>
            <option value="active">{{ $t('stock.products.filters.active') }}</option>
            <option value="archived">{{ $t('stock.products.filters.archived') }}</option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="row in rows"
            :key="row.id"
            :title="row.name"
            :subtitle="row.category ?? $t('stock.products.no_category')"
            :status-label="statusBadge(row).label"
            :status-color="statusBadge(row).tone"
            :rows="cardRows(row)"
            @open="selected = row"
          >
            <template #avatar>
              <ProductThumb :name="row.name" :photo-url="row.photo_url" :initials="row.initials" :size="44" />
            </template>
          </EntityCard>

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ activeFilterCount > 0 ? $t('stock.products.no_results') : $t('stock.products.empty') }}
          </p>
        </div>

        <div class="table-responsive d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light">
              <tr>
                <SortableTh field="name" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.products.columns.product') }}
                </SortableTh>
                <SortableTh field="sku" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.products.columns.sku') }}
                </SortableTh>
                <SortableTh field="category" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.products.columns.category') }}
                </SortableTh>
                <SortableTh field="unit_price" align="end" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.products.columns.unit_price') }}
                </SortableTh>
                <SortableTh field="margin" align="end" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.products.columns.margin') }}
                </SortableTh>
                <SortableTh field="stock_quantity" align="center" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.products.columns.stock') }}
                </SortableTh>
                <th>{{ $t('stock.products.columns.status') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="8" class="text-center text-muted py-5">
                  <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-archive-2-line"></i></div>
                  <p class="mb-1">
                    {{ activeFilterCount > 0 ? $t('stock.products.no_results') : $t('stock.products.empty') }}
                  </p>
                  <p v-if="activeFilterCount === 0" class="fs-13 mb-0">{{ $t('stock.products.empty_hint') }}</p>
                </td>
              </tr>

              <tr v-for="row in rows" :key="row.id">
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <ProductThumb :name="row.name" :photo-url="row.photo_url" :initials="row.initials" />
                    <div class="min-w-0">
                      <Link :href="route('products.show', row.id)" class="fw-medium text-body d-block text-truncate">
                        {{ row.name }}
                      </Link>
                      <span v-if="row.seller" class="text-muted fs-12">{{ row.seller.name }}</span>
                      <span v-else-if="row.barcode" class="text-muted fs-12">{{ row.barcode }}</span>
                    </div>
                    <i
                      v-if="row.is_fragile"
                      class="ri-alarm-warning-line text-warning"
                      :title="$t('stock.products.badges.fragile')"
                    ></i>
                  </div>
                </td>
                <td><code class="text-body">{{ row.sku }}</code></td>
                <td>{{ row.category ?? $t('stock.products.no_category') }}</td>
                <td class="text-end">{{ money(row.unit_price) }}</td>
                <td class="text-end">
                  <span v-if="row.margin === null" class="text-muted">{{ $t('common.empty_value') }}</span>
                  <span v-else :class="row.margin < 0 ? 'text-danger' : 'text-success'">{{ money(row.margin) }}</span>
                </td>
                <td class="text-center">
                  <StockLevel
                    :quantity="row.stock_quantity"
                    :is-low-stock="row.is_low_stock"
                    :is-out-of-stock="row.is_out_of_stock"
                    compact
                  />
                </td>
                <td>
                  <span
                    class="badge"
                    :class="`bg-${statusBadge(row).tone}-subtle text-${statusBadge(row).tone}`"
                    :title="row.blocked_reason ?? ''"
                  >
                    {{ statusBadge(row).label }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="hstack gap-1 justify-content-end">
                    <Link :href="route('products.show', row.id)" class="btn btn-sm btn-soft-primary">
                      <i class="ri-eye-line"></i>
                    </Link>
                    <Link
                      v-if="can.create"
                      :href="route('products.edit', row.id)"
                      class="btn btn-sm btn-soft-secondary"
                    >
                      <i class="ri-pencil-line"></i>
                    </Link>
                    <button
                      v-if="can.create && row.is_active"
                      type="button"
                      class="btn btn-sm btn-soft-danger"
                      :title="$t('stock.products.actions.archive')"
                      @click="archive(row)"
                    >
                      <i class="ri-archive-line"></i>
                    </button>
                  </div>
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

    <EntityDetailSheet
      :show="selected !== null"
      :title="selected?.name ?? ''"
      :subtitle="selected?.sku ?? ''"
      :status-label="selected ? statusBadge(selected).label : ''"
      :status-color="selected ? statusBadge(selected).tone : 'secondary'"
      :rows="selected ? sheetRows(selected) : []"
      @close="selected = null"
    >
      <template #actions>
        <Link :href="route('products.show', selected?.id)" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
        <Link
          v-if="can.create"
          :href="route('products.edit', selected?.id)"
          class="btn btn-soft-secondary sheet-action"
        >
          <i class="ri-pencil-line align-bottom"></i>
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
