<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  sectors: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  cities: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  city_id: props.filters.city_id ?? "",
  status: props.filters.status ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
/** Row whose mobile detail sheet is open. */
const selectedSector = ref(null);

const rows = computed(() => props.sectors.data ?? []);
const meta = computed(() => props.sectors.meta ?? {});

const cityOptions = computed(() => [
  { value: "", label: t("sectors.filters.all_cities") },
  ...props.cities.map((c) => ({ value: c.id, label: c.name })),
]);

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

const statusLabel = (sector) => (sector.is_active ? t("common.active") : t("common.inactive"));
const statusColor = (sector) => (sector.is_active ? "success" : "danger");

const cardRows = (sector) => [
  { label: t("sectors.table.delivery_price"), value: money(sector.delivery_price), emphasis: true },
  { label: t("sectors.table.return_price"), value: money(sector.return_price) },
  { label: t("sectors.table.delivery_delay"), value: sector.delivery_delay || t("common.empty_value") },
  { label: t("sectors.table.orders"), value: sector.orders_count ?? 0 },
];

const sheetRows = (sector) => [
  { label: t("sectors.filters.city"), value: sector.city?.name },
  ...cardRows(sector),
  { label: t("sectors.table.drivers"), value: sector.drivers_count ?? 0 },
];

const query = () => {
  const params = { per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("sectors.index"), query(), { preserveState: true, preserveScroll: true, replace: true });
};

const applyFilters = () => reload();
const resetFilters = () => {
  filters.search = "";
  filters.city_id = "";
  filters.status = "";
  reload();
};

watch(perPage, reload);
watch(() => filters.city_id, reload);
watch(() => filters.status, reload);

const goToPage = (url) => {
  if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const confirmDelete = (sector) => {
  Swal.fire({
    title: t("sectors.delete_confirm_title"),
    text: t("sectors.delete_confirm_text", { name: sector.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_delete"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (result.isConfirmed) {
      // The sheet may be the caller; it would otherwise linger on a row that no
      // longer exists.
      selectedSector.value = null;
      router.delete(route("sectors.destroy", sector.id), { preserveScroll: true });
    }
  });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000 });
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('sectors.title')" :pageTitle="$t('common.delivery_zones')" />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('sectors.list_title') }}</h5>
        </template>

        <template #actions>
          <Link v-if="can.create" :href="route('sectors.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('sectors.new_sector') }}</span>
          </Link>
        </template>

        <BCol md="4">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input v-model="filters.search" type="text" class="form-control" :placeholder="$t('sectors.filters.search_placeholder')" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('sectors.filters.city') }}</label>
          <Multiselect v-model="filters.city_id" :options="cityOptions" :searchable="true" :close-on-select="true" :placeholder="$t('sectors.filters.city_placeholder')" />
        </BCol>
        <BCol md="4">
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
            v-for="sector in rows"
            :key="sector.id"
            :title="sector.name"
            :subtitle="sector.city?.name ?? ''"
            :status-label="statusLabel(sector)"
            :status-color="statusColor(sector)"
            :rows="cardRows(sector)"
            @open="selectedSector = sector"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('sectors.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>{{ $t('sectors.table.sector') }}</th>
                <th>{{ $t('sectors.filters.city') }}</th>
                <th class="text-end">{{ $t('sectors.table.delivery_price') }}</th>
                <th class="text-end">{{ $t('sectors.table.return_price') }}</th>
                <th class="text-center">{{ $t('sectors.table.delivery_delay') }}</th>
                <th class="text-center">{{ $t('sectors.table.orders') }}</th>
                <th class="text-center">{{ $t('sectors.table.drivers') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sector in rows" :key="sector.id">
                <td><Link :href="route('sectors.show', sector.id)" class="fw-semibold">{{ sector.name }}</Link></td>
                <td>{{ sector.city?.name ?? $t('common.empty_value') }}</td>
                <td class="text-end fw-medium">{{ money(sector.delivery_price) }}</td>
                <td class="text-end fw-medium">{{ money(sector.return_price) }}</td>
                <td class="text-center">{{ sector.delivery_delay || $t('common.empty_value') }}</td>
                <td class="text-center">{{ sector.orders_count ?? 0 }}</td>
                <td class="text-center">{{ sector.drivers_count ?? 0 }}</td>
                <td>
                  <span class="badge" :class="sector.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ sector.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li class="list-inline-item"><Link :href="route('sectors.show', sector.id)" class="text-primary"><i class="ri-eye-fill fs-16"></i></Link></li>
                    <li v-if="can.update" class="list-inline-item"><Link :href="route('sectors.edit', sector.id)" class="text-warning"><i class="ri-pencil-fill fs-16"></i></Link></li>
                    <li v-if="can.delete" class="list-inline-item"><BLink class="text-danger" @click="confirmDelete(sector)"><i class="ri-delete-bin-5-fill fs-16"></i></BLink></li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="8" class="text-center text-muted py-4">{{ $t('sectors.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">{{ $t('common.rows_per_page') }}</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in [15, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">{{ $t('common.pagination_range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total ?? 0 }) }}</span>
            </div>
          </BCol>
          <BCol sm class="d-flex justify-content-sm-end">
            <ul class="pagination pagination-sm mb-0" v-if="meta.links">
              <li v-for="(link, i) in meta.links" :key="i" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                <button class="page-link" @click="goToPage(link.url)" v-html="link.label"></button>
              </li>
            </ul>
          </BCol>
        </BRow>
      </BCardBody>
    </BCard>

    <EntityDetailSheet
      :show="selectedSector !== null"
      :title="selectedSector?.name ?? ''"
      :subtitle="selectedSector?.city?.name ?? ''"
      :status-label="selectedSector ? statusLabel(selectedSector) : ''"
      :status-color="selectedSector ? statusColor(selectedSector) : 'secondary'"
      :rows="selectedSector ? sheetRows(selectedSector) : []"
      @close="selectedSector = null"
    >
      <template #actions>
        <Link :href="route('sectors.show', selectedSector?.id)" class="btn btn-primary flex-fill sheet-action">
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
        <Link
          v-if="can.update"
          :href="route('sectors.edit', selectedSector?.id)"
          class="btn btn-soft-warning sheet-action"
          :aria-label="$t('common.edit')"
        >
          <i class="ri-pencil-fill"></i>
        </Link>
        <button
          v-if="can.delete"
          type="button"
          class="btn btn-soft-danger sheet-action"
          :aria-label="$t('common.delete')"
          @click="confirmDelete(selectedSector)"
        >
          <i class="ri-delete-bin-5-fill"></i>
        </button>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
