<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SectionCard from './SectionCard.vue';

/**
 * Where the period's orders ended up, as three figures over one bar.
 *
 * A donut would answer the same question but costs a legend and a colour
 * lookup; a single stacked bar reads as "how much of the work is done" in the
 * time it takes to glance, which is the only question worth asking here.
 */
const props = defineProps({
  title: { type: String, required: true },
  href: { type: String, default: '' },
  /** @type {import('vue').PropType<Array<{key: string, label: string, value: number}>>} */
  columns: { type: Array, default: () => [] },
  /** @type {import('vue').PropType<Array<{key: string, value: number, tone: string}>>} */
  segments: { type: Array, default: () => [] },
  footnote: { type: String, default: '' },
  footnoteTone: { type: String, default: 'success' },
  footnoteIcon: { type: String, default: 'ri-checkbox-circle-fill' },
  emptyLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
});

const total = computed(() =>
  props.segments.reduce((sum, segment) => sum + (Number(segment.value) || 0), 0)
);

/**
 * Segments carrying a real but tiny share would otherwise collapse into an
 * invisible sliver, so each visible one keeps a minimum width and the rest of
 * the bar is shared out proportionally.
 */
const bars = computed(() => {
  if (total.value <= 0) {
    return [];
  }

  const present = props.segments.filter((segment) => Number(segment.value) > 0);
  const floor = 4;
  const spare = 100 - present.length * floor;

  return present.map((segment) => ({
    ...segment,
    width: floor + (Number(segment.value) / total.value) * spare,
  }));
});
</script>

<template>
  <SectionCard :title="title">
    <template v-if="href" #action>
      <Link :href="href" class="mdash-overview-more" :aria-label="title">
        <i class="ri-arrow-right-s-line"></i>
      </Link>
    </template>

    <div v-if="loading" class="mdash-overview-skeleton" aria-hidden="true"></div>

    <template v-else>
      <div class="mdash-overview-columns">
        <div v-for="column in columns" :key="column.key" class="mdash-overview-column">
          <p class="mdash-overview-label">{{ column.label }}</p>
          <p class="mdash-overview-value">{{ column.value }}</p>
        </div>
      </div>

      <div class="mdash-overview-bar" role="presentation">
        <span
          v-for="bar in bars"
          :key="bar.key"
          class="mdash-overview-segment"
          :class="`bg-${bar.tone}`"
          :style="{ width: `${bar.width}%` }"
        ></span>
      </div>

      <p v-if="total > 0" class="mdash-overview-footnote" :class="`text-${footnoteTone}`">
        <i :class="footnoteIcon"></i>
        {{ footnote }}
      </p>
      <p v-else class="mdash-overview-footnote text-muted">{{ emptyLabel }}</p>
    </template>
  </SectionCard>
</template>

<style scoped>
.mdash-overview-more {
  display: inline-flex;
  width: 1.75rem;
  height: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background-color: var(--vz-light, #f3f6f9);
  color: var(--mdash-muted);
  font-size: 1.125rem;
  text-decoration: none;
}

.mdash-overview-columns {
  display: flex;
  gap: 0.75rem;
}

.mdash-overview-column {
  min-width: 0;
  flex: 1 1 0;
}

.mdash-overview-label {
  overflow: hidden;
  margin: 0;
  color: var(--mdash-muted);
  font-size: 0.6875rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-overview-value {
  margin: 0.125rem 0 0;
  color: var(--vz-heading-color, #495057);
  font-size: 1.125rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.mdash-overview-bar {
  display: flex;
  overflow: hidden;
  gap: 0.1875rem;
  height: 0.5rem;
  margin-top: 0.875rem;
  border-radius: 999px;
  background-color: var(--vz-light, #f3f6f9);
}

.mdash-overview-segment {
  border-radius: 999px;
}

.mdash-overview-footnote {
  display: flex;
  align-items: center;
  gap: 0.3125rem;
  margin: 0.625rem 0 0;
  font-size: 0.75rem;
  font-weight: 500;
}

.mdash-overview-skeleton {
  height: 5rem;
  border-radius: 0.75rem;
  background-color: var(--vz-light, #f3f6f9);
  animation: mdash-pulse 1.4s ease-in-out infinite;
}

@keyframes mdash-pulse {
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .mdash-overview-skeleton {
    animation: none;
  }
}
</style>
