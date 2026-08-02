<script setup>
/**
 * Renders what the assistant *did*, next to what it said.
 *
 * The model is told not to recite tool output, because a sentence is a poor
 * carrier for a status change or a download. Each action type it can produce
 * gets a real affordance here instead: a badge pair, a download button, a
 * result list, a KPI grid.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  action: { type: Object, required: true },
});

const { t, locale } = useI18n();

const data = computed(() => props.action?.data ?? {});

const formatter = computed(() => new Intl.NumberFormat(locale.value === 'en' ? 'en-GB' : 'fr-FR', {
  maximumFractionDigits: 2,
}));

const n = (value) => formatter.value.format(Number(value ?? 0));

/** Metrics worth a tile, in the order an operations lead reads them. */
const KPI_TILES = [
  { key: 'delivery_success_rate', format: 'percent' },
  { key: 'delivered_orders', format: 'integer' },
  { key: 'failed_deliveries', format: 'integer' },
  { key: 'orders_in_period', format: 'integer' },
  { key: 'in_transit', format: 'integer' },
  { key: 'out_for_delivery', format: 'integer' },
  { key: 'returned_orders', format: 'integer' },
  { key: 'average_delivery_time_hours', format: 'hours' },
  { key: 'revenue_in_period', format: 'money' },
  { key: 'cash_to_collect', format: 'money' },
];

const formatMetric = (value, format) => {
  if (value === null || value === undefined) {
    return '—';
  }

  switch (format) {
    case 'percent':
      return `${n(value)} %`;
    case 'hours':
      return t('chatbot.kpis.hours', { value: n(value) });
    case 'money':
      return `${n(value)} ${t('chatbot.currency')}`;
    default:
      return n(value);
  }
};

const kpiTiles = computed(() => {
  const metrics = data.value.metrics ?? {};

  return KPI_TILES
    .filter((tile) => metrics[tile.key] !== null && metrics[tile.key] !== undefined)
    .map((tile) => ({
      key: tile.key,
      label: t(`chatbot.kpis.${tile.key}`),
      value: formatMetric(metrics[tile.key], tile.format),
      highlight: tile.key === 'delivery_success_rate',
    }));
});

const timeframeLabel = computed(() => {
  const filter = data.value.timeframe ?? {};

  return filter.from && filter.to ? `${filter.from} → ${filter.to}` : '';
});

/** Search buckets, flattened so the template stays a single loop. */
const searchGroups = computed(() => {
  const results = data.value.results ?? {};

  return ['orders', 'drivers', 'sellers', 'customers']
    .filter((group) => Array.isArray(results[group]) && results[group].length > 0)
    .map((group) => ({ key: group, label: t(`chatbot.search.${group}`), rows: results[group] }));
});
</script>

