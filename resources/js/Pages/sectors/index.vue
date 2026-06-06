<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

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

const rows = computed(() => props.sectors.data ?? []);
const meta = computed(() => props.sectors.meta ?? {});

const cityOptions = computed(() => [
  { value: "", label: "All cities" },
  ...props.cities.map((c) => ({ value: c.id, label: c.name })),
]);

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

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
    title: "Delete this sector?",
    text: `${sector.name} will be removed.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) router.delete(route("sectors.destroy", sector.id), { preserveScroll: true });
  });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000 });
});
</script>

<template>
  <Layout>
    <PageHeader title="Sectors" pageTitle="Delivery Zones" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm><h5 class="card-title mb-0">Sector List</h5></BCol>
          <BCol sm="auto">
            <Link v-if="can.create" :href="route('sectors.create')" class="btn btn-success">
              <i class="ri-add-line align-bottom me-1"></i> New Sector
            </Link>
          </BCol>
        </BRow>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="4">
            <label class="form-label">Search</label>
            <input v-model="filters.search" type="text" class="form-control" placeholder="Sector name…" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="4">
            <label class="form-label">City</label>
            <Multiselect v-model="filters.city_id" :options="cityOptions" :searchable="true" :close-on-select="true" placeholder="Filter by city…" />
          </BCol>
          <BCol md="2">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </BCol>
          <BCol md="2" class="d-flex align-items-end">
            <div class="hstack gap-2 w-100">
              <button class="btn btn-light w-100" @click="resetFilters"><i class="ri-refresh-line"></i></button>
              <button class="btn btn-primary w-100" @click="applyFilters"><i class="ri-search-line"></i></button>
            </div>
          </BCol>
        </BRow>
      </BCardBody>

      <BCardBody>
        <div class="table-responsive table-card">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>Sector</th>
                <th>City</th>
                <th class="text-end">Delivery Price</th>
                <th class="text-center">Orders</th>
                <th class="text-center">Drivers</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sector in rows" :key="sector.id">
                <td><Link :href="route('sectors.show', sector.id)" class="fw-semibold">{{ sector.name }}</Link></td>
                <td>{{ sector.city?.name ?? "—" }}</td>
                <td class="text-end fw-medium">{{ money(sector.delivery_price) }} MAD</td>
                <td class="text-center">{{ sector.orders_count ?? 0 }}</td>
                <td class="text-center">{{ sector.drivers_count ?? 0 }}</td>
                <td>
                  <span class="badge" :class="sector.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ sector.is_active ? "Active" : "Inactive" }}
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
                <td colspan="7" class="text-center text-muted py-4">No sectors found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">Rows per page</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in [15, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}</span>
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
  </Layout>
</template>
