<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderForm from "./Partials/OrderForm.vue";

defineProps({
  cities: { type: Array, default: () => [] },
  sectors: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const form = useForm({
  customer_first_name: "",
  customer_last_name: "",
  customer_phone: "",
  customer_address: "",
  city_id: null,
  sector_id: null,
  payment_method: "CASH",
  order_amount: "",
  delivery_price: "",
  notes: "",
  is_fragile: false,
  can_be_opened: false,
});

const submit = () => {
  form.post(route("orders.store"));
};
</script>

<template>
  <Layout>
    <PageHeader title="Create Order" pageTitle="Order Management" />
    <form @submit.prevent="submit">
      <OrderForm :form="form" :cities="cities" :sectors="sectors" :payment-methods="paymentMethods" />

      <div class="hstack gap-2 justify-content-end mb-4">
        <Link :href="route('orders.index')" class="btn btn-light">Cancel</Link>
        <BButton type="submit" variant="success" :disabled="form.processing">
          <i class="ri-save-line align-bottom me-1"></i> Create Order
        </BButton>
      </div>
    </form>
  </Layout>
</template>
