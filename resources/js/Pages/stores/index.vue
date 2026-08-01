<script setup>
import { Link } from '@inertiajs/vue3';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import { useStore } from '@/composables/useStore';

defineProps({
  stores: { type: Array, default: () => [] },
  can: { type: Object, default: () => ({}) },
});

const { activeStore, switchStore } = useStore();

const initials = (name) =>
  (name ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase();
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stores.title')" :pageTitle="$t('sidebar.my_shop')" />

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">{{ $t('stores.list_title') }}</h5>
            <Link v-if="can.create" :href="route('stores.create')" class="btn btn-success btn-sm">
              <i class="ri-add-line align-bottom me-1"></i> {{ $t('stores.create_button') }}
            </Link>
          </BCardHeader>

          <BCardBody>
            <p class="text-muted">{{ $t('stores.list_hint') }}</p>

            <BRow class="g-3">
              <BCol v-for="store in stores" :key="store.id" md="6" xl="4">
                <div
                  class="card border h-100 mb-0"
                  :class="{ 'border-primary': store.id === activeStore?.id }"
                >
                  <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                      <div class="store-card-logo rounded d-flex align-items-center justify-content-center">
                        <img
                          v-if="store.logo_url"
                          :src="store.logo_url"
                          :alt="store.name"
                          class="store-card-image"
                        />
                        <span v-else class="fw-semibold text-primary">{{ initials(store.name) }}</span>
                      </div>

                      <div class="flex-grow-1 overflow-hidden">
                        <h6 class="mb-1 text-truncate">{{ store.name }}</h6>
                        <p class="text-muted mb-0 fs-12 text-truncate">
                          {{ store.category || $t('stores.no_category') }}
                        </p>
                      </div>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mb-3">
                      <span v-if="store.is_default" class="badge bg-primary-subtle text-primary">
                        {{ $t('stores.badges.default') }}
                      </span>
                      <span v-if="store.id === activeStore?.id" class="badge bg-success-subtle text-success">
                        {{ $t('stores.badges.active_session') }}
                      </span>
                      <span v-if="!store.is_active" class="badge bg-danger-subtle text-danger">
                        {{ $t('common.inactive') }}
                      </span>
                    </div>

                    <ul class="list-unstyled text-muted fs-13 mb-3">
                      <li class="mb-1">
                        <i class="ri-shopping-basket-2-line align-bottom me-1"></i>
                        {{ $t('stores.orders_count', { count: store.orders_count ?? 0 }) }}
                      </li>
                      <li v-if="store.city">
                        <i class="ri-map-pin-line align-bottom me-1"></i> {{ store.city.name }}
                      </li>
                    </ul>

                    <div class="hstack gap-2">
                      <BButton
                        v-if="store.id !== activeStore?.id && store.is_active"
                        variant="soft-primary"
                        size="sm"
                        @click="switchStore(store.id)"
                      >
                        <i class="ri-arrow-left-right-line align-bottom me-1"></i>
                        {{ $t('stores.switch_to') }}
                      </BButton>
                      <Link
                        :href="route('stores.edit', store.id)"
                        class="btn btn-sm btn-light ms-auto"
                      >
                        <i class="ri-settings-3-line align-bottom me-1"></i> {{ $t('common.edit') }}
                      </Link>
                    </div>
                  </div>
                </div>
              </BCol>
            </BRow>

            <div v-if="!stores.length" class="text-center py-5">
              <i class="ri-store-2-line fs-1 text-muted"></i>
              <p class="text-muted mt-2 mb-0">{{ $t('stores.empty') }}</p>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.store-card-logo {
  height: 48px;
  width: 48px;
  flex-shrink: 0;
  background-color: rgba(var(--vz-primary-rgb), 0.12);
}

.store-card-image {
  max-height: 40px;
  max-width: 40px;
  object-fit: contain;
}
</style>
