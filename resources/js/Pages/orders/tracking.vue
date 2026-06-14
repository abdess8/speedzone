<script setup>
import { Link } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderTimeline from "./Partials/OrderTimeline.vue";
import PaymentMethodBadge from "@/Components/PaymentMethodBadge.vue";

const props = defineProps({
  order: { type: Object, required: true },
});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const displayMoney = (value) => (value != null && value !== "" ? `${money(value)} MAD` : "—");
</script>

<template>
  <Layout>
    <PageHeader :title="`Track ${order.tracking_number}`" pageTitle="Order Tracking" />

    <BRow class="justify-content-center">
      <BCol xl="8">
        <BCard no-body>
          <BCardBody class="text-center border-bottom">
            <h4 class="mb-1">{{ order.tracking_number }}</h4>
            <span class="badge fs-13" :class="`bg-${order.status_color}-subtle text-${order.status_color}`">
              {{ order.status_label }}
            </span>
            <div class="text-muted mt-2">
              Destination: <span class="fw-medium">{{ order.city?.name }}</span> &middot;
              Total: <span class="fw-medium">{{ displayMoney(order.total_amount) }}</span>
            </div>
          </BCardBody>

          <BCardBody class="border-bottom">
            <h5 class="card-title mb-3">Payment Information</h5>
            <BRow class="g-3">
              <BCol md="6">
                <div class="text-muted fs-13">Payment Method</div>
                <PaymentMethodBadge
                  :label="order.payment_method_label"
                  :emoji="order.payment_method_emoji"
                  :color="order.payment_method_color"
                />
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">Cash Collection Required</div>
                <span
                  class="badge fs-13"
                  :class="order.cash_collection_required ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary'"
                >
                  {{ order.cash_collection_required ? "YES" : "NO" }}
                </span>
              </BCol>
              <BCol v-if="order.amount_to_collect != null" md="6">
                <div class="text-muted fs-13">Amount to Collect</div>
                <div class="fw-semibold fs-16">{{ displayMoney(order.amount_to_collect) }}</div>
              </BCol>
              <BCol v-if="order.order_value != null" md="6">
                <div class="text-muted fs-13">Order Value</div>
                <div class="fw-medium">{{ displayMoney(order.order_value) }}</div>
              </BCol>
            </BRow>
          </BCardBody>

          <BCardBody>
            <h5 class="card-title mb-4">Tracking History</h5>
            <OrderTimeline :history="order.status_history ?? []" />
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
