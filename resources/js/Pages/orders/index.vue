<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import PaymentMethodBadge from "@/Components/PaymentMethodBadge.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import StatusPills from "@/Components/StatusPills.vue";
import StatusKpiCards from "@/Components/StatusKpiCards.vue";
import DriverOrderCard from "./Partials/DriverOrderCard.vue";
import DriverStatusSheet from "./Partials/DriverStatusSheet.vue";
import DeliveryOutcomeSheet from "./Partials/DeliveryOutcomeSheet.vue";
import DriverReturnSheet from "./Partials/DriverReturnSheet.vue";
import OrderCard from "./Partials/OrderCard.vue";
import OrderDetailSheet from "./Partials/OrderDetailSheet.vue";
import Swal from "sweetalert2";
import { formatMoney as money } from "@/common/formatMoney";
import { useBulkStatusAccess } from "@/composables/useBulkStatusAccess";

const { t } = useI18n();
const { canBulkEditOrders } = useBulkStatusAccess();

const props = defineProps({
  orders: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  stats: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
  workflow: { type: Object, default: () => ({}) },
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

/**
 * Pre-filtered view selected from the sidebar (`?status_group=delivery`).
 *
 * Kept out of `filters` on purpose: it is a scope the user navigated into, not
 * something he typed, so it survives "reset" and is dropped by leaving the view.
 *
 * A ref rather than a computed because picking a status pill has to be able to
 * drop the scope; it is re-synced whenever the server sends a new one.
 */
const statusGroup = ref(props.filters.status_group ?? "");

watch(
  () => props.filters.status_group,
  (value) => {
    statusGroup.value = value ?? "";
  }
);

const activeView = computed(() =>
  (props.filterOptions.statusGroups ?? []).find((group) => group.value === statusGroup.value)
);

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

const sort = ref(props.filters.sort ?? "created_at");
const direction = ref(props.filters.direction ?? "desc");
const perPage = ref(props.filters.per_page ?? 25);

const selected = ref([]);

const rows = computed(() => props.orders.data ?? []);
const meta = computed(() => props.orders.meta ?? {});

const displayMoney = (value) => (value != null && value !== "" ? money(value) : "—");

const query = () => {
  const params = { sort: sort.value, direction: direction.value, per_page: perPage.value };

  if (statusGroup.value) {
    params.status_group = statusGroup.value;
  }

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });

  return params;
};

// Filter options and abilities never change between two visits to this page,
// so every table interaction asks the server for the table only. The status
// counts do move with the filters, hence their place in the list.
const TABLE_PROPS = ["orders", "filters", "stats"];

