<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import EntityCard from '@/Components/EntityCard.vue';

const props = defineProps({
  alerts: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  types: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const { t, locale } = useI18n();

const rows = computed(() => props.alerts.data ?? []);
const meta = computed(() => props.alerts.meta ?? {});

const filters = reactive({
  search: props.filters.search ?? '',
  type: props.filters.type ?? '',
  display_format: props.filters.display_format ?? '',
  status: props.filters.status ?? '',
});

const perPage = ref(props.filters.per_page ?? 15);

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== '').length,
);

const query = () => {
  const params = { per_page: perPage.value };

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '') {
      params[key] = value;
    }
  });

  return params;
};

const reload = () => {
  router.get(route('alerts.index'), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

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
    router.get(url, {}, { preserveState: true, preserveScroll: true, replace: true });
  }
};

const STATUS_COLORS = { active: 'success', expired: 'secondary', disabled: 'warning' };

const statusColor = (alert) => STATUS_COLORS[alert.status] ?? 'secondary';
const statusLabel = (alert) => t(`alerts.statuses.${alert.status}`);
const typeColor = (alert) => alert.type;

const formatDate = (iso) =>
  iso
    ? new Date(iso).toLocaleString(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
      })
    : '';

const cityNames = computed(
  () => new Map(props.cities.map((city) => [String(city.value), city.label])),
);

/**
 * Reads the stored targeting back as a sentence. The table is where an
 * administrator checks they have not just broadcast to the whole company, so
 * "Sellers, Tangier" has to be legible at a glance.
 */
const audience = (alert) => {
  const named = alert.target_user_ids.length;
  const allRoles = alert.target_roles.includes('all');
  const allCities = alert.target_cities.includes('all');

  // Either dimension left empty silences the broadcast, leaving only the people
  // named one by one.
  if (alert.target_roles.length === 0 || alert.target_cities.length === 0) {
    return named ? t('alerts.audience.only_users', { count: named }) : t('alerts.audience.nobody');
  }

  const broadcast =
    allRoles && allCities
      ? t('alerts.audience.everyone')
      : t('alerts.audience.roles_in_cities', {
          roles: allRoles
            ? t('alerts.audience.all_roles')
            : alert.target_roles.map((role) => t(`roles.${role}`)).join(', '),
          cities: allCities
            ? t('alerts.audience.all_cities')
            : alert.target_cities
                .map((id) => cityNames.value.get(String(id)))
                .filter(Boolean)
                .join(', '),
        });

  return named ? `${broadcast} ${t('alerts.audience.plus_users', { count: named })}` : broadcast;
};

const toggle = (alert) => {
  router.patch(route('alerts.toggle', alert.id), {}, { preserveScroll: true });
};

const confirmDelete = (alert) => {
  Swal.fire({
    title: t('alerts.delete_confirm_title'),
    text: t('alerts.delete_confirm_text', { title: alert.title }),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#f06548',
    confirmButtonText: t('common.confirm_delete'),
    cancelButtonText: t('common.cancel'),
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('alerts.destroy', alert.id), { preserveScroll: true });
    }
  });
};

const flashToast = () => {
  const flash = usePage().props?.flash ?? {};

  if (flash.success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: flash.success,
      showConfirmButton: false,
      timer: 3000,
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
    });
  }
};

onMounted(flashToast);
watch(() => usePage().props?.flash, flashToast, { deep: true });

const cardRows = (alert) => [
  { label: t('alerts.table.audience'), value: audience(alert) },
  { label: t('alerts.table.format'), value: alert.format_label },
  { label: t('alerts.table.end_date'), value: formatDate(alert.end_date) },
];
</script>

