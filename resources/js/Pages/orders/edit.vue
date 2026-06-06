<script setup>
import { Link, useForm } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderForm from "./Partials/OrderForm.vue";

const props = defineProps({
  order: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const form = useForm({
  customer_first_name: props.order.customer.first_name,
  customer_last_name: props.order.customer.last_name,
  customer_phone: props.order.customer.phone,
  customer_address: props.order.customer.address,
  city_id: props.order.city_id,
  payment_method: props.order.payment_method,
  order_amount: props.order.order_amount,
  delivery_price: props.order.delivery_price,
  notes: props.order.notes,
  is_fragile: props.order.is_fragile,
  can_be_opened: props.order.can_be_opened,
});

const submit = () => {
  form.put(route("orders.update", props.order.id));
};
</script>

<template>
  <Layout>
    <PageHeader :title="`Edit ${order.tracking_number}`" pageTitle="Order Management" />
    <form @submit.prevent="submit">
      <OrderForm :form="form" :cities="cities" :payment-methods="paymentMethods" />

      <div class="hstack gap-2 justify-content-end mb-4">
        <Link :href="route('orders.show', order.id)" class="btn btn-light">Cancel</Link>
        <BButton type="submit" variant="primary" :disabled="form.processing">
          <i class="ri-save-line align-bottom me-1"></i> Save Changes
        </BButton>
      </div>
    </form>
  </Layout>
</template>