const reload = () => {
  router.get(route("orders.index"), query(), {
    only: TABLE_PROPS,
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const applyFilters = () => {
  selected.value = [];
  reload();
};

/**
 * Which pill reads as selected.
 *
 * A sidebar scope spans several statuses, so no single pill can stand for it —
 * leaving "All" lit would claim the list is unfiltered when it is not. None is
 * lit instead, and the badge beside the title explains the scope.
 */
const activeStatusPill = computed(() => (statusGroup.value ? null : filters.status));

const selectStatus = (value) => {
  filters.status = value;
  // The pill is an explicit choice; keeping the sidebar's scope on top of it
  // would silently narrow the very result the user just asked for.
  statusGroup.value = "";
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

const toggleSelect = (order) => {
  const index = selected.value.indexOf(order.id);
  if (index === -1) {
    selected.value.push(order.id);
  } else {
    selected.value.splice(index, 1);
  }
};

const goToPage = (url) => {
  if (!url) return;
  router.visit(url, { only: TABLE_PROPS, preserveState: true, preserveScroll: true });
};

const openPdf = (order) => window.open(route("orders.pdf", order.id), "_blank");

const exportSelected = () => {
  window.location = route("orders.export", { ids: selected.value.join(",") });
};

const printSelected = () => {
  window.open(route("orders.labels", { ids: selected.value.join(",") }), "_blank");
};

/*
 * Driver quick-action flow.
 *
 * The transition graph is shipped once per page keyed by source status, so the
 * options for a row are a lookup rather than a request.
 */
const canUpdateStatus = computed(() => props.workflow.can_update_status === true);
const isDriver = computed(() => props.workflow.is_driver === true);
const failureReasons = computed(() => props.workflow.failure_reasons ?? []);
const returnReasons = computed(() => props.workflow.return_reasons ?? []);
const deliveryOutcomes = computed(() => props.workflow.delivery_outcomes ?? []);

const transitionsFor = (order) => props.workflow.transitions?.[order.status] ?? [];

/*
 * A parcel on the round is not moved by picking a status: the driver reports
 * whether he handed it over, and the failure reason decides where it goes. Both
 * flows hang off the same button, so the row only has to know that *something*
 * can be done.
 */
const reportsOutcome = (order) =>
  (props.workflow.delivery_outcome_statuses ?? []).includes(order.status);

const canAct = (order) => reportsOutcome(order) || transitionsFor(order).length > 0;

/** A parcel already tied to a return must not spawn a second one. */
const canReturn = (order) =>
  props.workflow.can_create_return === true &&
  order.has_return !== true &&
  (props.workflow.return_eligible_statuses ?? []).includes(order.status);

const sheetOrder = ref(null);
const sheetProcessing = ref(false);
const sheetTransitions = computed(() =>
  sheetOrder.value ? transitionsFor(sheetOrder.value) : []
);

const openStatusSheet = (order) => {
  if (reportsOutcome(order)) {
    outcomeOrder.value = order;

    return;
  }

  sheetOrder.value = order;
};

const closeStatusSheet = () => {
  if (sheetProcessing.value) return;
  sheetOrder.value = null;
};

const submitStatusChange = ({ order, to_status, failure_reason, failure_note }) => {
  sheetProcessing.value = true;

  router.post(
    route("orders.bulk-status"),
    { ids: [order.id], to_status, failure_reason, failure_note },
    {
      preserveScroll: true,
      onFinish: () => {
        sheetProcessing.value = false;
        sheetOrder.value = null;
      },
    }
  );
};

const outcomeOrder = ref(null);
const outcomeProcessing = ref(false);

const closeOutcomeSheet = () => {
  if (outcomeProcessing.value) return;
  outcomeOrder.value = null;
};

const submitDeliveryOutcome = ({ order, outcome, failure_reason, note, attachment }) => {
  outcomeProcessing.value = true;

  // Multipart even without a file: Inertia cannot mix a File into a JSON body,
  // and branching on its presence would give the two paths different encodings.
  router.post(
    route("orders.delivery-outcome", order.id),
    { outcome, failure_reason, note, attachment },
    {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        outcomeProcessing.value = false;
        outcomeOrder.value = null;
      },
    }
  );
};

const returnOrder = ref(null);
const returnProcessing = ref(false);

const closeReturnSheet = () => {
  if (returnProcessing.value) return;
  returnOrder.value = null;
};

const submitReturn = ({ order, reason, return_notes }) => {
  returnProcessing.value = true;

  router.post(
    route("returns.store"),
    { order_id: order.id, reason, return_notes },
    {
      preserveScroll: true,
      onFinish: () => {
        returnProcessing.value = false;
        returnOrder.value = null;
      },
    }
  );
};

/* Mobile detail for everyone but the driver, rendered from the row in memory. */
const detailOrder = ref(null);

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
    <PageHeader :title="$t('orders.title')" :pageTitle="$t('orders.page_title')" />

    <StatusKpiCards
      :stats="stats"
      :model-value="filters.status"
      :all-label="$t('common.all_statuses')"
      @select="selectStatus"
    />

    <BCard no-body>
      <FilterPanel
        :active-count="activeFilterCount"
        :title="$t('common.filters_title')"
        @apply="applyFilters"
        @reset="resetFilters"
      >
        <template #title>
          <h5 class="card-title mb-0 text-truncate">{{ $t('orders.list_title') }}</h5>
          <!-- Names the sidebar view the list is restricted to, and offers a way out. -->
          <span v-if="activeView" class="badge bg-primary-subtle text-primary mt-1">
            {{ activeView.label }}
            <Link :href="route('orders.index')" class="text-primary ms-1" :aria-label="$t('common.clear_filters')">
              <i class="ri-close-line align-bottom"></i>
            </Link>
          </span>
        </template>

        <template #actions>
          <Link
            v-if="canBulkEditOrders"
            :href="route('bulk-status.index', { entity_type: 'ORDER' })"
            class="btn btn-soft-secondary text-nowrap"
            :title="$t('bulk_status.menu')"
            :aria-label="$t('bulk_status.menu')"
          >
            <i class="ri-list-check-3 align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('bulk_status.menu') }}</span>
          </Link>
          <Link
            v-if="can.create"
            :href="route('orders.import')"
            class="btn btn-soft-primary text-nowrap"
            :title="$t('orders.import.menu')"
            :aria-label="$t('orders.import.menu')"
          >
            <i class="ri-file-upload-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('orders.import.menu') }}</span>
          </Link>
          <Link
            v-if="can.create"
            :href="route('orders.create')"
            class="btn btn-success text-nowrap"
            :title="$t('orders.new_order')"
            :aria-label="$t('orders.new_order')"
          >
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('orders.new_order') }}</span>
          </Link>
        </template>

        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.tracking_number') }}</label>
          <input v-model="filters.tracking_number" type="text" class="form-control" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.customer_name') }}</label>
          <input v-model="filters.customer_name" type="text" class="form-control" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.customer_phone') }}</label>
          <input v-model="filters.customer_phone" type="tel" class="form-control" @keyup.enter="applyFilters" />
        </BCol>
        <BCol v-if="can.read_all" md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.seller') }}</label>
          <input v-model="filters.seller" type="text" class="form-control" :placeholder="$t('orders.filters.seller_placeholder')" @keyup.enter="applyFilters" />
        </BCol>

        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.city') }}</label>
          <select v-model="filters.city_id" class="form-select">
            <option value="">{{ $t('orders.filters.all_cities') }}</option>
            <option v-for="city in filterOptions.cities" :key="city.id" :value="city.id">{{ city.name }}</option>
          </select>
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('common.all_statuses') }}</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.payment_method') }}</label>
          <select v-model="filters.payment_method" class="form-select">
            <option value="">{{ $t('orders.filters.all_methods') }}</option>
            <option v-for="p in filterOptions.paymentMethods" :key="p.value" :value="p.value">{{ p.label }}</option>
          </select>
        </BCol>
        <BCol md="6" lg="3">
          <BRow class="g-2">
            <BCol cols="6">
              <label class="form-label">{{ $t('orders.filters.fragile') }}</label>
              <select v-model="filters.is_fragile" class="form-select">
                <option value="">{{ $t('common.any') }}</option>
                <option value="1">{{ $t('common.yes') }}</option>
                <option value="0">{{ $t('common.no') }}</option>
              </select>
            </BCol>
            <BCol cols="6">
              <label class="form-label">{{ $t('orders.filters.openable') }}</label>
              <select v-model="filters.can_be_opened" class="form-select">
                <option value="">{{ $t('common.any') }}</option>
                <option value="1">{{ $t('common.yes') }}</option>
                <option value="0">{{ $t('common.no') }}</option>
              </select>
            </BCol>
          </BRow>
        </BCol>

        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.created_from') }}</label>
          <input v-model="filters.created_from" type="date" class="form-control" />
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.created_to') }}</label>
          <input v-model="filters.created_to" type="date" class="form-control" />
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.delivered_from') }}</label>
          <input v-model="filters.delivery_from" type="date" class="form-control" />
        </BCol>
        <BCol md="6" lg="3">
          <label class="form-label">{{ $t('orders.filters.delivered_to') }}</label>
          <input v-model="filters.delivery_to" type="date" class="form-control" />
        </BCol>
      </FilterPanel>

      <!-- Status is what these lists are filtered by almost every time, so on a
           phone it gets a row of its own instead of four taps inside the sheet.
           Desktop keeps the select: there the whole form is visible at once. -->
      <StatusPills
        class="d-lg-none"
        :model-value="activeStatusPill"
        :options="filterOptions.statuses ?? []"
        :all-label="$t('common.all_statuses')"
        :label="$t('common.status')"
        @change="selectStatus"
      />

      <!-- Bulk action bar -->
      <BCardBody v-if="selected.length" class="bg-light border-bottom-dashed py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="fw-medium me-2">{{ $t('orders.bulk.selected', { count: selected.length }) }}</span>
          <button v-if="can.export" class="btn btn-sm btn-soft-secondary" @click="exportSelected">
            <i class="ri-download-2-line align-bottom me-1"></i> {{ $t('orders.bulk.export') }}
          </button>
          <button v-if="can.print" class="btn btn-sm btn-soft-secondary" @click="printSelected">
            <i class="ri-printer-line align-bottom me-1"></i> {{ $t('orders.bulk.print_labels') }}
          </button>
        </div>
      </BCardBody>

      <BCardBody>
        <!-- Mobile: cards for every role. Drivers get the field-work card, which
             is their whole view of an order; everybody else gets a summary card
             that opens a sheet. -->
        <div class="d-lg-none">
          <template v-if="isDriver">
            <DriverOrderCard
              v-for="order in rows"
              :key="order.id"
              :order="order"
              :can-update-status="canUpdateStatus"
              :transitions="transitionsFor(order)"
              :reports-outcome="reportsOutcome(order)"
              :can-create-return="canReturn(order)"
              @change-status="openStatusSheet"
              @create-return="returnOrder = $event"
            />
          </template>
          <template v-else>
            <OrderCard
              v-for="order in rows"
              :key="order.id"
              :order="order"
              :selectable="can.export || can.print"
              :selected="selected.includes(order.id)"
              @open="detailOrder = $event"
              @toggle-select="toggleSelect"
            />
          </template>

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('orders.empty') }}
          </p>
        </div>

        <!-- Desktop: high-density table. The reduced type size is what lets all
             twelve columns fit without a horizontal scroll. -->
        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr class="fs-11 text-uppercase">
                <th style="width: 32px">
                  <input class="form-check-input" type="checkbox" :checked="allChecked" @change="toggleAll" />
                </th>
                <th role="button" @click="sortBy('tracking_number')">
                  {{ $t('orders.table.tracking_number') }} <i :class="sortIcon('tracking_number')"></i>
                </th>
                <th>{{ $t('orders.table.customer') }}</th>
                <th>{{ $t('orders.filters.city') }}</th>
                <th>{{ $t('orders.table.payment') }}</th>
                <th role="button" class="text-end" @click="sortBy('order_amount')">
                  {{ $t('orders.table.to_collect') }} <i :class="sortIcon('order_amount')"></i>
                </th>
                <th role="button" class="text-end" @click="sortBy('order_value')">
                  {{ $t('orders.table.order_value') }} <i :class="sortIcon('order_value')"></i>
                </th>
                <th role="button" class="text-end" @click="sortBy('delivery_price')">
                  {{ $t('orders.table.delivery') }} <i :class="sortIcon('delivery_price')"></i>
                </th>
                <th class="text-end">{{ $t('orders.table.total') }}</th>
                <th role="button" @click="sortBy('status')">{{ $t('common.status') }} <i :class="sortIcon('status')"></i></th>
                <th role="button" @click="sortBy('created_at')">{{ $t('orders.table.created') }} <i :class="sortIcon('created_at')"></i></th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in rows" :key="order.id">
                <td>
                  <input class="form-check-input" type="checkbox" :value="order.id" v-model="selected" />
                </td>
                <td>
                  <Link v-if="can.view_details" :href="route('orders.show', order.id)" class="fw-semibold">{{ order.tracking_number }}</Link>
                  <span v-else class="fw-semibold">{{ order.tracking_number }}</span>
                  <div v-if="order.is_fragile || order.can_be_opened" class="mt-1">
                    <span v-if="order.is_fragile" class="badge bg-danger-subtle text-danger me-1">{{ $t('orders.badges.fragile') }}</span>
                    <span v-if="order.can_be_opened" class="badge bg-info-subtle text-info">{{ $t('orders.badges.openable') }}</span>
                  </div>
                </td>
                <td>
                  <div class="fw-medium">{{ order.customer.full_name }}</div>
                  <div class="text-muted fs-11">{{ order.customer.phone }}</div>
                </td>
                <td>
                  <div>{{ order.city?.name ?? "—" }}</div>
                  <div v-if="order.sector" class="text-muted fs-11">{{ order.sector.name }}</div>
                </td>
                <td>
                  <PaymentMethodBadge
                    :label="order.payment_method_label"
                    :emoji="order.payment_method_emoji"
                    :color="order.payment_method_color"
                  />
                </td>
                <td class="text-end">
                  <span v-if="order.is_already_paid" class="badge bg-success-subtle text-success">
                    {{ $t('orders.driver.already_paid') }}
                  </span>
                  <template v-else>{{ displayMoney(order.amount_to_collect) }}</template>
                </td>
                <td class="text-end">{{ displayMoney(order.order_value) }}</td>
                <td class="text-end">{{ money(order.delivery_price) }}</td>
                <td class="text-end fw-semibold">{{ money(order.total_amount) }}</td>
                <td>
                  <span class="badge" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
                    {{ order.status_label }}
                  </span>
                </td>
                <td class="text-muted">{{ new Date(order.created_at).toLocaleDateString() }}</td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li v-if="can.view_details" class="list-inline-item" :title="$t('common.view')">
                      <Link :href="route('orders.show', order.id)" class="text-primary"><i class="ri-eye-fill fs-14"></i></Link>
                    </li>
                    <li v-if="can.print" class="list-inline-item" :title="$t('orders.actions.print_label')">
                      <BLink class="text-secondary" @click="openPdf(order)"><i class="ri-printer-line fs-14"></i></BLink>
                    </li>
                    <li v-if="can.view_details" class="list-inline-item" :title="$t('common.edit')">
                      <Link :href="route('orders.edit', order.id)" class="text-warning"><i class="ri-pencil-fill fs-14"></i></Link>
                    </li>
                    <li v-if="isDriver && canUpdateStatus && canAct(order)" class="list-inline-item" :title="$t('orders.driver.update_status')">
                      <BLink class="text-primary" @click="openStatusSheet(order)"><i class="ri-refresh-line fs-14"></i></BLink>
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="12" class="text-center text-muted py-4">{{ $t('orders.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer: page size + pagination -->
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

    <DriverStatusSheet
      :show="sheetOrder !== null"
      :order="sheetOrder"
      :transitions="sheetTransitions"
      :failure-reasons="failureReasons"
      :processing="sheetProcessing"
      @close="closeStatusSheet"
      @submit="submitStatusChange"
    />

    <DeliveryOutcomeSheet
      :show="outcomeOrder !== null"
      :order="outcomeOrder"
      :outcomes="deliveryOutcomes"
      :failure-reasons="failureReasons"
      :processing="outcomeProcessing"
      @close="closeOutcomeSheet"
      @submit="submitDeliveryOutcome"
    />

    <DriverReturnSheet
      :show="returnOrder !== null"
      :order="returnOrder"
      :reasons="returnReasons"
      :processing="returnProcessing"
      @close="closeReturnSheet"
      @submit="submitReturn"
    />

    <OrderDetailSheet
      :show="detailOrder !== null"
      :order="detailOrder"
      :can="can"
      @close="detailOrder = null"
      @print="openPdf"
    />
  </Layout>
</template>
