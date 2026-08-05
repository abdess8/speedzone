<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import { resolveFooterItems, resolveMenuSections } from '@/navigation/menuItems';

/**
 * Horizontal navigation bar, in the Salesforce mould.
 *
 * The sidebar markup cannot simply be laid out sideways: it is a scrolling
 * column with a caption per group and an identity block on top, and none of
 * that survives the rotation. So the bar is its own component and reads the
 * same permission-filtered tree from `menuItems.js` — the two presentations can
 * disagree about looks, never about what a role may reach.
 *
 * A bar is a fixed width holding a variable number of destinations, and the one
 * thing it must never do is wrap onto a second line. Whatever does not fit
 * moves into "More", measured for real rather than guessed at a breakpoint,
 * because the tab set depends on the role and the labels on the locale.
 */
const page = usePage();
const { navigationContext } = usePermissions();

/** Grouped for the launcher panel; flattened for the bar itself. */
const sections = computed(() => {
  const context = navigationContext.value;
  const pinned = resolveFooterItems(context);

  return [
    ...resolveMenuSections(context),
    ...(pinned.length > 0 ? [{ key: '__pinned', labelKey: null, items: pinned }] : []),
  ];
});

const tabs = computed(() => sections.value.flatMap((section) => section.items));

function itemHref(item) {
  return item.route ? route(item.route) : item.href;
}

const currentPath = computed(() => page.url.split('?')[0]);

/**
 * How well an entry matches the current URL: -1 for no match, otherwise the
 * number of query parameters it pinned down.
 *
 * Several entries share a path and differ only by a filter — the order views
 * are all `/orders?status_group=…` — so the most specific match wins and "All"
 * does not light up next to the view actually on screen.
 */
function matchScore(item) {
  const target = itemHref(item);

  if (!target) {
    return -1;
  }

  const [path, query] = target.split('?');

  if (path !== currentPath.value) {
    return -1;
  }

  if (!query) {
    return 0;
  }

  const current = new URLSearchParams(page.url.split('?')[1] ?? '');
  const wanted = [...new URLSearchParams(query)];

  return wanted.every(([key, value]) => current.get(key) === value) ? wanted.length : -1;
}

function activeChildKey(item) {
  let best = null;
  let bestScore = -1;

  for (const child of item.children ?? []) {
    const score = matchScore(child);

    if (score > bestScore) {
      bestScore = score;
      best = child.key;
    }
  }

  return bestScore >= 0 ? best : null;
}

function isActive(item) {
  return item.children ? activeChildKey(item) !== null : matchScore(item) >= 0;
}

// --- Overflow ---------------------------------------------------------------

const bar = ref(null);
const tabList = ref(null);
const moreEl = ref(null);
const tabEls = [];

/** Cached tab widths, refreshed whenever the labels or the tab set change. */
const widths = ref([]);
/** Renders every tab for one frame so the widths can be read off the DOM. */
const measuring = ref(false);
const visibleCount = ref(0);

const overflowTabs = computed(() => tabs.value.slice(visibleCount.value));

function setTabEl(el, index) {
  tabEls[index] = el;
}

function layout() {
  const available = tabList.value?.clientWidth ?? 0;
  const total = widths.value.reduce((sum, width) => sum + width, 0);

  if (total <= available) {
    visibleCount.value = widths.value.length;

    return;
  }

  // "More" has to fit too. It is still hidden the first time round — that is
  // the very call deciding it is needed — so fall back to a roomy estimate.
  const budget = available - (moreEl.value?.offsetWidth || 110);
  let used = 0;
  let count = 0;

  for (const width of widths.value) {
    if (used + width > budget) {
      break;
    }

    used += width;
    count += 1;
  }

  visibleCount.value = count;
}

async function measure() {
  measuring.value = true;
  await nextTick();

  widths.value = tabs.value.map((_, index) => tabEls[index]?.offsetWidth ?? 0);

  measuring.value = false;
  await nextTick();

  layout();
}

/**
 * Widths read while the bar was hidden — on a phone, or before the layout
 * settled — are all zero and would let every tab claim it fits.
 */
function refresh() {
  if (tabList.value?.clientWidth) {
    if (widths.value.length === tabs.value.length && !widths.value.includes(0)) {
      layout();
    } else {
      measure();
    }
  }
}

let observer = null;

onMounted(() => {
  refresh();

  observer = new ResizeObserver(() => refresh());
  observer.observe(bar.value);
});

onBeforeUnmount(() => {
  observer?.disconnect();
  observer = null;
});

