<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import StoreForm from './Partials/StoreForm.vue';

defineProps({
  cities: { type: Array, default: () => [] },
});

const form = useForm({
  name: '',
  category: '',
  website: '',
  logo: null,
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  city_id: null,
  address: '',
  pickup_address_1: '',
  pickup_address_2: '',
  is_active: true,
});

const submit = () => {
  // forceFormData: the logo is a File, which JSON cannot carry.
  form.post(route('stores.store'), { forceFormData: true });
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stores.create_title')" :pageTitle="$t('stores.title')" />

    <form @submit.prevent="submit">
      <StoreForm :form="form" :cities="cities" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('stores.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('stores.create_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
