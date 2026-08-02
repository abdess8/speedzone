<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Secondary metrics, as a strip that scrolls sideways under the hero panel.
 *
 * Stacking them vertically would push everything else below the fold; a strip
 * keeps them one gesture away and, because the third card is deliberately cut
 * off at the edge, makes it obvious that the gesture exists.
 *
 * Each card carries a caption rather than a period-over-period delta: the API
 * returns a single window, so a trend arrow here would be invented.
 */
defineProps({
  /**
   * @type {import('vue').PropType<Array<{
   *   key: string, label: string, value: string, caption: string,
   *   tone: string, icon: string, href?: string
   * }>>}
   */
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <div class="mdash-strip" role="list">
    <template v-if="loading">
      <div v-for="n in 3" :key="n" class="mdash-stat mdash-stat-skeleton" aria-hidden="true"></div>
    </template>

    <template v-else>
      <component
        :is="item.href ? Link : 'div'"
        v-for="item in items"
        :key="item.key"
        :href="item.href"
        role="listitem"
        class="mdash-stat"
      >
        <span class="mdash-stat-icon" :class="`bg-${item.tone}-subtle text-${item.tone}`">
          <i :class="item.icon"></i>
        </span>
        <span class="mdash-stat-label">{{ item.label }}</span>
        <span class="mdash-stat-value">{{ item.value }}</span>
        <span class="mdash-stat-caption" :class="`text-${item.tone}`">{{ item.caption }}</span>
      </component>
    </template>
  </div>
</template>

<style scoped>
.mdash-strip {
  display: flex;
  overflow-x: auto;
  gap: 0.75rem;
  /* Cancels the page gutter so the first card lines up with the cards below
     while the last one can still scroll past the screen edge. */
  margin: 0 calc(var(--mdash-gutter) * -1);
  /* `overflow-x: auto` also clips the cross axis, so the cards' shadows need
     room inside the scroller or they are shaved off top and bottom. The parent
     folds this padding back into the lift that overlaps the hero. */
  padding: 0.875rem var(--mdash-gutter) 1.5rem;
  scroll-padding-left: var(--mdash-gutter);
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.mdash-strip::-webkit-scrollbar {
  display: none;
}

.mdash-stat {
  display: flex;
  min-width: 8.5rem;
  flex: 0 0 44%;
  flex-direction: column;
  padding: 0.875rem;
  border-radius: var(--mdash-radius);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--mdash-shadow);
  color: inherit;
  scroll-snap-align: start;
  text-decoration: none;
}

.mdash-stat-icon {
  display: inline-flex;
  width: 1.875rem;
  height: 1.875rem;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.5rem;
  border-radius: 0.625rem;
  font-size: 1rem;
}

.mdash-stat-label {
  color: var(--mdash-muted);
  font-size: 0.75rem;
}

.mdash-stat-value {
  margin-top: 0.0625rem;
  color: var(--vz-heading-color, #495057);
  font-size: 1.375rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.mdash-stat-caption {
  overflow: hidden;
  margin-top: 0.125rem;
  font-size: 0.6875rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-stat-skeleton {
  height: 7rem;
  animation: mdash-pulse 1.4s ease-in-out infinite;
}

@keyframes mdash-pulse {
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .mdash-stat-skeleton {
    animation: none;
  }
}
</style>