// Both the role (a different tab set) and the locale (different label widths)
// invalidate the cached measurements.
watch(
  () => [tabs.value.map((tab) => tab.key).join('|'), page.props.locale],
  () => {
    widths.value = [];
    refresh();
  }
);
</script>

<template>
  <nav ref="bar" class="app-topnav d-none d-md-block">
    <div class="app-topnav-inner">
      <BDropdown
        variant="link"
        class="app-topnav-launcher"
        toggle-class="app-topnav-launcher-btn arrow-none"
        menu-class="app-launcher-menu"
        :title="$t('navbar.app_launcher')"
      >
        <template #button-content>
          <i class="ri-apps-2-line" aria-hidden="true"></i>
          <span class="visually-hidden">{{ $t('navbar.app_launcher') }}</span>
        </template>

        <div class="app-launcher-grid">
          <div v-for="section in sections" :key="section.key" class="app-launcher-group">
            <h6 v-if="section.labelKey" class="app-launcher-caption">{{ $t(section.labelKey) }}</h6>

            <template v-for="item in section.items" :key="item.key">
              <Link
                v-if="!item.children"
                :href="itemHref(item)"
                class="app-launcher-link"
                :class="{ active: isActive(item) }"
              >
                <i :class="item.icon"></i>
                <span>{{ $t(item.labelKey) }}</span>
              </Link>

              <template v-else>
                <span class="app-launcher-link app-launcher-parent">
                  <i :class="item.icon"></i>
                  <span>{{ $t(item.labelKey) }}</span>
                </span>
                <Link
                  v-for="child in item.children"
                  :key="child.key"
                  :href="itemHref(child)"
                  class="app-launcher-link app-launcher-child"
                  :class="{ active: matchScore(child) >= 0 }"
                >
                  {{ $t(child.labelKey) }}
                </Link>
              </template>
            </template>
          </div>
        </div>
      </BDropdown>

      <span class="app-topnav-app">{{ $t('navbar.app_name') }}</span>

      <ul
        ref="tabList"
        class="app-topnav-tabs"
        :class="{ 'app-topnav-tabs-measuring': measuring }"
      >
        <li
          v-for="(tab, index) in tabs"
          :key="tab.key"
          :ref="(el) => setTabEl(el, index)"
          class="app-topnav-item"
          :class="{ 'app-topnav-item-hidden': !measuring && index >= visibleCount }"
        >
          <Link
            v-if="!tab.children"
            :href="itemHref(tab)"
            class="app-topnav-tab"
            :class="{ active: isActive(tab) }"
          >
            {{ $t(tab.labelKey) }}
          </Link>

          <BDropdown
            v-else
            variant="link"
            class="app-topnav-dropdown"
            :toggle-class="['app-topnav-tab', 'arrow-none', { active: isActive(tab) }]"
          >
            <template #button-content>
              {{ $t(tab.labelKey) }}
              <i class="ri-arrow-down-s-line app-topnav-caret" aria-hidden="true"></i>
            </template>

            <Link
              v-for="child in tab.children"
              :key="child.key"
              :href="itemHref(child)"
              class="dropdown-item"
              :class="{ active: activeChildKey(tab) === child.key }"
            >
              {{ $t(child.labelKey) }}
            </Link>
          </BDropdown>
        </li>

        <li v-show="overflowTabs.length > 0" ref="moreEl" class="app-topnav-item">
          <BDropdown
            variant="link"
            class="app-topnav-dropdown"
            menu-class="dropdown-menu-end app-topnav-more-menu"
            :toggle-class="['app-topnav-tab', 'arrow-none', { active: overflowTabs.some(isActive) }]"
          >
            <template #button-content>
              {{ $t('navbar.more') }}
              <i class="ri-arrow-down-s-line app-topnav-caret" aria-hidden="true"></i>
            </template>

            <template v-for="tab in overflowTabs" :key="tab.key">
              <Link
                v-if="!tab.children"
                :href="itemHref(tab)"
                class="dropdown-item"
                :class="{ active: isActive(tab) }"
              >
                <i :class="tab.icon" class="me-2"></i>{{ $t(tab.labelKey) }}
              </Link>

              <template v-else>
                <h6 class="dropdown-header">
                  <i :class="tab.icon" class="me-1"></i>{{ $t(tab.labelKey) }}
                </h6>
                <Link
                  v-for="child in tab.children"
                  :key="child.key"
                  :href="itemHref(child)"
                  class="dropdown-item ps-4"
                  :class="{ active: activeChildKey(tab) === child.key }"
                >
                  {{ $t(child.labelKey) }}
                </Link>
              </template>
            </template>
          </BDropdown>
        </li>
      </ul>
    </div>
  </nav>
</template>
