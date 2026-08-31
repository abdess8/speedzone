<script setup>
import { computed } from 'vue';

/**
 * Head-count per status, sitting above a list.
 *
 * Doubles as a filter: a card is the fastest way to answer "show me the ones
 * that are stuck", which is the question these screens are opened with. Rows
 * whose count is zero are dropped rather than rendered grey — a workflow with
 * twenty statuses would otherwise bury the three that are moving.
 */
const props = defineProps({
  /** @type {{value: string, label: string, color: string, icon: ?string, count: number}[]} */
  stats: { type: Array, default: () => [] },
  /** Currently filtered status, so its card reads as pressed. */
  modelValue: { type: [String, Number], default: '' },
  allLabel: { type: String, default: '' },
  /** Keep empty statuses on screen (short workflows read better complete). */
  showEmpty: { type: Boolean, default: false },
  /** Off for screens whose list is pinned to a single status: the cards there
   * report on the desk, they do not filter it. */
  clickable: { type: Boolean, default: true },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const selected = computed(() => (props.modelValue === null ? '' : String(props.modelValue)));

const total = computed(() =>
  props.stats.reduce((sum, stat) => sum + (Number(stat.count) || 0), 0)
);

const cards = computed(() =>
  props.stats.filter(
    (stat) => props.showEmpty || Number(stat.count) > 0 || String(stat.value) === selected.value
  )
);

/** Clicking the active card clears the filter, which is what a toggle owes you. */
const select = (value) => {
  if (!props.clickable) return;

  emit('select', String(value) === selected.value ? '' : value);
};
</script>

<template>
  <div v-if="loading" class="status-kpis placeholder-glow mb-3">
    <div v-for="n in 4" :key="n" class="status-kpi placeholder col-12"></div>
  </div>

  <div v-else-if="cards.length" class="status-kpis mb-3">
    <button
      v-if="allLabel && clickable"
      type="button"
      class="status-kpi"
      :class="{ 'status-kpi-active': selected === '' }"
      @click="emit('select', '')"
    >
      <span class="status-kpi-dot bg-secondary"></span>
      <span class="status-kpi-body">
        <span class="status-kpi-label">{{ allLabel }}</span>
        <span class="status-kpi-count">{{ total }}</span>
      </span>
    </button>

    <button
      v-for="stat in cards"
      :key="stat.value"
      type="button"
      class="status-kpi"
      :class="{ 'status-kpi-active': selected === String(stat.value), 'status-kpi-static': !clickable }"
      @click="select(stat.value)"
    >
      <span class="status-kpi-dot" :class="`bg-${stat.color}`"></span>
      <span class="status-kpi-body">
        <span class="status-kpi-label" :title="stat.label">
          <i v-if="stat.icon" :class="[stat.icon, `text-${stat.color}`, 'me-1']"></i>{{ stat.label }}
        </span>
        <span class="status-kpi-count" :class="`text-${stat.color}`">{{ stat.count }}</span>
      </span>
    </button>
  </div>
</template>

<style scoped>
.status-kpis {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(9.5rem, 1fr);
  gap: 0.75rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
  /* The row scrolls on a phone rather than wrapping into a wall of cards. */
  scroll-snap-type: x proximity;
}

@media (min-width: 992px) {
  .status-kpis {
    grid-auto-flow: row;
    grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
    grid-auto-columns: auto;
    overflow-x: visible;
  }
}

.status-kpi {
  display: flex;
  align-items: stretch;
  gap: 0.625rem;
  padding: 0.75rem 0.875rem;
  text-align: start;
  background-color: var(--vz-card-bg, #fff);
  border: 1px solid var(--vz-border-color);
  border-radius: var(--vz-border-radius);
  scroll-snap-align: start;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}

.status-kpi:hover {
  border-color: var(--vz-primary);
  transform: translateY(-1px);
}

.status-kpi-static,
.status-kpi-static:hover {
  border-color: var(--vz-border-color);
  cursor: default;
  transform: none;
}

.status-kpi-active {
  border-color: var(--vz-primary);
  box-shadow: 0 0 0 1px var(--vz-primary);
}

.status-kpi-dot {
  flex: 0 0 3px;
  border-radius: 3px;
}

.status-kpi-body {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.125rem;
}

.status-kpi-label {
  overflow: hidden;
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--vz-secondary-color);
  text-overflow: ellipsis;
  text-transform: uppercase;
  white-space: nowrap;
}

.status-kpi-count {
  font-size: 1.25rem;
  font-weight: 600;
  line-height: 1.2;
}

.status-kpi.placeholder {
  height: 4.25rem;
}
</style>
