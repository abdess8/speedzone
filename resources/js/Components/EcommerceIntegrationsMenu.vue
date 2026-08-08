<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ECOMMERCE_PLATFORMS } from '@/constants/ecommercePlatforms';
import { usePermissions } from '@/composables/usePermissions';

/**
 * Shortcut to the storefront connectors, next to the notification bell.
 *
 * Hidden outright rather than disabled for accounts without the grant: the
 * route is permission-gated, so an always-visible icon would only ever lead
 * those users to a 403.
 */
const { canAny } = usePermissions();

const visible = computed(() => canAny(['integrations.read', 'integrations.manage']));
</script>

<template>
  <BDropdown
    v-if="visible"
    variant="ghost-secondary"
    class="ms-1 header-item d-none d-sm-flex"
    toggle-class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle arrow-none"
    menu-class="dropdown-menu-end p-0"
    :title="$t('integrations.title')"
  >
    <template #button-content>
      <i class="ri-store-2-line fs-22"></i>
    </template>

    <div class="p-3 border-bottom">
      <h6 class="mb-1">{{ $t('integrations.title') }}</h6>
      <p class="text-muted fs-12 mb-0">{{ $t('integrations.menu_subtitle') }}</p>
    </div>

    <Link
      v-for="platform in ECOMMERCE_PLATFORMS"
      :key="platform.key"
      class="dropdown-item d-flex align-items-center gap-2 py-2"
      :href="route('integrations.index', { platform: platform.key })"
    >
      <span class="platform-mark" :style="{ backgroundColor: platform.color }">
        <i :class="platform.icon"></i>
      </span>
      <span class="flex-grow-1 align-middle">{{ platform.name }}</span>
      <span v-if="platform.status === 'soon'" class="badge bg-warning-subtle text-warning">
        {{ $t('integrations.status.soon') }}
      </span>
    </Link>

    <div class="dropdown-divider my-0"></div>

    <Link class="dropdown-item text-center fs-13 py-2" :href="route('integrations.index')">
      {{ $t('integrations.see_all') }}
      <i class="ri-arrow-right-line align-middle ms-1"></i>
    </Link>
  </BDropdown>
</template>

<style scoped>
.platform-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 0.375rem;
  font-size: 1rem;
  /* The tile carries the brand colour, so the glyph is always drawn on it. */
  color: #fff;
}
</style>
