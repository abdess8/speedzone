<script setup>
import { onMounted } from "vue";
import { Link, useForm, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderForm from "./Partials/OrderForm.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  cities: { type: Array, default: () => [] },
  sectors: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
  cloneData: { type: Object, default: null },
});

const emptyForm = () => ({
  customer_first_name: "",
  customer_last_name: "",
  customer_phone: "",
  customer_address: "",
  city_id: null,
  sector_id: null,
  payment_method: "CASH",
  order_amount: "",
  order_value: "",
  delivery_price: "",
  notes: "",
  is_fragile: false,
  can_be_opened: false,
  option_exchange: false,
});

const buildFormState = (data = null) => {
  if (!data) return emptyForm();
  return {
    customer_first_name: data.customer_first_name ?? "",
    customer_last_name: data.customer_last_name ?? "",
    customer_phone: data.customer_phone ?? "",
    customer_address: data.customer_address ?? "",
    city_id: data.city_id ?? null,
    sector_id: data.sector_id ?? null,
    payment_method: data.payment_method ?? "CASH",
    order_amount: data.order_amount ?? "",
    order_value: data.order_value ?? "",
    delivery_price: data.delivery_price ?? "",
    notes: data.notes ?? "",
    is_fragile: Boolean(data.is_fragile),
    can_be_opened: Boolean(data.can_be_opened),
    option_exchange: Boolean(data.option_exchange),
  };
};

const form = useForm(buildFormState(props.cloneData));

const submit = () => form.post(route("orders.store"));

const submitAndNew = () => {
  form.post(route("orders.store-and-new"), {
    preserveState: false,
    onSuccess: () => {
      form.defaults(emptyForm());
      form.reset();
    },
  });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) {
    Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3500, timerProgressBar: true });
  } else if (props.cloneData) {
    Swal.fire({ toast: true, position: "top-end", icon: "info", title: t("orders.clone_loaded"), showConfirmButton: false, timer: 3500, timerProgressBar: true });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('orders.create_title')" :pageTitle="$t('orders.page_title')" />
    <form @submit.prevent="submit">
      <OrderForm :form="form" :cities="cities" :sectors="sectors" :payment-methods="paymentMethods" />

      <div class="hstack gap-2 justify-content-center mb-4">
        <Link :href="route('orders.index')" class="btn btn-light">{{ $t('common.cancel') }}</Link>
        <BButton type="button" variant="soft-success" :disabled="form.processing" @click="submitAndNew">
          <i class="ri-add-line align-bottom me-1"></i>
          {{ $t('orders.create_and_new') }}
        </BButton>
        <BButton type="submit" variant="success" :disabled="form.processing">
          <i class="ri-add-line align-bottom me-1"></i> {{ $t('common.create') }}
        </BButton>
      </div>
    </form>
  </Layout>
</template>
