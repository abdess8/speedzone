<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import ProductForm from './Partials/ProductForm.vue';

defineProps({
  categories: { type: Array, default: () => [] },
});

const form = useForm({
  name: '',
  sku: '',
  barcode: '',
  category: '',
  description: '',
  photo: null,
  unit_price: '',
  cost_price: '',
  is_fragile: false,
  is_active: true,
  weight_grams: '',
  length_cm: '',
  width_cm: '',
  height_cm: '',
});

const submit = () => form.post(route('products.store'));
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.products.create_title')" :pageTitle="$t('stock.page_title')" />

    <form @submit.prevent="submit">
      <ProductForm :form="form" :categories="categories" />

      <div data-guide="product-submit" class="hstack gap-2 justify-content-center mb-4">
        <Link :href="route('products.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
        <BButton type="submit" variant="success" :disabled="form.processing">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-add-line align-bottom me-1"></i>
          {{ $t('common.create') }}
        </BButton>
      </div>
    </form>
  </Layout>
</template>
