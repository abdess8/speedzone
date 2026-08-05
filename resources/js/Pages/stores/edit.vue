<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import StoreForm from './Partials/StoreForm.vue';

const props = defineProps({
  store: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  hubCities: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const { t } = useI18n();

const form = useForm({
  // Inertia cannot send a multipart PUT, so the verb is spoofed and the
  // request goes out as POST.
  _method: 'put',
  name: props.store.name ?? '',
  category: props.store.category ?? '',
  website: props.store.website ?? '',
  logo: null,
  contact_name: props.store.contact_name ?? '',
  contact_phone: props.store.contact_phone ?? '',
  contact_email: props.store.contact_email ?? '',
  city_id: props.store.city_id ?? null,
  stock_hub_city_id: props.store.stock_hub_city_id ?? null,
  address: props.store.address ?? '',
  pickup_address_1: props.store.pickup_address_1 ?? '',
  pickup_address_2: props.store.pickup_address_2 ?? '',
  is_active: props.store.is_active ?? true,
  is_default: props.store.is_default ?? false,
});

const submit = () => {
  form.post(route('stores.update', props.store.id), { forceFormData: true });
};

const confirmDelete = () => {
  Swal.fire({
    title: t('stores.delete_confirm_title'),
    text: t('stores.delete_confirm_text', { name: props.store.name }),
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#f06548',
    confirmButtonText: t('common.confirm_delete'),
    cancelButtonText: t('common.cancel'),
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('stores.destroy', props.store.id));
    }
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="store.name" :pageTitle="$t('stores.title')" />

    <form @submit.prevent="submit">
      <StoreForm
        :form="form"
        :cities="cities"
        :hub-cities="hubCities"
        :current-logo-url="store.logo_url"
      />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <BCard v-if="!store.is_default" no-body>
            <BCardBody>
              <div class="form-check form-switch fs-15">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="storeDefault"
                  v-model="form.is_default"
                />
                <label class="form-check-label" for="storeDefault">
                  {{ $t('stores.fields.is_default') }}
                </label>
              </div>
              <p class="text-muted fs-12 mb-0 mt-1">{{ $t('stores.form.default_hint') }}</p>
            </BCardBody>
          </BCard>

          <div class="hstack gap-2 justify-content-end mb-4">
            <BButton v-if="can.delete" variant="soft-danger" type="button" @click="confirmDelete">
              <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('common.delete') }}
            </BButton>
            <Link :href="route('stores.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.save') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
