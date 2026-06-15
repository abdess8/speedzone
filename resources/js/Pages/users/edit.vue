<script setup>
import { ref, computed } from "vue";
import { Link, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import InputError from "@/Components/InputError.vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

const { t } = useI18n();

const props = defineProps({
  user: { type: Object, required: true },
  roles: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
});

const form = useForm({
  _method: "put",
  first_name: props.user.first_name || "",
  last_name: props.user.last_name || "",
  email: props.user.email || "",
  password: "",
  password_confirmation: "",
  role_id: props.user.role_id || "",
  city_id: props.user.city_id || "",
  address: props.user.address || "",
  pickup_address_1: props.user.pickup_address_1 || "",
  pickup_address_2: props.user.pickup_address_2 || "",
  phone_number: props.user.phone_number || "",
  cin: props.user.cin || "",
  ice_number: props.user.ice_number || "",
  photo: null,
  attached_files: [],
  removed_files: [],
});

const photoPreview = ref(null);

const cityOptions = computed(() =>
  props.cities.map((c) => ({ value: c.id, label: c.name }))
);

const roleLabel = (name) => {
  const key = `roles.${name}`;
  const translated = t(key);
  return translated !== key ? translated : name;
};

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

const toggleRemoveFile = (path) => {
  if (form.removed_files.includes(path)) {
    form.removed_files = form.removed_files.filter((p) => p !== path);
  } else {
    form.removed_files.push(path);
  }
};

const submit = () => {
  form.post(route("users.update", props.user.id), {
    forceFormData: true,
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('users.edit_title')" :pageTitle="$t('users.page_title')" />
    <form @submit.prevent="submit">
      <BRow>
        <BCol lg="8">
          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">{{ $t('users.form.account_info') }}</h5>
            </BCardHeader>
            <BCardBody>
              <BRow class="g-3">
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.first_name') }} <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.first_name" :class="{ 'is-invalid': form.errors.first_name }" />
                  <InputError :message="form.errors.first_name" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.last_name') }} <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="form.last_name" :class="{ 'is-invalid': form.errors.last_name }" />
                  <InputError :message="form.errors.last_name" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.email') }} <span class="text-danger">*</span></label>
                  <input type="email" class="form-control" v-model="form.email" :class="{ 'is-invalid': form.errors.email }" />
                  <InputError :message="form.errors.email" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.role') }} <span class="text-danger">*</span></label>
                  <select class="form-select" v-model="form.role_id" :class="{ 'is-invalid': form.errors.role_id }">
                    <option value="" disabled>{{ $t('users.form.select_role') }}</option>
                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ roleLabel(r.name) }}</option>
                  </select>
                  <InputError :message="form.errors.role_id" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.password') }} <span class="text-muted small">{{ $t('users.form.password_optional') }}</span></label>
                  <input type="password" class="form-control" v-model="form.password" :class="{ 'is-invalid': form.errors.password }" />
                  <InputError :message="form.errors.password" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.confirm_password') }}</label>
                  <input type="password" class="form-control" v-model="form.password_confirmation" />
                </BCol>
              </BRow>
            </BCardBody>
          </BCard>

          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">{{ $t('users.form.personal_info') }}</h5>
            </BCardHeader>
            <BCardBody>
              <BRow class="g-3">
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.phone') }}</label>
                  <input type="text" class="form-control" v-model="form.phone_number" :class="{ 'is-invalid': form.errors.phone_number }" />
                  <InputError :message="form.errors.phone_number" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.city') }} <span class="text-danger">*</span></label>
                  <Multiselect
                    v-model="form.city_id"
                    :options="cityOptions"
                    :searchable="true"
                    :close-on-select="true"
                    :placeholder="$t('users.form.select_city')"
                    :class="{ 'is-invalid': form.errors.city_id }"
                  />
                  <InputError :message="form.errors.city_id" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.cin') }}</label>
                  <input type="text" class="form-control" v-model="form.cin" :class="{ 'is-invalid': form.errors.cin }" />
                  <InputError :message="form.errors.cin" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.ice') }}</label>
                  <input type="text" class="form-control" v-model="form.ice_number" :class="{ 'is-invalid': form.errors.ice_number }" />
                  <InputError :message="form.errors.ice_number" />
                </BCol>
                <BCol md="12">
                  <label class="form-label">{{ $t('users.form.address') }}</label>
                  <textarea class="form-control" rows="3" v-model="form.address" :class="{ 'is-invalid': form.errors.address }"></textarea>
                  <InputError :message="form.errors.address" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.pickup_address_1') }}</label>
                  <textarea class="form-control" rows="2" v-model="form.pickup_address_1" :class="{ 'is-invalid': form.errors.pickup_address_1 }"></textarea>
                  <InputError :message="form.errors.pickup_address_1" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.pickup_address_2') }}</label>
                  <textarea class="form-control" rows="2" v-model="form.pickup_address_2" :class="{ 'is-invalid': form.errors.pickup_address_2 }"></textarea>
                  <InputError :message="form.errors.pickup_address_2" />
                </BCol>
              </BRow>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol lg="4">
          <BCard no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">{{ $t('users.form.profile_photo') }}</h5>
            </BCardHeader>
            <BCardBody class="text-center">
              <div class="mb-3">
                <img
                  v-if="photoPreview || user.photo_url"
                  :src="photoPreview || user.photo_url"
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
              <h5 class="card-title mb-0">{{ $t('users.form.attached_files') }}</h5>
            </BCardHeader>
            <BCardBody>
              <ul class="list-unstyled mb-3" v-if="user.attached_files_urls && user.attached_files_urls.length">
                <li
                  v-for="(file, i) in user.attached_files_urls"
                  :key="i"
                  class="d-flex align-items-center justify-content-between mb-2"
                >
                  <a :href="file.url" target="_blank" class="text-truncate me-2" :class="{ 'text-decoration-line-through text-muted': form.removed_files.includes(file.path) }">
                    <i class="ri-file-line align-middle me-1"></i>{{ file.name }}
                  </a>
                  <BButton size="sm" variant="soft-danger" type="button" @click="toggleRemoveFile(file.path)">
                    <i :class="form.removed_files.includes(file.path) ? 'ri-arrow-go-back-line' : 'ri-delete-bin-line'"></i>
                  </BButton>
                </li>
              </ul>
              <label class="form-label">{{ $t('users.form.add_more_files') }}</label>
              <input type="file" class="form-control" multiple @change="onFilesChange" :class="{ 'is-invalid': form.errors.attached_files }" />
              <InputError :message="form.errors.attached_files" />
              <ul class="list-unstyled mt-3 mb-0" v-if="form.attached_files.length">
                <li v-for="(file, i) in form.attached_files" :key="i" class="text-muted">
                  <i class="ri-file-add-line align-middle me-1"></i>{{ file.name }}
                </li>
              </ul>
            </BCardBody>
          </BCard>

          <div class="hstack gap-2 justify-content-end">
            <Link :href="route('users.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('users.update_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
