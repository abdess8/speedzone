<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import CityForm from "./Partials/CityForm.vue";

const form = useForm({
  name: "",
  code: "",
  region: "",
  is_active: true,
  is_stock_hub: false,
});

const submit = () => {
  form.post(route("cities.store"));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('cities.create_title')" :pageTitle="$t('cities.title')" />
    <form @submit.prevent="submit">
      <CityForm :form="form" />

      <BRow>
        <BCol xl="8" class="mx-auto">
          <div class="hstack gap-2 justify-content-end mb-4">
            <Link :href="route('cities.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
            <BButton type="submit" variant="success" :disabled="form.processing">
              <i class="ri-save-line align-bottom me-1"></i> {{ $t('cities.create_button') }}
            </BButton>
          </div>
        </BCol>
      </BRow>
    </form>
  </Layout>
</template>
