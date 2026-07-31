<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import BottomSheet from "@/Components/BottomSheet.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  drivers: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  cities: { type: Array, default: () => [] },
  sectors: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  city_id: props.filters.city_id ?? "",
  sector_id: props.filters.sector_id ?? "",
});

const rows = computed(() => props.drivers.data ?? []);
const meta = computed(() => props.drivers.meta ?? {});

const cityOptions = computed(() => [
  { value: "", label: t("driver_zones.filters.all_cities") },
  ...props.cities.map((c) => ({ value: c.id, label: c.name })),
]);

const sectorOptions = computed(() => [
  { value: "", label: t("driver_zones.filters.all_sectors") },
  ...props.sectors.map((s) => ({ value: s.id, label: `${s.city_name} › ${s.name}` })),
]);

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const query = () => {
  const params = {};
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("driver-zones.index"), query(), { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
  filters.search = "";
  filters.city_id = "";
  filters.sector_id = "";
  reload();
};

watch(() => filters.city_id, reload);
watch(() => filters.sector_id, reload);

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

/* --- Mobile cards --- */
const selectedDriver = ref(null);

const sectorNames = (driver) =>
  (driver.sectors ?? []).map((sector) => `${sector.city?.name} › ${sector.name}`).join(", ");

const cardRows = (driver) => [
  { label: t("driver_zones.table.zones"), value: driver.sectors_count ?? 0, emphasis: true },
  { label: t("users.table.phone"), value: driver.phone || null },
];

const sheetRows = (driver) => [
  ...cardRows(driver),
  { label: t("users.table.email"), value: driver.email },
  {
    label: t("driver_zones.table.assigned_sectors"),
    value: sectorNames(driver) || t("driver_zones.no_sectors_assigned"),
  },
];

const goToPage = (url) => {
  if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

/* --- Assignment modal --- */
const showModal = ref(false);
const activeDriver = ref(null);

const assignForm = useForm({ sector_ids: [], replace: false });

// Sector options for the modal, grouped by city for easy scanning.
const groupedSectorOptions = computed(() => {
  const byCity = {};
  props.sectors.forEach((s) => {
    byCity[s.city_name] = byCity[s.city_name] || [];
    byCity[s.city_name].push({
      value: s.id,
      label: t("driver_zones.sector_option_label", { name: s.name, price: money(s.delivery_price) }),
    });
  });
  return Object.entries(byCity).map(([label, options]) => ({ label, options }));
});

const openAssign = (driver) => {
  // The editor can be reached from the detail sheet, which must give way rather
  // than stack a second sheet behind it.
  selectedDriver.value = null;
  activeDriver.value = driver;
  assignForm.reset();
  assignForm.clearErrors();
  // Pre-select the driver's current sectors so the editor shows the full set.
  assignForm.sector_ids = (driver.sectors ?? []).map((s) => s.id);
  assignForm.replace = true;
  showModal.value = true;
};

const submitAssign = () => {
  assignForm.post(route("driver-zones.assign", activeDriver.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      showModal.value = false;
    },
  });
};

const removeSector = (driver, sector) => {
  Swal.fire({
    title: t("driver_zones.remove_confirm_title"),
    text: t("driver_zones.remove_confirm_text", { sector: sector.name, driver: driver.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("driver_zones.actions.remove"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("driver-zones.remove", [driver.id, sector.id]), { preserveScroll: true });
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
    <PageHeader :title="$t('driver_zones.title')" :pageTitle="$t('common.delivery_zones')" />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="reload" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('driver_zones.list_title') }}</h5>
        </template>

        <BCol lg="4">
          <label class="form-label">{{ $t('driver_zones.filters.search_driver') }}</label>
          <input v-model="filters.search" type="text" class="form-control" :placeholder="$t('driver_zones.filters.search_placeholder')" @keyup.enter="reload" />
        </BCol>
        <BCol lg="4">
          <label class="form-label">{{ $t('driver_zones.filters.filter_by_city') }}</label>
          <Multiselect v-model="filters.city_id" :options="cityOptions" :searchable="true" :close-on-select="true" :placeholder="$t('driver_zones.filters.city_placeholder')" />
        </BCol>
        <BCol lg="4">
          <label class="form-label">{{ $t('driver_zones.filters.filter_by_sector') }}</label>
          <Multiselect v-model="filters.sector_id" :options="sectorOptions" :searchable="true" :close-on-select="true" :placeholder="$t('driver_zones.filters.sector_placeholder')" />
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="driver in rows"
            :key="driver.id"
            :title="driver.name"
            :subtitle="driver.email"
            :rows="cardRows(driver)"
            @open="selectedDriver = driver"
          >
            <template v-if="can.assign" #actions>
              <button class="btn btn-sm btn-soft-primary flex-fill" @click="openAssign(driver)">
                <i class="ri-map-pin-add-line align-bottom me-1"></i> {{ $t('driver_zones.actions.manage') }}
              </button>
            </template>
          </EntityCard>

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('driver_zones.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th style="width: 22%">{{ $t('driver_zones.table.driver') }}</th>
                <th class="text-center" style="width: 90px">{{ $t('driver_zones.table.zones') }}</th>
                <th>{{ $t('driver_zones.table.assigned_sectors') }}</th>
                <th class="text-end" style="width: 130px">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="driver in rows" :key="driver.id">
                <td>
                  <div class="fw-semibold">{{ driver.name }}</div>
                  <div class="text-muted fs-12">{{ driver.email }}</div>
                  <div v-if="driver.phone" class="text-muted fs-12">{{ driver.phone }}</div>
                </td>
                <td class="text-center">
                  <span class="badge bg-primary-subtle text-primary">{{ driver.sectors_count ?? 0 }}</span>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <span v-for="sector in driver.sectors ?? []" :key="sector.id" class="badge bg-light text-body border d-inline-flex align-items-center gap-1">
                      {{ sector.city?.name }} › {{ sector.name }}
                      <BLink v-if="can.remove" class="text-danger lh-1" :title="$t('driver_zones.actions.remove')" @click="removeSector(driver, sector)">
                        <i class="ri-close-line"></i>
                      </BLink>
                    </span>
                    <span v-if="(driver.sectors ?? []).length === 0" class="text-muted fs-13">{{ $t('driver_zones.no_sectors_assigned') }}</span>
                  </div>
                </td>
                <td class="text-end">
                  <button v-if="can.assign" class="btn btn-sm btn-soft-primary" @click="openAssign(driver)">
                    <i class="ri-map-pin-add-line align-bottom me-1"></i> {{ $t('driver_zones.actions.manage') }}
                  </button>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="4" class="text-center text-muted py-4">{{ $t('driver_zones.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <span class="text-muted">{{ $t('driver_zones.pagination_range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total ?? 0 }) }}</span>
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
      :show="selectedDriver !== null"
      :title="selectedDriver?.name ?? ''"
      :subtitle="selectedDriver?.email ?? ''"
      :rows="selectedDriver ? sheetRows(selectedDriver) : []"
      @close="selectedDriver = null"
    >
      <template v-if="can.assign" #actions>
        <button class="btn btn-primary flex-fill sheet-action" @click="openAssign(selectedDriver)">
          <i class="ri-map-pin-add-line align-bottom me-1"></i> {{ $t('driver_zones.actions.manage') }}
        </button>
      </template>
    </EntityDetailSheet>

    <BottomSheet
      :show="showModal"
      :title="$t('driver_zones.modal.title', { name: activeDriver?.name ?? '' })"
      size="lg"
      @close="showModal = false"
    >
      <form @submit.prevent="submitAssign">
        <p class="text-muted">
          {{ $t('driver_zones.modal.description') }}
        </p>
        <Multiselect
          v-model="assignForm.sector_ids"
          mode="tags"
          :options="groupedSectorOptions"
          :groups="true"
          :searchable="true"
          :close-on-select="false"
          :placeholder="$t('driver_zones.modal.sectors_placeholder')"
        />
        <div v-if="assignForm.errors.sector_ids" class="text-danger fs-13 mt-1">{{ assignForm.errors.sector_ids }}</div>

        <div class="hstack gap-2 justify-content-end mt-4">
          <button type="button" class="btn btn-light" @click="showModal = false">{{ $t('common.cancel') }}</button>
          <BButton type="submit" variant="primary" :disabled="assignForm.processing">
            <i class="ri-save-line align-bottom me-1"></i> {{ $t('driver_zones.modal.save') }}
          </BButton>
        </div>
      </form>
    </BottomSheet>
  </Layout>
</template>
