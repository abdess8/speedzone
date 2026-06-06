<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const props = defineProps({
  cities: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);

const rows = computed(() => props.cities.data ?? []);
const meta = computed(() => props.cities.meta ?? {});

const query = () => {
  const params = { per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("cities.index"), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const applyFilters = () => reload();

const resetFilters = () => {
  filters.search = "";
  filters.status = "";
  reload();
};

watch(perPage, reload);

const goToPage = (url) => {
  if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const confirmDelete = (city) => {
  Swal.fire({
    title: "Delete this city?",
    text: `${city.name} will be removed. Cities with active sectors cannot be deleted.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route("cities.destroy", city.id), { preserveScroll: true });
    }
  });
};

const flashToast = () => {
  const flash = usePage().props?.flash ?? {};
  if (flash.success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: flash.success, showConfirmButton: false, timer: 3000 });
  }
  if (flash.error) {
    Swal.fire({ toast: true, position: "top-end", icon: "error", title: flash.error, showConfirmButton: false, timer: 4000 });
  }
};

onMounted(flashToast);
watch(() => usePage().props?.flash, flashToast, { deep: true });
</script>

<template>
  <Layout>
    <PageHeader title="Cities" pageTitle="Delivery Zones" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm>
            <h5 class="card-title mb-0">City List</h5>
          </BCol>
          <BCol sm="auto">
            <Link v-if="can.create" :href="route('cities.create')" class="btn btn-success">
              <i class="ri-add-line align-bottom me-1"></i> New City
            </Link>
          </BCol>
        </BRow>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="5">
            <label class="form-label">Search</label>
            <div class="search-box">
              <input
                v-model="filters.search"
                type="text"
                class="form-control"
                placeholder="Name, code or region…"
                @keyup.enter="applyFilters"
              />
            </div>
          </BCol>
          <BCol md="3">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </BCol>
          <BCol md="4" class="d-flex align-items-end">
            <div class="hstack gap-2 justify-content-end w-100">
              <button class="btn btn-light" @click="resetFilters">
                <i class="ri-refresh-line align-bottom me-1"></i> Reset
              </button>
              <button class="btn btn-primary" @click="applyFilters">
                <i class="ri-search-line align-bottom me-1"></i> Apply
              </button>
            </div>
          </BCol>
        </BRow>
      </BCardBody>

      <BCardBody>
        <div class="table-responsive table-card">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>Name</th>
                <th>Code</th>
                <th>Region</th>
                <th class="text-center">Sectors</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="city in rows" :key="city.id">
                <td>
                  <Link :href="route('cities.show', city.id)" class="fw-semibold">{{ city.name }}</Link>
                </td>
                <td>
                  <span v-if="city.code" class="badge bg-light text-body border">{{ city.code }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td>{{ city.region || "—" }}</td>
                <td class="text-center">
                  <span class="badge bg-primary-subtle text-primary">
                    {{ city.active_sectors_count ?? 0 }} / {{ city.sectors_count ?? 0 }}
                  </span>
                </td>
                <td>
                  <span class="badge" :class="city.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ city.is_active ? "Active" : "Inactive" }}
                  </span>
                </td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li class="list-inline-item" title="View">
                      <Link :href="route('cities.show', city.id)" class="text-primary"><i class="ri-eye-fill fs-16"></i></Link>
                    </li>
                    <li v-if="can.update" class="list-inline-item" title="Edit">
                      <Link :href="route('cities.edit', city.id)" class="text-warning"><i class="ri-pencil-fill fs-16"></i></Link>
                    </li>
                    <li v-if="can.delete" class="list-inline-item" title="Delete">
                      <BLink class="text-danger" @click="confirmDelete(city)"><i class="ri-delete-bin-5-fill fs-16"></i></BLink>
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="6" class="text-center text-muted py-4">No cities found.</td>
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
