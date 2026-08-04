<script setup>
import { computed } from 'vue';
import PanelCard from './PanelCard.vue';
import getChartColorsArray from '@/common/getChartColorsArray';

/**
 * The recent daily rhythm, short enough to read as a shape.
 *
 * The full 30-day series lives in the detailed section below; this card keeps
 * the tail of it, where a column per day is still wide enough to compare by
 * eye. The busiest day is the only one given the accent colour — that is the
 * question a bar chart this small gets asked, and answering it in the paint
 * saves the reader measuring columns against each other.
 */
const props = defineProps({
  title: { type: String, required: true },
  caption: { type: String, default: '' },
  labels: { type: Array, default: () => [] },
  series: { type: Array, default: () => [] },
  /** How many trailing points to keep. */
  window: { type: Number, default: 7 },
  footerIcon: { type: String, default: 'ri-checkbox-circle-line' },
  footerLabel: { type: String, default: '' },
  footerCaption: { type: String, default: '' },
  footerValue: { type: [String, Number], default: '' },
  loading: { type: Boolean, default: false },
  emptyLabel: { type: String, required: true },
});

/*
 * Resolved here rather than written as `var(--vz-…)` in the options:
 * ApexCharts puts these straight onto SVG attributes, where a custom property
 * reference never resolves.
 */
const [accent, muted, axisLabel] = getChartColorsArray(
  '["--vz-secondary", "--vz-light", "--vz-secondary-color"]'
);

const recent = computed(() => {
  const values = props.series.slice(-props.window).map((value) => Number(value) || 0);
  const labels = props.labels.slice(-props.window);

  return { values, labels };
});

const hasData = computed(() => recent.value.values.some((value) => value > 0));

const peakIndex = computed(() => recent.value.values.indexOf(Math.max(...recent.value.values)));

const chartSeries = computed(() => [{ name: props.title, data: recent.value.values }]);

const chartOptions = computed(() => ({
  chart: { type: 'bar', height: 200, toolbar: { show: false }, animations: { speed: 500 } },
  plotOptions: {
    bar: {
      // One colour per column, so the busiest day can be singled out without
      // splitting the data across two series.
      distributed: true,
      borderRadius: 6,
      columnWidth: '42%',
    },
  },
  colors: recent.value.values.map((_, index) => (index === peakIndex.value ? accent : muted)),
  dataLabels: { enabled: false },
  legend: { show: false },
  grid: { show: false, padding: { top: -10, right: 0, bottom: -8, left: 0 } },
  xaxis: {
    categories: recent.value.labels,
    axisBorder: { show: false },
    axisTicks: { show: false },
    labels: { style: { fontSize: '11px', colors: axisLabel } },
    tooltip: { enabled: false },
  },
  yaxis: { show: false },
  tooltip: { x: { show: true } },
  states: { hover: { filter: { type: 'darken', value: 0.9 } } },
}));
</script>

<template>
  <PanelCard :title="title" :caption="caption" fill>
    <div v-if="loading" class="ddash-volume-skeleton" aria-hidden="true"></div>

    <p v-else-if="!hasData" class="ddash-volume-empty">{{ emptyLabel }}</p>

    <apexchart v-else type="bar" height="200" :series="chartSeries" :options="chartOptions" />

    <div v-if="footerLabel" class="ddash-volume-footer">
      <span class="ddash-volume-icon">
        <i :class="footerIcon"></i>
      </span>
      <span class="ddash-volume-text">
        <span class="ddash-volume-label">{{ footerLabel }}</span>
        <span v-if="footerCaption" class="ddash-volume-caption">{{ footerCaption }}</span>
      </span>
      <span class="ddash-volume-value">{{ loading ? '—' : footerValue }}</span>
    </div>
  </PanelCard>
</template>

<style scoped>
.ddash-volume-footer {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  /* Sticks to the bottom of a card stretched by its taller neighbour. */
  margin-top: auto;
  padding-top: 0.875rem;
  border-top: 1px solid var(--vz-border-color, #e9ebec);
}

.ddash-volume-icon {
  display: inline-flex;
  width: 2.25rem;
  height: 2.25rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  background-color: var(--vz-secondary-bg-subtle, rgba(223, 34, 34, 0.1));
  color: var(--vz-secondary, #293742);
  font-size: 1.0625rem;
}

.ddash-volume-text {
  display: block;
  min-width: 0;
  flex-grow: 1;
}

.ddash-volume-label {
  display: block;
  overflow: hidden;
  color: var(--vz-heading-color, #495057);
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ddash-volume-caption {
  display: block;
  color: var(--ddash-muted, #878a99);
  font-size: 0.6875rem;
}

.ddash-volume-value {
  flex-shrink: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.ddash-volume-empty {
  margin: 0;
  padding: 3.75rem 0;
  color: var(--ddash-muted, #878a99);
  font-size: 0.8125rem;
  text-align: center;
}

.ddash-volume-skeleton {
  height: 12.5rem;
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
  .ddash-volume-skeleton {
    animation: none;
  }
}
</style>
