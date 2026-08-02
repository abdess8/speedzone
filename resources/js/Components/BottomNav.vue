<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import { resolveMobileTabs } from '@/navigation/menuItems';
import BottomSheet from './BottomSheet.vue';

/**
 * Native-feeling tab bar that replaces the off-canvas sidebar on phones.
 *
 * An off-canvas drawer costs two taps and a reach to the top-left corner before
 * a driver can even see where he is going. A fixed bar keeps the four or five
 * destinations that matter permanently in thumb reach, and the entries that do
 * not fit move into the overflow sheet rather than disappearing.
 *
 * Shown below `md`, the breakpoint at which the sidebar is already off screen,
 * so the two are never visible together.
 *
 * Tabs are resolved from the same navigation definition and the same permission
 * context as the desktop sidebar, so the two can never disagree.
 */
const page = usePage();
const { navigationContext } = usePermissions();

const tabs = computed(() => resolveMobileTabs(navigationContext.value));

/** Tab whose group/overflow sheet is open, if any. */
const openTab = ref(null);
/**
 * Group opened from *inside* the sheet, e.g. Settings or Invoices.
 *
 * The sidebar renders those as collapsibles and they carry no URL of their own,
 * so the sheet has to expose their children rather than try to navigate to them.
 */
const drilldown = ref(null);

/** Whichever level the sheet is currently showing. */
const sheetGroup = computed(() => drilldown.value ?? openTab.value);
/** Key of the tab being pressed, for the tactile press-down animation. */
const pressedKey = ref(null);

const currentPath = ref('/');

/** Inertia visits never remount this bar, so the active tab is synced by event. */
let stopNavigateListener = null;

function syncPath() {
  currentPath.value = window.location.pathname;
}

/**
 * The stylesheet keys the page bottom padding and the hidden hamburger off this
 * class. It is only set once a usable bar exists: a user whose permissions
 * collapse the bar to a single tab keeps the drawer trigger instead.
 */
watch(
  () => tabs.value.length > 1,
  (enabled) => {
    document.body.classList.toggle('has-bottom-nav', enabled);
  }
);

onMounted(() => {
  syncPath();
  document.body.classList.toggle('has-bottom-nav', tabs.value.length > 1);

  stopNavigateListener = router.on('navigate', () => {
    syncPath();
    closeSheet();
  });
});

onBeforeUnmount(() => {
  stopNavigateListener?.();
  document.body.classList.remove('has-bottom-nav');
});

/**
 * Every destination a tab leads to, including through nested groups — the
 * overflow tab holds collapsibles whose own children carry the URLs.
 */
function destinationsOf(tab) {
  const collect = (items) =>
    items.flatMap((item) => (item.children ? collect(item.children) : [item.href]));

  return (tab.children ? collect(tab.children) : [tab.href]).filter(Boolean);
}

function coversPath(tab) {
  return destinationsOf(tab).some((path) => {
    if (path === '/') {
      return currentPath.value === '/';
    }

    // Also matches detail screens, so a section stays lit while browsing into it.
    return currentPath.value === path || currentPath.value.startsWith(`${path}/`);
  });
}

/**
 * At most one tab is lit. A path can legitimately belong to two tabs — a seller's
 * "Cash" tab resolves to /invoices, which the overflow tab also lists — and the
 * first match wins, so the specific tab beats the catch-all declared after it.
 */
const activeKey = computed(() => tabs.value.find(coversPath)?.key ?? null);

function isActive(tab) {
  return tab.key === activeKey.value;
}

/**
 * Unread notifications ride on the profile-adjacent overflow tab, mirroring the
 * dot the topbar bell shows on desktop.
 */
const unreadCount = computed(() => page.props.notifications?.unread_count ?? 0);

function badgeFor(tab) {
  return tab.overflow ? unreadCount.value : 0;
}

/**
 * Confirm the touch on devices that support it. Wrapped because iOS Safari has
 * no Vibration API and would throw on the bare call.
 */
function haptic() {
  if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
    navigator.vibrate(8);
  }
}

function press(tab) {
  pressedKey.value = tab.key;
  window.setTimeout(() => {
    pressedKey.value = null;
  }, 180);
}

function closeSheet() {
  openTab.value = null;
  drilldown.value = null;
}

function select(tab) {
  press(tab);
  haptic();

  if (tab.children) {
    drilldown.value = null;
    openTab.value = tab;

    return;
  }

  if (currentPath.value !== tab.href) {
    router.visit(tab.href);
  }
}

function go(item) {
  haptic();

  if (item.children) {
    drilldown.value = item;

    return;
  }

  closeSheet();
  router.visit(item.route ? route(item.route) : item.href);
}
</script>

