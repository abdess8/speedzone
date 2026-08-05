<script setup>
import { computed } from 'vue';
import { useStore } from 'vuex';

/**
 * Switches the application chrome between a left sidebar and a horizontal top
 * navigation bar.
 *
 * Both modes render the exact same menu tree from `menuItems.js`; only the
 * stylesheet changes, keyed off `data-layout` on the document element. The
 * choice is a personal preference rather than an account setting, so it lives
 * in the `theme-customizer` localStorage entry alongside the colour mode.
 */
const store = useStore();

const MODES = [
  { value: 'vertical', labelKey: 'navbar.nav_mode.sidebar', icon: 'ri-layout-left-line' },
  { value: 'horizontal', labelKey: 'navbar.nav_mode.topnav', icon: 'ri-layout-top-line' },
];

const current = computed(() => store.state.layout.layoutType);

const activeMode = computed(
  () => MODES.find((mode) => mode.value === current.value) ?? MODES[0]
);

function select(mode) {
  if (mode.value === current.value) {
    return;
  }

  store.dispatch('layout/changeLayoutType', { layoutType: mode.value });
}
</script>

<template>
  <BDropdown
    class="ms-1 header-item d-none d-sm-flex"
    variant="ghost-secondary"
    toggle-class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle arrow-none"
    menu-class="dropdown-menu-end"
    :title="$t('navbar.nav_mode.label')"
  >
    <template #button-content>
      <i :class="activeMode.icon" class="fs-22"></i>
    </template>

    <h6 class="dropdown-header">{{ $t('navbar.nav_mode.label') }}</h6>

    <BLink
      v-for="mode in MODES"
      :key="mode.value"
      href="javascript:void(0);"
      class="dropdown-item d-flex align-items-center"
      :class="{ active: current === mode.value }"
      @click="select(mode)"
    >
      <i :class="mode.icon" class="fs-16 me-2"></i>
      <span class="align-middle flex-grow-1">{{ $t(mode.labelKey) }}</span>
      <i v-if="current === mode.value" class="ri-check-line fs-16 ms-2"></i>
    </BLink>
  </BDropdown>
</template>
