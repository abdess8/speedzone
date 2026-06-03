<script>
import { Link, router } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

export default {
  components: {
    Layout,
    PageHeader,
    Link,
  },
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
    formatDate(value) {
      if (!value) return "-";
      return new Date(value).toLocaleDateString("en-GB", {
        day: "2-digit",
        month: "short",
        year: "numeric",
      });
    },
    confirmDelete(user) {
      Swal.fire({
        title: "Are you sure?",
        text: `Delete user "${user.full_name}"? This cannot be undone.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#f06548",
        cancelButtonColor: "#878a99",
        confirmButtonText: "Yes, delete it!",
      }).then((result) => {
        if (result.isConfirmed) {
          router.delete(route("users.destroy", user.id), {
            preserveScroll: true,
          });
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
    <PageHeader title="Users" pageTitle="User Management" />
    <BRow>
      <BCol lg="12">
        <BCard no-body id="usersList">
          <BCardHeader class="border-bottom-dashed">
            <BRow class="g-4 align-items-center">
              <BCol sm>
                <h5 class="card-title mb-0">User List</h5>
              </BCol>
              <BCol sm="auto">
                <Link :href="route('users.create')" class="btn btn-success add-btn">
                  <i class="ri-add-line align-bottom me-1"></i> Create User
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
                    placeholder="Search by name, email, phone, CIN..."
                    v-model="search"
                  />
                  <i class="ri-search-line search-icon"></i>
                </div>
              </BCol>
              <BCol xl="3">
                <select class="form-select" v-model="role">
                  <option value="">All Roles</option>
                  <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </BCol>
            </BRow>
          </BCardBody>

          <BCardBody>
            <div class="table-responsive table-card mb-1">
              <table class="table align-middle">
                <thead class="table-light text-muted">
                  <tr>
                    <th scope="col">Photo</th>
                    <th scope="col">Full Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Phone</th>
                    <th scope="col">City</th>
                    <th scope="col">CIN</th>
                    <th scope="col">ICE</th>
                    <th scope="col">Role</th>
                    <th scope="col">Created</th>
                    <th scope="col">Action</th>
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
                    <td>{{ user.phone_number || "-" }}</td>
                    <td>{{ user.city || "-" }}</td>
                    <td>{{ user.cin || "-" }}</td>
                    <td>{{ user.ice_number || "-" }}</td>
                    <td>
                      <span
                        v-if="user.role"
                        class="badge"
                        :class="roleBadgeClasses[user.role.name] || 'bg-secondary-subtle text-secondary'"
                      >
                        {{ user.role.name }}
                      </span>
                      <span v-else class="text-muted">-</span>
                    </td>
                    <td>{{ formatDate(user.created_at) }}</td>
                    <td>
                      <ul class="list-inline hstack gap-2 mb-0">
                        <li class="list-inline-item" title="View">
                          <Link :href="route('users.show', user.id)" class="text-primary d-inline-block">
                            <i class="ri-eye-fill fs-16"></i>
                          </Link>
                        </li>
                        <li class="list-inline-item" title="Edit">
                          <Link :href="route('users.edit', user.id)" class="text-warning d-inline-block">
                            <i class="ri-pencil-fill fs-16"></i>
                          </Link>
                        </li>
                        <li class="list-inline-item" title="Delete">
                          <BLink class="text-danger d-inline-block" @click="confirmDelete(user)">
                            <i class="ri-delete-bin-5-fill fs-16"></i>
                          </BLink>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  <tr v-if="users.data.length === 0">
                    <td colspan="10" class="text-center text-muted py-4">
                      No users found.
                    </td>
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
                    <Link
                      v-if="link.url"
                      class="page-link"
                      :href="link.url"
                      preserve-scroll
                      v-html="link.label"
                    />
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
