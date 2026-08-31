<script>
import { Link, router } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import FilterPanel from "@/Components/FilterPanel.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import SortableTh from "@/Components/SortableTh.vue";
import { roleLabel } from "@/utils/roleLabel";
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
  components: { Layout, PageHeader, Link, FilterPanel, EntityCard, EntityDetailSheet, SortableTh },
  props: {
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      search: this.filters.search || "",
      role: this.filters.role || "",
      // The server sorts; these only hold what it echoed back, so the header
      // knows which arrow to draw. Same contract as `useTableSort`, spelled out
      // here because this page is on the Options API.
      sort: this.filters.sort || "",
      direction: this.filters.direction || "desc",
      searchTimer: null,
      /** Row whose mobile detail sheet is open. */
      selectedUser: null,
      /**
       * Ids whose photo failed to load. A stored path is no proof the file is
       * still on disk, and tracking failures per user lets one dead upload fall
       * back to initials without affecting the other rows.
       */
      failedPhotos: new Set(),
      /** Vendor accounts whose team is currently unfolded. */
      expandedTeams: new Set(),
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
    // A search matches team members too, and the owner's row is the only place
    // they appear — leaving it folded would answer the search with a name the
    // reader never typed. Unfolding is additive, so a row collapsed by hand
    // stays collapsed until the next set of results arrives.
    users: {
      immediate: true,
      handler(page) {
        if (!this.search) return;

        page.data.forEach((user) => {
          if (this.hasTeam(user)) this.expandedTeams.add(user.id);
        });
      },
    },
  },
  mounted() {
    this.flashMessage();
  },
  methods: {
    roleLabel(role) {
      return roleLabel(role, this.$t);
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
        {
          search: this.search || undefined,
          role: this.role || undefined,
          sort: this.sort,
          direction: this.direction,
        },
        { preserveState: true, replace: true, preserveScroll: true }
      );
    },
    /** Clicking the active column flips it; another starts it ascending. */
    sortBy(field) {
      if (this.sort === field) {
        this.direction = this.direction === "asc" ? "desc" : "asc";
      } else {
        this.sort = field;
        this.direction = "asc";
      }

      this.applyFilters();
    },
    initials(user) {
      const first = (user.first_name || user.name || "?").charAt(0);
      const last = (user.last_name || "").charAt(0);
      return (first + last).toUpperCase();
    },
    /** Team members arrive flattened, with only the name already assembled. */
    memberInitials(member) {
      const [first = "?", last = ""] = (member.full_name || "?").split(/\s+/);
      return (first.charAt(0) + last.charAt(0)).toUpperCase();
    },
    hasPhoto(user) {
      return Boolean(user.photo_url) && !this.failedPhotos.has(user.id);
    },
    hasTeam(user) {
      return Boolean(user.team_members?.length);
    },
    isTeamOpen(user) {
      return this.expandedTeams.has(user.id);
    },
    toggleTeam(user) {
      if (this.expandedTeams.has(user.id)) {
        this.expandedTeams.delete(user.id);
      } else {
        this.expandedTeams.add(user.id);
      }
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
        { label: this.$t("users.table.city"), value: this.cityName(user) },
        { label: this.$t("orders.table.created"), value: this.formatDate(user.created_at) },
      ];
    },
    /**
     * The same three lines for a team member. They go straight to the detail
     * page instead of a sheet: the flattened row holds less than the sheet
     * would show, so the sheet would be a step that adds nothing.
     */
    memberCardRows(member) {
      return [
        { label: this.$t("users.table.phone"), value: member.phone_number },
        { label: this.$t("users.table.city"), value: member.city },
        { label: this.$t("orders.table.created"), value: this.formatDate(member.created_at) },
      ];
    },
    openUser(user) {
      router.get(route("users.show", user.id));
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
                <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleLabel(r) }}</option>
              </select>
            </BCol>
          </FilterPanel>

          <BCardBody>
            <div class="d-lg-none">
              <template v-for="user in users.data" :key="user.id">
                <EntityCard
                  :title="user.full_name"
                  :subtitle="user.email"
                  :status-label="user.role ? roleLabel(user.role) : ''"
                  :status-color="user.role ? roleColor(user.role.name) : 'secondary'"
                  :rows="cardRows(user)"
                  @open="selectedUser = user"
                >
                  <template #avatar>
                    <img
                      v-if="hasPhoto(user)"
                      :src="user.photo_url"
                      :alt="user.full_name"
                      class="avatar-xs rounded-circle object-fit-cover flex-shrink-0"
                      @error="failedPhotos.add(user.id)"
                    />
                    <div
                      v-else
                      class="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-medium flex-shrink-0"
                    >
                      {{ initials(user) }}
                    </div>
                  </template>

                  <template v-if="hasTeam(user)" #actions>
                    <button
                      type="button"
                      class="btn btn-sm btn-soft-primary w-100"
                      :aria-expanded="isTeamOpen(user)"
                      @click="toggleTeam(user)"
                    >
                      <i
                        class="ri-arrow-down-s-line align-bottom me-1 user-team-caret"
                        :class="{ 'is-open': isTeamOpen(user) }"
                      ></i>
                      {{ $t('users.team.sub_users') }} ({{ user.team_members.length }})
                    </button>
                  </template>
                </EntityCard>

                <div v-if="hasTeam(user) && isTeamOpen(user)" class="user-subcards">
                  <EntityCard
                    v-for="member in user.team_members"
                    :key="`${user.id}-${member.id}`"
                    :title="member.full_name"
                    :subtitle="member.email"
                    :status-label="member.role_label"
                    status-color="secondary"
                    :rows="memberCardRows(member)"
                    @open="openUser(member)"
                  >
                    <template #avatar>
                      <img
                        v-if="member.photo_url && !failedPhotos.has(member.id)"
                        :src="member.photo_url"
                        :alt="member.full_name"
                        class="avatar-xs rounded-circle object-fit-cover flex-shrink-0"
                        @error="failedPhotos.add(member.id)"
                      />
                      <div
                        v-else
                        class="avatar-xs rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center fw-medium flex-shrink-0"
                      >
                        {{ memberInitials(member) }}
                      </div>
                    </template>
                  </EntityCard>
                </div>
              </template>

              <p v-if="users.data.length === 0" class="text-center text-muted py-4 mb-0">
                {{ $t('users.empty') }}
              </p>
            </div>

            <div class="table-responsive table-card mb-1 d-none d-lg-block">
              <table class="table align-middle">
                <thead class="table-light text-muted">
                  <tr>
                    <th scope="col" class="user-team-col">
                      <span class="visually-hidden">{{ $t('users.team.sub_users') }}</span>
                    </th>
                    <th scope="col">{{ $t('users.table.photo') }}</th>
                    <SortableTh field="full_name" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.full_name') }}
                    </SortableTh>
                    <SortableTh field="email" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.email') }}
                    </SortableTh>
                    <SortableTh field="phone_number" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.phone') }}
                    </SortableTh>
                    <SortableTh field="city" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.city') }}
                    </SortableTh>
                    <SortableTh field="cin" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.cin') }}
                    </SortableTh>
                    <SortableTh field="ice_number" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.ice') }}
                    </SortableTh>
                    <SortableTh field="role" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('users.table.role') }}
                    </SortableTh>
                    <SortableTh field="created_at" :sort="sort" :direction="direction" @sort="sortBy">
                      {{ $t('orders.table.created') }}
                    </SortableTh>
                    <th scope="col">{{ $t('common.action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="user in users.data" :key="user.id">
                    <tr :class="{ 'user-row-open': hasTeam(user) && isTeamOpen(user) }">
                      <td class="user-team-col pe-0">
                        <button
                          v-if="hasTeam(user)"
                          type="button"
                          class="user-team-toggle"
                          :class="{ 'is-open': isTeamOpen(user) }"
                          :aria-expanded="isTeamOpen(user)"
                          :title="$t('users.team.toggle', { name: user.full_name })"
                          :aria-label="$t('users.team.toggle', { name: user.full_name })"
                          @click="toggleTeam(user)"
                        >
                          <i class="ri-arrow-right-s-line"></i>
                        </button>
                      </td>
                      <td>
                        <img
                          v-if="hasPhoto(user)"
                          :src="user.photo_url"
                          :alt="user.full_name"
                          class="avatar-xs rounded-circle object-fit-cover"
                          @error="failedPhotos.add(user.id)"
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
                        <span
                          v-if="hasTeam(user)"
                          class="badge bg-primary-subtle text-primary ms-1"
                          :title="$t('users.team.sub_users')"
                        >
                          <i class="ri-team-line align-bottom me-1"></i>{{ user.team_members.length }}
                        </span>
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
                          {{ roleLabel(user.role) }}
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

                    <template v-if="hasTeam(user) && isTeamOpen(user)">
                      <tr
                        v-for="member in user.team_members"
                        :key="`${user.id}-${member.id}`"
                        class="user-subrow"
                      >
                        <td class="user-team-col"></td>
                        <td>
                          <img
                            v-if="member.photo_url && !failedPhotos.has(member.id)"
                            :src="member.photo_url"
                            :alt="member.full_name"
                            class="avatar-xs rounded-circle object-fit-cover"
                            @error="failedPhotos.add(member.id)"
                          />
                          <div
                            v-else
                            class="avatar-xs rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center fw-medium"
                          >
                            {{ memberInitials(member) }}
                          </div>
                        </td>
                        <td>
                          <i class="ri-corner-down-right-line text-muted me-1 align-middle"></i>
                          <Link :href="route('users.show', member.id)" class="fw-medium link-primary">
                            {{ member.full_name }}
                          </Link>
                          <span
                            v-if="member.status"
                            class="badge ms-1"
                            :class="member.status_class"
                          >
                            {{ $t(`user_statuses.${member.status}`) }}
                          </span>
                        </td>
                        <td>{{ member.email }}</td>
                        <td>{{ member.phone_number || $t('common.empty_value_short') }}</td>
                        <td>{{ member.city || $t('common.empty_value_short') }}</td>
                        <td>{{ member.cin || $t('common.empty_value_short') }}</td>
                        <td>{{ $t('common.empty_value_short') }}</td>
                        <td>
                          <span v-if="member.role_label" class="badge bg-secondary-subtle text-secondary">
                            {{ member.role_label }}
                          </span>
                          <span v-else class="text-muted">{{ $t('common.empty_value_short') }}</span>
                        </td>
                        <td>{{ formatDate(member.created_at) }}</td>
                        <td>
                          <ul class="list-inline hstack gap-2 mb-0">
                            <li class="list-inline-item" :title="$t('common.view')">
                              <Link :href="route('users.show', member.id)" class="text-primary d-inline-block">
                                <i class="ri-eye-fill fs-16"></i>
                              </Link>
                            </li>
                            <li class="list-inline-item" :title="$t('common.edit')">
                              <Link :href="route('users.edit', member.id)" class="text-warning d-inline-block">
                                <i class="ri-pencil-fill fs-16"></i>
                              </Link>
                            </li>
                          </ul>
                        </td>
                      </tr>
                    </template>
                  </template>

                  <tr v-if="users.data.length === 0">
                    <td colspan="11" class="text-center text-muted py-4">{{ $t('users.empty') }}</td>
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
      :subtitle="selectedUser?.role ? roleLabel(selectedUser.role) : ''"
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

<style scoped>
.user-team-col {
  width: 2.25rem;
}

.user-team-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  color: var(--vz-secondary-color);
  background: none;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.35rem;
  transition: transform 0.2s ease, color 0.15s ease, border-color 0.15s ease;
}

.user-team-toggle:hover,
.user-team-toggle:focus-visible {
  color: var(--vz-primary);
  border-color: var(--vz-primary);
}

/* Turning the chevron rather than swapping the glyph keeps the open and closed
   states on one element, so the row does not reflow as the team unfolds. */
.user-team-toggle.is-open,
.user-team-caret.is-open {
  transform: rotate(90deg);
}

.user-team-caret {
  display: inline-block;
  transition: transform 0.2s ease;
}

.user-team-caret.is-open {
  transform: rotate(180deg);
}

/* The team is a continuation of the row above it, so the rail runs down the
   left of the block and the surface steps back towards the page. */
.user-row-open > td {
  border-bottom-color: transparent;
}

.user-subrow > td {
  background-color: var(--vz-light);
}

.user-subrow > td:first-child {
  box-shadow: inset 2px 0 0 0 var(--vz-primary);
}

.user-subcards {
  margin-left: 1rem;
  padding-left: 0.75rem;
  border-left: 2px solid var(--vz-primary);
}
</style>
