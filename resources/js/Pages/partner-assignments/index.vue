<script setup>
import { reactive, ref, computed } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  partners: { type: Object, default: () => ({ data: [], meta: {}, links: {} }) },
  admins: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? "",
});

const rows = computed(() => props.partners.data ?? []);
const meta = computed(() => props.partners.meta ?? {});

/** Row whose mobile detail sheet is open. */
const selectedPartner = ref(null);

const adminOptions = computed(() =>
  props.admins.map((a) => ({ value: a.id, label: `${a.name}${a.email ? ` (${a.email})` : ""}` }))
);

/** Drives the "Filter" badge, since the form itself is collapsed by default. */
const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== "" && value !== null).length
);

const adminNames = (partner) => (partner.users ?? []).map((user) => user.name).join(", ");

const cardRows = (partner) => [
  { label: t("partners.assignments.assigned_admins"), value: partner.users_count ?? 0, emphasis: true },
  { label: t("partners.table.ice_number"), value: partner.ice_number },
];

const reload = () => {
  const params = {};
  if (filters.search) params.search = filters.search;
  router.get(route("partner-assignments.index"), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  filters.search = "";
  reload();
};

const goToPage = (url) => {
  if (url) router.visit(url, { preserveState: true, preserveScroll: true });
};

const showModal = ref(false);
const activePartner = ref(null);
const assignForm = useForm({ user_ids: [], replace: false });

const openAssign = (partner) => {
  // The sheet renders above the modal, so it has to step aside first.
  selectedPartner.value = null;
  activePartner.value = partner;
  assignForm.user_ids = (partner.users ?? []).map((u) => u.id);
  assignForm.replace = false;
  assignForm.clearErrors();
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  activePartner.value = null;
};

const submitAssign = () => {
  if (!activePartner.value) return;

  assignForm.post(route("partner-assignments.assign", activePartner.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeModal();
      Swal.fire({
        icon: "success",
        title: t("partners.assignments.saved"),
        timer: 2000,
        showConfirmButton: false,
      });
    },
  });
};

