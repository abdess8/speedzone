<script setup>
import { reactive, ref, computed, watch, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  partners: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  filters: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
  status: props.filters.status ?? "",
});

const perPage = ref(props.filters.per_page ?? 15);
/** Row whose mobile detail sheet is open. */
const selectedPartner = ref(null);

const rows = computed(() => props.partners.data ?? []);
const meta = computed(() => props.partners.meta ?? {});

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

const statusLabel = (partner) => (partner.is_active ? t("common.active") : t("common.inactive"));

const cardRows = (partner) => [
  { label: t("partners.table.reception_city"), value: partner.reception_city },
  { label: t("partners.table.orders"), value: partner.orders_count ?? 0 },
  {
    label: t("partners.table.sync"),
    value: `${partner.sync_frequency_minutes} ${t("partners.form.minutes")}`,
  },
];

const sheetRows = (partner) => [
  { label: t("partners.table.ice_number"), value: partner.ice_number },
  ...cardRows(partner),
];

const query = () => {
  const params = { per_page: perPage.value };
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== "" && value !== null) params[key] = value;
  });
  return params;
};

const reload = () => {
  router.get(route("partners.index"), query(), {
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

const confirmDelete = (partner) => {
  Swal.fire({
    title: t("partners.delete_confirm_title"),
    text: t("partners.delete_confirm_text", { name: partner.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_delete"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (result.isConfirmed) {
      // The sheet may be the caller; it would otherwise linger on a row that no
      // longer exists.
      selectedPartner.value = null;
      router.delete(route("partners.destroy", partner.id), { preserveScroll: true });
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
    <PageHeader :title="$t('partners.title')" :pageTitle="$t('sidebar.settings.title')" />

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('partners.list_title') }}</h5>
        </template>

        <template #actions>
          <Link v-if="can.create" :href="route('partners.create')" class="btn btn-success">
            <i class="ri-add-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('partners.new_partner') }}</span>
          </Link>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('common.search') }}</label>
          <div class="search-box">
            <input
              v-model="filters.search"
              type="text"
              class="form-control"
              :placeholder="$t('partners.filters.search_placeholder')"
              @keyup.enter="applyFilters"
            />
          </div>
        </BCol>
        <BCol md="3">
          <label class="form-label">{{ $t('common.status') }}</label>
          <select v-model="filters.status" class="form-select">
            <option value="">{{ $t('common.all') }}</option>
            <option value="active">{{ $t('common.active') }}</option>
            <option value="inactive">{{ $t('common.inactive') }}</option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <div class="d-lg-none">
          <EntityCard
            v-for="partner in rows"
            :key="partner.id"
            :title="partner.name"
            :subtitle="partner.ice_number ?? ''"
            :status-label="statusLabel(partner)"
            :status-color="partner.is_active ? 'success' : 'danger'"
            :rows="cardRows(partner)"
            @open="selectedPartner = partner"
          >
            <template #avatar>
              <img
                v-if="partner.logo_url"
                :src="partner.logo_url"
                alt=""
                class="avatar-xs rounded flex-shrink-0"
              />
            </template>
          </EntityCard>
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">{{ $t('partners.empty') }}</p>
        </div>

        <div class="table-responsive table-card d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light text-muted">
              <tr>
                <th>{{ $t('partners.table.name') }}</th>
                <th>{{ $t('partners.table.ice_number') }}</th>
                <th>{{ $t('partners.table.reception_city') }}</th>
                <th class="text-center">{{ $t('partners.table.orders') }}</th>
                <th class="text-center">{{ $t('partners.table.sync') }}</th>
                <th>{{ $t('common.status') }}</th>
                <th class="text-end">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="partner in rows" :key="partner.id">
                <td>
                  <Link :href="route('partners.show', partner.id)" class="fw-semibold d-flex align-items-center gap-2">
                    <img v-if="partner.logo_url" :src="partner.logo_url" alt="" width="24" height="24" class="rounded" />
                    {{ partner.name }}
                  </Link>
                </td>
                <td>{{ partner.ice_number || $t('common.empty_value') }}</td>
                <td>{{ partner.reception_city || $t('common.empty_value') }}</td>
                <td class="text-center">
                  <span class="badge bg-primary-subtle text-primary">{{ partner.orders_count ?? 0 }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-body border">{{ partner.sync_frequency_minutes }} {{ $t('partners.form.minutes') }}</span>
                </td>
                <td>
                  <span class="badge" :class="partner.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ partner.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </td>
                <td class="text-end">
                  <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                    <li class="list-inline-item" :title="$t('common.view')">
                      <Link :href="route('partners.show', partner.id)" class="text-primary"><i class="ri-eye-fill fs-16"></i></Link>
                    </li>
                    <li v-if="can.update" class="list-inline-item" :title="$t('common.edit')">
                      <Link :href="route('partners.edit', partner.id)" class="text-warning"><i class="ri-pencil-fill fs-16"></i></Link>
                    </li>
                    <li v-if="can.delete" class="list-inline-item" :title="$t('common.delete')">
                      <BLink class="text-danger" @click="confirmDelete(partner)"><i class="ri-delete-bin-5-fill fs-16"></i></BLink>
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="7" class="text-center text-muted py-4">{{ $t('partners.empty') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <BRow class="align-items-center mt-3 g-3">
          <BCol sm="auto">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted">{{ $t('common.rows_per_page') }}</span>
              <select v-model="perPage" class="form-select form-select-sm" style="width: auto">
                <option v-for="size in [15, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
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

    <EntityDetailSheet
      :show="selectedPartner !== null"
      :title="selectedPartner?.name ?? ''"
      :subtitle="selectedPartner?.ice_number ?? ''"
      :status-label="selectedPartner ? statusLabel(selectedPartner) : ''"
      :status-color="selectedPartner?.is_active ? 'success' : 'danger'"
      :rows="selectedPartner ? sheetRows(selectedPartner) : []"
      @close="selectedPartner = null"
    >
      <template #actions>
        <Link
          :href="route('partners.show', selectedPartner?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
        <Link
          v-if="can.update"
          :href="route('partners.edit', selectedPartner?.id)"
          class="btn btn-soft-warning sheet-action"
          :aria-label="$t('common.edit')"
        >
          <i class="ri-pencil-fill"></i>
        </Link>
        <button
          v-if="can.delete"
          type="button"
          class="btn btn-soft-danger sheet-action"
          :aria-label="$t('common.delete')"
          @click="confirmDelete(selectedPartner)"
        >
          <i class="ri-delete-bin-5-fill"></i>
        </button>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
