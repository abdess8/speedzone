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

import { formatMoney as money } from "@/common/formatMoney";

const props = defineProps({
  roles: { type: Array, default: () => [] },
  cities: { type: Array, default: () => [] },
  sectors: { type: Array, default: () => [] },
  billingFrequencies: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const form = useForm({
  first_name: "",
  last_name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role_id: "",
  city_id: "",
  address: "",
  pickup_address_1: "",
  pickup_address_2: "",
  phone_number: "",
  cin: "",
  ice_number: "",
  photo: null,
  attached_files: [],
  billing_enabled: false,
  billing_frequency: "monthly",
  next_billing_date: "",
  payment_method: "",
  bank_name: "",
  rib: "",
  rib_attachment: null,
  cin_front_attachment: null,
  cin_back_attachment: null,
  sector_ids: [],
});

const driverRoleId = computed(() => props.roles.find((r) => r.name === "Driver")?.id ?? null);
const isDriverRole = computed(() => form.role_id === driverRoleId.value);

const groupedSectorOptions = computed(() => {
  const byCity = {};
  props.sectors.forEach((s) => {
    byCity[s.city_name] = byCity[s.city_name] || [];
    byCity[s.city_name].push({
      value: s.id,
      label: t("users.form.sector_option_label", { name: s.name, price: money(s.delivery_price) }),
    });
  });
  return Object.entries(byCity).map(([label, options]) => ({ label, options }));
});

const onBillingFileChange = (field, event) => {
  form[field] = event.target.files[0] || null;
};

const photoPreview = ref(null);

const cityOptions = computed(() =>
  (props.cities ?? []).map((c) => ({ value: c.id, label: c.name }))
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

const submit = () => {
  form.transform((data) => ({
    ...data,
    city_id: isDriverRole.value ? null : data.city_id,
    sector_ids: isDriverRole.value ? data.sector_ids : [],
  })).post(route("users.store"), {
    forceFormData: true,
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('users.create_title')" :pageTitle="$t('users.page_title')" />
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
                  <label class="form-label">{{ $t('users.form.password') }} <span class="text-danger">*</span></label>
                  <input type="password" class="form-control" v-model="form.password" :class="{ 'is-invalid': form.errors.password }" />
                  <InputError :message="form.errors.password" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.confirm_password') }} <span class="text-danger">*</span></label>
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
                <BCol md="6" v-if="!isDriverRole">
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

          <BCard v-if="isDriverRole" no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">{{ $t('users.form.sectors_info') }}</h5>
            </BCardHeader>
            <BCardBody>
              <p class="text-muted mb-3">{{ $t('users.form.sectors_description') }}</p>
              <label class="form-label">{{ $t('users.form.sectors') }} <span class="text-danger">*</span></label>
              <Multiselect
                v-model="form.sector_ids"
                mode="tags"
                :options="groupedSectorOptions"
                :groups="true"
                :searchable="true"
                :close-on-select="false"
                :placeholder="$t('users.form.sectors_placeholder')"
                :class="{ 'is-invalid': form.errors.sector_ids }"
              />
              <InputError :message="form.errors.sector_ids" />
            </BCardBody>
          </BCard>

          <BCard v-if="!isDriverRole" no-body>
            <BCardHeader>
              <h5 class="card-title mb-0">{{ $t('users.form.billing_info') }}</h5>
            </BCardHeader>
            <BCardBody>
              <BRow class="g-3">
                <BCol md="12">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="billing_enabled_create" v-model="form.billing_enabled" />
                    <label class="form-check-label" for="billing_enabled_create">{{ $t('users.form.billing_enabled') }}</label>
                  </div>
                </BCol>
                <BCol md="4">
                  <label class="form-label">{{ $t('users.form.billing_frequency') }}</label>
                  <select class="form-select" v-model="form.billing_frequency" :class="{ 'is-invalid': form.errors.billing_frequency }">
                    <option v-for="f in billingFrequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                  </select>
                  <InputError :message="form.errors.billing_frequency" />
                </BCol>
                <BCol md="4">
                  <label class="form-label">{{ $t('users.form.next_billing_date') }}</label>
                  <input type="date" class="form-control" v-model="form.next_billing_date" :class="{ 'is-invalid': form.errors.next_billing_date }" />
                  <InputError :message="form.errors.next_billing_date" />
                </BCol>
                <BCol md="4">
                  <label class="form-label">{{ $t('users.form.payment_method') }}</label>
                  <select class="form-select" v-model="form.payment_method" :class="{ 'is-invalid': form.errors.payment_method }">
                    <option value="">{{ $t('users.form.select_payment_method') }}</option>
                    <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                  </select>
                  <InputError :message="form.errors.payment_method" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.bank_name') }}</label>
                  <input type="text" class="form-control" v-model="form.bank_name" :class="{ 'is-invalid': form.errors.bank_name }" />
                  <InputError :message="form.errors.bank_name" />
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('users.form.rib') }}</label>
                  <input type="text" class="form-control" v-model="form.rib" :class="{ 'is-invalid': form.errors.rib }" />
                  <InputError :message="form.errors.rib" />
                </BCol>
                <BCol md="4">
                  <label class="form-label">{{ $t('users.form.rib_attachment') }}</label>
                  <input type="file" class="form-control" accept=".pdf,image/*" @change="onBillingFileChange('rib_attachment', $event)" :class="{ 'is-invalid': form.errors.rib_attachment }" />
                  <InputError :message="form.errors.rib_attachment" />
                </BCol>
                <BCol md="4">
                  <label class="form-label">{{ $t('users.form.cin_front_attachment') }}</label>
                  <input type="file" class="form-control" accept=".pdf,image/*" @change="onBillingFileChange('cin_front_attachment', $event)" :class="{ 'is-invalid': form.errors.cin_front_attachment }" />
                  <InputError :message="form.errors.cin_front_attachment" />
                </BCol>
                <BCol md="4">
                  <label class="form-label">{{ $t('users.form.cin_back_attachment') }}</label>
                  <input type="file" class="form-control" accept=".pdf,image/*" @change="onBillingFileChange('cin_back_attachment', $event)" :class="{ 'is-invalid': form.errors.cin_back_attachment }" />
                  <InputError :message="form.errors.cin_back_attachment" />
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
              <h5 class="card-title mb-0">{{ $t('users.form.attached_files') }}</h5>
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
            <Link :href="route('users.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('users.create') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
