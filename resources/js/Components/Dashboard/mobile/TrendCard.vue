<script setup>
import { computed } from 'vue';
import SectionCard from './SectionCard.vue';
import getChartColorsArray from '@/common/getChartColorsArray';

/**
 * Daily order volume, led by a sentence rather than by the chart.
 *
 * A 30-point line on a 360 px screen is a shape, not a reading: nobody pulls a
 * number off it. So the last day and its change are spelled out in words above,
 * and the line is left to do the one thing it is good at — showing whether the
 * shape is going up or down.
 */
const props = defineProps({
  title: { type: String, required: true },
  caption: { type: String, default: '' },
  /** Headline split in two so the figure can be emphasised on its own. */
  headline: { type: String, required: true },
  headlineSuffix: { type: String, default: '' },
  comparison: { type: String, default: '' },
  delta: { type: Number, default: null },
  deltaPercent: { type: Number, default: null },
  steadyLabel: { type: String, required: true },
  labels: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  emptyLabel: { type: String, required: true },
});

const [accent] = getChartColorsArray('["--vz-primary"]');

const hasData = computed(() => props.series.some((value) => Number(value) > 0));

const deltaTone = computed(() => {
  if (!props.delta) {
    return 'muted';
  }

  return props.delta > 0 ? 'success' : 'danger';
});

const deltaIcon = computed(() => {
  if (!props.delta) {
    return 'ri-subtract-line';
  }

  return props.delta > 0 ? 'ri-arrow-right-up-line' : 'ri-arrow-right-down-line';
});

const deltaText = computed(() => {
  if (!props.delta) {
    return props.steadyLabel;
  }

  const sign = props.delta > 0 ? '+' : '−';
  const magnitude = Math.abs(props.delta);

  if (props.deltaPercent == null) {
    return `${sign}${magnitude}`;
  }

  return `${sign}${magnitude} · ${Math.abs(props.deltaPercent)}%`;
});

const chartSeries = computed(() => [{ name: props.title, data: props.series }]);

const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 170,
    toolbar: { show: false },
    zoom: { enabled: false },
    parentHeightOffset: 0,
    animations: { easing: 'easeinout', speed: 500 },
  },
  colors: [accent],
  stroke: { curve: 'smooth', width: 2.5 },
  dataLabels: { enabled: false },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0, stops: [0, 95, 100] },
  },
  grid: {
    // ApexCharts writes this straight onto an SVG attribute, where `var()` does
    // not resolve — hence a literal rather than a theme token.
    borderColor: 'rgba(56, 65, 74, 0.08)',
    strokeDashArray: 4,
    xaxis: { lines: { show: false } },
    padding: { top: 0, right: 6, bottom: 0, left: 6 },
  },
  markers: { size: 0, hover: { size: 5 } },
  xaxis: {
    categories: props.labels,
    // Four anchors is all a phone-width axis can label without overlapping.
    tickAmount: 3,
    axisBorder: { show: false },
    axisTicks: { show: false },
    tooltip: { enabled: false },
    labels: { rotate: 0, hideOverlappingLabels: true, style: { fontSize: '10px' } },
  },
  yaxis: { show: false },
  tooltip: { marker: { show: false } },
}));
</script>

<template>
  <SectionCard :title="title" :caption="caption">
    <div v-if="loading" class="mdash-trend-skeleton" aria-hidden="true"></div>

    <template v-else-if="hasData">
      <p class="mdash-trend-headline">
        <span class="mdash-trend-figure">{{ headline }}</span>
        <span v-if="headlineSuffix" class="mdash-trend-suffix">{{ headlineSuffix }}</span>
        <span class="mdash-trend-delta" :class="`bg-${deltaTone}-subtle text-${deltaTone}`">
          <i :class="deltaIcon"></i>{{ deltaText }}
        </span>
      </p>
      <p v-if="comparison" class="mdash-trend-comparison">{{ comparison }}</p>

      <apexchart type="area" height="170" :series="chartSeries" :options="chartOptions" />
    </template>

    <p v-else class="mdash-trend-empty">{{ emptyLabel }}</p>
  </SectionCard>
</template>

<style scoped>
.mdash-trend-headline {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin: 0;
  font-size: 0.9375rem;
}

.mdash-trend-figure {
  color: var(--vz-secondary, #f15a24);
  font-weight: 700;
}

.mdash-trend-suffix {
  color: var(--vz-heading-color, #495057);
}

.mdash-trend-delta {
  display: inline-flex;
  align-items: center;
  gap: 0.1875rem;
  padding: 0.125rem 0.4375rem;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
}

.mdash-trend-comparison {
  margin: 0.1875rem 0 0;
  color: var(--mdash-muted);
  font-size: 0.75rem;
}

.mdash-trend-empty,
.mdash-trend-skeleton {
  margin: 0;
}

.mdash-trend-empty {
  padding: 2rem 0;
  color: var(--mdash-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.mdash-trend-skeleton {
  height: 11rem;
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
  .mdash-trend-skeleton {
    animation: none;
  }
}
</style>
