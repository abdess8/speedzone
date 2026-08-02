<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * A single figure and how far along it is, as one short card.
 *
 * The bar is not decoration: each of these metrics is a part of a whole the
 * reader already knows — a success rate out of every delivery attempt, cash
 * collected out of cash owed — and the fill says how much of that whole has
 * been covered without a second number to compare against.
 */
const props = defineProps({
  value: { type: String, required: true },
  label: { type: String, required: true },
  caption: { type: String, default: '' },
  /** Fill of the bar, 0–100. */
  percent: { type: Number, default: 0 },
  tone: { type: String, default: 'primary' },
  href: { type: String, default: '' },
  loading: { type: Boolean, default: false },
});

const width = computed(() => `${Math.min(100, Math.max(0, Math.round(props.percent)))}%`);
</script>

<template>
  <component :is="href ? Link : 'div'" :href="href || undefined" class="ddash-metric">
    <span class="ddash-metric-badge" :class="`bg-${tone}-subtle text-${tone}`">
      {{ loading ? '—' : value }}
    </span>

    <span class="ddash-metric-main">
      <span class="ddash-metric-label">{{ label }}</span>

      <span class="ddash-metric-track">
        <span class="ddash-metric-fill" :class="`bg-${tone}`" :style="{ width: loading ? '0%' : width }"></span>
      </span>

      <span v-if="caption" class="ddash-metric-caption">{{ caption }}</span>
    </span>
  </component>
</template>

<style scoped>
.ddash-metric {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-radius: var(--ddash-radius, 1.25rem);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--ddash-shadow, 0 1px 2px rgba(13, 42, 77, 0.08));
  color: inherit;
  text-decoration: none;
}

.ddash-metric-badge {
  display: inline-flex;
  min-width: 4.5rem;
  height: 3.5rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  padding: 0 0.625rem;
  border-radius: 1rem;
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.ddash-metric-main {
  display: block;
  min-width: 0;
  flex-grow: 1;
}

.ddash-metric-label {
  display: block;
  overflow: hidden;
  color: var(--vz-heading-color, #495057);
  font-size: 0.875rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-metric-track {
  display: block;
  overflow: hidden;
  height: 0.5rem;
  margin-top: 0.5rem;
  border-radius: 999px;
  background-color: var(--vz-light, #f3f6f9);
}

.ddash-metric-fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  transition: width 0.4s ease;
}

.ddash-metric-caption {
  display: block;
  overflow: hidden;
  margin-top: 0.3125rem;
  color: var(--ddash-muted, #878a99);
  font-size: 0.6875rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .ddash-metric-fill {
    transition: none;
  }
}
</style>
