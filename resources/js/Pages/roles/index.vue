<script>
import { Link, router } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import EntityCard from "@/Components/EntityCard.vue";
import EntityDetailSheet from "@/Components/EntityDetailSheet.vue";
import Swal from "sweetalert2";

export default {
  components: { Layout, PageHeader, Link, EntityCard, EntityDetailSheet },
  props: {
    roles: { type: Array, default: () => [] },
  },
  data() {
    return {
      /** Row whose mobile detail sheet is open. */
      selectedRole: null,
    };
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
    /** Shown as a subtitle only when translated, so it never repeats the title. */
    rawName(role) {
      return this.roleLabel(role.name) === role.name ? "" : role.name;
    },
    /** Detail lines shared by the mobile card and its sheet. */
    cardRows(role) {
      return [
        { label: this.$t("roles.table.permissions"), value: role.permissions_count },
        { label: this.$t("roles.table.users"), value: role.users_count },
      ];
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
          // The sheet may be the caller; it would otherwise linger on a row
          // that no longer exists.
          this.selectedRole = null;
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
                <div class="hstack gap-2 justify-content-end">
                  <Link :href="route('roles.guides.edit')" class="btn btn-light">
                    <i class="ri-graduation-cap-line align-bottom me-1"></i>
                    {{ $t('guides.access.title') }}
                  </Link>
                  <Link :href="route('roles.create')" class="btn btn-success add-btn">
                    <i class="ri-add-line align-bottom me-1"></i> {{ $t('roles.create') }}
                  </Link>
                </div>
              </BCol>
            </BRow>
          </BCardHeader>

          <BCardBody>
            <div class="d-lg-none">
              <EntityCard
                v-for="role in roles"
                :key="role.id"
                :title="roleLabel(role.name)"
                :subtitle="rawName(role)"
                :rows="cardRows(role)"
                @open="selectedRole = role"
              />
              <p v-if="roles.length === 0" class="text-center text-muted py-4 mb-0">
                {{ $t('roles.empty') }}
              </p>
            </div>

            <div class="table-responsive table-card mb-1 d-none d-lg-block">
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

    <EntityDetailSheet
      :show="selectedRole !== null"
      :title="selectedRole ? roleLabel(selectedRole.name) : ''"
      :subtitle="selectedRole ? rawName(selectedRole) : ''"
      :rows="selectedRole ? cardRows(selectedRole) : []"
      @close="selectedRole = null"
    >
      <template #actions>
        <Link
          :href="route('roles.edit', selectedRole?.id)"
          class="btn btn-primary flex-fill sheet-action"
        >
          <i class="ri-pencil-fill align-bottom me-1"></i> {{ $t('common.edit') }}
        </Link>
        <button
          type="button"
          class="btn btn-soft-danger sheet-action"
          :aria-label="$t('common.delete')"
          @click="confirmDelete(selectedRole)"
        >
          <i class="ri-delete-bin-5-fill"></i>
        </button>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
