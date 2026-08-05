<script setup>
import { computed, ref } from 'vue';
import SectionCard from './SectionCard.vue';
import getChartColorsArray from '@/common/getChartColorsArray';

/**
 * How the period's orders split, by status or by city.
 *
 * The desktop dashboard spends two full cards on this, one per dimension. On a
 * phone they share a card and a segmented control, because the two are read the
 * same way and never at the same time.
 *
 * The donut answers "is this balanced"; the ranked bars underneath answer "how
 * much exactly", which a donut cannot without a legend nobody reads.
 */
const props = defineProps({
  title: { type: String, required: true },
  totalLabel: { type: String, required: true },
  allLabel: { type: String, required: true },
  /** @type {import('vue').PropType<Array<{key: string, label: string}>>} */
  tabs: { type: Array, default: () => [] },
  /** @type {import('vue').PropType<Record<string, {labels: string[], series: number[]}>>} */
  datasets: { type: Object, default: () => ({}) },
  /** Caption under each bar, already interpolated per entry by the parent. */
  shareLabel: { type: Function, required: true },
  emptyLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
});

const palette = getChartColorsArray(
  '["--vz-primary", "--vz-success", "--vz-warning", "--vz-danger", "--vz-info", "--vz-secondary", "--vz-dark"]'
);

/**
 * ApexCharts puts the arc gap colour on an SVG attribute, where `var()` never
 * resolves — so the theme's card background is read once, here, as a literal.
 */
const arcGapColor =
  getComputedStyle(document.documentElement).getPropertyValue('--vz-card-bg').trim() || '#fff';

const activeTab = ref(props.tabs[0]?.key ?? '');

const dataset = computed(() => props.datasets[activeTab.value] ?? { labels: [], series: [] });

const total = computed(() =>
  (dataset.value.series ?? []).reduce((sum, value) => sum + (Number(value) || 0), 0)
);

/** Ranked largest first, which is the order the eye wants and the donut uses. */
const entries = computed(() => {
  const { labels = [], series = [] } = dataset.value;

  return labels
    .map((label, index) => ({ label, value: Number(series[index]) || 0 }))
    .filter((entry) => entry.value > 0)
    .sort((a, b) => b.value - a.value)
    .map((entry, index) => ({
      ...entry,
      color: palette[index % palette.length],
      share: total.value > 0 ? Math.round((entry.value / total.value) * 100) : 0,
    }));
});

/** Beyond six bars the card turns into a list, and the tail is all noise. */
const topEntries = computed(() => entries.value.slice(0, 6));

const chartSeries = computed(() => entries.value.map((entry) => entry.value));

const chartOptions = computed(() => ({
  chart: { type: 'donut', height: 240, animations: { speed: 500 } },
  labels: entries.value.map((entry) => entry.label),
  colors: entries.value.map((entry) => entry.color),
  legend: { show: false },
  dataLabels: { enabled: false },
  // The gap between arcs is what turns the ring into countable segments.
  stroke: { width: 4, colors: [arcGapColor] },
  plotOptions: {
    pie: {
      donut: {
        size: '74%',
        labels: {
          show: true,
          value: { fontSize: '1.5rem', fontWeight: 700, offsetY: 4 },
          total: {
            show: true,
            label: props.totalLabel,
            fontSize: '0.75rem',
            formatter: () => String(total.value),
          },
        },
      },
    },
  },
  tooltip: { y: { formatter: (value) => String(value) } },
}));
</script>

<template>
  <SectionCard :title="title">
    <template v-if="tabs.length > 1" #action>
      <div class="mdash-tabs" role="tablist">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          role="tab"
          class="mdash-tab"
          :class="{ 'mdash-tab-active': tab.key === activeTab }"
          :aria-selected="tab.key === activeTab"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>
    </template>

    <div v-if="loading" class="mdash-breakdown-skeleton" aria-hidden="true"></div>

    <p v-else-if="!entries.length" class="mdash-breakdown-empty">{{ emptyLabel }}</p>

    <template v-else>
      <apexchart type="donut" height="240" :series="chartSeries" :options="chartOptions" />

      <p class="mdash-breakdown-all">{{ allLabel }}</p>

      <ul class="mdash-breakdown-list">
        <li v-for="entry in topEntries" :key="entry.label" class="mdash-breakdown-item">
          <p class="mdash-breakdown-label">
            <span class="mdash-breakdown-share" :style="{ color: entry.color }">
              {{ entry.share }}%
            </span>
            {{ entry.label }}
          </p>
          <div class="mdash-breakdown-track">
            <span
              class="mdash-breakdown-fill"
              :style="{ width: `${Math.max(entry.share, 2)}%`, backgroundColor: entry.color }"
            ></span>
          </div>
          <p class="mdash-breakdown-caption">{{ shareLabel(entry.value, total) }}</p>
        </li>
      </ul>
    </template>
  </SectionCard>
</template>

<style scoped>
.mdash-tabs {
  display: inline-flex;
  flex-shrink: 0;
  padding: 0.1875rem;
  border-radius: 999px;
  background-color: var(--vz-light, #f3f6f9);
}

.mdash-tab {
  padding: 0.3125rem 0.75rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--mdash-muted);
  font-size: 0.75rem;
  font-weight: 600;
  transition: background-color 0.18s ease, color 0.18s ease;
}

.mdash-tab-active {
  background-color: var(--vz-card-bg, #fff);
  box-shadow: 0 1px 3px rgba(56, 65, 74, 0.14);
  color: var(--vz-heading-color, #495057);
}

.mdash-breakdown-all {
  margin: 0.5rem 0 0.75rem;
  color: var(--mdash-muted);
  font-size: 0.75rem;
}

.mdash-breakdown-list {
  margin: 0;
  padding: 0;
  list-style: none;
}

.mdash-breakdown-item + .mdash-breakdown-item {
  margin-top: 0.875rem;
}

.mdash-breakdown-label {
  overflow: hidden;
  margin: 0 0 0.3125rem;
  color: var(--vz-heading-color, #495057);
  font-size: 0.8125rem;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-breakdown-share {
  font-weight: 700;
}

.mdash-breakdown-track {
  overflow: hidden;
  height: 0.375rem;
  border-radius: 999px;
  background-color: var(--vz-light, #f3f6f9);
}

.mdash-breakdown-fill {
  display: block;
  height: 100%;
  border-radius: 999px;
}

.mdash-breakdown-caption {
  margin: 0.3125rem 0 0;
  color: var(--mdash-muted);
  font-size: 0.6875rem;
}

.mdash-breakdown-empty {
  margin: 0;
  padding: 2rem 0;
  color: var(--mdash-muted);
  font-size: 0.8125rem;
  text-align: center;
}

.mdash-breakdown-skeleton {
  height: 14rem;
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
  .mdash-tab,
  .mdash-breakdown-skeleton {
    animation: none;
    transition: none;
  }
}
</style>