<template>
  <Layout>
    <PageHeader :title="$t('alerts.title')" :pageTitle="$t('common.settings')" />

    <BCard no-body>
      <FilterPanel
        :active-count="activeFilterCount"
        @apply="applyFilters"
        @reset="resetFilters"
      >
        <template #title>
          <h5 class="card-title mb-1">{{ $t('alerts.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('alerts.subtitle') }}</p>
        </template>

        <template #actions>
          <Link v-if="can.create" :href="route('alerts.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('alerts.create_title') }}</span>
          </Link>
        </template>

        <BCol md="4">
          <label class="form-label">{{ $t('common.search') }}</label>
          <div class="search-box">
            <input
              v-model="filters.search"
              type="text"
              class="form-control"
              :placeholder="$t('alerts.filters.search')"
              @keyup.enter="applyFilters"
            />
          </div>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('alerts.filters.type') }}</label>
          <select v-model="filters.type" class="form-select">
            <option value="">{{ $t('alerts.filters.all') }}</option>
            <option v-for="type in types" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('alerts.filters.format') }}</label>
          <select v-model="filters.display_format" class="form-select">
            <option value="">{{ $t('alerts.filters.all') }}</option>
            <option v-for="format in formats" :key="format.value" :value="format.value">
              {{ format.label }}
            </option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('alerts.filters.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('alerts.filters.all') }}</option>
            <option value="active">{{ $t('alerts.statuses.active') }}</option>
            <option value="expired">{{ $t('alerts.statuses.expired') }}</option>
            <option value="disabled">{{ $t('alerts.statuses.disabled') }}</option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="alert in rows"
            :key="alert.id"
            :title="alert.title"
            :subtitle="alert.excerpt"
            :status-label="statusLabel(alert)"
            :status-color="statusColor(alert)"
            :rows="cardRows(alert)"
          >
            <template #actions>
              <Link
                v-if="can.update"
                :href="route('alerts.edit', alert.id)"
                class="btn btn-sm btn-soft-warning"
              >
                <i class="ri-pencil-fill"></i>
              </Link>
              <button
                v-if="can.delete"
                type="button"
                class="btn btn-sm btn-soft-danger"
                @click="confirmDelete(alert)"
              >
                <i class="ri-delete-bin-5-fill"></i>
              </button>
            </template>
          </EntityCard>
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('alerts.table.empty') }}
          </p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>{{ $t('alerts.table.announcement') }}</th>
                <th>{{ $t('alerts.table.type') }}</th>
                <th>{{ $t('alerts.table.format') }}</th>
                <th>{{ $t('alerts.table.audience') }}</th>
                <th>{{ $t('alerts.table.status') }}</th>
                <th>{{ $t('alerts.table.end_date') }}</th>
                <th class="text-end">{{ $t('alerts.table.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="alert in rows" :key="alert.id">
                <td class="text-wrap" style="max-width: 22rem">
                  <span class="fw-semibold d-block">{{ alert.title }}</span>
                  <span class="text-muted fs-13">{{ alert.excerpt }}</span>
                </td>
                <td>
                  <span class="badge" :class="`bg-${typeColor(alert)}-subtle text-${typeColor(alert)}`">
                    <i :class="alert.type_icon" class="align-bottom me-1"></i>
                    {{ alert.type_label }}
                  </span>
                </td>
                <td>
                  <span class="text-body">{{ alert.format_label }}</span>
                  <i
                    v-if="alert.display_format === 'banner' && !alert.is_dismissible"
                    class="ri-pushpin-fill text-warning ms-1"
                    :title="$t('alerts.form.dismissible_hint')"
                  ></i>
                </td>
                <td class="text-wrap" style="max-width: 16rem">{{ audience(alert) }}</td>
                <td>
                  <span class="badge" :class="`bg-${statusColor(alert)}-subtle text-${statusColor(alert)}`">
                    {{ statusLabel(alert) }}
                  </span>
                </td>
                <td>{{ formatDate(alert.end_date) }}</td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li v-if="can.update && alert.status !== 'expired'" class="list-inline-item">
                      <div class="form-check form-switch mb-0">
                        <input
                          class="form-check-input"
                          type="checkbox"
                          role="switch"
                          :checked="alert.is_active"
                          :title="alert.is_active ? $t('alerts.actions.disable') : $t('alerts.actions.enable')"
                          @change="toggle(alert)"
                        />
                      </div>
                    </li>
                    <li v-if="can.update" class="list-inline-item" :title="$t('alerts.actions.edit')">
                      <Link :href="route('alerts.edit', alert.id)" class="text-warning">
                        <i class="ri-pencil-fill fs-16"></i>
                      </Link>
                    </li>
                    <li v-if="can.delete" class="list-inline-item" :title="$t('alerts.actions.delete')">
                      <BLink class="text-danger" @click="confirmDelete(alert)">
                        <i class="ri-delete-bin-5-fill fs-16"></i>
                      </BLink>
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="7" class="text-center text-muted py-5">
                  <i class="ri-megaphone-line d-block fs-1 mb-2 opacity-50"></i>
                  {{ $t('alerts.table.empty') }}
                  <div class="fs-13">{{ $t('alerts.table.empty_hint') }}</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">{{ $t('common.rows_per_page') }}</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in [15, 25, 50, 100]" :key="size" :value="size">
                  {{ size }}
                </option>
              </select>
              <span class="text-muted">
                {{
                  $t('common.pagination_range', {
                    from: meta.from ?? 0,
                    to: meta.to ?? 0,
                    total: meta.total ?? 0,
                  })
                }}
              </span>
            </div>
          </BCol>
          <BCol sm class="d-flex justify-content-sm-end">
            <ul v-if="meta.links" class="pagination pagination-sm mb-0">
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
  </Layout>
</template>
