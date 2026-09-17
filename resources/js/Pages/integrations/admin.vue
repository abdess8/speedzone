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
  integrations: { type: Object, default: () => ({ data: [], links: [] }) },
  stats: { type: Object, default: () => ({}) },
  platforms: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const filters = reactive({
  search: props.filters.search ?? '',
  platform: props.filters.platform ?? '',
  status: props.filters.status ?? '',
});

const selected = ref(null);
const rows = computed(() => props.integrations.data ?? []);

const activeFilterCount = computed(
  () => [filters.search, filters.platform, filters.status].filter(Boolean).length
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
  router.get(route('integrations.index'), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const { sort, direction, sortBy } = useTableSort(props.filters, reload);

const applyFilters = () => reload();

const resetFilters = () => {
  filters.search = '';
  filters.platform = '';
  filters.status = '';
  reload();
};

const statusClass = (status) => {
  if (status === 'connected') {
    return { class: 'bg-success-subtle text-success', color: 'success' };
  }
  if (status === 'error') {
    return { class: 'bg-danger-subtle text-danger', color: 'danger' };
  }
  if (status === 'pending') {
    return { class: 'bg-info-subtle text-info', color: 'info' };
  }
  return { class: 'bg-secondary-subtle text-secondary', color: 'secondary' };
};

const statusLabel = (status) => t(`integrations.status.${status}`);

const formatDate = (value) => {
  if (!value) {
    return t('integrations.sync.never');
  }

  return new Date(value).toLocaleString();
};

const shopLabel = (row) => row.shop_name || row.shop_slug || t('common.empty_value');

const cardRows = (row) => [
  { label: t('integrations.admin.table.store'), value: row.store?.name },
  { label: t('integrations.admin.table.shop'), value: shopLabel(row) },
  { label: t('integrations.admin.table.last_sync'), value: formatDate(row.last_synced_at) },
];

const sheetRows = (row) => [
  { label: t('integrations.admin.table.user'), value: row.seller?.name },
  { label: t('users.table.email'), value: row.seller?.email },
  { label: t('integrations.admin.table.store'), value: row.store?.name },
  { label: t('integrations.admin.table.platform'), value: row.platform_name },
  { label: t('integrations.admin.table.shop'), value: shopLabel(row) },
  { label: t('integrations.admin.sheet.email'), value: row.email },
  { label: t('integrations.admin.sheet.connected_at'), value: formatDate(row.connected_at) },
  { label: t('integrations.admin.table.last_sync'), value: formatDate(row.last_synced_at) },
  { label: t('integrations.admin.table.history'), value: row.syncs_count },
  { label: t('integrations.admin.sheet.error'), value: row.last_error },
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
    <PageHeader :title="$t('integrations.admin.title')" :pageTitle="$t('common.settings')" />

    <BRow class="g-3 mb-3">
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('integrations.admin.stats.total') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.total ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('integrations.admin.stats.connected') }}</div>
            <div class="fs-4 fw-semibold mt-1 text-success">{{ stats.connected ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('integrations.admin.stats.error') }}</div>
            <div class="fs-4 fw-semibold mt-1 text-danger">{{ stats.error ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-1">{{ $t('integrations.admin.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('integrations.admin.subtitle') }}</p>
        </template>

        <template #actions>
          <div v-if="can.manage" class="d-flex flex-wrap gap-2">
            <Link
              :href="route('integrations.youcan')"
              class="btn btn-success"
            >
              <i class="ri-add-line align-bottom"></i>
              <span class="d-none d-sm-inline ms-1">{{ $t('integrations.admin.create_youcan') }}</span>
            </Link>
            <Link
              :href="route('integrations.shopify')"
              class="btn btn-success"
            >
              <i class="ri-add-line align-bottom"></i>
              <span class="d-none d-sm-inline ms-1">{{ $t('integrations.admin.create_shopify') }}</span>
            </Link>
          </div>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="text"
            class="form-control"
            :placeholder="$t('integrations.admin.search_placeholder')"
            @keyup.enter="applyFilters"
          />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('integrations.admin.table.platform') }}</label>
          <select v-model="filters.platform" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="platform in platforms" :key="platform.value" :value="platform.value">
              {{ platform.label }}
            </option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="status in statuses" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="row in rows"
            :key="row.id"
            :title="row.seller?.name || $t('common.empty_value')"
            :subtitle="row.seller?.email ?? ''"
            :status-label="statusLabel(row.status)"
            :status-color="statusClass(row.status).color"
            :rows="cardRows(row)"
            @open="selected = row"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('integrations.admin.empty') }}
          </p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <SortableTh field="seller" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('integrations.admin.table.user') }}
                </SortableTh>
                <th>{{ $t('integrations.admin.table.store') }}</th>
                <SortableTh field="platform" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('integrations.admin.table.platform') }}
                </SortableTh>
                <th>{{ $t('integrations.admin.table.shop') }}</th>
                <SortableTh field="status" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('common.status') }}
                </SortableTh>
                <SortableTh field="last_synced_at" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('integrations.admin.table.last_sync') }}
                </SortableTh>
                <th class="text-center">{{ $t('integrations.admin.table.history') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>
                  <div class="fw-semibold">{{ row.seller?.name || $t('common.empty_value') }}</div>
                  <div class="text-muted fs-12">{{ row.seller?.email }}</div>
                </td>
                <td>{{ row.store?.name || $t('common.empty_value') }}</td>
                <td>
                  <span class="d-inline-flex align-items-center gap-2">
                    <span
                      class="platform-mark"
                      :style="{ backgroundColor: row.platform_color }"
                    >
                      <i :class="row.platform_icon"></i>
                    </span>
                    {{ row.platform_name }}
                  </span>
                </td>
                <td>
                  <div>{{ shopLabel(row) }}</div>
                  <div v-if="row.shop_slug && row.shop_name" class="text-muted fs-12">
                    {{ row.shop_slug }}
                  </div>
                </td>
                <td>
                  <span class="badge" :class="statusClass(row.status).class">
                    {{ statusLabel(row.status) }}
                  </span>
                </td>
                <td>{{ formatDate(row.last_synced_at) }}</td>
                <td class="text-center">
                  <span class="badge bg-light text-body">{{ row.syncs_count }}</span>
                </td>
                <td class="text-end">
                  <Link :href="row.manage_url" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line align-bottom me-1"></i>
                    {{ $t('common.view') }}
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="8" class="text-center text-muted py-4">
                  {{ $t('integrations.admin.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-end mt-3" v-if="integrations.last_page > 1">
          <ul class="pagination pagination-sm mb-0">
            <li
              v-for="(link, index) in integrations.links"
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
      :title="selected?.seller?.name ?? ''"
      :subtitle="selected?.store?.name ?? ''"
      :status-label="selected ? statusLabel(selected.status) : ''"
      :status-color="selected ? statusClass(selected.status).color : 'secondary'"
      :rows="selected ? sheetRows(selected) : []"
      @close="selected = null"
    >
      <template #actions>
        <Link :href="selected?.manage_url" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-history-line align-bottom me-1"></i>
          {{ $t('common.view') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>

<style scoped>
.platform-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.375rem;
  font-size: 0.85rem;
  color: #fff;
}
</style>
