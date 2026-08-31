<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import StatusKpiCards from "@/Components/StatusKpiCards.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  tickets: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  stats: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  filterOptions: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  reference: props.filters.reference ?? "",
  subject: props.filters.subject ?? "",
  seller: props.filters.seller ?? "",
  assigned_to: props.filters.assigned_to ?? "",
  status: props.filters.status ?? "",
  category: props.filters.category ?? "",
  created_from: props.filters.created_from ?? "",
  created_to: props.filters.created_to ?? "",
});

const sort = ref(props.filters.sort ?? "created_at");
const direction = ref(props.filters.direction ?? "desc");
const perPage = ref(props.filters.per_page ?? 25);
/** Row whose mobile detail sheet is open. */
const selectedTicket = ref(null);

const rows = computed(() => props.tickets.data ?? []);
const meta = computed(() => props.tickets.meta ?? {});

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : "—");
const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : "—");

const objectLabel = (ticket) => {
  if (!ticket.object_type_label) return "—";
  const ref = ticket.object?.reference ?? (ticket.object_id ? `#${ticket.object_id}` : "");
  return ref ? `${ticket.object_type_label}: ${ref}` : ticket.object_type_label;
};

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

const cardRows = (ticket) => [
  { label: t("support_tickets.table.category"), value: ticket.category_label },
  { label: t("support_tickets.table.object"), value: objectLabel(ticket) },
  { label: t("support_tickets.table.created_at"), value: formatDate(ticket.created_at) },
];

const sheetRows = (ticket) => [
  ...cardRows(ticket),
  ...(props.can.read_all
    ? [
        { label: t("support_tickets.table.seller"), value: ticket.creator?.name },
        {
          label: t("support_tickets.table.assigned"),
          value: ticket.assignee?.name ?? t("support_tickets.filters.unassigned"),
        },
      ]
    : []),
];

const query = () => {
  const params = { sort: sort.value, direction: direction.value, per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("support-tickets.index"), query(), { preserveState: true, preserveScroll: true, replace: true });
};

const applyFilters = () => reload();

