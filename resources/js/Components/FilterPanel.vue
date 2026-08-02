<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BottomSheet from './BottomSheet.vue';

/**
 * Toolbar + collapsible filter form for list screens.
 *
 * Filter forms on these screens carry a dozen inputs. Rendering them expanded
 * pushed the actual data below the fold on a laptop and off the screen entirely
 * on a phone, so the form starts hidden and the toolbar advertises how many
 * filters are currently narrowing the list.
 *
 * The panel adapts to the pointer rather than to the viewport alone: a drawer
 * under the toolbar on desktop, a bottom sheet within thumb reach on mobile.
 *
 * Usage:
 *   <FilterPanel :active-count="n" @apply="reload" @reset="clear">
 *     <template #title>…</template>
 *     <template #actions><Link …>New</Link></template>
 *     …filter fields…
 *   </FilterPanel>
 */
const props = defineProps({
  /** Number of filters currently applied; drives the badge and the clear button. */
  activeCount: { type: Number, default: 0 },
  /** Heading shown above the fields (defaults to a generic "Filters"). */
  title: { type: String, default: '' },
  /** Below this width the fields move into a bottom sheet. */
  desktopBreakpoint: { type: Number, default: 992 },
  /**
   * Name under which an interactive guide can spotlight this toolbar.
   *
   * An explicit prop because the component renders a fragment, and a plain
   * `data-guide` attribute on the tag would be silently dropped.
   */
  guide: { type: String, default: null },
});

const emit = defineEmits(['apply', 'reset']);

const { t } = useI18n();

const open = ref(false);
const isDesktop = ref(true);

let mediaQuery = null;

function syncViewport(event) {
  isDesktop.value = event.matches;
}

onMounted(() => {
  mediaQuery = window.matchMedia(`(min-width: ${props.desktopBreakpoint}px)`);
  isDesktop.value = mediaQuery.matches;
  mediaQuery.addEventListener('change', syncViewport);
});

onBeforeUnmount(() => {
  mediaQuery?.removeEventListener('change', syncViewport);
});

const heading = computed(() => props.title || t('common.filters_title'));

function toggle() {
  open.value = !open.value;
}

function apply() {
  emit('apply');
  // Mobile: the sheet covers the list, so it must get out of the way to show
  // the result. Desktop: the drawer sits above the table and can stay open for
  // successive refinements.
  if (!isDesktop.value) {
    open.value = false;
  }
}

function reset() {
  emit('reset');

  if (!isDesktop.value) {
    open.value = false;
  }
}
</script>

<template>
  <div class="card-header border-bottom-dashed" :data-guide="guide">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1 min-w-0">
        <slot name="title"></slot>
      </div>

      <slot name="actions"></slot>

      <button
        type="button"
        class="btn btn-soft-primary filter-trigger"
        :class="{ active: open }"
        :aria-expanded="open"
        @click="toggle"
      >
        <i class="ri-filter-3-line align-bottom me-1"></i>
        <span>{{ $t('common.filter') }}</span>
        <span v-if="activeCount > 0" class="badge bg-primary ms-1">{{ activeCount }}</span>
      </button>

      <!-- Escape hatch: clearing everything is the most common follow-up to a
           filtered view and should not require opening the form again. -->
      <button
        v-if="activeCount > 0"
        type="button"
        class="btn btn-ghost-danger filter-trigger"
        :title="$t('common.clear_filters')"
        @click="reset"
      >
        <i class="ri-close-circle-line align-bottom"></i>
        <span class="d-none d-sm-inline ms-1">{{ $t('common.clear_filters') }}</span>
      </button>
    </div>
  </div>

  <!-- Desktop: drawer pushed under the toolbar -->
  <Transition name="filter-drawer">
    <div v-if="isDesktop && open" class="card-body border-bottom-dashed filter-drawer">
      <BRow class="g-3">
        <slot></slot>

        <BCol cols="12">
          <div class="hstack gap-2 justify-content-end">
            <button type="button" class="btn btn-light text-nowrap" @click="reset">
              <i class="ri-refresh-line align-bottom me-1"></i> {{ $t('common.reset') }}
            </button>
            <button type="button" class="btn btn-primary text-nowrap" @click="apply">
              <i class="ri-search-line align-bottom me-1"></i> {{ $t('common.apply_filters') }}
            </button>
          </div>
        </BCol>
      </BRow>
    </div>
  </Transition>

  <!-- Mobile: the same fields inside a thumb-reachable sheet -->
  <BottomSheet v-if="!isDesktop" :show="open" :title="heading" @close="open = false">
    <BRow class="g-3">
      <slot></slot>
    </BRow>

    <template #footer>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-light filter-sheet-action" @click="reset">
          <i class="ri-refresh-line align-bottom"></i>
        </button>
        <button type="button" class="btn btn-primary flex-fill filter-sheet-action" @click="apply">
          <i class="ri-search-line align-bottom me-1"></i> {{ $t('common.apply_filters') }}
        </button>
      </div>
    </template>
  </BottomSheet>
</template>

<style scoped>
.filter-trigger {
  min-height: 40px;
  white-space: nowrap;
}

.filter-sheet-action {
  min-height: 48px;
}

.filter-drawer-enter-active,
.filter-drawer-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.filter-drawer-enter-from,
.filter-drawer-leave-to {
  opacity: 0;
  transform: translateY(-0.5rem);
}

.min-w-0 {
  min-width: 0;
}

@media (prefers-reduced-motion: reduce) {
  .filter-drawer-enter-active,
  .filter-drawer-leave-active {
    transition: none;
  }
}
</style>
