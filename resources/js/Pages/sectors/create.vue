<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import SectorForm from "./Partials/SectorForm.vue";

const props = defineProps({
  cities: { type: Array, default: () => [] },
  defaultCityId: { type: Number, default: null },
});

const form = useForm({
  city_id: props.defaultCityId,
  name: "",
  delivery_price: "",
  is_active: true,
});

const submit = () => {
  form.post(route("sectors.store"));
};
</script>

<template>
  <Layout>
    <PageHeader title="Create Sector" pageTitle="Sectors" />
    <form @submit.prevent="submit">
      <SectorForm :form="form" :cities="cities" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('sectors.index')" class="btn btn-light">Cancel</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> Create Sector
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
