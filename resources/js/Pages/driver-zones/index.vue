<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

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
  { value: "", label: "All cities" },
  ...props.cities.map((c) => ({ value: c.id, label: c.name })),
]);

const sectorOptions = computed(() => [
  { value: "", label: "All sectors" },
  ...props.sectors.map((s) => ({ value: s.id, label: `${s.city_name} › ${s.name}` })),
]);

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

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
    byCity[s.city_name].push({ value: s.id, label: `${s.name} (${money(s.delivery_price)} MAD)` });
  });
  return Object.entries(byCity).map(([label, options]) => ({ label, options }));
});

const openAssign = (driver) => {
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
    title: "Remove sector?",
    text: `${sector.name} will be unassigned from ${driver.name}.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Remove",
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
    <PageHeader title="Driver Assignment" pageTitle="Delivery Zones" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <h5 class="card-title mb-0">Driver Zone Management</h5>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="4">
            <label class="form-label">Search Driver</label>
            <input v-model="filters.search" type="text" class="form-control" placeholder="Name, email or phone…" @keyup.enter="reload" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Filter by City</label>
            <Multiselect v-model="filters.city_id" :options="cityOptions" :searchable="true" :close-on-select="true" placeholder="City…" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Filter by Sector</label>
            <Multiselect v-model="filters.sector_id" :options="sectorOptions" :searchable="true" :close-on-select="true" placeholder="Sector…" />
          </BCol>
          <BCol md="2" class="d-flex align-items-end">
            <div class="hstack gap-2 w-100">
              <button class="btn btn-light w-100" @click="resetFilters"><i class="ri-refresh-line"></i></button>
              <button class="btn btn-primary w-100" @click="reload"><i class="ri-search-line"></i></button>
            </div>
          </BCol>
        </BRow>
      </BCardBody>

      <BCardBody>
        <div class="table-responsive table-card">
          <table class="table align-middle mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th style="width: 22%">Driver</th>
                <th class="text-center" style="width: 90px">Zones</th>
                <th>Assigned Sectors</th>
                <th class="text-end" style="width: 130px">Actions</th>
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
                      <BLink v-if="can.remove" class="text-danger lh-1" title="Remove" @click="removeSector(driver, sector)">
                        <i class="ri-close-line"></i>
                      </BLink>
                    </span>
                    <span v-if="(driver.sectors ?? []).length === 0" class="text-muted fs-13">No sectors assigned.</span>
                  </div>
                </td>
                <td class="text-end">
                  <button v-if="can.assign" class="btn btn-sm btn-soft-primary" @click="openAssign(driver)">
                    <i class="ri-map-pin-add-line align-bottom me-1"></i> Manage
                  </button>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="4" class="text-center text-muted py-4">No drivers found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <span class="text-muted">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }} drivers</span>
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

    <BModal v-model="showModal" :title="`Assign Sectors — ${activeDriver?.name ?? ''}`" hide-footer size="lg">
      <form @submit.prevent="submitAssign">
        <p class="text-muted">
          Select the delivery sectors this driver should serve. The current selection replaces the existing assignment.
        </p>
        <Multiselect
          v-model="assignForm.sector_ids"
          mode="tags"
          :options="groupedSectorOptions"
          :groups="true"
          :searchable="true"
          :close-on-select="false"
          placeholder="Search and select sectors…"
        />
        <div v-if="assignForm.errors.sector_ids" class="text-danger fs-13 mt-1">{{ assignForm.errors.sector_ids }}</div>

        <div class="hstack gap-2 justify-content-end mt-4">
          <button type="button" class="btn btn-light" @click="showModal = false">Cancel</button>
          <BButton type="submit" variant="primary" :disabled="assignForm.processing">
            <i class="ri-save-line align-bottom me-1"></i> Save Assignment
          </BButton>
        </div>
      </form>
    </BModal>
  </Layout>
</template>