const removeUser = (partner, user) => {
  Swal.fire({
    title: t("partners.assignments.remove_confirm_title"),
    text: t("partners.assignments.remove_confirm_text", { name: user.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: t("common.confirm_title"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;

    // The sheet may be the caller; it holds a copy of the row and would keep
    // listing the admin that is about to be removed.
    selectedPartner.value = null;

    router.delete(route("partner-assignments.remove", [partner.id, user.id]), {
      preserveScroll: true,
    });
  });
};
</script>

<template>
  <Layout>
    <PageHeader
      :title="t('partners.assignments.title')"
      :pageTitle="t('partners.assignments.list_title')"
    />

    <div class="card">
      <FilterPanel :active-count="activeFilterCount" @apply="reload" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ t("partners.assignments.list_title") }}</h5>
        </template>

        <BCol md="4">
          <label class="form-label">{{ t("common.search") }}</label>
          <div class="search-box">
            <input
              v-model="filters.search"
              type="search"
              class="form-control"
              :placeholder="t('partners.filters.search_placeholder')"
              @keyup.enter="reload"
            />
            <i class="ri-search-line search-icon"></i>
          </div>
        </BCol>
      </FilterPanel>

      <div class="card-body">
        <div class="d-lg-none">
          <EntityCard
            v-for="partner in rows"
            :key="partner.id"
            :title="partner.name"
            :subtitle="adminNames(partner)"
            :rows="cardRows(partner)"
            @open="selectedPartner = partner"
          />
          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ t("partners.empty") }}
          </p>
        </div>

        <div class="table-responsive d-none d-lg-block">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ t("partners.table.name") }}</th>
                <th>{{ t("partners.assignments.assigned_admins") }}</th>
                <th class="text-end">{{ t("common.actions") }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="3" class="text-center text-muted py-4">{{ t("partners.empty") }}</td>
              </tr>
              <tr v-for="partner in rows" :key="partner.id">
                <td>
                  <div class="fw-medium">{{ partner.name }}</div>
                  <small class="text-muted">{{ partner.users_count ?? 0 }} admin(s)</small>
                </td>
                <td>
                  <div v-if="(partner.users ?? []).length" class="d-flex flex-wrap gap-1">
                    <span
                      v-for="user in partner.users"
                      :key="user.id"
                      class="badge bg-light text-dark border d-inline-flex align-items-center gap-1"
                    >
                      {{ user.name }}
                      <button
                        v-if="can.remove"
                        type="button"
                        class="btn btn-link btn-sm p-0 text-danger"
                        @click="removeUser(partner, user)"
                      >
                        <i class="ri-close-line"></i>
                      </button>
                    </span>
                  </div>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-end">
                  <button
                    v-if="can.assign"
                    type="button"
                    class="btn btn-sm btn-primary"
                    @click="openAssign(partner)"
                  >
                    <i class="ri-user-add-line me-1"></i>
                    {{ t("partners.assignments.assign_action") }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="meta.last_page > 1" class="d-flex justify-content-end mt-3">
          <ul class="pagination mb-0">
            <li
              v-for="link in partners.links"
              :key="link.label"
              class="page-item"
              :class="{ active: link.active, disabled: !link.url }"
            >
              <button type="button" class="page-link" v-html="link.label" @click="goToPage(link.url)" />
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ t("partners.assignments.modal_title", { name: activePartner?.name }) }}
            </h5>
            <button type="button" class="btn-close" @click="closeModal"></button>
          </div>
          <form @submit.prevent="submitAssign">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">{{ t("partners.assignments.select_admins") }}</label>
                <Multiselect
                  v-model="assignForm.user_ids"
                  mode="tags"
                  :options="adminOptions"
                  :searchable="true"
                  :close-on-select="false"
                />
                <div v-if="assignForm.errors.user_ids" class="text-danger small mt-1">
                  {{ assignForm.errors.user_ids }}
                </div>
              </div>
              <div class="form-check">
                <input
                  id="replace-users"
                  v-model="assignForm.replace"
                  class="form-check-input"
                  type="checkbox"
                />
                <label class="form-check-label" for="replace-users">
                  {{ t("partners.assignments.replace_hint") }}
                </label>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="closeModal">{{ t("common.cancel") }}</button>
              <button type="submit" class="btn btn-primary" :disabled="assignForm.processing">
                {{ t("common.save") }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <EntityDetailSheet
      :show="selectedPartner !== null"
      :title="selectedPartner?.name ?? ''"
      :rows="selectedPartner ? cardRows(selectedPartner) : []"
      @close="selectedPartner = null"
    >
      <div v-if="selectedPartner" class="mt-3">
        <p class="text-muted fs-13 mb-2">{{ t("partners.assignments.assigned_admins") }}</p>
        <div v-if="(selectedPartner.users ?? []).length" class="d-flex flex-wrap gap-1">
          <span
            v-for="user in selectedPartner.users"
            :key="user.id"
            class="badge bg-light text-dark border d-inline-flex align-items-center gap-1"
          >
            {{ user.name }}
            <button
              v-if="can.remove"
              type="button"
              class="btn btn-link btn-sm p-0 text-danger"
              :aria-label="t('common.delete')"
              @click="removeUser(selectedPartner, user)"
            >
              <i class="ri-close-line"></i>
            </button>
          </span>
        </div>
        <p v-else class="text-muted fs-13 mb-0">{{ t("partners.show.no_assigned_admins") }}</p>
      </div>

      <template v-if="can.assign" #actions>
        <button
          type="button"
          class="btn btn-primary flex-fill sheet-action"
          @click="openAssign(selectedPartner)"
        >
          <i class="ri-user-add-line align-bottom me-1"></i>
          {{ t("partners.assignments.assign_action") }}
        </button>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