<template>
  <!-- A status transition: what it was, what it is now. -->
  <div v-if="action.type === 'order_status_changed'" class="cb-action">
    <div class="cb-action-head">
      <i class="ri-refresh-line" />
      <span>{{ t('chatbot.actions.status_changed') }}</span>
    </div>

    <a class="cb-action-order" :href="data.order?.url">
      <span class="cb-tracking">{{ data.order?.tracking_number }}</span>
      <span v-if="data.order?.customer_name" class="cb-muted">{{ data.order.customer_name }}</span>
    </a>

    <div class="cb-transition">
      <span class="badge" :class="`bg-${data.previous_status_color}-subtle text-${data.previous_status_color}`">
        {{ data.previous_status_label }}
      </span>
      <i class="ri-arrow-right-line cb-muted" />
      <span class="badge" :class="`bg-${data.order?.status_color}-subtle text-${data.order?.status_color}`">
        <i :class="data.order?.status_icon" />
        {{ data.order?.status_label }}
      </span>
    </div>
  </div>

  <!-- A generated document: one click away. -->
  <div v-else-if="action.type === 'invoice_ready'" class="cb-action">
    <div class="cb-action-head">
      <i class="ri-file-text-line" />
      <span>
        {{ data.kind === 'seller_invoice' ? t('chatbot.actions.invoice_ready') : t('chatbot.actions.statement_ready') }}
      </span>
    </div>

    <div class="cb-document">
      <div class="cb-document-info">
        <span class="cb-tracking">{{ data.reference }}</span>
        <span v-if="data.amount !== null && data.amount !== undefined" class="cb-muted">
          {{ n(data.amount) }} {{ t('chatbot.currency') }}
        </span>
      </div>

      <a class="btn btn-sm btn-primary" :href="data.download_url" target="_blank" rel="noopener">
        <i class="ri-download-2-line align-bottom me-1" />
        {{ t('chatbot.actions.download') }}
      </a>
    </div>
  </div>

  <!-- Search hits, grouped by entity. -->
  <div v-else-if="action.type === 'search_results'" class="cb-action">
    <div class="cb-action-head">
      <i class="ri-search-line" />
      <span>{{ t('chatbot.actions.results', { count: data.total }) }}</span>
    </div>

    <div v-for="group in searchGroups" :key="group.key" class="cb-group">
      <p class="cb-group-title">{{ group.label }}</p>

      <template v-if="group.key === 'orders'">
        <a v-for="row in group.rows" :key="row.id" class="cb-row" :href="row.url">
          <span class="cb-row-main">
            <span class="cb-tracking">{{ row.tracking_number }}</span>
            <span class="cb-muted">{{ row.customer_name }}<template v-if="row.city"> · {{ row.city }}</template></span>
          </span>
          <span class="badge" :class="`bg-${row.status_color}-subtle text-${row.status_color}`">
            {{ row.status_label }}
          </span>
        </a>
      </template>

      <template v-else-if="group.key === 'customers'">
        <div v-for="row in group.rows" :key="row.phone" class="cb-row">
          <span class="cb-row-main">
            <span class="cb-tracking">{{ row.name || row.phone }}</span>
            <span class="cb-muted">{{ row.phone }}</span>
          </span>
          <span class="badge bg-primary-subtle text-primary">
            {{ t('chatbot.search.orders_count', { count: row.orders }) }}
          </span>
        </div>
      </template>

      <template v-else>
        <div v-for="row in group.rows" :key="row.id" class="cb-row">
          <span class="cb-row-main">
            <span class="cb-tracking">{{ row.name }}</span>
            <span class="cb-muted">{{ row.phone || row.email }}</span>
          </span>
          <span v-if="row.city" class="cb-muted">{{ row.city }}</span>
        </div>
      </template>
    </div>
  </div>

  <!-- A KPI snapshot for the requested window. -->
  <div v-else-if="action.type === 'kpi_report'" class="cb-action">
    <div class="cb-action-head">
      <i class="ri-bar-chart-2-line" />
      <span>{{ t('chatbot.actions.kpis') }}</span>
      <span v-if="timeframeLabel" class="cb-muted ms-auto">{{ timeframeLabel }}</span>
    </div>

    <div class="cb-kpi-grid">
      <div v-for="tile in kpiTiles" :key="tile.key" class="cb-kpi" :class="{ 'cb-kpi-highlight': tile.highlight }">
        <span class="cb-kpi-value">{{ tile.value }}</span>
        <span class="cb-kpi-label">{{ tile.label }}</span>
      </div>
    </div>

    <div v-if="data.top_drivers?.length" class="cb-group">
      <p class="cb-group-title">{{ t('chatbot.kpis.top_drivers') }}</p>
      <div v-for="driver in data.top_drivers" :key="driver.driver_id" class="cb-row">
        <span class="cb-row-main">
          <span class="cb-tracking">{{ driver.driver_name }}</span>
          <span class="cb-muted">{{ t('chatbot.kpis.delivered_count', { count: driver.delivered }) }}</span>
        </span>
        <span v-if="driver.success_rate !== null" class="badge bg-success-subtle text-success">
          {{ n(driver.success_rate) }} %
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cb-action {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 0.5rem;
  padding: 0.75rem;
  border: 1px solid var(--vz-border-color, #e9ebec);
  border-radius: 0.75rem;
  background-color: var(--vz-card-bg, #fff);
}

.cb-action-head {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.cb-muted {
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.75rem;
}

.cb-tracking {
  color: var(--vz-body-color, #212529);
  font-size: 0.8125rem;
  font-weight: 600;
}

.cb-action-order {
  display: flex;
  flex-direction: column;
  text-decoration: none;
}

.cb-action-order:hover .cb-tracking {
  text-decoration: underline;
}

.cb-transition {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.375rem;
}

.cb-document {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.cb-document-info {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.cb-group + .cb-group {
  margin-top: 0.25rem;
}

.cb-group-title {
  margin: 0 0 0.25rem;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
}

.cb-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.375rem 0;
  border-bottom: 1px solid var(--vz-border-color, #e9ebec);
  text-decoration: none;
}

.cb-row:last-child {
  border-bottom: 0;
}

a.cb-row:hover .cb-tracking {
  text-decoration: underline;
}

.cb-row-main {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

/* Two columns keeps every label on one line inside a 380px panel; a third
   would force the longer ones to wrap and stagger the tile heights. */
.cb-kpi-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.cb-kpi {
  display: flex;
  flex-direction: column;
  padding: 0.5rem 0.625rem;
  border-radius: 0.5rem;
  background-color: var(--vz-light, #f3f6f9);
}

.cb-kpi-highlight {
  background-color: var(--vz-success-bg-subtle, #daf4ea);
}

.cb-kpi-value {
  color: var(--vz-heading-color, #495057);
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.2;
}

.cb-kpi-label {
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.6875rem;
}
</style>
