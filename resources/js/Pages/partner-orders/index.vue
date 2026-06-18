<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import PaymentMethodBadge from "@/Components/PaymentMethodBadge.vue";
import PartnerOrderQrScanner from "./Partials/PartnerOrderQrScanner.vue";
import Swal from "sweetalert2";
import { formatMoney as money } from "@/common/formatMoney";

const { t } = useI18n();

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
  partner_id: props.filters.partner_id ?? "",
  city_id: props.filters.city_id ?? "",
  status: props.filters.status ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const sort = ref(props.filters.sort ?? "created_at");
const direction = ref(props.filters.direction ?? "desc");
const perPage = ref(props.filters.per_page ?? 25);
const syncing = ref(false);
const selected = ref([]);
const selectedDriverId = ref("");
const showScanner = ref(false);
const bulkProcessing = ref(false);

const rows = computed(() => props.orders.data ?? []);
const meta = computed(() => props.orders.meta ?? {});

const selectedPartner = computed(() =>
  props.filterOptions.partners?.find((p) => String(p.id) === String(filters.partner_id))
);

const allChecked = computed(
  () => rows.value.length > 0 && selected.value.length === rows.value.length
);

const toggleAll = () => {
  selected.value = allChecked.value ? [] : rows.value.map((o) => o.id);
};

