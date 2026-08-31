<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import SectorForm from "./Partials/SectorForm.vue";

const props = defineProps({
  cities: { type: Array, default: () => [] },
  defaultCityId: { type: Number, default: null },
  can: { type: Object, default: () => ({}) },
});

const form = useForm({
  city_id: props.defaultCityId,
  name: "",
  delivery_price: "",
  return_price: "",
  delivery_driver_price: "",
  delivery_delay: "",
  is_active: true,
});

const submit = () => {
  form.post(route("sectors.store"));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('sectors.create_title')" :pageTitle="$t('sectors.title')" />
    <form @submit.prevent="submit">
      <SectorForm :form="form" :cities="cities" :can-edit-driver-price="!!can.view_driver_price" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('sectors.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('sectors.create_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
