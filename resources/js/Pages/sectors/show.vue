<script setup>
import { onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const props = defineProps({
  sector: { type: Object, required: true },
  can: { type: Object, default: () => ({}) },
});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));

const confirmDelete = () => {
  Swal.fire({
    title: "Delete this sector?",
    text: `${props.sector.name} will be removed.`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: "Yes, delete it!",
  }).then((result) => {
    if (result.isConfirmed) router.delete(route("sectors.destroy", props.sector.id));
  });
};

onMounted(() => {
  const success = usePage().props?.flash?.success;
  if (success) Swal.fire({ toast: true, position: "top-end", icon: "success", title: success, showConfirmButton: false, timer: 3000 });
});
</script>

<template>
  <Layout>
    <PageHeader :title="sector.name" pageTitle="Sectors" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="sector.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
          {{ sector.is_active ? "Active" : "Inactive" }}
        </span>
        <div class="ms-auto hstack gap-2">
          <Link :href="route('sectors.index')" class="btn btn-sm btn-light"><i class="ri-arrow-left-line align-bottom me-1"></i> Back</Link>
          <Link v-if="can.update" :href="route('sectors.edit', sector.id)" class="btn btn-sm btn-soft-warning"><i class="ri-pencil-line align-bottom me-1"></i> Edit</Link>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" @click="confirmDelete"><i class="ri-delete-bin-line align-bottom me-1"></i> Delete</button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="6" class="mx-auto">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">Sector Information</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="6"><div class="text-muted fs-13">Name</div><div class="fw-semibold">{{ sector.name }}</div></BCol>
              <BCol md="6">
                <div class="text-muted fs-13">City</div>
                <div class="fw-semibold">
                  <Link v-if="sector.city" :href="route('cities.show', sector.city.id)">{{ sector.city.name }}</Link>
                  <span v-else>—</span>
                </div>
              </BCol>
              <BCol md="6"><div class="text-muted fs-13">Delivery Price</div><div class="fw-bold fs-18 text-primary">{{ money(sector.delivery_price) }} MAD</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">Orders</div><div class="fw-semibold">{{ sector.orders_count ?? 0 }}</div></BCol>
              <BCol md="6"><div class="text-muted fs-13">Assigned Drivers</div><div class="fw-semibold">{{ sector.drivers_count ?? 0 }}</div></BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