const query = () => {
  const params = { sort: sort.value, direction: direction.value, per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("partner-orders.index"), query(), {
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

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

const syncPartner = async () => {
  if (!filters.partner_id) {
    Swal.fire({ icon: "warning", title: t("partners.sync.select_partner") });
    return;
  }

  syncing.value = true;

  try {
    const { data } = await axios.post(route("partners.sync", filters.partner_id));
    Swal.fire({
      icon: "success",
      title: t("partners.sync.done"),
      text: data.message,
      timer: 4000,
      showConfirmButton: false,
    });
    reload();
  } catch (error) {
    const message = error.response?.data?.message ?? t("partners.sync.failed");
    Swal.fire({ icon: "error", title: t("partners.sync.failed"), text: message });
  } finally {
    syncing.value = false;
  }
};

const bulkAdvanceStatus = () => {
  if (selected.value.length === 0) return;

  Swal.fire({
    title: t("partners.orders.bulk.advance_confirm_title"),
    text: t("partners.orders.bulk.advance_confirm_text", { count: selected.value.length }),
    icon: "question",
    showCancelButton: true,
    confirmButtonText: t("partners.orders.bulk.advance_action"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#0ab39c",
  }).then((result) => {
    if (!result.isConfirmed) return;

    bulkProcessing.value = true;
    router.post(
      route("partner-orders.bulk-advance-status"),
      { ids: selected.value },
      {
        preserveScroll: true,
        onSuccess: () => {
          selected.value = [];
        },
        onFinish: () => {
          bulkProcessing.value = false;
        },
      }
    );
  });
};

const bulkAssignDriver = () => {
  if (selected.value.length === 0 || !selectedDriverId.value) return;

  Swal.fire({
    title: t("partners.orders.bulk.assign_confirm_title"),
    text: t("partners.orders.bulk.assign_confirm_text", { count: selected.value.length }),
    icon: "question",
    showCancelButton: true,
    confirmButtonText: t("driver_invoices.assign.assign_action"),
    cancelButtonText: t("common.cancel"),
    confirmButtonColor: "#0ab39c",
  }).then((result) => {
    if (!result.isConfirmed) return;

    bulkProcessing.value = true;
    router.post(
      route("partner-orders.bulk-assign-driver"),
      { ids: selected.value, driver_id: Number(selectedDriverId.value) },
      {
        preserveScroll: true,
        onSuccess: () => {
          selected.value = [];
          selectedDriverId.value = "";
        },
        onFinish: () => {
          bulkProcessing.value = false;
        },
      }
    );
  });
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
    <PageHeader :title="$t('partners.orders.title')" :pageTitle="$t('partners.title')" />

    <BCard no-body>
      <BCardHeader class="border-bottom-dashed">
        <BRow class="g-3 align-items-center">
          <BCol sm>
            <h5 class="card-title mb-0">{{ $t('partners.orders.list_title') }}</h5>
            <p class="text-muted fs-13 mb-0">{{ $t('partners.orders.subtitle') }}</p>
          </BCol>
          <BCol sm="auto">
            <div class="hstack gap-2">
              <button
                v-if="can.scan"
                type="button"
                class="btn btn-soft-info"
                @click="showScanner = true"
              >
                <i class="ri-qr-scan-2-line align-bottom me-1"></i>
                {{ $t('partners.orders.scanner.open') }}
              </button>
              <Link v-if="can.view_partners" :href="route('partners.index')" class="btn btn-light">
                <i class="ri-links-line align-bottom me-1"></i> {{ $t('partners.title') }}
              </Link>
              <button
                v-if="can.sync"
                type="button"
                class="btn btn-primary"
                :disabled="syncing || !filters.partner_id"
                @click="syncPartner"
              >
                <span v-if="syncing" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ri-refresh-line align-bottom me-1"></i>
                {{ syncing ? $t('partners.sync.running') : $t('partners.sync.button') }}
              </button>
            </div>
          </BCol>
        </BRow>
      </BCardHeader>

      <BCardBody class="border-bottom-dashed">
        <BRow class="g-3">
          <BCol md="3">
            <label class="form-label">{{ $t('partners.orders.filter_partner') }} <span class="text-danger">*</span></label>
            <select v-model="filters.partner_id" class="form-select">
              <option value="">{{ $t('partners.orders.all_partners') }}</option>
              <option v-for="partner in filterOptions.partners" :key="partner.id" :value="partner.id">
                {{ partner.name }}
              </option>
            </select>
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('orders.filters.tracking_number') }}</label>
            <input v-model="filters.tracking_number" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('orders.filters.customer_name') }}</label>
            <input v-model="filters.customer_name" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('orders.filters.customer_phone') }}</label>
            <input v-model="filters.customer_phone" type="text" class="form-control" @keyup.enter="applyFilters" />
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('orders.filters.city') }}</label>
            <select v-model="filters.city_id" class="form-select">
              <option value="">{{ $t('orders.filters.all_cities') }}</option>
              <option v-for="city in filterOptions.cities" :key="city.id" :value="city.id">{{ city.name }}</option>
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
            <label class="form-label">{{ $t('orders.filters.created_from') }}</label>
            <input v-model="filters.created_from" type="date" class="form-control" />
          </BCol>
          <BCol md="3">
            <label class="form-label">{{ $t('orders.filters.created_to') }}</label>
            <input v-model="filters.created_to" type="date" class="form-control" />
          </BCol>
          <BCol cols="12">
            <div class="hstack gap-2 justify-content-end">
              <button class="btn btn-light" @click="resetFilters">
                <i class="ri-refresh-line align-bottom me-1"></i> {{ $t('common.reset') }}
              </button>
              <button class="btn btn-primary" @click="applyFilters">
                <i class="ri-search-line align-bottom me-1"></i> {{ $t('common.apply_filters') }}
              </button>
            </div>
          </BCol>
        </BRow>
      </BCardBody>

      <BCardBody v-if="selected.length && can.bulk_status" class="bg-light border-bottom-dashed py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="fw-medium me-2">{{ $t('partners.orders.bulk.selected', { count: selected.length }) }}</span>
          <button
            type="button"
            class="btn btn-sm btn-soft-success"
            :disabled="bulkProcessing"
            @click="bulkAdvanceStatus"
          >
            <i class="ri-skip-forward-line align-bottom me-1"></i>
            {{ $t('partners.orders.bulk.advance_action') }}
          </button>
          <template v-if="can.assign_driver && filterOptions.drivers?.length">
            <select v-model="selectedDriverId" class="form-select form-select-sm" style="max-width: 220px">
              <option value="">{{ $t('driver_invoices.assign.select_driver') }}</option>
              <option v-for="driver in filterOptions.drivers" :key="driver.id" :value="driver.id">
                {{ driver.name }}
              </option>
            </select>
            <button
              type="button"
              class="btn btn-sm btn-soft-primary"
              :disabled="bulkProcessing || !selectedDriverId"
              @click="bulkAssignDriver"
            >
              <i class="ri-user-add-line align-bottom me-1"></i>
              {{ $t('partners.orders.bulk.assign_action') }}
            </button>
          </template>
        </div>
      </BCardBody>

      <BCardBody>
        <div v-if="selectedPartner && can.sync" class="alert alert-info py-2 fs-13 mb-3">
          <i class="ri-information-line me-1"></i>
          {{ $t('partners.sync.hint', { name: selectedPartner.name }) }}
        </div>

        <div class="table-responsive table-card">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th v-if="can.bulk_status" style="width: 40px">
                  <input class="form-check-input" type="checkbox" :checked="allChecked" @change="toggleAll" />
                </th>
                <th role="button" @click="sortBy('tracking_number')">
                  {{ $t('orders.table.tracking_number') }} <i :class="sortIcon('tracking_number')"></i>
                </th>
                <th>{{ $t('partners.orders.external_code') }}</th>
                <th>{{ $t('partners.table.name') }}</th>
                <th>{{ $t('orders.table.customer') }}</th>
                <th>{{ $t('orders.filters.city') }}</th>
                <th role="button" class="text-end" @click="sortBy('order_amount')">
                  {{ $t('orders.table.to_collect') }} <i :class="sortIcon('order_amount')"></i>
                </th>
                <th role="button" @click="sortBy('status')">{{ $t('common.status') }} <i :class="sortIcon('status')"></i></th>
                <th>{{ $t('partners.orders.option_exchange') }}</th>
                <th role="button" @click="sortBy('created_at')">{{ $t('orders.table.created') }} <i :class="sortIcon('created_at')"></i></th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td :colspan="can.bulk_status ? 11 : 10" class="text-center text-muted py-4">{{ $t('partners.orders.empty') }}</td>
              </tr>
              <tr v-for="order in rows" :key="order.id">
                <td v-if="can.bulk_status">
                  <input class="form-check-input" type="checkbox" :value="order.id" v-model="selected" />
                </td>
                <td>
                  <Link :href="route('orders.show', order.id)" class="fw-semibold">{{ order.tracking_number }}</Link>
                </td>
                <td><code v-if="order.external_tracking_code">{{ order.external_tracking_code }}</code><span v-else>—</span></td>
                <td>{{ order.partner?.name ?? "—" }}</td>
                <td>
                  <div class="fw-medium">{{ order.customer.full_name }}</div>
                  <div class="text-muted fs-12">{{ order.customer.phone }}</div>
                </td>
                <td>
                  <div>{{ order.city?.name ?? "—" }}</div>
                  <div v-if="order.sector" class="text-muted fs-12">{{ order.sector.name }}</div>
                </td>
                <td class="text-end">{{ order.amount_to_collect != null ? money(order.amount_to_collect) : "—" }}</td>
                <td>
                  <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                    {{ order.status_label }}
                  </span>
                </td>
                <td>
                  <span v-if="order.option_exchange" class="badge bg-warning-subtle text-warning">
                    {{ $t('common.yes') }}
                  </span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-muted fs-13">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                <td class="text-end">
                  <Link :href="route('orders.show', order.id)" class="text-primary" :title="$t('common.view')">
                    <i class="ri-eye-fill fs-16"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="meta.total" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
          <div class="text-muted fs-13">
            {{ $t('common.showing') }} {{ meta.from }}–{{ meta.to }} {{ $t('common.of') }} {{ meta.total }}
          </div>
          <nav v-if="meta.links?.length">
            <ul class="pagination pagination-sm mb-0">
              <li
                v-for="(link, i) in meta.links"
                :key="i"
                class="page-item"
                :class="{ active: link.active, disabled: !link.url }"
              >
                <button type="button" class="page-link" @click="goToPage(link.url)" v-html="link.label"></button>
              </li>
            </ul>
          </nav>
        </div>
      </BCardBody>
    </BCard>

    <PartnerOrderQrScanner :show="showScanner" @close="showScanner = false" />
  </Layout>
</template>
