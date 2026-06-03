<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";

const props = defineProps({
  user: { type: Object, required: true },
});

const initials = computed(() => {
  const first = (props.user.first_name || props.user.name || "?").charAt(0);
  const last = (props.user.last_name || "").charAt(0);
  return (first + last).toUpperCase();
});

const roleBadge = computed(() => {
  const map = {
    SuperAdmin: "bg-danger-subtle text-danger",
    Admin: "bg-primary-subtle text-primary",
    Vendeur: "bg-success-subtle text-success",
    Livreur: "bg-info-subtle text-info",
    Partenaire: "bg-warning-subtle text-warning",
  };
  return map[props.user.role?.name] || "bg-secondary-subtle text-secondary";
});

const formatDate = (value) => {
  if (!value) return "-";
  return new Date(value).toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};
</script>

<template>
  <Layout>
    <PageHeader title="User Details" pageTitle="User Management" />
    <BRow>
      <BCol lg="4">
        <BCard no-body>
          <BCardBody class="text-center">
            <img
              v-if="user.photo_url"
              :src="user.photo_url"
              :alt="user.full_name"
              class="rounded-circle avatar-xl object-fit-cover mx-auto"
            />
            <div
              v-else
              class="avatar-xl rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center mx-auto fs-22 fw-medium"
            >
              {{ initials }}
            </div>
            <h5 class="mt-3 mb-1">{{ user.full_name }}</h5>
            <p class="text-muted mb-2">{{ user.email }}</p>
            <span class="badge" :class="roleBadge" v-if="user.role">{{ user.role.name }}</span>
          </BCardBody>
          <BCardBody class="border-top">
            <div class="d-flex gap-2">
              <Link :href="route('users.edit', user.id)" class="btn btn-warning w-100">
                <i class="ri-pencil-fill align-bottom me-1"></i> Edit
              </Link>
              <Link :href="route('users.index')" class="btn btn-light w-100">Back</Link>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol lg="8">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">Information</h5>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive">
              <table class="table table-borderless mb-0">
                <tbody>
                  <tr>
                    <th class="ps-0" scope="row" style="width: 35%">First Name</th>
                    <td class="text-muted">{{ user.first_name || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Last Name</th>
                    <td class="text-muted">{{ user.last_name || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Email</th>
                    <td class="text-muted">{{ user.email }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Phone Number</th>
                    <td class="text-muted">{{ user.phone_number || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">City</th>
                    <td class="text-muted">{{ user.city || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Address</th>
                    <td class="text-muted">{{ user.address || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">CIN</th>
                    <td class="text-muted">{{ user.cin || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">ICE Number</th>
                    <td class="text-muted">{{ user.ice_number || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Role</th>
                    <td class="text-muted">{{ user.role?.name || "-" }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Created At</th>
                    <td class="text-muted">{{ formatDate(user.created_at) }}</td>
                  </tr>
                  <tr>
                    <th class="ps-0" scope="row">Last Updated</th>
                    <td class="text-muted">{{ formatDate(user.updated_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>

        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">Attached Documents</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="user.attached_files_urls && user.attached_files_urls.length">
              <BRow class="g-3">
                <BCol md="6" v-for="(file, i) in user.attached_files_urls" :key="i">
                  <div class="border rounded p-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-truncate">
                      <i class="ri-file-text-line fs-20 text-primary me-2"></i>
                      <span class="text-truncate">{{ file.name }}</span>
                    </div>
                    <a :href="file.url" target="_blank" download class="btn btn-sm btn-soft-primary ms-2">
                      <i class="ri-download-2-line"></i>
                    </a>
                  </div>
                </BCol>
              </BRow>
            </div>
            <p class="text-muted mb-0" v-else>No documents attached.</p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
