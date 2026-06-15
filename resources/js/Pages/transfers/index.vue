<script setup>
import { computed, ref, reactive, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CreateTransferModal from "./Partials/CreateTransferModal.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  transfers: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  eligibleOrders: { type: Array, default: () => [] },
  staff: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
  from_city_id: props.filters.from_city_id ?? "",
  to_city_id: props.filters.to_city_id ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
const showCreateModal = ref(false);

const rows = computed(() => props.transfers.data ?? []);
const meta = computed(() => props.transfers.meta ?? {});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const formatDate = (value) => (value ? new Date(value).toLocaleString() : t("common.empty_value"));

const query = () => {
  const params = { per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("transfers.index"), query(), {
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
    <PageHeader :title="$t('transfers.title')" :pageTitle="$t('transfers.page_title')" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm>
            <h5 class="card-title mb-0">{{ $t('transfers.title') }}</h5>
          </BCol>
          <BCol sm="auto">
            <button v-if="can.create" type="button" class="btn btn-success" @click="showCreateModal = true">
              <i class="ri-add-line align-bottom me-1"></i> {{ $t('transfers.create') }}
            </button>
          </BCol>
        </BRow>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="3">
            <label class="form-label">{{ $t('transfers.filters.reference') }}</label>
            <input v-model="filters.search" type="text" class="form-control" :placeholder="$t('transfers.filters.reference_placeholder')" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('transfers.filters.from_city') }}</label>
            <select v-model="filters.from_city_id" class="form-select">
              <option value="">{{ $t('transfers.filters.all_cities') }}</option>
              <option v-for="city in filterOptions.cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('transfers.filters.to_city') }}</label>
            <select v-model="filters.to_city_id" class="form-select">
              <option value="">{{ $t('transfers.filters.all_cities') }}</option>
              <option v-for="city in filterOptions.cities" :key="`to-${city.id}`" :value="city.id">{{ city.name }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('common.status') }}</label>
            <select v-model="filters.status" class="form-select">
              <option value="">{{ $t('common.all_statuses') }}</option>
              <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('transfers.filters.created_from') }}</label>
            <input v-model="filters.created_from" type="date" class="form-control" />
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('transfers.filters.created_to') }}</label>
            <input v-model="filters.created_to" type="date" class="form-control" />
          </BCol>
          <BCol cols="12">
            <div class="hstack gap-2 justify-content-end">
              <button type="button" class="btn btn-light text-nowrap" @click="resetFilters">{{ $t('common.reset') }}</button>
              <button type="button" class="btn btn-primary text-nowrap" @click="applyFilters">
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
                <th>{{ $t('transfers.filters.reference') }}</th>
                <th>{{ $t('transfers.table.from_city') }}</th>
                <th>{{ $t('transfers.table.to_city') }}</th>
                <th class="text-center">{{ $t('transfers.table.packages') }}</th>
                <th class="text-end">{{ $t('transfers.table.total_amount') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th>{{ $t('transfers.table.created') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transfer in rows" :key="transfer.id">
                <td>
                  <Link :href="route('transfers.show', transfer.id)" class="fw-semibold">{{ transfer.reference }}</Link>
                </td>
                <td>{{ transfer.from_city?.name ?? $t('common.empty_value') }}</td>
                <td>{{ transfer.to_city?.name ?? $t('common.empty_value') }}</td>
                <td class="text-center">{{ transfer.number_of_packages }}</td>
                <td class="text-end fw-semibold">{{ money(transfer.total_amount) }} {{ $t('common.currency_mad') }}</td>
                <td>
                  <span class="badge" :class="`bg-${transfer.status_color}-subtle text-${transfer.status_color}`">
                    {{ transfer.status_label }}
                  </span>
                </td>
                <td class="text-muted fs-13">{{ formatDate(transfer.created_at) }}</td>
                <td class="text-end">
                  <Link :href="route('transfers.show', transfer.id)" class="btn btn-sm btn-soft-primary">
                    <i class="ri-eye-line"></i>
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="8" class="text-center text-muted py-4">{{ $t('transfers.empty') }}</td>
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

    <CreateTransferModal
      :show="showCreateModal"
      :cities="filterOptions.cities ?? []"
      :default-from-city-id="filterOptions.defaultFromCityId"
      :staff="staff"
      @close="showCreateModal = false"
      @created="showCreateModal = false"
    />
  </Layout>
</template>
