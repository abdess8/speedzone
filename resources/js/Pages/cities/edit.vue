<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CityForm from "./Partials/CityForm.vue";

const props = defineProps({
  city: { type: Object, required: true },
});

const form = useForm({
  name: props.city.name,
  code: props.city.code,
  region: props.city.region,
  is_active: props.city.is_active,
  is_stock_hub: props.city.is_stock_hub,
});

const submit = () => {
  form.put(route("cities.update", props.city.id));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('cities.edit_title', { name: city.name })" :pageTitle="$t('cities.title')" />
    <form @submit.prevent="submit">
      <CityForm :form="form" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('cities.show', city.id)" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="primary" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('common.save_changes') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
