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
  roles: { type: Object, default: () => ({ data: [], links: [] }) },
  stats: { type: Object, default: () => ({}) },
  sellers: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const filters = reactive({
  search: props.filters.search ?? '',
  seller_id: props.filters.seller_id ?? '',
});

const selected = ref(null);
const rows = computed(() => props.roles.data ?? []);

const activeFilterCount = computed(
  () => [filters.search, filters.seller_id].filter(Boolean).length
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
  router.get(route('team.roles.index'), query(), {
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
  reload();
};

const cardRows = (row) => [
  { label: t('team.roles.admin.table.seller'), value: row.seller?.name },
  { label: t('team.roles.fields.permissions'), value: t('team.roles.permissions_count', { count: row.permissions_count }) },
  { label: t('team.roles.admin.table.members'), value: t('team.roles.members_count', { count: row.members_count }) },
];

const sheetRows = (row) => [
  { label: t('team.roles.admin.table.seller'), value: row.seller?.name },
  { label: t('users.table.email'), value: row.seller?.email },
  { label: t('team.roles.fields.permissions'), value: t('team.roles.permissions_count', { count: row.permissions_count }) },
  { label: t('team.roles.admin.table.members'), value: t('team.roles.members_count', { count: row.members_count }) },
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
    <PageHeader :title="$t('team.roles.admin.title')" :pageTitle="$t('team.title')" />

    <BRow class="g-3 mb-3">
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('team.roles.admin.stats.total') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.total ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('team.roles.admin.stats.sellers') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.sellers ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('team.roles.admin.stats.assigned') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.assigned ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-1">{{ $t('team.roles.admin.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('team.roles.admin.subtitle') }}</p>
        </template>

        <template #actions>
          <Link :href="route('team.index')" class="btn btn-light">
            <i class="ri-arrow-left-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('team.roles.back') }}</span>
          </Link>
          <Link v-if="can.create" :href="route('team.roles.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('team.roles.add') }}</span>
          </Link>
        </template>

        <BCol md="6">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="text"
            class="form-control"
            :placeholder="$t('team.roles.admin.search_placeholder')"
            @keyup.enter="applyFilters"
          />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('team.roles.admin.table.seller') }}</label>
          <select v-model="filters.seller_id" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option v-for="seller in sellers" :key="seller.id" :value="seller.id">
              {{ seller.name }}
            </option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="row in rows"
            :key="row.id"
            :title="row.label"
            :subtitle="row.seller?.name ?? ''"
            :rows="cardRows(row)"
            @open="selected = row"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('team.roles.admin.empty') }}
          </p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <SortableTh field="label" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('team.roles.fields.label') }}
                </SortableTh>
                <SortableTh field="seller" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('team.roles.admin.table.seller') }}
                </SortableTh>
                <SortableTh field="permissions" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('team.roles.fields.permissions') }}
                </SortableTh>
                <SortableTh field="members" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('team.roles.admin.table.members') }}
                </SortableTh>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td class="fw-semibold">{{ row.label }}</td>
                <td>
                  <div class="fw-semibold">{{ row.seller?.name || $t('common.empty_value') }}</div>
                  <div class="text-muted fs-12">{{ row.seller?.email }}</div>
                </td>
                <td>{{ $t('team.roles.permissions_count', { count: row.permissions_count }) }}</td>
                <td>{{ $t('team.roles.members_count', { count: row.members_count }) }}</td>
                <td class="text-end">
                  <Link :href="route('team.roles.edit', row.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-pencil-line align-bottom me-1"></i>
                    {{ $t('common.edit') }}
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="5" class="text-center text-muted py-4">
                  {{ $t('team.roles.admin.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="roles.last_page > 1" class="d-flex justify-content-end mt-3">
          <ul class="pagination pagination-sm mb-0">
            <li
              v-for="(link, index) in roles.links"
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
      :title="selected?.label ?? ''"
      :subtitle="selected?.seller?.name ?? ''"
      :rows="selected ? sheetRows(selected) : []"
      @close="selected = null"
    >
      <template #actions>
        <Link :href="route('team.roles.edit', selected?.id)" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-pencil-line align-bottom me-1"></i>
          {{ $t('common.edit') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
