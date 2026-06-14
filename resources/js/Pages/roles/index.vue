<script>
import { Link, router } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

export default {
  components: { Layout, PageHeader, Link },
  props: {
    roles: { type: Array, default: () => [] },
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
    confirmDelete(role) {
      Swal.fire({
        title: this.$t("common.confirm_title"),
        text: this.$t("roles.delete_confirm_text", { name: role.name }),
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#f06548",
        cancelButtonColor: "#878a99",
        confirmButtonText: this.$t("common.confirm_delete"),
        cancelButtonText: this.$t("common.cancel"),
      }).then((result) => {
        if (result.isConfirmed) {
          router.delete(route("roles.destroy", role.id), { preserveScroll: true });
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
    <PageHeader :title="$t('roles.title')" :pageTitle="$t('roles.page_title')" />
    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="border-bottom-dashed">
            <BRow class="g-4 align-items-center">
              <BCol sm>
                <h5 class="card-title mb-0">{{ $t('roles.list_title') }}</h5>
              </BCol>
              <BCol sm="auto">
                <Link :href="route('roles.create')" class="btn btn-success add-btn">
                  <i class="ri-add-line align-bottom me-1"></i> {{ $t('roles.create') }}
                </Link>
              </BCol>
            </BRow>
          </BCardHeader>

          <BCardBody>
            <div class="table-responsive table-card mb-1">
              <table class="table align-middle">
                <thead class="table-light text-muted">
                  <tr>
                    <th scope="col">{{ $t('roles.table.role') }}</th>
                    <th scope="col">{{ $t('roles.table.permissions') }}</th>
                    <th scope="col">{{ $t('roles.table.users') }}</th>
                    <th scope="col">{{ $t('common.action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="role in roles" :key="role.id">
                    <td>
                      <span class="fw-medium">{{ roleLabel(role.name) }}</span>
                    </td>
                    <td>
                      <span class="badge bg-primary-subtle text-primary">
                        {{ $t('roles.table.permissions_count', { count: role.permissions_count }) }}
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-secondary-subtle text-secondary">
                        {{ $t('roles.table.users_count', { count: role.users_count }) }}
                      </span>
                    </td>
                    <td>
                      <ul class="list-inline hstack gap-2 mb-0">
                        <li class="list-inline-item" :title="$t('common.edit')">
                          <Link :href="route('roles.edit', role.id)" class="text-warning d-inline-block">
                            <i class="ri-pencil-fill fs-16"></i>
                          </Link>
                        </li>
                        <li class="list-inline-item" :title="$t('common.delete')">
                          <BLink class="text-danger d-inline-block" @click="confirmDelete(role)">
                            <i class="ri-delete-bin-5-fill fs-16"></i>
                          </BLink>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  <tr v-if="roles.length === 0">
                    <td colspan="4" class="text-center text-muted py-4">{{ $t('roles.empty') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
