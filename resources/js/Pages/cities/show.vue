<script setup>
import { computed, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";

const { t, locale } = useI18n();

const props = defineProps({
  city: { type: Object, required: true },
  can: { type: Object, default: () => ({}) },
});

const page = usePage();

import { formatAmount, formatMoney as money, formatMoneyOrEmpty } from "@/common/formatMoney";

const formatDate = (value) => {
  if (!value) return t("common.empty_value");
  return new Date(value).toLocaleString(locale.value === "en" ? "en-GB" : "fr-FR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

const sectorsList = computed(() => {
  const sectors = props.city?.sectors;
  if (Array.isArray(sectors)) return sectors;
  if (sectors?.data && Array.isArray(sectors.data)) return sectors.data;
  return [];
});

const createSectorUrl = computed(() => route("sectors.create") + "?city_id=" + props.city.id);

const flashToast = () => {
  const flash = page.props?.flash ?? {};
  if (flash.success) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "success",
      title: flash.success,
      showConfirmButton: false,
      timer: 3000,
    });
  }
  if (flash.error) {
    Swal.fire({
      toast: true,
      position: "top-end",
      icon: "error",
      title: flash.error,
      showConfirmButton: false,
      timer: 4000,
    });
  }
};

watch(() => page.props?.flash, flashToast, { deep: true, immediate: true });

const confirmDeleteCity = () => {
  Swal.fire({
    title: t("cities.delete_confirm_title"),
    text: t("cities.delete_confirm_text", { name: props.city.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_delete"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (result.isConfirmed) router.delete(route("cities.destroy", props.city.id));
  });
};

const confirmDeleteSector = (sector) => {
  Swal.fire({
    title: t("cities.sector_delete_confirm_title"),
    text: t("cities.sector_delete_confirm_text", { name: sector.name, city: props.city.name }),
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#f06548",
    confirmButtonText: t("common.confirm_remove"),
    cancelButtonText: t("common.cancel"),
  }).then((result) => {
    if (!result.isConfirmed) return;

    router.delete(route("sectors.destroy", sector.id) + "?return_to=city", {
      preserveScroll: true,
    });
  });
};
</script>

<template>
  <Layout>
    <PageHeader :title="city.name" :pageTitle="$t('cities.title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center gap-2">
        <span
          class="badge fs-13"
          :class="city.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
        >
          {{ city.is_active ? $t('common.active') : $t('common.inactive') }}
        </span>
        <span class="text-muted fs-13">
          {{ sectorsList.length }} {{ $t('cities.table.sectors').toLowerCase() }}
        </span>
        <div class="ms-auto hstack gap-2">
          <Link :href="route('cities.index')" class="btn btn-sm btn-light">
            <i class="ri-arrow-left-line align-bottom me-1"></i> {{ $t('common.back') }}
          </Link>
          <Link v-if="can.update" :href="route('cities.edit', city.id)" class="btn btn-sm btn-soft-warning">
            <i class="ri-pencil-line align-bottom me-1"></i> {{ $t('common.edit') }}
          </Link>
          <button v-if="can.delete" class="btn btn-sm btn-soft-danger" @click="confirmDeleteCity">
            <i class="ri-delete-bin-line align-bottom me-1"></i> {{ $t('common.delete') }}
          </button>
        </div>
      </BCardBody>
    </BCard>

    <BRow>
      <BCol xl="4">
        <BCard no-body>
          <BCardHeader><h5 class="card-title mb-0">{{ $t('cities.show.info') }}</h5></BCardHeader>
          <BCardBody>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('cities.table.name') }}</div>
              <div class="fw-semibold">{{ city.name }}</div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('cities.table.code') }}</div>
              <div class="fw-semibold">
                <span v-if="city.code" class="badge bg-light text-body border">{{ city.code }}</span>
                <span v-else>{{ $t('common.empty_value') }}</span>
              </div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('cities.table.region') }}</div>
              <div class="fw-semibold">{{ city.region || $t('common.empty_value') }}</div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('cities.show.total_sectors') }}</div>
              <div class="fw-semibold">
                {{ city.sectors_count ?? sectorsList.length }}
                <span class="text-muted">({{ $t('cities.show.active_sectors', { count: city.active_sectors_count ?? 0 }) }})</span>
              </div>
            </div>
            <div class="mb-3">
              <div class="text-muted fs-13">{{ $t('cities.show.created') }}</div>
              <div class="fw-semibold">{{ formatDate(city.created_at) }}</div>
            </div>
            <div>
              <div class="text-muted fs-13">{{ $t('cities.show.updated') }}</div>
              <div class="fw-semibold">{{ formatDate(city.updated_at) }}</div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol xl="8">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">{{ $t('cities.show.sectors_in', { name: city.name }) }}</h5>
            <Link v-if="can.sectors_create" :href="createSectorUrl" class="btn btn-sm btn-success">
              <i class="ri-add-line align-bottom me-1"></i> {{ $t('cities.show.add_sector') }}
            </Link>
          </BCardHeader>
          <BCardBody>
            <div class="table-responsive table-card">
              <table class="table align-middle table-nowrap mb-0">
                <thead class="table-light text-muted">
                  <tr>
                    <th>{{ $t('sectors.table.sector') }}</th>
                    <th class="text-end">{{ $t('sectors.table.delivery_price') }}</th>
                    <th class="text-end">{{ $t('sectors.table.return_price') }}</th>
                    <th class="text-center">{{ $t('sectors.table.orders') }}</th>
                    <th>{{ $t('common.status') }}</th>
                    <th class="text-end">{{ $t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sector in sectorsList" :key="sector.id">
                    <td class="fw-medium">{{ sector.name }}</td>
                    <td class="text-end">{{ money(sector.delivery_price) }}</td>
                    <td class="text-end">{{ money(sector.return_price) }}</td>
                    <td class="text-center">{{ sector.orders_count ?? 0 }}</td>
                    <td>
                      <span
                        class="badge"
                        :class="sector.is_active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'"
                      >
                        {{ sector.is_active ? $t('common.active') : $t('common.inactive') }}
                      </span>
                    </td>
                    <td class="text-end">
                      <ul class="list-inline hstack gap-2 mb-0 justify-content-end">
                        <li v-if="can.sectors_update" class="list-inline-item" :title="$t('common.edit')">
                          <Link :href="route('sectors.edit', sector.id)" class="text-warning">
                            <i class="ri-pencil-fill fs-16"></i>
                          </Link>
                        </li>
                        <li v-if="can.sectors_delete" class="list-inline-item" :title="$t('common.delete')">
                          <BLink class="text-danger" @click="confirmDeleteSector(sector)">
                            <i class="ri-delete-bin-5-fill fs-16"></i>
                          </BLink>
                        </li>
                      </ul>
                    </td>
                  </tr>
                  <tr v-if="sectorsList.length === 0">
                    <td colspan="6" class="text-center text-muted py-4">
                      {{ $t('cities.show.no_sectors') }}
                      <Link v-if="can.sectors_create" :href="createSectorUrl" class="ms-1">{{ $t('cities.show.add_first_sector') }}</Link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>
