<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import SortableTh from '@/Components/SortableTh.vue';
import { useTableSort } from '@/composables/useTableSort';

/**
 * The stock ledger, read whole.
 *
 * Every row here is immutable, so the screen is read-only by construction: the
 * only affordances are filters and links back to the document that caused the
 * movement.
 */

const { t } = useI18n();

const props = defineProps({
  movements: { type: Object, default: () => ({ data: [], meta: {} }) },
  filters: { type: Object, default: () => ({}) },
  sources: { type: Array, default: () => [] },
  reasons: { type: Array, default: () => [] },
  stores: { type: Array, default: () => [] },
});

const filters = reactive({
  product: props.filters.product ?? '',
  source: props.filters.source ?? '',
  reason: props.filters.reason ?? '',
  store_id: props.filters.store_id ?? '',
  from: props.filters.from ?? '',
  to: props.filters.to ?? '',
});

const rows = computed(() => props.movements.data ?? []);
const meta = computed(() => props.movements.meta ?? {});

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== '' && value !== null).length
);

const reload = () => {
  const params = { sort: sort.value, direction: direction.value };

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null) {
      params[key] = value;
    }
  });

  router.get(route('stock.movements'), params, {
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
    router.visit(url, { preserveState: true, preserveScroll: true });
  }
};

const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : t('common.empty_value'));

const signed = (delta) => (delta > 0 ? `+${delta}` : String(delta));
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.movements.title')" :pageTitle="$t('stock.page_title')" />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="reload" @reset="resetFilters">
        <template #title>
          <div>
            <h5 class="card-title mb-1">{{ $t('stock.movements.title') }}</h5>
            <p class="text-muted fs-13 mb-0">{{ $t('stock.movements.subtitle') }}</p>
          </div>
        </template>

        <BCol md="4">
          <label class="form-label">{{ $t('stock.movements.columns.product') }}</label>
          <input
            v-model="filters.product"
            type="search"
            class="form-control"
            :placeholder="$t('stock.movements.filters.product')"
            @keyup.enter="reload"
          />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('stock.movements.columns.store') }}</label>
          <select v-model="filters.store_id" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
          </select>
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('stock.movements.columns.source') }}</label>
          <select v-model="filters.source" class="form-select">
            <option value="">{{ $t('stock.movements.filters.source') }}</option>
            <option v-for="source in sources" :key="source.value" :value="source.value">
              {{ source.label }}
            </option>
          </select>
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('stock.movements.columns.reason') }}</label>
          <select v-model="filters.reason" class="form-select">
            <option value="">{{ $t('stock.movements.filters.reason') }}</option>
            <option v-for="reason in reasons" :key="reason.value" :value="reason.value">
              {{ reason.label }}
            </option>
          </select>
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('stock.movements.filters.from') }}</label>
          <input v-model="filters.from" type="date" class="form-control" />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('stock.movements.filters.to') }}</label>
          <input v-model="filters.to" type="date" class="form-control" />
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="table-responsive">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light">
              <tr>
                <SortableTh field="created_at" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.date') }}
                </SortableTh>
                <SortableTh field="product" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.product') }}
                </SortableTh>
                <SortableTh field="store" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.store') }}
                </SortableTh>
                <SortableTh field="source" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.source') }}
                </SortableTh>
                <SortableTh field="reason" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.reason') }}
                </SortableTh>
                <SortableTh field="stock_before" align="end" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.before') }}
                </SortableTh>
                <SortableTh field="delta" align="center" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.delta') }}
                </SortableTh>
                <SortableTh field="stock_after" align="end" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.after') }}
                </SortableTh>
                <SortableTh field="author" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stock.movements.columns.author') }}
                </SortableTh>
                <th>{{ $t('stock.movements.columns.document') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="10" class="text-center text-muted py-5">
                  <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-file-list-3-line"></i></div>
                  {{ $t('stock.movements.empty') }}
                </td>
              </tr>

              <tr v-for="row in rows" :key="row.id">
                <td class="text-muted">{{ formatDateTime(row.created_at) }}</td>
                <td>
                  <Link
                    v-if="row.product.id"
                    :href="route('products.show', row.product.id)"
                    class="fw-medium text-body"
                  >
                    {{ row.product.name }}
                  </Link>
                  <span v-else class="text-muted">{{ $t('common.empty_value') }}</span>
                  <span v-if="row.product.sku" class="d-block text-muted fs-12">{{ row.product.sku }}</span>
                </td>
                <td class="text-muted">{{ row.store ?? $t('common.empty_value') }}</td>
                <td>
                  <span class="badge" :class="`bg-${row.source_color}-subtle text-${row.source_color}`">
                    <i :class="`${row.source_icon} align-bottom me-1`"></i>{{ row.source_label }}
                  </span>
                </td>
                <td>
                  <span
                    v-if="row.reason_label"
                    class="badge"
                    :class="`bg-${row.reason_color}-subtle text-${row.reason_color}`"
                    :title="row.note ?? ''"
                  >
                    {{ row.reason_label }}
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-end text-muted">{{ row.stock_before }}</td>
                <td class="text-center">
                  <span class="fw-semibold" :class="row.delta > 0 ? 'text-success' : 'text-danger'">
                    {{ signed(row.delta) }}
                  </span>
                </td>
                <td class="text-end fw-medium">{{ row.stock_after }}</td>
                <td class="text-muted">{{ row.author ?? $t('common.empty_value') }}</td>
                <td class="text-muted">{{ row.reception ?? row.order ?? '—' }}</td>
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
  </Layout>
</template>