const selectStatus = (value) => {
  filters.status = value;
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
    <PageHeader :title="$t('support_tickets.title')" :pageTitle="$t('support_tickets.page_title')" />

    <StatusKpiCards
      :stats="stats"
      :model-value="filters.status"
      :all-label="$t('support_tickets.filters.all_statuses')"
      show-empty
      @select="selectStatus"
    />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('support_tickets.list_title') }}</h5>
        </template>

        <template #actions>
          <Link v-if="can.create" :href="route('support-tickets.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('support_tickets.create') }}</span>
          </Link>
        </template>

        <BCol md="2">
          <label class="form-label">{{ $t('support_tickets.filters.reference') }}</label>
          <input v-model="filters.reference" type="text" class="form-control" @keyup.enter="applyFilters" />
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('support_tickets.filters.subject') }}</label>
          <input v-model="filters.subject" type="text" class="form-control" @keyup.enter="applyFilters" />
        </BCol>
        <BCol v-if="can.read_all" md="2">
          <label class="form-label">{{ $t('support_tickets.filters.seller') }}</label>
          <input v-model="filters.seller" type="text" class="form-control" :placeholder="$t('support_tickets.filters.seller_placeholder')" @keyup.enter="applyFilters" />
        </BCol>
        <BCol v-if="can.assign" md="2">
          <label class="form-label">{{ $t('support_tickets.filters.assigned_to') }}</label>
          <select v-model="filters.assigned_to" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option value="unassigned">{{ $t('support_tickets.filters.unassigned') }}</option>
            <option v-for="agent in filterOptions.agents" :key="agent.id" :value="agent.id">{{ agent.name }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('support_tickets.filters.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('support_tickets.filters.all_statuses') }}</option>
            <option v-for="s in filterOptions.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('support_tickets.filters.category') }}</label>
          <select v-model="filters.category" class="form-select">
            <option value="">{{ $t('support_tickets.filters.all_categories') }}</option>
            <option v-for="c in filterOptions.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('support_tickets.filters.created_from') }}</label>
          <input v-model="filters.created_from" type="date" class="form-control" />
        </BCol>
        <BCol md="2">
          <label class="form-label">{{ $t('support_tickets.filters.created_to') }}</label>
          <input v-model="filters.created_to" type="date" class="form-control" />
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="ticket in rows"
            :key="ticket.id"
            :title="ticket.reference"
            :subtitle="ticket.subject"
            :status-label="ticket.status_label"
            :status-color="ticket.status_color"
            :rows="cardRows(ticket)"
            @open="selectedTicket = ticket"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('support_tickets.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th role="button" @click="sortBy('reference')">
                  {{ $t('support_tickets.table.reference') }} <i :class="sortIcon('reference')"></i>
                </th>
                <th v-if="can.read_all">{{ $t('support_tickets.table.seller') }}</th>
                <th>{{ $t('support_tickets.table.object') }}</th>
                <th role="button" @click="sortBy('category')">
                  {{ $t('support_tickets.table.category') }} <i :class="sortIcon('category')"></i>
                </th>
                <th>{{ $t('support_tickets.table.subject') }}</th>
                <th role="button" @click="sortBy('status')">
                  {{ $t('support_tickets.table.status') }} <i :class="sortIcon('status')"></i>
                </th>
                <th v-if="can.read_all">{{ $t('support_tickets.table.assigned') }}</th>
                <th role="button" @click="sortBy('created_at')">
                  {{ $t('support_tickets.table.created_at') }} <i :class="sortIcon('created_at')"></i>
                </th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ticket in rows" :key="ticket.id">
                <td>
                  <Link :href="route('support-tickets.show', ticket.id)" class="fw-semibold">{{ ticket.reference }}</Link>
                </td>
                <td v-if="can.read_all">
                  <div class="fw-medium">{{ ticket.creator?.name ?? "—" }}</div>
                  <div class="text-muted fs-12">{{ ticket.creator?.email }}</div>
                </td>
                <td class="text-muted fs-13">{{ objectLabel(ticket) }}</td>
                <td>
                  <span class="badge" :class="`bg-${ticket.category_color}-subtle text-${ticket.category_color}`">
                    <i :class="ticket.category_icon" class="align-bottom me-1"></i>{{ ticket.category_label }}
                  </span>
                </td>
                <td class="text-truncate" style="max-width: 180px">{{ ticket.subject }}</td>
                <td>
                  <span class="badge" :class="`bg-${ticket.status_color}-subtle text-${ticket.status_color}`">
                    <i :class="ticket.status_icon" class="align-bottom me-1"></i>{{ ticket.status_label }}
                  </span>
                </td>
                <td v-if="can.read_all">
                  <span v-if="ticket.assignee" class="fw-medium">{{ ticket.assignee.name }}</span>
                  <span v-else class="text-muted">{{ $t('support_tickets.filters.unassigned') }}</span>
                </td>
                <td class="text-muted fs-13">{{ formatDate(ticket.created_at) }}</td>
                <td class="text-end">
                  <Link :href="route('support-tickets.show', ticket.id)" class="text-primary" :title="$t('support_tickets.actions.view')">
                    <i class="ri-eye-fill fs-16"></i>
                  </Link>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td :colspan="can.read_all ? 9 : 7" class="text-center text-muted py-4">{{ $t('support_tickets.empty') }}</td>
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

    <EntityDetailSheet
      :show="selectedTicket !== null"
      :title="selectedTicket?.reference ?? ''"
      :subtitle="selectedTicket?.subject ?? ''"
      :status-label="selectedTicket?.status_label ?? ''"
      :status-color="selectedTicket?.status_color ?? 'secondary'"
      :rows="selectedTicket ? sheetRows(selectedTicket) : []"
      @close="selectedTicket = null"
    >
      <template #actions>
        <Link
          :href="route('support-tickets.show', selectedTicket?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('support_tickets.actions.view') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
