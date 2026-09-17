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
import { useTableSort } from '@/composables/useTableSort';

const props = defineProps({
  stores: { type: Object, default: () => ({ data: [], links: [] }) },
  stats: { type: Object, default: () => ({}) },
  sellers: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const filters = reactive({
  search: props.filters.search ?? '',
  seller_id: props.filters.seller_id ?? '',
  status: props.filters.status ?? '',
});

const selected = ref(null);
const rows = computed(() => props.stores.data ?? []);

const activeFilterCount = computed(
  () => [filters.search, filters.seller_id, filters.status].filter(Boolean).length
);

const query = () => {
  const params = { sort: sort.value, direction: direction.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null) {
      params[key] = value;
    }
  });
  return params;
};

const reload = () => {
  router.get(route('stores.index'), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const { sort, direction, sortBy } = useTableSort(props.filters, reload);

const applyFilters = () => reload();

const resetFilters = () => {
  filters.search = '';
  filters.seller_id = '';
  filters.status = '';
  reload();
};

const statusMeta = (row) => {
  if (!row.is_active) {
    return { class: 'bg-danger-subtle text-danger', color: 'danger', label: t('common.inactive') };
  }

  return { class: 'bg-success-subtle text-success', color: 'success', label: t('common.active') };
};

const cardRows = (row) => [
  { label: t('stores.admin.table.seller'), value: row.owner?.name },
  { label: t('stores.fields.category'), value: row.category || t('stores.no_category') },
  { label: t('stores.fields.city'), value: row.city?.name },
  { label: t('stores.admin.table.orders'), value: row.orders_count ?? 0 },
];

const sheetRows = (row) => [
  { label: t('stores.admin.table.seller'), value: row.owner?.name },
  { label: t('users.table.email'), value: row.owner?.email },
  { label: t('stores.fields.category'), value: row.category || t('stores.no_category') },
  { label: t('stores.fields.city'), value: row.city?.name },
  { label: t('stores.admin.table.orders'), value: row.orders_count ?? 0 },
  { label: t('stores.fields.contact_phone'), value: row.contact_phone },
];

const showFlash = () => {
  const flash = usePage().props?.flash ?? {};

  if (flash.success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: flash.success,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }

  if (flash.error) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'error',
      title: flash.error,
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
    });
  }
};

onMounted(showFlash);

watch(
  () => [usePage().props?.flash?.success, usePage().props?.flash?.error],
  showFlash,
);
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stores.admin.title')" :pageTitle="$t('sidebar.my_shop')" />

    <BRow class="g-3 mb-3">
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('stores.admin.stats.total') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.total ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('stores.admin.stats.sellers') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.sellers ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('stores.admin.stats.inactive') }}</div>
            <div class="fs-4 fw-semibold mt-1 text-danger">{{ stats.inactive ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-1">{{ $t('stores.admin.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('stores.admin.subtitle') }}</p>
        </template>

        <template #actions>
          <Link v-if="can.create" :href="route('stores.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('stores.create_button') }}</span>
          </Link>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="text"
            class="form-control"
            :placeholder="$t('stores.admin.search_placeholder')"
            @keyup.enter="applyFilters"
          />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('stores.admin.table.seller') }}</label>
          <select v-model="filters.seller_id" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="seller in sellers" :key="seller.id" :value="seller.id">
              {{ seller.name }}
            </option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option value="active">{{ $t('common.active') }}</option>
            <option value="inactive">{{ $t('common.inactive') }}</option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="row in rows"
            :key="row.id"
            :title="row.name"
            :subtitle="row.owner?.name ?? ''"
            :status-label="statusMeta(row).label"
            :status-color="statusMeta(row).color"
            :rows="cardRows(row)"
            @open="selected = row"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('stores.admin.empty') }}
          </p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <SortableTh field="name" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stores.fields.name') }}
                </SortableTh>
                <SortableTh field="seller" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('stores.admin.table.seller') }}
                </SortableTh>
                <th>{{ $t('stores.fields.city') }}</th>
                <th class="text-center">{{ $t('stores.admin.table.orders') }}</th>
                <SortableTh field="status" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('common.status') }}
                </SortableTh>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>
                  <div class="fw-semibold">{{ row.name }}</div>
                  <div class="text-muted fs-12">{{ row.category || $t('stores.no_category') }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ row.owner?.name || $t('common.empty_value') }}</div>
                  <div class="text-muted fs-12">{{ row.owner?.email }}</div>
                </td>
                <td>{{ row.city?.name || $t('common.empty_value') }}</td>
                <td class="text-center">
                  <span class="badge bg-light text-body">{{ row.orders_count ?? 0 }}</span>
                </td>
                <td>
                  <span class="badge" :class="statusMeta(row).class">{{ statusMeta(row).label }}</span>
                  <span v-if="row.is_default" class="badge bg-primary-subtle text-primary ms-1">
                    {{ $t('stores.badges.default') }}
                  </span>
                </td>
                <td class="text-end">
                  <Link :href="route('stores.edit', row.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-pencil-line align-bottom me-1"></i>
                    {{ $t('common.edit') }}
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="6" class="text-center text-muted py-4">
                  {{ $t('stores.admin.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="stores.last_page > 1" class="d-flex justify-content-end mt-3">
          <ul class="pagination pagination-sm mb-0">
            <li
              v-for="(link, index) in stores.links"
              :key="index"
              class="page-item"
              :class="{ active: link.active, disabled: !link.url }"
            >
              <Link v-if="link.url" class="page-link" :href="link.url" preserve-scroll v-html="link.label" />
              <span v-else class="page-link" v-html="link.label"></span>
            </li>
          </ul>
        </div>
      </BCardBody>
    </BCard>

    <EntityDetailSheet
      :show="selected !== null"
      :title="selected?.name ?? ''"
      :subtitle="selected?.owner?.name ?? ''"
      :status-label="selected ? statusMeta(selected).label : ''"
      :status-color="selected ? statusMeta(selected).color : 'secondary'"
      :rows="selected ? sheetRows(selected) : []"
      @close="selected = null"
    >
      <template #actions>
        <Link :href="route('stores.edit', selected?.id)" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-pencil-line align-bottom me-1"></i>
          {{ $t('common.edit') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
