<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CreatePickupModal from "./Partials/CreatePickupModal.vue";
import QrScanner from "./Partials/QrScanner.vue";
import Swal from "sweetalert2";

const props = defineProps({
  pickups: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  eligibleOrders: { type: Array, default: () => [] },
  pickupAddresses: { type: Array, default: () => [] },
  pageTitle: { type: String, default: "Pickup Requests" },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
  seller_id: props.filters.seller_id ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
const showCreateModal = ref(false);
const showQrScanner = ref(false);

const rows = computed(() => props.pickups.data ?? []);
const meta = computed(() => props.pickups.meta ?? {});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const formatDate = (value) => (value ? new Date(value).toLocaleString() : "—");

const query = () => {
  const params = { per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("pickup-requests.index"), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const applyFilters = () => reload();

const resetFilters = () => {
  Object.keys(filters).forEach((key) => (filters[key] = ""));
  reload();
};

watch(perPage, reload);

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: success,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="pageTitle" pageTitle="Pickup Management" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm>
            <h5 class="card-title mb-0">{{ pageTitle }}</h5>
          </BCol>
          <BCol sm="auto">
            <div class="hstack gap-2">
              <button v-if="can.scan" type="button" class="btn btn-soft-primary" @click="showQrScanner = true">
                <i class="ri-qr-scan-2-line align-bottom me-1"></i> QR Scan
              </button>
              <button v-if="can.create" type="button" class="btn btn-success" @click="showCreateModal = true">
                <i class="ri-add-line align-bottom me-1"></i> Create Pickup Request
              </button>
            </div>
          </BCol>
        </BRow>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="3">
            <label class="form-label">Reference</label>
            <input v-model="filters.search" type="text" class="form-control" placeholder="PU-2026-000001" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="">All statuses</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </BCol>
          <BCol v-if="can.read_all" md="3">
            <label class="form-label">Seller</label>
            <select v-model="filters.seller_id" class="form-select">
              <option value="">All sellers</option>
              <option v-for="seller in filterOptions.sellers" :key="seller.id" :value="seller.id">{{ seller.name }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">Created from</label>
            <input v-model="filters.created_from" type="date" class="form-control" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Created to</label>
            <input v-model="filters.created_to" type="date" class="form-control" />
          </BCol>
          <BCol md="3" class="d-flex align-items-end gap-2">
            <button type="button" class="btn btn-primary" @click="applyFilters">
              <i class="ri-search-line align-bottom me-1"></i> Apply Filters
            </button>
            <button type="button" class="btn btn-light" @click="resetFilters">Reset</button>
          </BCol>
        </BRow>
      </BCardBody>

      <BCardBody>
        <div class="table-responsive table-card">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>Reference</th>
                <th v-if="can.read_all">Seller</th>
                <th>Address</th>
                <th class="text-center">Packages</th>
                <th class="text-end">Total Amount</th>
                <th>Driver</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="pickup in rows" :key="pickup.id">
                <td>
                  <Link :href="route('pickup-requests.show', pickup.id)" class="fw-semibold">{{ pickup.reference }}</Link>
                </td>
                <td v-if="can.read_all">{{ pickup.creator?.name ?? "—" }}</td>
                <td>
                  <span class="text-truncate d-inline-block" style="max-width: 220px" :title="pickup.pickup_address">
                    {{ pickup.pickup_address }}
                  </span>
                </td>
                <td class="text-center">{{ pickup.number_of_packages }}</td>
                <td class="text-end fw-semibold">{{ money(pickup.total_orders_amount) }} MAD</td>
                <td>{{ pickup.assignee?.name ?? "—" }}</td>
                <td>
                  <span class="badge" :class="`bg-${pickup.status_color}-subtle text-${pickup.status_color}`">
                    {{ pickup.status_label }}
                  </span>
                </td>
                <td class="text-muted fs-13">{{ formatDate(pickup.created_at) }}</td>
                <td class="text-end">
                  <Link :href="route('pickup-requests.show', pickup.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line"></i>
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td :colspan="can.read_all ? 9 : 8" class="text-center text-muted py-4">No pickup requests found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">Rows per page</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in filterOptions.pageSizes" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}</span>
            </div>
          </BCol>
          <BCol sm class="d-flex justify-content-sm-end">
            <ul class="pagination pagination-sm mb-0" v-if="meta.links">
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

    <CreatePickupModal
      :show="showCreateModal"
      :eligible-orders="eligibleOrders"
      :pickup-addresses="pickupAddresses"
      @close="showCreateModal = false"
      @created="showCreateModal = false"
    />

    <QrScanner :show="showQrScanner" @close="showQrScanner = false" />
  </Layout>
</template>
