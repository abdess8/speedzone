<script>
import { Link, router } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

export default {
  components: { Layout, PageHeader, Link },
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
    };
  },
  computed: {
    roleBadgeClasses() {
      return {
        SuperAdmin: "bg-danger-subtle text-danger",
        Admin: "bg-primary-subtle text-primary",
        Vendeur: "bg-success-subtle text-success",
        Livreur: "bg-info-subtle text-info",
        Partenaire: "bg-warning-subtle text-warning",
      };
    },
  },
  watch: {
    search() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => this.applyFilters(), 350);
    },
    role() {
      this.applyFilters();
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
          <BCardHeader class="border-bottom-dashed">
            <BRow class="g-4 align-items-center">
              <BCol sm>
                <h5 class="card-title mb-0">{{ $t('users.list_title') }}</h5>
              </BCol>
              <BCol sm="auto">
                <Link :href="route('users.create')" class="btn btn-success add-btn">
                  <i class="ri-add-line align-bottom me-1"></i> {{ $t('users.create') }}
                </Link>
              </BCol>
            </BRow>
          </BCardHeader>

          <BCardBody class="border-bottom-dashed border-bottom">
            <BRow class="g-3">
              <BCol xl="6">
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
                <select class="form-select" v-model="role">
                  <option value="">{{ $t('users.filters.all_roles') }}</option>
                  <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleLabel(r.name) }}</option>
                </select>
              </BCol>
            </BRow>
          </BCardBody>

          <BCardBody>
            <div class="table-responsive table-card mb-1">
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
  </Layout>
</template>
