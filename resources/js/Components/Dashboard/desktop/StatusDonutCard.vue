<script setup>
import { computed } from 'vue';
import PanelCard from './PanelCard.vue';
import getChartColorsArray from '@/common/getChartColorsArray';

/**
 * Where the period's orders currently sit, as a ring with a written legend.
 *
 * A ring answers "is one status swallowing everything?" at a glance but never
 * gives a number, so the legend beside it carries the counts. Only the largest
 * few statuses are named: the tail of a status breakdown is a long list of
 * ones and twos that pushes the shape of the thing off the card. The remainder
 * is folded into a single row so the legend still adds up to the total.
 */
const props = defineProps({
  title: { type: String, required: true },
  totalLabel: { type: String, required: true },
  othersLabel: { type: String, required: true },
  labels: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  emptyLabel: { type: String, required: true },
});

const NAMED_SLICES = 4;

const palette = getChartColorsArray(
  '["--vz-primary", "--vz-success", "--vz-warning", "--vz-info", "--vz-danger", "--vz-secondary"]'
);

const slices = computed(() =>
  props.labels
    .map((label, index) => ({ label, value: Number(props.series[index]) || 0 }))
    .filter((slice) => slice.value > 0)
    .sort((a, b) => b.value - a.value)
);

const total = computed(() => slices.value.reduce((sum, slice) => sum + slice.value, 0));

const hasData = computed(() => total.value > 0);

/** The named slices, plus one row standing in for everything smaller. */
const legend = computed(() => {
  const named = slices.value.slice(0, NAMED_SLICES).map((slice, index) => ({
    ...slice,
    color: palette[index % palette.length],
  }));

  const remainder = slices.value.slice(NAMED_SLICES).reduce((sum, slice) => sum + slice.value, 0);

  if (remainder > 0) {
    named.push({ label: props.othersLabel, value: remainder, color: palette[NAMED_SLICES % palette.length] });
  }

  return named;
});

const share = (value) => (total.value ? Math.round((value / total.value) * 100) : 0);

const chartSeries = computed(() => legend.value.map((slice) => slice.value));

const chartOptions = computed(() => ({
  chart: { type: 'donut', height: 210, sparkline: { enabled: true } },
  labels: legend.value.map((slice) => slice.label),
  colors: legend.value.map((slice) => slice.color),
  stroke: { width: 0 },
  dataLabels: { enabled: false },
  legend: { show: false },
  plotOptions: { pie: { donut: { size: '72%' } } },
  tooltip: { y: { formatter: (value) => `${value}` } },
}));
</script>

<template>
  <PanelCard :title="title" fill>
    <div v-if="loading" class="ddash-donut-skeleton" aria-hidden="true"></div>

    <p v-else-if="!hasData" class="ddash-donut-empty">{{ emptyLabel }}</p>

    <div v-else class="ddash-donut">
      <div class="ddash-donut-side">
        <p class="ddash-donut-total">{{ total }}</p>
        <p class="ddash-donut-total-label">{{ totalLabel }}</p>

        <ul class="ddash-donut-legend">
          <li v-for="slice in legend" :key="slice.label">
            <span class="ddash-donut-dot" :style="{ backgroundColor: slice.color }"></span>
            <span class="ddash-donut-name">{{ slice.label }}</span>
            <span class="ddash-donut-value">{{ share(slice.value) }}%</span>
          </li>
        </ul>
      </div>

      <div class="ddash-donut-chart">
        <apexchart type="donut" height="210" :series="chartSeries" :options="chartOptions" />
      </div>
    </div>
  </PanelCard>
</template>

<style scoped>
.ddash-donut {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.ddash-donut-side {
  min-width: 0;
  flex: 1 1 0;
}

.ddash-donut-total {
  margin: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 2.125rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.ddash-donut-total-label {
  margin: 0.125rem 0 0;
  color: var(--ddash-muted, #878a99);
  font-size: 0.75rem;
}

.ddash-donut-legend {
  margin: 0.875rem 0 0;
  padding: 0;
  list-style: none;
}

.ddash-donut-legend li {
  display: flex;
  align-items: center;
  gap: 0.4375rem;
  padding: 0.1875rem 0;
  font-size: 0.75rem;
}

.ddash-donut-dot {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  border-radius: 50%;
}

.ddash-donut-name {
  overflow: hidden;
  flex-grow: 1;
  color: var(--ddash-muted, #878a99);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-donut-value {
  flex-shrink: 0;
  color: var(--vz-heading-color, #495057);
  font-weight: 600;
}

.ddash-donut-chart {
  width: 13.125rem;
  flex-shrink: 0;
}

.ddash-donut-empty {
  margin: 0;
  padding: 3.5rem 0;
  color: var(--ddash-muted, #878a99);
  font-size: 0.8125rem;
  text-align: center;
}

.ddash-donut-skeleton {
  height: 13.125rem;
  border-radius: 0.75rem;
  background-color: var(--vz-light, #f3f6f9);
  animation: ddash-pulse 1.4s ease-in-out infinite;
}

@keyframes ddash-pulse {
  50% {
    opacity: 0.5;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ddash-donut-skeleton {
    animation: none;
  }
}
</style>
