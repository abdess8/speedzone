<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import AdminSellerCard from '@/Components/AdminSellerCard.vue';
import StoreForm from './Partials/StoreForm.vue';

const props = defineProps({
  cities: { type: Array, default: () => [] },
  hubCities: { type: Array, default: () => [] },
  admin: { type: Boolean, default: false },
  sellers: { type: Array, default: () => [] },
  seller: { type: Object, default: null },
});

const form = useForm({
  seller_id: props.seller?.id ?? '',
  name: '',
  category: '',
  website: '',
  logo: null,
  contact_name: '',
  contact_phone: '',
  contact_email: '',
  city_id: null,
  stock_hub_city_id: null,
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
      <AdminSellerCard
        v-if="admin"
        v-model="form.seller_id"
        :sellers="sellers"
        :error="form.errors.seller_id"
        :label="$t('stores.admin.seller_field')"
        :placeholder="$t('stores.admin.seller_placeholder')"
        :help="$t('stores.admin.seller_help')"
      />

      <StoreForm :form="form" :cities="cities" :hub-cities="hubCities" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('stores.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton
              data-guide="store-submit"
              type="submit"
              variant="success"
              :disabled="form.processing || (admin && !form.seller_id)"
            >
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('stores.create_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
