<script setup>
import { ref } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import InputError from "@/Components/InputError.vue";

defineProps({
  roles: { type: Array, default: () => [] },
});

const form = useForm({
  first_name: "",
  last_name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role_id: "",
  city: "",
  address: "",
  phone_number: "",
  cin: "",
  ice_number: "",
  photo: null,
  attached_files: [],
});

const photoPreview = ref(null);

const onPhotoChange = (event) => {
  const file = event.target.files[0];
  form.photo = file || null;
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => (photoPreview.value = e.target.result);
    reader.readAsDataURL(file);
  } else {
    photoPreview.value = null;
  }
};

const onFilesChange = (event) => {
  form.attached_files = Array.from(event.target.files);
};

const submit = () => {
  form.post(route("users.store"), {
    forceFormData: true,
  });
};
</script>

<template>
  <Layout>
    <PageHeader title="Create User" pageTitle="User Management" />
    <form @submit.prevent="submit">
      <BRow>
        <BCol lg="8">
          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">Account Information</h5>
            </BCardHeader>
            <BCardBody>
              <BRow class="g-3">
                <BCol md="6">
                  <label class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.first_name" :class="{ 'is-invalid': form.errors.first_name }" />
                  <InputError :message="form.errors.first_name" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.last_name" :class="{ 'is-invalid': form.errors.last_name }" />
                  <InputError :message="form.errors.last_name" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">Email <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" v-model="form.email" :class="{ 'is-invalid': form.errors.email }" />
                  <InputError :message="form.errors.email" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">Role <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="form.role_id" :class="{ 'is-invalid': form.errors.role_id }">
                    <option value="" disabled>Select a role</option>
                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                  </select>
                  <InputError :message="form.errors.role_id" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" v-model="form.password" :class="{ 'is-invalid': form.errors.password }" />
                  <InputError :message="form.errors.password" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" v-model="form.password_confirmation" />
                </BCol>
              </BRow>
            </BCardBody>
          </BCard>

          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">Personal Information</h5>
            </BCardHeader>
            <BCardBody>
              <BRow class="g-3">
                <BCol md="6">
                  <label class="form-label">Phone Number</label>
                  <input type="text" class="form-control" v-model="form.phone_number" :class="{ 'is-invalid': form.errors.phone_number }" />
                  <InputError :message="form.errors.phone_number" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">City</label>
                  <input type="text" class="form-control" v-model="form.city" :class="{ 'is-invalid': form.errors.city }" />
                  <InputError :message="form.errors.city" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">CIN</label>
                  <input type="text" class="form-control" v-model="form.cin" :class="{ 'is-invalid': form.errors.cin }" />
                  <InputError :message="form.errors.cin" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">ICE Number</label>
                  <input type="text" class="form-control" v-model="form.ice_number" :class="{ 'is-invalid': form.errors.ice_number }" />
                  <InputError :message="form.errors.ice_number" />
                </BCol>
                <BCol md="12">
                  <label class="form-label">Address</label>
                  <textarea class="form-control" rows="3" v-model="form.address" :class="{ 'is-invalid': form.errors.address }"></textarea>
                  <InputError :message="form.errors.address" />
                </BCol>
              </BRow>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol lg="4">
          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">Profile Photo</h5>
            </BCardHeader>
            <BCardBody class="text-center">
              <div class="mb-3">
                <img
                  v-if="photoPreview"
                  :src="photoPreview"
                  class="rounded-circle avatar-lg object-fit-cover"
                  alt="preview"
                />
                <div
                  v-else
                  class="avatar-lg rounded-circle bg-light text-muted d-flex align-items-center justify-content-center mx-auto"
                >
                  <i class="ri-user-3-line fs-24"></i>
                </div>
              </div>
              <input type="file" class="form-control" accept="image/*" @change="onPhotoChange" :class="{ 'is-invalid': form.errors.photo }" />
              <InputError :message="form.errors.photo" />
            </BCardBody>
          </BCard>

          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">Attached Files</h5>
            </BCardHeader>
            <BCardBody>
              <input type="file" class="form-control" multiple @change="onFilesChange" :class="{ 'is-invalid': form.errors.attached_files }" />
              <InputError :message="form.errors.attached_files" />
              <ul class="list-unstyled mt-3 mb-0" v-if="form.attached_files.length">
                <li v-for="(file, i) in form.attached_files" :key="i" class="text-muted">
                  <i class="ri-file-line align-middle me-1"></i>{{ file.name }}
                </li>
              </ul>
            </BCardBody>
          </BCard>

          <div class="hstack gap-2 justify-content-end">
            <Link :href="route('users.index')" class="btn btn-light">Cancel</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> Create User
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
