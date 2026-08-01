<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import getChartColorsArray from '@/common/getChartColorsArray';
import { formatMoneyRounded } from '@/common/formatMoney';

/**
 * The one figure the dashboard exists to answer, given the coloured panel.
 *
 * For a COD network that figure is the cash still owed by the field, so it
 * leads the grid while every other metric is relegated to a white card. The
 * mobile hero makes the same call with the same gradient — a phone and a
 * desktop should not disagree about what matters most.
 *
 * The line behind the amount is deliberately unlabelled: at this size it is
 * read as a direction, not as values, and the panel below spells the numbers
 * out anyway.
 */
const props = defineProps({
  label: { type: String, required: true },
  amount: { type: [Number, String], default: 0 },
  currency: { type: String, default: 'MAD' },
  periodLabel: { type: String, default: '' },
  /** Daily order counts, drawn as the backdrop line. */
  series: { type: Array, default: () => [] },
  /**
   * @type {import('vue').PropType<Array<{
   *   key: string, label: string, value: string, href?: string
   * }>>}
   */
  stats: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const formattedAmount = computed(() => formatMoneyRounded(props.amount));

const hasSpark = computed(() => props.series.some((value) => Number(value) > 0));

const sparkSeries = computed(() => [{ name: props.label, data: props.series }]);

const sparkOptions = {
  chart: {
    type: 'area',
    height: 90,
    sparkline: { enabled: true },
    animations: { speed: 500 },
  },
  colors: ['#ffffff'],
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0, stops: [0, 100] },
  },
  tooltip: { enabled: false },
  // The panel is a backdrop, not a chart: hover affordances would promise an
  // interaction the figures below already provide.
  states: { hover: { filter: { type: 'none' } }, active: { filter: { type: 'none' } } },
};

// Referenced so the theme's palette stays the single source of the accent even
// though the panel paints its own gradient.
const [accent] = getChartColorsArray('["--vz-secondary"]');
</script>

<template>
  <section class="ddash-hero" :style="{ '--ddash-hero-glow': accent }">
    <div class="ddash-hero-head">
      <p class="ddash-hero-label">{{ label }}</p>
      <span v-if="periodLabel" class="ddash-hero-chip">{{ periodLabel }}</span>
    </div>

    <div v-if="loading" class="ddash-hero-skeleton" aria-hidden="true"></div>
    <p v-else class="ddash-hero-amount">
      {{ formattedAmount }}<span class="ddash-hero-currency">{{ currency }}</span>
    </p>

    <div class="ddash-hero-spark">
      <apexchart
        v-if="hasSpark && !loading"
        type="area"
        height="90"
        :series="sparkSeries"
        :options="sparkOptions"
      />
    </div>

    <div class="ddash-hero-stats">
      <component
        :is="stat.href ? Link : 'div'"
        v-for="stat in stats"
        :key="stat.key"
        :href="stat.href"
        class="ddash-hero-stat"
      >
        <span class="ddash-hero-stat-label">{{ stat.label }}</span>
        <span class="ddash-hero-stat-value">{{ loading ? '—' : stat.value }}</span>
      </component>
    </div>
  </section>
</template>

<style scoped>
.ddash-hero {
  position: relative;
  display: flex;
  overflow: hidden;
  height: 100%;
  flex-direction: column;
  padding: 1.375rem 1.5rem 1.25rem;
  border-radius: var(--ddash-radius, 1.25rem);
  background: linear-gradient(140deg, #1b62c4 0%, #0d4a9d 52%, #08356f 100%);
  color: #fff;
}

/* Soft light source in the top-right, so the panel is not a flat rectangle. */
.ddash-hero::after {
  position: absolute;
  top: -40%;
  right: -18%;
  width: 20rem;
  height: 20rem;
  border-radius: 50%;
  background: radial-gradient(circle, var(--ddash-hero-glow, #f15a24) 0%, rgba(241, 90, 36, 0) 68%);
  content: '';
  opacity: 0.38;
  pointer-events: none;
}

.ddash-hero-head,
.ddash-hero-amount,
.ddash-hero-spark,
.ddash-hero-stats {
  position: relative;
  z-index: 1;
}

.ddash-hero-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.ddash-hero-label {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.ddash-hero-chip {
  flex-shrink: 0;
  padding: 0.1875rem 0.625rem;
  border-radius: 999px;
  background-color: rgba(255, 255, 255, 0.16);
  font-size: 0.6875rem;
  font-weight: 600;
  white-space: nowrap;
}

.ddash-hero-amount {
  display: flex;
  align-items: baseline;
  margin: 0.875rem 0 0;
  font-size: 2.75rem;
  font-weight: 700;
  letter-spacing: -0.035em;
  line-height: 1.05;
}

.ddash-hero-currency {
  margin-left: 0.4375rem;
  font-size: 1rem;
  font-weight: 600;
  opacity: 0.75;
}

.ddash-hero-skeleton {
  width: 11rem;
  height: 2.875rem;
  margin-top: 0.875rem;
  border-radius: 0.5rem;
  background-color: rgba(255, 255, 255, 0.2);
  animation: ddash-pulse 1.4s ease-in-out infinite;
}

/* Reserves the line's height whether or not there is a line, so the footer
   stats sit at the same place as the sibling panel's own footer. */
.ddash-hero-spark {
  min-height: 5.625rem;
  flex-grow: 1;
  margin: 0 -0.5rem;
}

.ddash-hero-stats {
  display: flex;
  margin-top: 0.75rem;
  gap: 0.5rem;
}

.ddash-hero-stat {
  min-width: 0;
  flex: 1 1 0;
  color: inherit;
  text-decoration: none;
}

.ddash-hero-stat-label,
.ddash-hero-stat-value {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-hero-stat-label {
  color: rgba(255, 255, 255, 0.72);
  font-size: 0.75rem;
}

.ddash-hero-stat-value {
  margin-top: 0.125rem;
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

@keyframes ddash-pulse {
  50% {
    opacity: 0.45;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ddash-hero-skeleton {
    animation: none;
  }
}
</style>
