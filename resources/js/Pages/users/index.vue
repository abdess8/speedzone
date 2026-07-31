<script>
import { Link, router } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

/** Contextual colour per role, used by both the desktop badge and the mobile card. */
const ROLE_COLORS = {
  SuperAdmin: "danger",
  Admin: "primary",
  Dispatcher: "secondary",
  Seller: "success",
  Vendeur: "success",
  Driver: "info",
  Livreur: "info",
  Partner: "warning",
  Partenaire: "warning",
};

export default {
  components: { Layout, PageHeader, Link, FilterPanel, EntityCard, EntityDetailSheet },
  props: {
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      search: this.filters.search || "",
      role: this.filters.role || "",
      searchTimer: null,
      /** Row whose mobile detail sheet is open. */
      selectedUser: null,
    };
  },
  computed: {
    /** Table badge classes, derived from {@see roleColor} so the two never drift. */
    roleBadgeClasses() {
      return Object.fromEntries(
        Object.entries(ROLE_COLORS).map(([name, color]) => [
          name,
          `bg-${color}-subtle text-${color}`,
        ])
      );
    },
    /** Drives the "Filter" badge, since the form itself is collapsed by default. */
    activeFilterCount() {
      return [this.search, this.role].filter(Boolean).length;
    },
  },
  watch: {
    // Both go through one timer so clearing several filters at once — which the
    // reset button does — results in a single request.
    search() {
      this.scheduleFilters(350);
    },
    role() {
      this.scheduleFilters(0);
    },
  },
  mounted() {
    this.flashMessage();
  },
  methods: {
    roleLabel(name) {
      const key = `roles.${name}`;
      const translated = this.$t(key);
      return translated !== key ? translated : name;
    },
    roleColor(name) {
      return ROLE_COLORS[name] ?? "secondary";
    },
    scheduleFilters(delay) {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.applyFilters(), delay);
    },
    applyFilters() {
      router.get(
        route("users.index"),
        { search: this.search || undefined, role: this.role || undefined },
        { preserveState: true, replace: true, preserveScroll: true }
      );
    },
    initials(user) {
      const first = (user.first_name || user.name || "?").charAt(0);
      const last = (user.last_name || "").charAt(0);
      return (first + last).toUpperCase();
    },
    cityName(user) {
      if (!user.city) return this.$t("common.empty_value_short");
      return typeof user.city === "object" ? user.city.name : user.city;
    },
    resetFilters() {
      this.search = "";
      this.role = "";
    },
    /** Detail lines shared by the mobile card and its sheet. */
    cardRows(user) {
      return [
        { label: this.$t("users.table.phone"), value: user.phone_number },
        { label: this.$t("sidebar.cities"), value: this.cityName(user) },
        { label: this.$t("orders.table.created"), value: this.formatDate(user.created_at) },
      ];
    },
    sheetRows(user) {
      return [
        { label: this.$t("users.table.email"), value: user.email },
        ...this.cardRows(user),
        { label: this.$t("users.table.cin"), value: user.cin },
        { label: this.$t("users.table.ice"), value: user.ice_number },
      ];
    },
    formatDate(value) {
      if (!value) return this.$t("common.empty_value_short");
      return new Date(value).toLocaleDateString(this.$page.props.locale === "en" ? "en-GB" : "fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });
    },
    confirmDelete(user) {
      Swal.fire({
        title: this.$t("common.confirm_title"),
        text: this.$t("users.delete_confirm_text", { name: user.full_name }),
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#f06548",
        cancelButtonColor: "#878a99",
        confirmButtonText: this.$t("common.confirm_delete"),
        cancelButtonText: this.$t("common.cancel"),
      }).then((result) => {
        if (result.isConfirmed) {
          // The sheet may be the caller; it would otherwise linger on a row
          // that no longer exists.
          this.selectedUser = null;
          router.delete(route("users.destroy", user.id), { preserveScroll: true });
        }
      });
    },
    flashMessage() {
      const success = this.$page.props.flash?.success;
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
    },
  },
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('users.title')" :pageTitle="$t('users.page_title')" />
    <BRow>
      <BCol lg="12">
        <BCard no-body id="usersList">
          <FilterPanel :active-count="activeFilterCount" @apply="applyFilters" @reset="resetFilters">
            <template #title>
              <h5 class="card-title mb-0">{{ $t('users.list_title') }}</h5>
            </template>

            <template #actions>
              <Link :href="route('users.create')" class="btn btn-success add-btn">
                <i class="ri-add-line align-bottom"></i>
                <span class="d-none d-sm-inline ms-1">{{ $t('users.create') }}</span>
              </Link>
            </template>

            <BCol xl="6">
              <label class="form-label">{{ $t('users.table.full_name') }}</label>
              <div class="search-box">
                <input
                  type="text"
                  class="form-control search"
                  :placeholder="$t('users.filters.search_placeholder')"
                  v-model="search"
                />
                <i class="ri-search-line search-icon"></i>
              </div>
            </BCol>
            <BCol xl="3">
              <label class="form-label">{{ $t('users.table.role') }}</label>
              <select class="form-select" v-model="role">
                <option value="">{{ $t('users.filters.all_roles') }}</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleLabel(r.name) }}</option>
              </select>
            </BCol>
          </FilterPanel>

          <BCardBody>
            <div class="d-lg-none">
              <EntityCard
                v-for="user in users.data"
                :key="user.id"
                :title="user.full_name"
                :subtitle="user.email"
                :status-label="user.role ? roleLabel(user.role.name) : ''"
                :status-color="user.role ? roleColor(user.role.name) : 'secondary'"
                :rows="cardRows(user)"
                @open="selectedUser = user"
              >
                <template #avatar>
                  <img
                    v-if="user.photo_url"
                    :src="user.photo_url"
                    :alt="user.full_name"
                    class="avatar-xs rounded-circle object-fit-cover flex-shrink-0"
                  />
                  <div
                    v-else
                    class="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-medium flex-shrink-0"
                  >
                    {{ initials(user) }}
                  </div>
                </template>
              </EntityCard>
              <p v-if="users.data.length === 0" class="text-center text-muted py-4 mb-0">
                {{ $t('users.empty') }}
              </p>
            </div>

            <div class="table-responsive table-card mb-1 d-none d-lg-block">
              <table class="table align-middle">
                <thead class="table-light text-muted">
                  <tr>
                    <th scope="col">{{ $t('users.table.photo') }}</th>
                    <th scope="col">{{ $t('users.table.full_name') }}</th>
                    <th scope="col">{{ $t('users.table.email') }}</th>
                    <th scope="col">{{ $t('users.table.phone') }}</th>
                    <th scope="col">{{ $t('sidebar.cities') }}</th>
                    <th scope="col">{{ $t('users.table.cin') }}</th>
                    <th scope="col">{{ $t('users.table.ice') }}</th>
                    <th scope="col">{{ $t('users.table.role') }}</th>
                    <th scope="col">{{ $t('orders.table.created') }}</th>
                    <th scope="col">{{ $t('common.action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="user in users.data" :key="user.id">
                    <td>
                      <img
                        v-if="user.photo_url"
                        :src="user.photo_url"
                        :alt="user.full_name"
                        class="avatar-xs rounded-circle object-fit-cover"
                      />
                      <div
                        v-else
                        class="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-medium"
                      >
                        {{ initials(user) }}
                      </div>
                    </td>
                    <td>
                      <Link :href="route('users.show', user.id)" class="fw-medium link-primary">
                        {{ user.full_name }}
                      </Link>
                    </td>
                    <td>{{ user.email }}</td>
                    <td>{{ user.phone_number || $t('common.empty_value_short') }}</td>
                    <td>{{ cityName(user) }}</td>
                    <td>{{ user.cin || $t('common.empty_value_short') }}</td>
                    <td>{{ user.ice_number || $t('common.empty_value_short') }}</td>
                    <td>
                      <span
                        v-if="user.role"
                        class="badge"
                        :class="roleBadgeClasses[user.role.name] || 'bg-secondary-subtle text-secondary'"
                      >
                        {{ roleLabel(user.role.name) }}
                      </span>
                      <span v-else class="text-muted">{{ $t('common.empty_value_short') }}</span>
                    </td>
                    <td>{{ formatDate(user.created_at) }}</td>
                    <td>
                      <ul class="list-inline hstack gap-2 mb-0">
                        <li class="list-inline-item" :title="$t('common.view')">
                          <Link :href="route('users.show', user.id)" class="text-primary d-inline-block">
                            <i class="ri-eye-fill fs-16"></i>
                          </Link>
                        </li>
                        <li class="list-inline-item" :title="$t('common.edit')">
                          <Link :href="route('users.edit', user.id)" class="text-warning d-inline-block">
                            <i class="ri-pencil-fill fs-16"></i>
                          </Link>
                        </li>
                        <li class="list-inline-item" :title="$t('common.delete')">
                          <BLink class="text-danger d-inline-block" @click="confirmDelete(user)">
                            <i class="ri-delete-bin-5-fill fs-16"></i>
                          </BLink>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  <tr v-if="users.data.length === 0">
                    <td colspan="10" class="text-center text-muted py-4">{{ $t('users.empty') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end mt-3" v-if="users.last_page > 1">
              <div class="pagination-wrap hstack gap-2">
                <ul class="pagination listjs-pagination mb-0">
                  <li
                    v-for="(link, index) in users.links"
                    :key="index"
                    class="page-item"
                    :class="{ active: link.active, disabled: !link.url }"
                  >
                    <Link v-if="link.url" class="page-link" :href="link.url" preserve-scroll v-html="link.label" />
                    <span v-else class="page-link" v-html="link.label"></span>
                  </li>
                </ul>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <EntityDetailSheet
      :show="selectedUser !== null"
      :title="selectedUser?.full_name ?? ''"
      :subtitle="selectedUser?.role ? roleLabel(selectedUser.role.name) : ''"
      :rows="selectedUser ? sheetRows(selectedUser) : []"
      @close="selectedUser = null"
    >
      <template #actions>
        <Link
          :href="route('users.show', selectedUser?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-eye-line align-bottom me-1"></i> {{ $t('common.view') }}
        </Link>
        <Link
          :href="route('users.edit', selectedUser?.id)"
          class="btn btn-soft-warning sheet-action"
          :aria-label="$t('common.edit')"
        >
          <i class="ri-pencil-fill"></i>
        </Link>
        <button
          type="button"
          class="btn btn-soft-danger sheet-action"
          :aria-label="$t('common.delete')"
          @click="confirmDelete(selectedUser)"
        >
          <i class="ri-delete-bin-5-fill"></i>
        </button>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
