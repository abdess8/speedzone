<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  invoices: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  invoice_number: props.filters.invoice_number ?? "",
  driver: props.filters.driver ?? "",
  status: props.filters.status ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const sort = ref(props.filters.sort ?? "created_at");
const direction = ref(props.filters.direction ?? "desc");
const perPage = ref(props.filters.per_page ?? 25);

const rows = computed(() => props.invoices.data ?? []);
const meta = computed(() => props.invoices.meta ?? {});

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");

const period = (inv) => {
  if (!inv.period_start && !inv.period_end) return "—";
  return `${inv.period_start ?? "…"} → ${inv.period_end ?? "…"}`;
};

const query = () => {
  const params = { sort: sort.value, direction: direction.value, per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("driver-invoices.index"), query(), { preserveState: true, preserveScroll: true, replace: true });
};

const applyFilters = () => reload();

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

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

const openPdf = (invoice) => window.open(route("driver-invoices.pdf", invoice.id), "_blank");

onMounted(() => {
  const flash = usePage().props?.flash ?? {};
  if (flash.success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: flash.success, showConfirmButton: false, timer: 3000, timerProgressBar: true });
  }
  if (flash.error) {
    Swal.fire({ toast: true, position: "top-end", icon: "error", title: flash.error, showConfirmButton: false, timer: 4000, timerProgressBar: true });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('driver_invoices.title')" :pageTitle="$t('driver_invoices.page_title')" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm>
            <h5 class="card-title mb-0">{{ $t('driver_invoices.list_title') }}</h5>
          </BCol>
          <BCol sm="auto">
            <div class="hstack gap-2">
              <Link v-if="can.pay" :href="route('driver-invoices.payments')" class="btn btn-soft-primary">
                <i class="ri-bank-card-line align-bottom me-1"></i> {{ $t('driver_invoices.payments.title') }}
              </Link>
              <Link v-if="can.generate" :href="route('driver-invoices.create')" class="btn btn-success">
                <i class="ri-add-line align-bottom me-1"></i> {{ $t('driver_invoices.generate') }}
              </Link>
            </div>
          </BCol>
        </BRow>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="3">
            <label class="form-label">{{ $t('driver_invoices.filters.invoice_number') }}</label>
            <input v-model="filters.invoice_number" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol v-if="can.read_all" md="3">
            <label class="form-label">{{ $t('driver_invoices.filters.driver') }}</label>
            <input v-model="filters.driver" type="text" class="form-control" :placeholder="$t('driver_invoices.filters.driver_placeholder')" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="2">
            <label class="form-label">{{ $t('driver_invoices.filters.status') }}</label>
            <select v-model="filters.status" class="form-select">
              <option value="">{{ $t('driver_invoices.filters.all_statuses') }}</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </BCol>
          <BCol md="2">
            <label class="form-label">{{ $t('driver_invoices.filters.created_from') }}</label>
            <input v-model="filters.created_from" type="date" class="form-control" />
          </BCol>
          <BCol md="2">
            <label class="form-label">{{ $t('driver_invoices.filters.created_to') }}</label>
            <input v-model="filters.created_to" type="date" class="form-control" />
          </BCol>
          <BCol cols="12">
            <div class="hstack gap-2 justify-content-end">
              <button class="btn btn-light text-nowrap" @click="resetFilters">
                <i class="ri-refresh-line align-bottom me-1"></i> {{ $t('common.reset') }}
              </button>
              <button class="btn btn-primary text-nowrap" @click="applyFilters">
                <i class="ri-search-line align-bottom me-1"></i> {{ $t('common.apply_filters') }}
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
                <th role="button" @click="sortBy('invoice_number')">
                  {{ $t('driver_invoices.table.invoice_number') }} <i :class="sortIcon('invoice_number')"></i>
                </th>
                <th v-if="can.read_all">{{ $t('driver_invoices.table.driver') }}</th>
                <th>{{ $t('driver_invoices.table.period') }}</th>
                <th role="button" class="text-end" @click="sortBy('deliveries_count')">
                  {{ $t('driver_invoices.table.deliveries') }} <i :class="sortIcon('deliveries_count')"></i>
                </th>
                <th role="button" class="text-end" @click="sortBy('total_amount')">
                  {{ $t('driver_invoices.table.total') }} <i :class="sortIcon('total_amount')"></i>
                </th>
                <th role="button" @click="sortBy('status')">{{ $t('driver_invoices.table.status') }} <i :class="sortIcon('status')"></i></th>
                <th role="button" @click="sortBy('generated_at')">{{ $t('driver_invoices.table.generated_at') }} <i :class="sortIcon('generated_at')"></i></th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="inv in rows" :key="inv.id">
                <td>
                  <Link :href="route('driver-invoices.show', inv.id)" class="fw-semibold">{{ inv.invoice_number }}</Link>
                </td>
                <td v-if="can.read_all">
                  <div class="fw-medium">{{ inv.driver?.full_name ?? inv.driver?.name ?? "—" }}</div>
                  <div class="text-muted fs-12">{{ inv.driver?.email }}</div>
                </td>
                <td class="text-muted fs-13">{{ period(inv) }}</td>
                <td class="text-end">{{ inv.deliveries_count }}</td>
                <td class="text-end fw-semibold">{{ money(inv.total_amount) }}</td>
                <td>
                  <span class="badge" :class="`bg-${inv.status_color}-subtle text-${inv.status_color}`">
                    <i :class="inv.status_icon" class="align-bottom me-1"></i>{{ inv.status_label }}
                  </span>
                </td>
                <td class="text-muted fs-13">{{ formatDate(inv.generated_at) }}</td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li class="list-inline-item" :title="$t('driver_invoices.actions.view')">
                      <Link :href="route('driver-invoices.show', inv.id)" class="text-primary"><i class="ri-eye-fill fs-16"></i></Link>
                    </li>
                    <li v-if="inv.pdf_url" class="list-inline-item" :title="$t('driver_invoices.actions.view_pdf')">
                      <BLink class="text-secondary" @click="openPdf(inv)"><i class="ri-file-pdf-2-line fs-16"></i></BLink>
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td :colspan="can.read_all ? 8 : 7" class="text-center text-muted py-4">{{ $t('driver_invoices.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">{{ $t('common.rows_per_page') }}</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in filterOptions.pageSizes" :key="size" :value="size">{{ size }}</option>
              </select>
              <span class="text-muted">
                {{ $t('common.pagination_range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total ?? 0 }) }}
              </span>
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
