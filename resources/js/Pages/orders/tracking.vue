<script setup>
import { Link } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import OrderTimeline from "./Partials/OrderTimeline.vue";

const props = defineProps({
  order: { type: Object, required: true },
});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );
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
              Total: <span class="fw-medium">{{ money(order.total_amount) }} MAD</span>
            </div>
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
