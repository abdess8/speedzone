<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const props = defineProps({
  orders: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  tracking_number: props.filters.tracking_number ?? "",
  customer_name: props.filters.customer_name ?? "",
  customer_phone: props.filters.customer_phone ?? "",
  seller: props.filters.seller ?? "",
  city_id: props.filters.city_id ?? "",
  status: props.filters.status ?? "",
  payment_method: props.filters.payment_method ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
  delivery_from: props.filters.delivery_from ?? "",
  delivery_to: props.filters.delivery_to ?? "",
  is_fragile: props.filters.is_fragile ?? "",
  can_be_opened: props.filters.can_be_opened ?? "",
});

const sort = ref(props.filters.sort ?? "created_at");
const direction = ref(props.filters.direction ?? "desc");
const perPage = ref(props.filters.per_page ?? 25);

const selected = ref([]);
const bulkStatus = ref("");

const rows = computed(() => props.orders.data ?? []);
const meta = computed(() => props.orders.meta ?? {});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const query = () => {
  const params = { sort: sort.value, direction: direction.value, per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("orders.index"), query(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const applyFilters = () => {
  selected.value = [];
  reload();
};

const resetFilters = () => {
  Object.keys(filters).forEach((key) => (filters[key] = ""));
  sort.value = "created_at";
  direction.value = "desc";
  reload();
};

const sortBy = (field) => {
  if (sort.value === field) {
    direction.value = direction.value === "asc" ? "desc" : "asc";
  } else {
    sort.value = field;
    direction.value = "asc";
  }
  reload();
};

const sortIcon = (field) => {
  if (sort.value !== field) return "ri-arrow-up-down-line text-muted";
  return direction.value === "asc" ? "ri-sort-asc" : "ri-sort-desc";
};

watch(perPage, reload);

const allChecked = computed(
  () => rows.value.length > 0 && selected.value.length === rows.value.length
);

const toggleAll = () => {
  selected.value = allChecked.value ? [] : rows.value.map((o) => o.id);
};

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

const openPdf = (order) => window.open(route("orders.pdf", order.id), "_blank");

const exportSelected = () => {
  window.location = route("orders.export", { ids: selected.value.join(",") });
};

const printSelected = () => {
  window.open(route("orders.labels", { ids: selected.value.join(",") }), "_blank");
};

const applyBulkStatus = () => {
  if (!bulkStatus.value || selected.value.length === 0) return;
  router.post(
    route("orders.bulk-status"),
    { ids: selected.value, to_status: bulkStatus.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        selected.value = [];
        bulkStatus.value = "";
      },
    }
  );
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
    <PageHeader title="Orders" pageTitle="Order Management" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm>
            <h5 class="card-title mb-0">Order List</h5>
          </BCol>
          <BCol sm="auto">
            <div class="hstack gap-2">
              <Link v-if="can.create" :href="route('orders.create')" class="btn btn-success">
                <i class="ri-add-line align-bottom me-1"></i> New Order
              </Link>
            </div>
          </BCol>
        </BRow>
      </BCardHeader>

      <!-- Filters -->
      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="3">
            <label class="form-label">Tracking / Order #</label>
            <input v-model="filters.tracking_number" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Customer Name</label>
            <input v-model="filters.customer_name" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Customer Phone</label>
            <input v-model="filters.customer_phone" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Seller</label>
            <input v-model="filters.seller" type="text" class="form-control" placeholder="Name or ID" @keyup.enter="applyFilters" />
          </BCol>

          <BCol md="3">
            <label class="form-label">City</label>
            <select v-model="filters.city_id" class="form-select">
              <option value="">All cities</option>
              <option v-for="city in filterOptions.cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">Status</label>
            <select v-model="filters.status" class="form-select">
              <option value="">All statuses</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">Payment Method</label>
            <select v-model="filters.payment_method" class="form-select">
              <option value="">All methods</option>
              <option v-for="p in filterOptions.paymentMethods" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <BRow class="g-2">
              <BCol cols="6">
                <label class="form-label">Fragile</label>
                <select v-model="filters.is_fragile" class="form-select">
                  <option value="">Any</option>
                  <option value="1">Yes</option>
                  <option value="0">No</option>
                </select>
              </BCol>
              <BCol cols="6">
                <label class="form-label">Openable</label>
                <select v-model="filters.can_be_opened" class="form-select">
                  <option value="">Any</option>
                  <option value="1">Yes</option>
                  <option value="0">No</option>
                </select>
              </BCol>
            </BRow>
          </BCol>

          <BCol md="3">
            <label class="form-label">Created From</label>
            <input v-model="filters.created_from" type="date" class="form-control" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Created To</label>
            <input v-model="filters.created_to" type="date" class="form-control" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Delivered From</label>
            <input v-model="filters.delivery_from" type="date" class="form-control" />
          </BCol>
          <BCol md="3">
            <label class="form-label">Delivered To</label>
            <input v-model="filters.delivery_to" type="date" class="form-control" />
          </BCol>

          <BCol cols="12">
            <div class="hstack gap-2 justify-content-end">
              <button class="btn btn-light" @click="resetFilters">
                <i class="ri-refresh-line align-bottom me-1"></i> Reset
              </button>
              <button class="btn btn-primary" @click="applyFilters">
                <i class="ri-search-line align-bottom me-1"></i> Apply Filters
              </button>
            </div>
          </BCol>
        </BRow>
      </BCardBody>

      <!-- Bulk action bar -->
      <BCardBody v-if="selected.length" class="bg-light border-bottom-dashed py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="fw-medium me-2">{{ selected.length }} selected</span>
          <button v-if="can.export" class="btn btn-sm btn-soft-secondary" @click="exportSelected">
            <i class="ri-download-2-line align-bottom me-1"></i> Export
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="printSelected">
            <i class="ri-printer-line align-bottom me-1"></i> Print Labels
          </button>
          <div class="d-flex align-items-center gap-1 ms-2">
            <select v-model="bulkStatus" class="form-select form-select-sm" style="width: auto">
              <option value="">Change status…</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <button class="btn btn-sm btn-primary" :disabled="!bulkStatus" @click="applyBulkStatus">Apply</button>
          </div>
        </div>
      </BCardBody>

      <!-- Table -->
      <BCardBody>
        <div class="table-responsive table-card">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th style="width: 40px">
                  <input class="form-check-input" type="checkbox" :checked="allChecked" @change="toggleAll" />
                </th>
                <th role="button" @click="sortBy('tracking_number')">
                  Tracking # <i :class="sortIcon('tracking_number')"></i>
                </th>
                <th>Customer</th>
                <th>City</th>
                <th>Payment</th>
                <th role="button" class="text-end" @click="sortBy('order_amount')">
                  Order <i :class="sortIcon('order_amount')"></i>
                </th>
                <th role="button" class="text-end" @click="sortBy('delivery_price')">
                  Delivery <i :class="sortIcon('delivery_price')"></i>
                </th>
                <th class="text-end">Total</th>
                <th role="button" @click="sortBy('status')">Status <i :class="sortIcon('status')"></i></th>
                <th role="button" @click="sortBy('created_at')">Created <i :class="sortIcon('created_at')"></i></th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in rows" :key="order.id">
                <td>
                  <input class="form-check-input" type="checkbox" :value="order.id" v-model="selected" />
                </td>
                <td>
                  <Link :href="route('orders.show', order.id)" class="fw-semibold">{{ order.tracking_number }}</Link>
                  <div v-if="order.is_fragile || order.can_be_opened" class="mt-1">
                    <span v-if="order.is_fragile" class="badge bg-danger-subtle text-danger me-1">Fragile</span>
                    <span v-if="order.can_be_opened" class="badge bg-info-subtle text-info">Openable</span>
                  </div>
                </td>
                <td>
                  <div class="fw-medium">{{ order.customer.full_name }}</div>
                  <div class="text-muted fs-12">{{ order.customer.phone }}</div>
                </td>
                <td>{{ order.city?.name ?? "—" }}</td>
                <td>
                  <span class="badge" :class="order.payment_method === 'COD' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success'">
                    {{ order.payment_method_label }}
                  </span>
                </td>
                <td class="text-end">{{ money(order.order_amount) }}</td>
                <td class="text-end">{{ money(order.delivery_price) }}</td>
                <td class="text-end fw-semibold">{{ money(order.total_amount) }}</td>
                <td>
                  <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                    {{ order.status_label }}
                  </span>
                </td>
                <td class="text-muted fs-13">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li class="list-inline-item" title="View">
                      <Link :href="route('orders.show', order.id)" class="text-primary"><i class="ri-eye-fill fs-16"></i></Link>
                    </li>
                    <li v-if="can.print" class="list-inline-item" title="Print Label">
                      <BLink class="text-secondary" @click="openPdf(order)"><i class="ri-printer-line fs-16"></i></BLink>
                    </li>
                    <li class="list-inline-item" title="Edit">
                      <Link :href="route('orders.edit', order.id)" class="text-warning"><i class="ri-pencil-fill fs-16"></i></Link>
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="11" class="text-center text-muted py-4">No orders found.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer: page size + pagination -->
        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">Rows per page</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in filterOptions.pageSizes" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">
                {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total ?? 0 }}
              </span>
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
  </Layout>
</template>
