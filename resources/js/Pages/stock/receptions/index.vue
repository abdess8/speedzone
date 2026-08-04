<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import StatusPills from '@/Components/StatusPills.vue';
import EntityCard from '@/Components/EntityCard.vue';
import EntityDetailSheet from '@/Components/EntityDetailSheet.vue';

/**
 * Inbound shipment slips, from all three sides of the counter.
 *
 * Both staff readers open this screen on a worklist, so the controller floats what
 * is waiting on them to the top: parcels to fetch for a collector, parcels to count
 * for the depot. A vendor sees the same list in plain reverse-chronological order,
 * because for him it is a history rather than a queue.
 */

const { t } = useI18n();

const props = defineProps({
  receptions: { type: Object, default: () => ({ data: [], meta: {} }) },
  filters: { type: Object, default: () => ({}) },
  statuses: { type: Array, default: () => [] },
  hubCities: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  reference: props.filters.reference ?? '',
  status: props.filters.status ?? '',
  destination_city_id: props.filters.destination_city_id ?? '',
});

/** A collector reads an empty list as "nothing to drive to", not as "nothing sent". */
const isCollectorView = computed(() => props.can.collect && !props.can.receive && !props.can.create);

const selected = ref(null);

const rows = computed(() => props.receptions.data ?? []);
const meta = computed(() => props.receptions.meta ?? {});

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== '' && value !== null).length
);

const reload = () => {
  const params = {};

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null) {
      params[key] = value;
    }
  });

  router.get(route('stock-receptions.index'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  Object.keys(filters).forEach((key) => {
    filters[key] = '';
  });
  reload();
};

const selectStatus = (value) => {
  filters.status = value;
  reload();
};

const goToPage = (url) => {
  if (url) {
    router.visit(url, { preserveState: true, preserveScroll: true });
  }
};

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : t('common.empty_value'));

// Collection city first on the phone: it is the one fact that decides whether the
// card in front of a driver is his problem.
const cardRows = (row) => [
  { label: t('stock.receptions.columns.pickup_city'), value: row.pickup_city },
  { label: t('stock.receptions.columns.items'), value: String(row.items_count) },
  { label: t('stock.receptions.columns.sent'), value: String(row.quantity_sent), emphasis: true },
  {
    label: t('stock.receptions.columns.collected'),
    value: row.quantity_collected === null ? null : String(row.quantity_collected),
  },
  {
    label: t('stock.receptions.columns.received'),
    value: row.quantity_received === null ? null : String(row.quantity_received),
  },
];