<template>
  <nav v-if="tabs.length > 1" class="bottom-nav d-md-none" :aria-label="$t('sidebar.bottom_nav.label')">
    <div class="bottom-nav-bar">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="bottom-nav-tab"
        :class="{
          'bottom-nav-tab-active': isActive(tab),
          'bottom-nav-tab-pressed': pressedKey === tab.key,
        }"
        :aria-current="isActive(tab) ? 'page' : undefined"
        @click="select(tab)"
      >
        <span class="bottom-nav-icon">
          <i :class="isActive(tab) ? tab.activeIcon : tab.icon"></i>
          <span v-if="badgeFor(tab) > 0" class="bottom-nav-dot" aria-hidden="true"></span>
        </span>
        <span class="bottom-nav-label">{{ $t(tab.labelKey) }}</span>
      </button>
    </div>
  </nav>

  <!-- Group and overflow tabs open a sheet instead of navigating: they stand for
       a set of destinations, not for one. Entries inside can be groups too, so
       the sheet browses one level deeper rather than opening a second sheet. -->
  <BottomSheet
    :show="sheetGroup !== null"
    :title="sheetGroup ? $t(sheetGroup.labelKey) : ''"
    @close="closeSheet"
  >
    <template #header>
      <div class="d-flex align-items-center gap-1">
        <button
          v-if="drilldown"
          type="button"
          class="btn btn-ghost-secondary btn-icon"
          :aria-label="$t('common.back')"
          @click="drilldown = null"
        >
          <i class="ri-arrow-left-s-line fs-20"></i>
        </button>
        <h5 class="flex-grow-1 mb-0 fs-15 fw-semibold text-truncate">
          {{ sheetGroup ? $t(sheetGroup.labelKey) : '' }}
        </h5>
        <!-- Balances the back button so the title stays optically centred. -->
        <span v-if="drilldown" class="btn btn-icon invisible" aria-hidden="true"></span>
      </div>
    </template>

    <div class="d-grid gap-2">
      <button
        v-for="item in sheetGroup?.children ?? []"
        :key="item.key"
        type="button"
        class="btn btn-light text-start bottom-nav-sheet-item"
        @click="go(item)"
      >
        <i v-if="item.icon" :class="item.icon" class="fs-18 me-2 align-middle"></i>
        <span class="fw-medium">{{ $t(item.labelKey) }}</span>
        <i class="ri-arrow-right-s-line float-end fs-18"></i>
      </button>
    </div>
  </BottomSheet>
</template>

<style scoped>
.bottom-nav {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1040;
  padding: 0 0.75rem calc(0.75rem + env(safe-area-inset-bottom, 0px));
  pointer-events: none;
}

.bottom-nav-bar {
  display: flex;
  align-items: stretch;
  justify-content: space-around;
  border-radius: 1.15rem;
  background-color: var(--vz-card-bg, #fff);
  box-shadow: 0 -2px 10px rgba(56, 65, 74, 0.06), 0 8px 24px rgba(56, 65, 74, 0.14);
  pointer-events: auto;
}

.bottom-nav-tab {
  display: flex;
  min-width: 0;
  min-height: 56px;
  flex: 1 1 0;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.125rem;
  padding: 0.5rem 0.25rem;
  border: 0;
  background: transparent;
  color: var(--vz-secondary-color, #878a99);
  transition: color 0.15s ease, transform 0.18s ease;
}

/* Stands in for the haptic tap on devices without a vibration motor. */
.bottom-nav-tab-pressed {
  transform: scale(0.92);
}

.bottom-nav-icon {
  position: relative;
  display: inline-flex;
  width: 2.25rem;
  height: 2.25rem;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  font-size: 1.25rem;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.bottom-nav-tab-active {
  color: var(--vz-primary, #0d4a9d);
}

/* The active tab reads as a filled pill, which survives a glance in sunlight
   far better than a colour change on a thin outline icon. */
.bottom-nav-tab-active .bottom-nav-icon {
  background-color: var(--vz-primary, #0d4a9d);
  color: #fff;
}

.bottom-nav-label {
  max-width: 100%;
  overflow: hidden;
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1.1;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.bottom-nav-dot {
  position: absolute;
  top: 0.125rem;
  right: 0.125rem;
  width: 0.5rem;
  height: 0.5rem;
  border: 2px solid var(--vz-card-bg, #fff);
  border-radius: 50%;
  background-color: var(--vz-danger, #f06548);
}

.bottom-nav-sheet-item {
  min-height: 48px;
}

@media (prefers-reduced-motion: reduce) {
  .bottom-nav-tab,
  .bottom-nav-icon {
    transition: none;
  }

  .bottom-nav-tab-pressed {
    transform: none;
  }
}
</style>
