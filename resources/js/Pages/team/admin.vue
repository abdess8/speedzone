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
  members: { type: Object, default: () => ({ data: [], links: [] }) },
  stats: { type: Object, default: () => ({}) },
  sellers: { type: Array, default: () => [] },
  statuses: { type: Array, default: () => [] },
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
const confirming = ref(null);
const rows = computed(() => props.members.data ?? []);

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
  router.get(route('team.index'), query(), {
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

const statusColor = (status) => {
  if (status === 'ACTIVE') {
    return 'success';
  }
  if (status === 'SUSPENDED') {
    return 'dark';
  }

  return 'secondary';
};

const lastActivityLabel = (member) => {
  if (!member?.last_activity) {
    return t('team.sessions.never');
  }

  return new Date(member.last_activity * 1000).toLocaleString();
};

const cardRows = (row) => [
  { label: t('team.admin.table.seller'), value: row.seller?.name },
  { label: t('team.fields.roles'), value: (row.roles ?? []).join(', ') },
  { label: t('team.fields.stores'), value: (row.stores ?? []).join(', ') },
];

const sheetRows = (row) => [
  { label: t('team.admin.table.seller'), value: row.seller?.name },
  { label: t('users.table.email'), value: row.seller?.email },
  { label: t('team.fields.email'), value: row.email },
  { label: t('team.fields.roles'), value: (row.roles ?? []).join(', ') },
  { label: t('team.fields.stores'), value: (row.stores ?? []).join(', ') },
  { label: t('team.fields.sessions'), value: lastActivityLabel(row) },
];

const askSuspend = (member) => {
  confirming.value = member;
};

const suspend = () => {
  const member = confirming.value;
  confirming.value = null;
  router.put(route('team.suspend', member.id), {}, { preserveScroll: true });
};

const reactivate = (member) => {
  router.put(route('team.reactivate', member.id), {}, { preserveScroll: true });
};

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
    <PageHeader :title="$t('team.admin.title')" :pageTitle="$t('sidebar.my_shop')" />

    <BRow class="g-3 mb-3">
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('team.admin.stats.total') }}</div>
            <div class="fs-4 fw-semibold mt-1">{{ stats.total ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('team.admin.stats.active') }}</div>
            <div class="fs-4 fw-semibold mt-1 text-success">{{ stats.active ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
      <BCol md="4">
        <BCard no-body>
          <BCardBody>
            <div class="text-muted fs-13">{{ $t('team.admin.stats.suspended') }}</div>
            <div class="fs-4 fw-semibold mt-1 text-danger">{{ stats.suspended ?? 0 }}</div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-1">{{ $t('team.admin.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('team.admin.subtitle') }}</p>
        </template>

        <template #actions>
          <Link
            v-if="can.manage_roles"
            :href="route('team.roles.index')"
            class="btn btn-light"
          >
            <i class="ri-shield-user-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('team.manage_roles') }}</span>
          </Link>
          <Link v-if="can.create" :href="route('team.create')" class="btn btn-success">
            <i class="ri-user-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('team.add') }}</span>
          </Link>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="text"
            class="form-control"
            :placeholder="$t('team.admin.search_placeholder')"
            @keyup.enter="applyFilters"
          />
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('team.admin.table.seller') }}</label>
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
            :title="row.name"
            :subtitle="row.email"
            :status-label="$t(`user_statuses.${row.status}`)"
            :status-color="statusColor(row.status)"
            :rows="cardRows(row)"
            @open="selected = row"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('team.admin.empty') }}
          </p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <SortableTh field="name" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('team.fields.first_name') }}
                </SortableTh>
                <SortableTh field="seller" :sort="sort" :direction="direction" @sort="sortBy">
                  {{ $t('team.admin.table.seller') }}
                </SortableTh>
                <th>{{ $t('team.fields.roles') }}</th>
                <th>{{ $t('team.fields.stores') }}</th>
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
                  <div class="text-muted fs-12">{{ row.email }}</div>
                </td>
                <td>
                  <div class="fw-semibold">{{ row.seller?.name || $t('common.empty_value') }}</div>
                  <div class="text-muted fs-12">{{ row.seller?.email }}</div>
                </td>
                <td>
                  <span
                    v-for="role in row.roles"
                    :key="role"
                    class="badge bg-primary-subtle text-primary me-1"
                  >
                    {{ role }}
                  </span>
                </td>
                <td>
                  <span
                    v-for="store in row.stores"
                    :key="store"
                    class="badge bg-light text-body me-1"
                  >
                    {{ store }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="row.status_class">
                    {{ $t(`user_statuses.${row.status}`) }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="hstack gap-1 justify-content-end">
                    <Link
                      :href="route('team.edit', row.id)"
                      class="btn btn-sm btn-light btn-icon"
                      :title="$t('common.edit')"
                      :aria-label="$t('common.edit')"
                    >
                      <i class="ri-pencil-line align-bottom"></i>
                    </Link>
                    <BButton
                      v-if="row.status === 'SUSPENDED'"
                      variant="soft-success"
                      size="sm"
                      class="text-nowrap"
                      @click="reactivate(row)"
                    >
                      {{ $t('team.actions.reactivate') }}
                    </BButton>
                    <BButton
                      v-else
                      variant="soft-danger"
                      size="sm"
                      class="text-nowrap"
                      @click="askSuspend(row)"
                    >
                      {{ $t('team.actions.suspend') }}
                    </BButton>
                  </div>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="6" class="text-center text-muted py-4">
                  {{ $t('team.admin.empty') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="members.last_page > 1" class="d-flex justify-content-end mt-3">
          <ul class="pagination pagination-sm mb-0">
            <li
              v-for="(link, index) in members.links"
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
      :subtitle="selected?.seller?.name ?? ''"
      :status-label="selected ? $t(`user_statuses.${selected.status}`) : ''"
      :status-color="selected ? statusColor(selected.status) : 'secondary'"
      :rows="selected ? sheetRows(selected) : []"
      @close="selected = null"
    >
      <template #actions>
        <Link :href="route('team.edit', selected?.id)" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-pencil-line align-bottom me-1"></i>
          {{ $t('common.edit') }}
        </Link>
      </template>
    </EntityDetailSheet>

    <BModal
      :model-value="confirming !== null"
      :title="$t('team.suspend_confirm_title', { name: confirming?.name })"
      hide-footer
      @update:model-value="confirming = null"
    >
      <p class="text-muted">{{ $t('team.suspend_confirm_text') }}</p>
      <div class="hstack gap-2 justify-content-end">
        <BButton variant="light" @click="confirming = null">{{ $t('common.cancel') }}</BButton>
        <BButton variant="danger" @click="suspend">{{ $t('team.actions.suspend') }}</BButton>
      </div>
    </BModal>
  </Layout>
</template>
