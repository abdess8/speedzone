<script setup>
import { computed, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const props = defineProps({
  city: { type: Object, required: true },
  can: { type: Object, default: () => ({}) },
});

const page = usePage();

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const formatDate = (value) => {
  if (!value) return "—";
  return new Date(value).toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

// Normalise sectors whether they arrive as a plain array or a { data: [...] } wrapper.
const sectorsList = computed(() => {
  const sectors = props.city?.sectors;
  if (Array.isArray(sectors)) return sectors;
  if (sectors?.data && Array.isArray(sectors.data)) return sectors.data;
  return [];
});

const createSectorUrl = computed(() => route("sectors.create") + "?city_id=" + props.city.id);

const flashToast = () => {
  const flash = page.props?.flash ?? {};
  if (flash.success) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: flash.success,
      showConfirmButton: false,
      timer: 3000,
    });
  }
  if (flash.error) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: flash.error,
      showConfirmButton: false,
      timer: 4000,
    });
  }
};

watch(() => page.props?.flash, flashToast, { deep: true, immediate: true });

const confirmDeleteCity = () => {
  Swal.fire({
    title: "Delete this city?",
    text: `${props.city.name} will be removed. Cities with active sectors cannot be deleted.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) router.delete(route("cities.destroy", props.city.id));
  });
};

const confirmDeleteSector = (sector) => {
  Swal.fire({
    title: "Remove this sector?",
    text: `${sector.name} will be removed from ${props.city.name}.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Yes, remove it!",
  }).then((result) => {
    if (!result.isConfirmed) return;

    router.delete(route("sectors.destroy", sector.id) + "?return_to=city", {
      preserveScroll: true,
    });
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="city.name" pageTitle="Cities" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span
          class="badge fs-13"
          :class="city.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
        >
          {{ city.is_active ? "Active" : "Inactive" }}
        </span>
        <span class="text-muted fs-13">
          {{ sectorsList.length }} sector{{ sectorsList.length === 1 ? "" : "s" }}
        </span>
        <div class="ms-auto hstack gap-2">
          <Link :href="route('cities.index')" class="btn btn-sm btn-light">
            <i class="ri-arrow-left-line align-bottom me-1"></i> Back
          </Link>
          <Link v-if="can.update" :href="route('cities.edit', city.id)" class="btn btn-sm btn-soft-warning">
            <i class="ri-pencil-line align-bottom me-1"></i> Edit
          </Link>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" @click="confirmDeleteCity">
            <i class="ri-delete-bin-line align-bottom me-1"></i> Delete
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">City Information</h5></BCardHeader>
          <BCardBody>
            <div class="mb-3">
              <div class="text-muted fs-13">Name</div>
              <div class="fw-semibold">{{ city.name }}</div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">Code</div>
              <div class="fw-semibold">
                <span v-if="city.code" class="badge bg-light text-body border">{{ city.code }}</span>
                <span v-else>—</span>
              </div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">Region</div>
              <div class="fw-semibold">{{ city.region || "—" }}</div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">Total Sectors</div>
              <div class="fw-semibold">
                {{ city.sectors_count ?? sectorsList.length }}
                <span class="text-muted">({{ city.active_sectors_count ?? 0 }} active)</span>
              </div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">Created</div>
              <div class="fw-semibold">{{ formatDate(city.created_at) }}</div>
            </div>
            <div>
              <div class="text-muted fs-13">Last Updated</div>
              <div class="fw-semibold">{{ formatDate(city.updated_at) }}</div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="8">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Sectors in {{ city.name }}</h5>
            <Link v-if="can.sectors_create" :href="createSectorUrl" class="btn btn-sm btn-success">
              <i class="ri-add-line align-bottom me-1"></i> Add Sector
            </Link>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive table-card">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>Sector</th>
                    <th class="text-end">Delivery Price</th>
                    <th class="text-center">Orders</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sector in sectorsList" :key="sector.id">
                    <td class="fw-medium">{{ sector.name }}</td>
                    <td class="text-end">{{ money(sector.delivery_price) }} MAD</td>
                    <td class="text-center">{{ sector.orders_count ?? 0 }}</td>
                    <td>
                      <span
                        class="badge"
                        :class="sector.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                      >
                        {{ sector.is_active ? "Active" : "Inactive" }}
                      </span>
                    </td>
                    <td class="text-end">
                      <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                        <li v-if="can.sectors_update" class="list-inline-item" title="Edit">
                          <Link :href="route('sectors.edit', sector.id)" class="text-warning">
                            <i class="ri-pencil-fill fs-16"></i>
                          </Link>
                        </li>
                        <li v-if="can.sectors_delete" class="list-inline-item" title="Delete">
                          <BLink class="text-danger" @click="confirmDeleteSector(sector)">
                            <i class="ri-delete-bin-5-fill fs-16"></i>
                          </BLink>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  <tr v-if="sectorsList.length === 0">
                    <td colspan="5" class="text-center text-muted py-4">
                      No sectors yet for this city.
                      <Link v-if="can.sectors_create" :href="createSectorUrl" class="ms-1">Add the first sector</Link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
