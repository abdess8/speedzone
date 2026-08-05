<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';

/**
 * Settings, as a gear in the topbar rather than a section of the navigation.
 *
 * These are the account's own knobs — who you are, what the platform tells you,
 * how it talks to your other systems — and none of them is a place you work in,
 * so none of them earns a permanent seat in the menu.
 */
const { can } = usePermissions();

const items = computed(() =>
  [
    {
      key: 'profile',
      labelKey: 'sidebar.settings.profile',
      icon: 'ri-user-settings-line',
      href: route('profile.show'),
      visible: true,
    },
    {
      key: 'alerts',
      labelKey: 'sidebar.settings.alerts',
      icon: 'ri-megaphone-line',
      href: '/alerts',
      visible: can('alerts.read'),
    },
    {
      key: 'roles',
      labelKey: 'sidebar.settings.roles_permissions',
      icon: 'ri-shield-keyhole-line',
      href: '/roles',
      visible: can('roles.read'),
    },
    {
      key: 'api-integrations',
      labelKey: 'sidebar.settings.api_integrations',
      icon: 'ri-plug-line',
      href: route('api-integrations.index'),
      visible: can('orders.create'),
    },
  ].filter((item) => item.visible)
);
</script>

<template>
  <BDropdown
    variant="ghost-secondary"
    class="ms-1 header-item d-none d-sm-flex"
    toggle-class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle arrow-none"
    menu-class="dropdown-menu-end"
    :title="$t('sidebar.settings.title')"
  >
    <template #button-content>
      <i class="bx bx-cog fs-22"></i>
    </template>

    <h6 class="dropdown-header">{{ $t('sidebar.settings.title') }}</h6>

    <Link v-for="item in items" :key="item.key" class="dropdown-item" :href="item.href">
      <i :class="item.icon" class="text-muted fs-16 align-middle me-1"></i>
      <span class="align-middle">{{ $t(item.labelKey) }}</span>
    </Link>
  </BDropdown>
</template>
