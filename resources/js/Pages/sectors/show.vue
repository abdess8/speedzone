<script setup>
import { computed, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import UserAvatar from "@/Components/UserAvatar.vue";
import Swal from "sweetalert2";

const { t } = useI18n();

const props = defineProps({
  sector: { type: Object, required: true },
  can: { type: Object, default: () => ({}) },
});

import { formatMoney as money } from "@/common/formatMoney";

const driversList = computed(() => {
  const drivers = props.sector?.drivers;
  if (Array.isArray(drivers)) return drivers;
  if (drivers?.data && Array.isArray(drivers.data)) return drivers.data;
  return [];
});

const confirmDelete = () => {
  Swal.fire({
    title: t("sectors.delete_confirm_title"),
    text: t("sectors.delete_confirm_text", { name: props.sector.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_delete"),
    cancelButtonText: t("common.cancel"),
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
    <PageHeader :title="sector.name" :pageTitle="$t('sectors.title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge fs-13" :class="sector.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
          {{ sector.is_active ? $t('common.active') : $t('common.inactive') }}
        </span>
        <!-- Labels collapse below `sm`; `title` still names the icon-only button. -->
        <div class="ms-auto action-bar">
          <Link :href="route('sectors.index')" class="btn btn-sm btn-light" :title="$t('common.back')">
            <i class="ri-arrow-left-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('common.back') }}</span>
          </Link>
          <Link v-if="can.update" :href="route('sectors.edit', sector.id)" class="btn btn-sm btn-soft-warning" :title="$t('common.edit')">
            <i class="ri-pencil-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('common.edit') }}</span>
          </Link>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" :title="$t('common.delete')" @click="confirmDelete">
            <i class="ri-delete-bin-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('common.delete') }}</span>
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="6">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('sectors.show.info') }}</h5></BCardHeader>
          <BCardBody>
            <BRow class="g-3">
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.table.sector') }}</div>
                <div class="fw-semibold">{{ sector.name }}</div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.form.city') }}</div>
                <div class="fw-semibold">
                  <Link v-if="sector.city" :href="route('cities.show', sector.city.id)">{{ sector.city.name }}</Link>
                  <span v-else>{{ $t('common.empty_value') }}</span>
                </div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.table.delivery_price') }}</div>
                <div class="fw-bold fs-18 text-primary">{{ money(sector.delivery_price) }}</div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.table.return_price') }}</div>
                <div class="fw-bold fs-18 text-danger">{{ money(sector.return_price) }}</div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.form.delivery_driver_price') }}</div>
                <div class="fw-bold fs-18 text-success">{{ money(sector.delivery_driver_price) }}</div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.table.delivery_delay') }}</div>
                <div class="fw-semibold">{{ sector.delivery_delay || $t('common.empty_value') }}</div>
              </BCol>
              <BCol md="6">
                <div class="text-muted fs-13">{{ $t('sectors.table.orders') }}</div>
                <div class="fw-semibold">{{ sector.orders_count ?? 0 }}</div>
              </BCol>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="6">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('sectors.show.assigned_drivers', { name: sector.name }) }}</h5>
          </BCardHeader>
          <BCardBody>
            <div v-if="driversList.length" class="vstack gap-3">
              <div v-for="driver in driversList" :key="driver.id" class="d-flex align-items-center gap-3">
                <UserAvatar :user="driver" :size="40" clickable show-name />
                <div class="flex-grow-1 min-w-0">
                  <div class="text-muted fs-12 text-truncate">{{ driver.email }}</div>
                  <div v-if="driver.phone" class="text-muted fs-12">{{ driver.phone }}</div>
                </div>
              </div>
            </div>
            <p v-else class="text-muted mb-0">{{ $t('sectors.show.no_drivers') }}</p>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
