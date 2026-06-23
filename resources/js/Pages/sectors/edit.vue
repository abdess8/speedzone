<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import SectorForm from "./Partials/SectorForm.vue";

const props = defineProps({
  sector: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
});

const form = useForm({
  city_id: props.sector.city_id,
  name: props.sector.name,
  delivery_price: props.sector.delivery_price,
  return_price: props.sector.return_price ?? "",
  delivery_driver_price: props.sector.delivery_driver_price ?? "",
  is_active: props.sector.is_active,
});

const submit = () => {
  form.put(route("sectors.update", props.sector.id));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('sectors.edit_title', { name: sector.name })" :pageTitle="$t('sectors.title')" />
    <form @submit.prevent="submit">
      <SectorForm :form="form" :cities="cities" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('sectors.show', sector.id)" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="primary" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.save_changes') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
