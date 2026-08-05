<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import ReceptionForm from './Partials/ReceptionForm.vue';

const props = defineProps({
  products: { type: Array, default: () => [] },
  hubCities: { type: Array, default: () => [] },
  shopDepotCityId: { type: Number, default: null },
});

const form = useForm({
  status: 'DRAFT',
  // Pre-filled with the shop's depot when it has one; there is nothing left to
  // choose after the first shipment.
  destination_city_id: props.shopDepotCityId,
  sent_at: new Date().toISOString().slice(0, 10),
  sending_notes: '',
  items: [],
});

const hasItems = computed(() => form.items.length > 0);

/**
 * Two exits, one endpoint.
 *
 * A draft can still be corrected; requesting a collection freezes the quantities
 * because that is the document the collector will count against at the counter.
 */
const submit = (status) => {
  form.status = status;
  form.post(route('stock-receptions.store'));
};
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.receptions.create_title')" :pageTitle="$t('stock.page_title')" />

    <form @submit.prevent="submit('DRAFT')">
      <ReceptionForm
        :form="form"
        :products="products"
        :hub-cities="hubCities"
        :shop-depot-city-id="shopDepotCityId"
      />

      <div data-guide="reception-submit" class="hstack gap-2 justify-content-center flex-wrap mb-4">
        <Link :href="route('stock-receptions.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
        <BButton type="submit" variant="soft-primary" :disabled="form.processing || !hasItems">
          <i class="ri-draft-line align-bottom me-1"></i>
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
