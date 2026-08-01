<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useStore } from '@/composables/useStore';
import { usePermissions } from '@/composables/usePermissions';

const { activeStore, availableStores, hasMultipleStores, switchStore } = useStore();
const { can } = usePermissions();

/** Nothing to show for staff accounts, which have no store context at all. */
const visible = computed(() => activeStore.value !== null);

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
  <BDropdown
    v-if="visible"
    variant="link"
    class="header-item store-switcher"
    toggle-class="arrow-none px-2"
    menu-class="dropdown-menu-end store-switcher-menu"
  >
    <template #button-content>
      <span class="d-flex align-items-center gap-2">
        <img
          v-if="activeStore.logo_url"
          :src="activeStore.logo_url"
          :alt="activeStore.name"
          class="store-switcher-logo rounded"
        />
        <span v-else class="store-switcher-initials rounded">{{ initials(activeStore.name) }}</span>

        <span class="text-start d-none d-md-inline-block">
          <span class="d-block fw-medium lh-1 store-switcher-name">{{ activeStore.name }}</span>
          <span class="d-block fs-11 text-muted">{{ $t('stores.switcher.label') }}</span>
        </span>

        <i v-if="hasMultipleStores" class="mdi mdi-chevron-down fs-16 text-muted"></i>
      </span>
    </template>

    <h6 class="dropdown-header">{{ $t('stores.switcher.heading') }}</h6>

    <BLink
      v-for="store in availableStores"
      :key="store.id"
      href="javascript:void(0);"
      class="dropdown-item d-flex align-items-center gap-2 py-2"
      :class="{ active: store.id === activeStore.id }"
      @click="switchStore(store.id)"
    >
      <img
        v-if="store.logo_url"
        :src="store.logo_url"
        :alt="store.name"
        class="store-switcher-logo rounded"
      />
      <span v-else class="store-switcher-initials rounded">{{ initials(store.name) }}</span>

      <span class="flex-grow-1">
        <span class="d-block align-middle">{{ store.name }}</span>
        <span v-if="store.category" class="d-block fs-11 text-muted">{{ store.category }}</span>
      </span>

      <i v-if="store.id === activeStore.id" class="mdi mdi-check text-success fs-16"></i>
    </BLink>

    <template v-if="can('stores.read')">
      <div class="dropdown-divider"></div>
      <Link class="dropdown-item" :href="route('stores.index')">
        <i class="mdi mdi-store-cog-outline text-muted fs-16 align-middle me-1"></i>
        <span class="align-middle">{{ $t('stores.switcher.manage') }}</span>
      </Link>
    </template>
  </BDropdown>
</template>

<style scoped>
.store-switcher-logo {
  height: 32px;
  width: 32px;
  object-fit: contain;
  background-color: var(--vz-light);
}

.store-switcher-initials {
  height: 32px;
  width: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: var(--vz-primary);
  background-color: rgba(var(--vz-primary-rgb), 0.12);
}

.store-switcher-name {
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.store-switcher-menu {
  min-width: 260px;
}
</style>
