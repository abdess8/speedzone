<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import PartnerForm from "./Partials/PartnerForm.vue";
import { senditPartnerDefaults } from "./senditDefaults.js";

const props = defineProps({
  cities: { type: Array, default: () => [] },
  order_statuses: { type: Array, default: () => [] },
  order_fields: { type: Array, default: () => [] },
  auth_types: { type: Array, default: () => [] },
});

const form = useForm({
  logo: null,
  ice_number: "",
  is_active: true,
  reception_city_id: null,
  client_id: "",
  client_secret: "",
  api_key_header: "X-API-Key",
  sync_frequency_minutes: 30,
  sync_status: false,
  city_ids: [],
  sector_ids: [],
  ...senditPartnerDefaults,
});

const submit = () => {
  form.post(route("partners.store"), {
    forceFormData: true,
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('partners.create_title')" :pageTitle="$t('partners.title')" />
    <form @submit.prevent="submit">
      <PartnerForm
        :form="form"
        :cities="cities"
        :order-statuses="order_statuses"
        :order-fields="order_fields"
        :auth-types="auth_types"
      />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('partners.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('partners.create_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
