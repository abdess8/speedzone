<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import ProductForm from './Partials/ProductForm.vue';

const props = defineProps({
  product: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
});

const form = useForm({
  // PUT cannot carry a file upload, so the update goes out as a spoofed POST
  // multipart request whenever the seller replaces the photo.
  _method: 'put',
  name: props.product.name ?? '',
  sku: props.product.sku ?? '',
  barcode: props.product.barcode ?? '',
  category: props.product.category ?? '',
  description: props.product.description ?? '',
  photo: null,
  unit_price: props.product.unit_price ?? '',
  cost_price: props.product.cost_price ?? '',
  is_fragile: Boolean(props.product.is_fragile),
  is_active: Boolean(props.product.is_active),
  weight_grams: props.product.weight_grams ?? '',
  length_cm: props.product.length_cm ?? '',
  width_cm: props.product.width_cm ?? '',
  height_cm: props.product.height_cm ?? '',
});

const submit = () => form.post(route('products.update', props.product.id));
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.products.edit_title')" :pageTitle="$t('stock.page_title')" />

    <form @submit.prevent="submit">
      <ProductForm
        :form="form"
        :categories="categories"
        :current-photo-url="product.photo_url"
        :stock-quantity="product.stock_quantity"
      />

      <div class="hstack gap-2 justify-content-center mb-4">
        <Link :href="route('products.show', product.id)" class="btn btn-light">{{ $t('common.cancel') }}</Link>
        <BButton type="submit" variant="success" :disabled="form.processing">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-save-3-line align-bottom me-1"></i>
          {{ $t('common.save') }}
        </BButton>
      </div>
    </form>
  </Layout>
</template>
