<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import PartnerForm from "./Partials/PartnerForm.vue";

const props = defineProps({
  partner: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  order_statuses: { type: Array, default: () => [] },
  order_fields: { type: Array, default: () => [] },
  auth_types: { type: Array, default: () => [] },
});

const form = useForm({
  _method: "put",
  name: props.partner.name,
  logo: null,
  ice_number: props.partner.ice_number ?? "",
  is_active: props.partner.is_active,
  reception_city_id: props.partner.reception_city_id ?? null,
  api_base_url: props.partner.api_base_url ?? "",
  auth_type: props.partner.auth_type ?? "BASIC",
  client_id: props.partner.client_id ?? "",
  client_secret: "",
  endpoint_login: props.partner.endpoint_login ?? "",
  api_key_header: props.partner.api_key_header ?? "X-API-Key",
  login_username_field: props.partner.login_username_field ?? "public_key",
  login_password_field: props.partner.login_password_field ?? "secret_key",
  login_token_field: props.partner.login_token_field ?? "data.token",
  endpoint_statuses: props.partner.endpoint_statuses ?? "/all-status-deliveries",
  endpoint_deliveries: props.partner.endpoint_deliveries ?? "/deliveries",
  endpoint_update: props.partner.endpoint_update ?? "/update-deliveries",
  ingestion_partner_status: props.partner.ingestion_partner_status ?? "",
  sync_frequency_minutes: props.partner.sync_frequency_minutes ?? 30,
  sync_status: props.partner.sync_status ?? false,
  city_ids: [...(props.partner.city_ids ?? [])],
  sector_ids: [...(props.partner.sector_ids ?? [])],
  status_mappings: (props.partner.status_mappings ?? []).map((m) => ({
    speedzone_status: m.speedzone_status,
    partner_status: m.partner_status,
  })),
  field_mappings: (props.partner.field_mappings ?? []).map((m) => ({
    speedzone_field: m.speedzone_field,
    partner_field: m.partner_field,
  })),
});

const submit = () => {
  form.post(route("partners.update", props.partner.id), {
    forceFormData: true,
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('partners.edit_title', { name: partner.name })" :pageTitle="$t('partners.title')" />
    <form @submit.prevent="submit">
      <PartnerForm
        :form="form"
        :cities="cities"
        :order-statuses="order_statuses"
        :order-fields="order_fields"
        :auth-types="auth_types"
        :partner-id="partner.id"
        :is-edit="true"
        :current-logo-url="partner.logo_url"
      />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('partners.show', partner.id)" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="primary" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.save_changes') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