const sheetRows = (row) => [
  ...cardRows(row),
  { label: t('stock.receptions.columns.destination'), value: row.destination_city },
  { label: t('stock.receptions.columns.sent_at'), value: formatDate(row.sent_at) },
  { label: t('stock.receptions.columns.received_at'), value: formatDate(row.received_at) },
  { label: t('stock.receptions.people.sent_by'), value: row.sender },
  { label: t('stock.receptions.people.collected_by'), value: row.collector ?? t('stock.receptions.people.pending') },
  { label: t('stock.receptions.people.received_by'), value: row.receiver ?? t('stock.receptions.people.pending') },
];

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
    <PageHeader :title="$t('stock.receptions.title')" :pageTitle="$t('stock.page_title')" />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="reload" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('stock.receptions.list_title') }}</h5>
        </template>

        <template #actions>
          <Link
            v-if="can.create"
            data-guide="reception-create"
            :href="route('stock-receptions.create')"
            class="btn btn-success"
          >
            <i class="ri-truck-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('stock.receptions.create_button') }}</span>
          </Link>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('stock.receptions.columns.reference') }}</label>
          <input
            v-model="filters.reference"
            type="search"
            class="form-control"
            :placeholder="$t('stock.receptions.filters.reference')"
            @keyup.enter="reload"
          />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('stock.receptions.filters.status') }}</option>
            <option v-for="status in statuses" :key="status.value" :value="status.value">
              {{ status.label }}
            </option>
          </select>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('stock.receptions.columns.destination') }}</label>
          <select v-model="filters.destination_city_id" class="form-select">
            <option value="">{{ $t('stock.receptions.filters.destination') }}</option>
            <option v-for="city in hubCities" :key="city.id" :value="city.id">
              {{ city.name }}
            </option>
          </select>
        </BCol>
      </FilterPanel>

      <StatusPills
        class="d-lg-none"
        :model-value="filters.status"
        :options="statuses"
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
            :subtitle="row.seller ?? ''"
            :status-label="row.status_label"
            :status-color="row.status_color"
            :rows="cardRows(row)"
            @open="selected = row"
          />

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ isCollectorView ? $t('stock.receptions.empty_collector') : $t('stock.receptions.empty') }}
          </p>
        </div>

        <div class="table-responsive d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ $t('stock.receptions.columns.reference') }}</th>
                <th>{{ $t('stock.receptions.columns.status') }}</th>
                <th>{{ $t('stock.receptions.columns.seller') }}</th>
                <th>{{ $t('stock.receptions.columns.pickup_city') }}</th>
                <th>{{ $t('stock.receptions.columns.destination') }}</th>
                <th class="text-center">{{ $t('stock.receptions.columns.items') }}</th>
                <th class="text-end">{{ $t('stock.receptions.columns.sent') }}</th>
                <th class="text-end">{{ $t('stock.receptions.columns.collected') }}</th>
                <th class="text-end">{{ $t('stock.receptions.columns.received') }}</th>
                <th>{{ $t('stock.receptions.columns.sent_at') }}</th>
                <th>{{ $t('stock.receptions.columns.received_at') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="12" class="text-center text-muted py-5">
                  <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-truck-line"></i></div>
                  <p class="mb-1">
                    {{ isCollectorView ? $t('stock.receptions.empty_collector') : $t('stock.receptions.empty') }}
                  </p>
                  <p class="fs-13 mb-0">
                    {{
                      isCollectorView
                        ? $t('stock.receptions.empty_collector_hint')
                        : $t('stock.receptions.empty_hint')
                    }}
                  </p>
                </td>
              </tr>

              <tr v-for="row in rows" :key="row.id">
                <td>
                  <Link :href="route('stock-receptions.show', row.id)" class="fw-medium text-body">
                    {{ row.reference }}
                  </Link>
                </td>
                <td>
                  <span class="badge" :class="`bg-${row.status_color}-subtle text-${row.status_color}`">
                    <i :class="`${row.status_icon} align-bottom me-1`"></i>{{ row.status_label }}
                  </span>
                </td>
                <td>{{ row.seller ?? $t('common.empty_value') }}</td>
                <td>
                  <span v-if="row.pickup_city" class="text-nowrap">
                    <i class="ri-store-2-line align-bottom me-1 text-muted"></i>{{ row.pickup_city }}
                  </span>
                  <span v-else class="text-muted">{{ $t('common.empty_value') }}</span>
                </td>
                <td>
                  <span v-if="row.destination_city" class="badge bg-info-subtle text-info">
                    <i class="ri-map-pin-line align-bottom me-1"></i>{{ row.destination_city }}
                  </span>
                  <span v-else class="text-muted">{{ $t('common.empty_value') }}</span>
                </td>
                <td class="text-center">{{ row.items_count }}</td>
                <td class="text-end fw-medium">{{ row.quantity_sent }}</td>
                <td class="text-end">
                  <span v-if="row.quantity_collected === null" class="text-muted">
                    {{ $t('stock.receptions.people.pending') }}
                  </span>
                  <span
                    v-else
                    class="fw-medium"
                    :class="row.quantity_collected < row.quantity_sent ? 'text-warning' : 'text-info'"
                  >
                    {{ row.quantity_collected }}
                  </span>
                </td>
                <td class="text-end">
                  <span v-if="row.quantity_received === null" class="text-muted">
                    {{ $t('stock.receptions.people.pending') }}
                  </span>
                  <span
                    v-else
                    class="fw-medium"
                    :class="
                      row.quantity_received < (row.quantity_collected ?? row.quantity_sent)
                        ? 'text-warning'
                        : 'text-success'
                    "
                  >
                    {{ row.quantity_received }}
                  </span>
                </td>
                <td class="text-muted">{{ formatDate(row.sent_at) }}</td>
                <td class="text-muted">{{ formatDate(row.received_at) }}</td>
                <td class="text-end">
                  <Link :href="route('stock-receptions.show', row.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line"></i>
                  </Link>
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
      :title="selected?.reference ?? ''"
      :subtitle="selected?.seller ?? ''"
      :status-label="selected?.status_label ?? ''"
      :status-color="selected?.status_color ?? 'secondary'"
      :rows="selected ? sheetRows(selected) : []"
      @close="selected = null"
    >
      <template #actions>
        <Link :href="route('stock-receptions.show', selected?.id)" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
