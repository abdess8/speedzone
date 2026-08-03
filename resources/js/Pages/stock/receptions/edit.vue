<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import ReceptionForm from './Partials/ReceptionForm.vue';

const props = defineProps({
  reception: { type: Object, required: true },
  products: { type: Array, default: () => [] },
  hubCities: { type: Array, default: () => [] },
  shopDepotCityId: { type: Number, default: null },
});

const form = useForm({
  status: 'DRAFT',
  destination_city_id: props.reception.destination_city_id ?? props.shopDepotCityId,
  sent_at: props.reception.sent_at ?? '',
  sending_notes: props.reception.sending_notes ?? '',
  items: (props.reception.items ?? []).map((item) => ({
    product_id: item.product_id,
    name: item.name,
    sku: item.sku,
    photo_url: item.photo_url,
    initials: item.initials,
    stock_quantity: item.stock_quantity,
    quantity_sent: item.quantity_sent,
    note: item.note ?? '',
  })),
});

const hasItems = computed(() => form.items.length > 0);

const submit = (status) => {
  form.status = status;
  form.put(route('stock-receptions.update', props.reception.id));
};
</script>

<template>
  <Layout>
    <PageHeader
      :title="$t('stock.receptions.edit_title')"
      :pageTitle="$t('stock.receptions.detail_title', { reference: reception.reference })"
    />

    <form @submit.prevent="submit('DRAFT')">
      <ReceptionForm
        :form="form"
        :products="products"
        :hub-cities="hubCities"
        :shop-depot-city-id="shopDepotCityId"
      />

      <div class="hstack gap-2 justify-content-center flex-wrap mb-4">
        <Link :href="route('stock-receptions.show', reception.id)" class="btn btn-light">
          {{ $t('common.cancel') }}
        </Link>
        <BButton type="submit" variant="soft-primary" :disabled="form.processing || !hasItems">
          <i class="ri-save-3-line align-bottom me-1"></i>
          {{ $t('stock.receptions.form.save_draft') }}
        </BButton>
        <BButton
          type="button"
          variant="success"
          :disabled="form.processing || !hasItems"
          @click="submit('AWAITING_PICKUP')"
        >
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="ri-time-line align-bottom me-1"></i>
          {{ $t('stock.receptions.form.save_and_send') }}
        </BButton>
      </div>
    </form>
  </Layout>
</template>
