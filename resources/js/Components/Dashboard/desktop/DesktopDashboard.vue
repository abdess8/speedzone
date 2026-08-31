<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import flatPickr from 'vue-flatpickr-component';
import { French } from 'flatpickr/dist/l10n/fr.js';
import 'flatpickr/dist/flatpickr.css';
import SimpleBar from 'simplebar-vue';
import KpiCard from '@/Components/Dashboard/KpiCard.vue';
import ChartCard from '@/Components/Dashboard/ChartCard.vue';
import HeroPanel from './HeroPanel.vue';
import StatusDonutCard from './StatusDonutCard.vue';
import MetricBarCard from './MetricBarCard.vue';
import VolumeCard from './VolumeCard.vue';
import ActivityPanel from './ActivityPanel.vue';
import TaskTiles from './TaskTiles.vue';
import { PERIOD_VALUES } from '@/services/DashboardService';
import getChartColorsArray from '@/common/getChartColorsArray';
import { formatMoney, formatMoneyRounded } from '@/common/formatMoney';

/**
 * The dashboard as a screen you can take in at once.
 *
 * Everything this page can report used to arrive at the same weight: 23 metric
 * cards, then 8 charts, then 3 tables, in the order they happened to be
 * written. Nothing was wrong with any single number, but a wall of equals has
 * no answer in it — the reader has to find the figure that matters before they
 * can read it.
 *
 * So the top of the screen is a summary that ranks: the cash owed, where the
 * orders sit, the recent rhythm, and what is waiting to be cleared. The rest
 * is not thrown away — an operations team does eventually need revenue per
 * seller and delivery times per agent — it moves below a toggle, where it is
 * one click away instead of in the way.
 *
 * State stays with the page. This component only renders and asks.
 */
const props = defineProps({
  dashboard: { type: Object, default: null },
  widgets: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  period: { type: String, required: true },
  customRange: { type: String, default: '' },
});

/**
 * Whether a family of panels is open to this role.
 *
 * The server decides and also omits the matching figures, so this only spares
 * the reader an empty frame — it is not what keeps the numbers private.
 */
const shows = (section) => props.widgets?.[section] === true;

const emit = defineEmits(['update:period', 'update:customRange', 'refresh']);

const { t } = useI18n();
const page = usePage();

/*
 * The page owns the filter, so the controls write through rather than holding
 * a copy: a local mirror would drift the moment the period changed anywhere
 * else, such as the stepper the mobile view offers.
 */
const selectedPeriod = computed({
  get: () => props.period,
  set: (value) => emit('update:period', value),
});

const selectedRange = computed({
  get: () => props.customRange,
  set: (value) => emit('update:customRange', value),
});

const userName = computed(() => page.props.auth?.user?.name ?? t('dashboard.default_team'));
const dateLocale = computed(() => (page.props.locale === 'en' ? 'en-US' : 'fr-FR'));

const showDetails = ref(false);

const summary = computed(() => props.dashboard?.summary ?? {});
const charts = computed(() => props.dashboard?.charts ?? {});
const recentOrders = computed(() => props.dashboard?.recentOrders ?? []);
const recentActivities = computed(() => props.dashboard?.recentActivities ?? []);
const topCustomers = computed(() => props.dashboard?.topCustomers ?? []);
const paymentMethods = computed(() => props.dashboard?.paymentMethods ?? { labels: [], series: [] });
const agentPerformance = computed(() => charts.value?.deliveryAgentsPerformance ?? []);

/*
 * The summary panel shows the head of each stream, not all of it: the API
 * returns ten orders and twenty events, and a list that long would run past
 * the column beside it. The full ten-column table and the scrollable feed are
 * still in the detailed section below.
 */
const PANEL_ROWS = 6;

const panelOrders = computed(() => recentOrders.value.slice(0, PANEL_ROWS));
const panelEvents = computed(() => recentActivities.value.slice(0, PANEL_ROWS));

const number = (value) => Number(value) || 0;

const periodOptions = computed(() =>
  PERIOD_VALUES.map((value) => ({ value, label: t(`dashboard.periods.${value}`) }))
);

const periodLabel = computed(() => t(`dashboard.periods.${props.period}`));

const flatpickrConfig = computed(() => ({
  mode: 'range',
  dateFormat: 'Y-m-d',
  locale: page.props.locale === 'fr' ? French : undefined,
}));

/* ------------------------------------------------------------------ summary */

const ordersByDay = computed(() => charts.value?.ordersByDay ?? { labels: [], series: [] });

const heroStats = computed(() =>
  [
    {
      key: 'delivered',
      label: t('dashboard.desktop.hero.delivered'),
      value: String(number(summary.value.delivered_orders)),
      href: '/orders?status_group=delivered',
      section: null,
    },
    {
      key: 'in_transit',
      label: t('dashboard.desktop.hero.in_transit'),
      value: String(number(summary.value.in_transit)),
      href: '/orders?status_group=delivery',
      section: 'operations',
    },
    {
      key: 'returns',
      label: t('dashboard.desktop.hero.returns'),
      value: String(number(summary.value.returned_orders)),
      href: '/orders?status_group=failed',
      section: 'operations',
    },
  ].filter((stat) => stat.section === null || shows(stat.section))
);

const successGauge = computed(() => charts.value?.deliverySuccessRate ?? {});

const successRate = computed(() => number(summary.value.delivery_success_rate));

/**
 * Cash owed and cash already in hand are the same pot seen at two moments, so
 * the bar reads as the share of the period's collection that is done.
 */
const codExpected = computed(
  () => number(summary.value.cod_collected) + number(summary.value.cash_to_collect)
);

const codPercent = computed(() =>
  codExpected.value > 0 ? (number(summary.value.cod_collected) / codExpected.value) * 100 : 0
);

const tasks = computed(() =>
  [
    {
      key: 'pending_pickup',
      label: t('dashboard.desktop.tasks.pending_pickup'),
      count: number(summary.value.pending_pickup),
      tone: 'info',
      icon: 'ri-inbox-unarchive-line',
      href: '/orders?status_group=pickup',
    },
    {
      key: 'failed',
      label: t('dashboard.desktop.tasks.failed'),
      count: number(summary.value.failed_deliveries),
      tone: 'danger',
      icon: 'ri-close-circle-line',
      href: '/orders?status_group=failed',
    },
    {
      key: 'transfers',
      label: t('dashboard.desktop.tasks.transfers'),
      count: number(summary.value.pending_transfers),
      tone: 'warning',
      icon: 'ri-route-line',
      href: '/transfers',
    },
    {
      key: 'at_agency',
      label: t('dashboard.desktop.tasks.at_agency'),
      count: number(summary.value.orders_at_agency),
      tone: 'primary',
      icon: 'ri-building-line',
      href: '/orders',
    },
  ].filter((task) => task.count > 0)
);

/* ------------------------------------------------------------------- charts */

const chartColors = getChartColorsArray(
  '["--vz-primary", "--vz-success", "--vz-warning", "--vz-info", "--vz-danger", "--vz-secondary"]'
);

const ordersByDayOptions = computed(() => ({
  chart: { type: 'line', height: 320, toolbar: { show: false } },
  stroke: { curve: 'smooth', width: 2 },
  dataLabels: { enabled: false },
  xaxis: { categories: ordersByDay.value.labels ?? [] },
  colors: [chartColors[0]],
  grid: { strokeDashArray: 4 },
}));

const ordersByDaySeries = computed(() => [
  { name: t('dashboard.series.orders'), data: ordersByDay.value.series ?? [] },
]);

const ordersByCityOptions = computed(() => ({
  chart: { type: 'bar', height: 320, toolbar: { show: false } },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
  dataLabels: { enabled: false },
  xaxis: { categories: charts.value?.ordersByCity?.labels ?? [] },
  colors: [chartColors[1]],
  grid: { strokeDashArray: 4 },
}));

const ordersByCitySeries = computed(() => [
  { name: t('dashboard.series.orders'), data: charts.value?.ordersByCity?.series ?? [] },
]);

const paymentMethodsOptions = computed(() => ({
  chart: { type: 'pie', height: 300 },
  labels: paymentMethods.value?.labels ?? [],
  legend: { position: 'bottom' },
  colors: chartColors,
}));

const paymentMethodsSeries = computed(() => paymentMethods.value?.series ?? []);

const monthlyRevenueOptions = computed(() => ({
  chart: { type: 'area', height: 320, toolbar: { show: false } },
  stroke: { curve: 'smooth', width: 2 },
  fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
  dataLabels: { enabled: false },
  xaxis: { categories: charts.value?.monthlyRevenue?.labels ?? [] },
  yaxis: { labels: { formatter: (v) => `${Math.round(v)} MAD` } },
  colors: [chartColors[2]],
  grid: { strokeDashArray: 4 },
}));

const monthlyRevenueSeries = computed(() => [
  { name: t('dashboard.series.revenue'), data: charts.value?.monthlyRevenue?.series ?? [] },
]);

const successGaugeSeries = computed(() => [successGauge.value?.rate ?? 0]);

const successGaugeOptions = computed(() => ({
  chart: { type: 'radialBar', height: 300 },
  plotOptions: {
    radialBar: {
      hollow: { size: '65%' },
      dataLabels: {
        name: { show: true, fontSize: '14px', offsetY: -8 },
        value: { show: true, fontSize: '22px', formatter: (v) => `${v}%` },
      },
    },
  },
  labels: [t('dashboard.series.success_rate')],
  colors: [chartColors[1]],
}));

const sellersChartOptions = computed(() => ({
  chart: { type: 'bar', height: 320, toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: true },
  xaxis: { categories: charts.value?.ordersPerSeller?.labels ?? [] },
  colors: [chartColors[0]],
}));

const sellersChartSeries = computed(() => [
  { name: t('dashboard.series.orders'), data: charts.value?.ordersPerSeller?.series ?? [] },
]);

const agentChartOptions = computed(() => ({
  chart: {
    type: 'bar',
    height: Math.max(280, agentPerformance.value.length * 36),
    toolbar: { show: false },
  },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  dataLabels: { enabled: true },
  xaxis: { categories: agentPerformance.value.map((a) => a.driver_name) },
  colors: [chartColors[3]],
}));

const agentChartSeries = computed(() => [
  { name: t('dashboard.series.delivered'), data: agentPerformance.value.map((a) => a.delivered) },
]);

const deliveredFailedLabel = computed(() =>
  t('dashboard.charts.delivered_failed', {
    delivered: successGauge.value?.delivered ?? 0,
    failed: successGauge.value?.failed ?? 0,
  })
);

const formatPercent = (value) => (value != null ? `${value}%` : '—');
const formatHours = (value) => (value != null ? `${value}h` : '—');
const formatDate = (value) => (value ? new Date(value).toLocaleDateString(dateLocale.value) : '—');
const statusBadgeClass = (color) => `badge bg-${color}-subtle text-${color}`;
</script>

<template>
  <div class="ddash">
    <header class="ddash-head">
      <div class="ddash-heading">
        <h1 class="ddash-title">{{ t('dashboard.desktop.title') }}</h1>
        <p class="ddash-subtitle">{{ t('dashboard.greeting', { name: userName }) }}</p>
      </div>

      <div class="ddash-controls">
        <BFormSelect v-model="selectedPeriod" class="form-select ddash-select" :disabled="loading">
          <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
            {{ opt.label }}
          </option>
        </BFormSelect>

        <div v-if="period === 'custom'" class="input-group ddash-picker">
          <flat-pickr
            v-model="selectedRange"
            :config="flatpickrConfig"
            class="form-control"
            :placeholder="t('dashboard.select_date_range')"
          />
          <div class="input-group-text bg-primary border-primary text-white">
            <i class="ri-calendar-2-line"></i>
          </div>
        </div>

        <Link :href="route('orders.create')" class="btn btn-secondary ddash-cta">
          <i class="ri-add-circle-line align-middle me-1"></i>
          {{ t('dashboard.create_shipment') }}
        </Link>

        <button
          type="button"
          class="ddash-refresh"
          :disabled="loading"
          :aria-label="t('dashboard.desktop.refresh')"
          @click="$emit('refresh')"
        >
          <i class="ri-refresh-line" :class="{ 'ddash-spin': loading }"></i>
        </button>
      </div>
    </header>

    <BAlert v-if="error" variant="danger" show class="ddash-alert">
      {{ error }}
      <BButton variant="link" class="p-0 ms-1" @click="$emit('refresh')">
        {{ t('dashboard.retry') }}
      </BButton>
    </BAlert>

    <BRow class="g-4">
      <BCol xl="8">
        <BRow class="g-4">
          <BCol v-if="shows('financials')" :lg="shows('operations') ? 6 : 12">
            <HeroPanel
              :label="t('dashboard.desktop.cash_headline')"
              :amount="summary.cash_to_collect"
              :currency="t('dashboard.desktop.currency')"
              :period-label="periodLabel"
              :series="ordersByDay.series"
              :stats="heroStats"
              :loading="loading"
            />
          </BCol>

          <BCol v-if="shows('operations')" :lg="shows('financials') ? 6 : 12">
            <StatusDonutCard
              :title="t('dashboard.charts.orders_by_status')"
              :total-label="t('dashboard.desktop.status.total')"
              :others-label="t('dashboard.desktop.status.others')"
              :labels="charts.ordersByStatus?.labels ?? []"
              :series="charts.ordersByStatus?.series ?? []"
              :loading="loading"
              :empty-label="t('dashboard.empty.chart')"
            />
          </BCol>

          <BCol cols="12">
            <ActivityPanel
              :orders-tab-label="t('dashboard.tables.recent_orders')"
              :events-tab-label="t('dashboard.tables.recent_activity')"
              :view-all-label="t('dashboard.view_all')"
              :orders="panelOrders"
              :events="panelEvents"
              :currency="t('dashboard.desktop.currency')"
              :empty-orders-label="t('dashboard.empty.orders')"
              :empty-events-label="t('dashboard.empty.activity')"
              :loading="loading"
            />
          </BCol>
        </BRow>
      </BCol>

      <BCol xl="4">
        <div class="ddash-side">
          <MetricBarCard
            v-if="shows('performance')"
            :value="`${successRate}%`"
            :label="t('dashboard.desktop.success.label')"
            :caption="deliveredFailedLabel"
            :percent="successRate"
            tone="success"
            :loading="loading"
          />

          <div class="ddash-side-grow">
            <VolumeCard
              :title="t('dashboard.desktop.volume.title')"
              :caption="t('dashboard.desktop.volume.caption')"
              :labels="ordersByDay.labels"
              :series="ordersByDay.series"
              footer-icon="ri-check-double-line"
              :footer-label="t('dashboard.desktop.volume.footer_label')"
              :footer-caption="t('dashboard.desktop.volume.footer_caption')"
              :footer-value="summary.delivered_orders ?? 0"
              :loading="loading"
              :empty-label="t('dashboard.empty.chart')"
            />
          </div>

          <MetricBarCard
            v-if="shows('financials')"
            :value="formatMoneyRounded(summary.cod_collected)"
            :label="t('dashboard.desktop.collected.label')"
            :caption="
              t('dashboard.desktop.collected.caption', {
                total: `${formatMoneyRounded(codExpected)} ${t('dashboard.desktop.currency')}`,
              })
            "
            :percent="codPercent"
            tone="warning"
            :loading="loading"
          />
        </div>
      </BCol>
    </BRow>

    <section v-if="shows('operations')" class="ddash-band">
      <h2 class="ddash-band-title">{{ t('dashboard.desktop.tasks.title') }}</h2>
      <TaskTiles
        :items="tasks"
        :empty-label="t('dashboard.desktop.tasks.empty')"
        :loading="loading"
      />
    </section>

    <section class="ddash-band">
      <button
        type="button"
        class="ddash-details-toggle"
        :aria-expanded="showDetails"
        @click="showDetails = !showDetails"
      >
        <span class="ddash-details-text">
          <span class="ddash-details-label">
            {{ showDetails ? t('dashboard.desktop.details.hide') : t('dashboard.desktop.details.show') }}
          </span>
          <span class="ddash-details-caption">{{ t('dashboard.desktop.details.caption') }}</span>
        </span>
        <i class="ri-arrow-down-s-line ddash-details-chevron" :class="{ 'ddash-details-open': showDetails }"></i>
      </button>
    </section>

    <div v-if="showDetails" class="ddash-details">
      <BRow>
        <BCol xl="3" md="6">
          <KpiCard :title="t('dashboard.kpis.orders_today')" :value="summary.orders_today" icon="bx bx-package" :loading="loading" :link="route('orders.index')" :link-label="t('dashboard.view_orders')" />
        </BCol>
        <BCol xl="3" md="6">
          <KpiCard :title="t('dashboard.kpis.orders_this_month')" :value="summary.orders_this_month" icon="ri-calendar-line" icon-class="text-info" icon-bg="bg-info-subtle" :loading="loading" />
        </BCol>
        <BCol xl="3" md="6">
          <KpiCard :title="t('dashboard.kpis.delivered_orders')" :value="summary.delivered_orders" icon="bx bx-check-shield" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
        </BCol>
        <template v-if="shows('operations')">
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.pending_pickup')" :value="summary.pending_pickup" icon="ri-time-line" icon-class="text-warning" icon-bg="bg-warning-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.in_transit')" :value="summary.in_transit" icon="ri-truck-line" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.out_for_delivery')" :value="summary.out_for_delivery" icon="ri-e-bike-2-line" icon-class="text-warning" icon-bg="bg-warning-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.returned_orders')" :value="summary.returned_orders" icon="ri-arrow-go-back-line" icon-class="text-dark" icon-bg="bg-dark-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.cancelled_orders')" :value="summary.cancelled_orders" icon="ri-stop-circle-line" icon-class="text-secondary" icon-bg="bg-secondary-subtle" :loading="loading" />
          </BCol>
        </template>
        <template v-if="shows('financials')">
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.cash_to_collect')" :value="summary.cash_to_collect" suffix=" MAD" :decimals="2" icon="ri-money-dollar-box-line" icon-class="text-warning" icon-bg="bg-warning-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.cod_collected')" :value="summary.cod_collected" suffix=" MAD" :decimals="2" icon="ri-hand-coin-line" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
          </BCol>
        </template>
        <template v-if="shows('performance')">
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.delivery_success_rate')" :value="summary.delivery_success_rate ?? 0" suffix="%" :decimals="1" icon="ri-percent-line" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.average_delivery_time')" :value="summary.average_delivery_time_hours ?? 0" suffix=" h" :decimals="1" icon="ri-timer-line" icon-class="text-info" icon-bg="bg-info-subtle" :loading="loading" />
          </BCol>
        </template>
        <template v-if="shows('network')">
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.active_sellers')" :value="summary.active_sellers" icon="ri-store-2-line" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.active_delivery_agents')" :value="summary.active_delivery_agents" icon="bx bx-id-card" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
          </BCol>
        </template>
        <BCol v-if="shows('customers')" xl="3" md="6">
          <KpiCard :title="t('dashboard.kpis.new_customers')" :value="summary.new_customers" icon="ri-user-add-line" icon-class="text-info" icon-bg="bg-info-subtle" :loading="loading" />
        </BCol>
        <template v-if="shows('financials')">
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.revenue_in_period')" :value="summary.revenue_in_period" suffix=" MAD" :decimals="2" icon="bx bx-dollar-circle" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.revenue_today')" :value="summary.revenue_today" suffix=" MAD" :decimals="2" icon="ri-funds-line" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.revenue_this_month')" :value="summary.revenue_this_month" suffix=" MAD" :decimals="2" icon="ri-line-chart-line" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.average_order_value')" :value="summary.average_order_value" suffix=" MAD" :decimals="2" icon="ri-scales-3-line" icon-class="text-secondary" icon-bg="bg-secondary-subtle" :loading="loading" />
          </BCol>
        </template>
        <template v-if="shows('operations')">
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.pending_transfers')" :value="summary.pending_transfers" icon="ri-truck-line" icon-class="text-warning" icon-bg="bg-warning-subtle" :loading="loading" :link="route('transfers.index')" :link-label="t('dashboard.view_transfers')" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.orders_at_agency')" :value="summary.orders_at_agency" icon="ri-building-line" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
          </BCol>
          <BCol xl="3" md="6">
            <KpiCard :title="t('dashboard.kpis.failed_deliveries')" :value="summary.failed_deliveries" icon="ri-close-circle-line" icon-class="text-danger" icon-bg="bg-danger-subtle" :loading="loading" />
          </BCol>
        </template>
        <BCol xl="3" md="6">
          <KpiCard :title="t('dashboard.kpis.orders_in_period')" :value="summary.orders_in_period" icon="ri-file-list-3-line" icon-class="text-info" icon-bg="bg-info-subtle" :loading="loading" />
        </BCol>
      </BRow>

      <BRow class="mt-1">
        <BCol xl="8">
          <ChartCard :title="t('dashboard.charts.orders_by_day')" :loading="loading" :empty="!ordersByDaySeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="line" height="320" :series="ordersByDaySeries" :options="ordersByDayOptions" />
          </ChartCard>
        </BCol>
        <BCol v-if="shows('performance')" xl="4">
          <ChartCard :title="t('dashboard.charts.delivery_success_rate')" :loading="loading" :empty="successGauge.rate == null" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="radialBar" height="300" :series="successGaugeSeries" :options="successGaugeOptions" />
            <div class="text-center text-muted fs-13">{{ deliveredFailedLabel }}</div>
          </ChartCard>
        </BCol>
      </BRow>

      <BRow class="mt-1">
        <BCol v-if="shows('operations')" xl="6">
          <ChartCard :title="t('dashboard.charts.orders_by_city')" :loading="loading" :empty="!ordersByCitySeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="bar" height="320" :series="ordersByCitySeries" :options="ordersByCityOptions" />
          </ChartCard>
        </BCol>
        <BCol v-if="shows('financials')" xl="6">
          <ChartCard :title="t('dashboard.charts.payment_methods')" :loading="loading" :empty="!paymentMethodsSeries.length" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="pie" height="300" :series="paymentMethodsSeries" :options="paymentMethodsOptions" />
            <p v-if="paymentMethods.note" class="text-muted fs-12 mb-0 mt-2">{{ paymentMethods.note }}</p>
          </ChartCard>
        </BCol>
      </BRow>

      <BRow class="mt-1">
        <BCol v-if="shows('financials')" xl="8">
          <ChartCard :title="t('dashboard.charts.monthly_revenue')" :loading="loading" :empty="!monthlyRevenueSeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="area" height="320" :series="monthlyRevenueSeries" :options="monthlyRevenueOptions" />
          </ChartCard>
        </BCol>
        <BCol v-if="shows('operations')" xl="4">
          <ChartCard :title="t('dashboard.charts.orders_by_status_summary')" :loading="loading" :empty="!(charts.ordersByStatus?.series ?? []).length" :empty-message="t('dashboard.empty.chart')">
            <div class="row g-3">
              <div v-for="(label, idx) in charts.ordersByStatus?.labels ?? []" :key="label" class="col-6">
                <div class="border rounded p-3 text-center">
                  <h5 class="mb-1">{{ charts.ordersByStatus?.series?.[idx] ?? 0 }}</h5>
                  <p class="text-muted mb-0 fs-13">{{ label }}</p>
                </div>
              </div>
            </div>
          </ChartCard>
        </BCol>
      </BRow>

      <BRow class="mt-1">
        <BCol v-if="shows('network')" xl="6">
          <ChartCard :title="t('dashboard.charts.orders_per_seller')" :loading="loading" :empty="!sellersChartSeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="bar" height="320" :series="sellersChartSeries" :options="sellersChartOptions" />
          </ChartCard>
        </BCol>
        <BCol v-if="shows('performance')" xl="6">
          <ChartCard :title="t('dashboard.charts.delivery_agents_performance')" :loading="loading" :empty="!agentPerformance.length" :empty-message="t('dashboard.empty.chart')">
            <apexchart type="bar" :height="Math.max(280, agentPerformance.length * 36)" :series="agentChartSeries" :options="agentChartOptions" />
            <div v-if="agentPerformance.length" class="table-responsive mt-3">
              <table class="table table-sm table-borderless mb-0 fs-13">
                <thead>
                  <tr class="text-muted">
                    <th>{{ t('dashboard.tables.agent') }}</th>
                    <th>{{ t('dashboard.tables.delivered') }}</th>
                    <th>{{ t('dashboard.tables.success') }}</th>
                    <th>{{ t('dashboard.tables.avg_time') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="agent in agentPerformance" :key="agent.driver_id">
                    <td>{{ agent.driver_name }}</td>
                    <td>{{ agent.delivered }}</td>
                    <td>{{ formatPercent(agent.success_rate) }}</td>
                    <td>{{ formatHours(agent.average_delivery_time_hours) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </ChartCard>
        </BCol>
      </BRow>

      <BRow class="mt-1">
        <BCol xl="8">
          <BCard no-body>
            <BCardHeader class="align-items-center d-flex">
              <BCardTitle class="mb-0 flex-grow-1">{{ t('dashboard.tables.recent_orders') }}</BCardTitle>
              <Link :href="route('orders.index')" class="btn btn-sm btn-soft-primary">{{ t('dashboard.view_all') }}</Link>
            </BCardHeader>
            <BCardBody>
              <div v-if="loading" class="placeholder-glow">
                <span v-for="n in 5" :key="n" class="placeholder col-12 mb-2 d-block"></span>
              </div>
              <div v-else-if="!recentOrders.length" class="text-center text-muted py-4">
                <i class="ri-inbox-line fs-1 d-block mb-2 opacity-50"></i>
                {{ t('dashboard.empty.orders') }}
              </div>
              <div v-else class="table-responsive table-card">
                <table class="table table-hover table-centered align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>{{ t('dashboard.tables.tracking') }}</th>
                      <th>{{ t('dashboard.tables.customer') }}</th>
                      <th>{{ t('dashboard.tables.seller') }}</th>
                      <th>{{ t('dashboard.tables.pickup') }}</th>
                      <th>{{ t('dashboard.tables.destination') }}</th>
                      <th>{{ t('dashboard.tables.status') }}</th>
                      <th>{{ t('dashboard.tables.payment') }}</th>
                      <th>{{ t('dashboard.tables.amount') }}</th>
                      <th>{{ t('dashboard.tables.agent') }}</th>
                      <th>{{ t('dashboard.tables.created') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="order in recentOrders" :key="order.id">
                      <td>
                        <Link :href="route('orders.show', order.id)" class="fw-medium">{{ order.tracking_number }}</Link>
                      </td>
                      <td>
                        <div>{{ order.customer_name }}</div>
                        <small class="text-muted">{{ order.customer_phone }}</small>
                      </td>
                      <td>{{ order.seller_name ?? '—' }}</td>
                      <td>{{ order.pickup_city ?? '—' }}</td>
                      <td>{{ order.destination_city ?? '—' }}</td>
                      <td>
                        <span :class="statusBadgeClass(order.status_color)" class="badge">{{ order.status_label }}</span>
                      </td>
                      <td>{{ order.payment_method_label }}</td>
                      <td>{{ formatMoney(order.amount) }} MAD</td>
                      <td>{{ order.delivery_agent ?? '—' }}</td>
                      <td><small>{{ formatDate(order.created_at) }}</small></td>
                      <td>
                        <Link :href="route('orders.show', order.id)" class="btn btn-sm btn-soft-primary">{{ t('dashboard.view') }}</Link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCardBody>
          </BCard>
        </BCol>

        <BCol xl="4">
          <BCard no-body class="h-100">
            <BCardHeader>
              <BCardTitle class="mb-0">{{ t('dashboard.tables.recent_activity') }}</BCardTitle>
            </BCardHeader>
            <BCardBody class="p-0">
              <div v-if="loading" class="p-4 placeholder-glow">
                <span v-for="n in 6" :key="n" class="placeholder col-12 mb-3 d-block"></span>
              </div>
              <div v-else-if="!recentActivities.length" class="text-center text-muted py-5">
                <i class="ri-history-line fs-1 d-block mb-2 opacity-50"></i>
                {{ t('dashboard.empty.activity') }}
              </div>
              <SimpleBar v-else style="max-height: 520px">
                <div class="p-4">
                  <div v-for="activity in recentActivities" :key="activity.id" class="d-flex mb-4">
                    <div class="flex-shrink-0">
                      <span class="avatar-xs rounded-circle d-flex align-items-center justify-content-center bg-light">
                        <i :class="activity.status_icon"></i>
                      </span>
                    </div>
                    <div class="flex-grow-1 ms-3">
                      <h6 class="mb-1 fs-14">
                        <span :class="statusBadgeClass(activity.status_color)" class="badge me-1">{{ activity.status_label }}</span>
                        <Link v-if="activity.order_id" :href="route('orders.show', activity.order_id)" class="text-muted">
                          {{ activity.tracking_number }}
                        </Link>
                      </h6>
                      <p class="text-muted mb-0 fs-13">{{ activity.actor_name }}</p>
                      <small class="text-muted">{{ activity.created_at_human }}</small>
                    </div>
                  </div>
                </div>
              </SimpleBar>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>

      <BRow v-if="shows('customers')" class="mt-1 mb-4">
        <BCol cols="12">
          <BCard no-body>
            <BCardHeader>
              <BCardTitle class="mb-0">{{ t('dashboard.tables.top_customers') }}</BCardTitle>
            </BCardHeader>
            <BCardBody>
              <div v-if="loading" class="placeholder-glow">
                <span v-for="n in 5" :key="n" class="placeholder col-12 mb-2 d-block"></span>
              </div>
              <div v-else-if="!topCustomers.length" class="text-center text-muted py-4">
                {{ t('dashboard.empty.customers') }}
              </div>
              <div v-else class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>{{ t('dashboard.tables.customer') }}</th>
                      <th>{{ t('dashboard.tables.phone') }}</th>
                      <th>{{ t('dashboard.tables.orders') }}</th>
                      <th>{{ t('dashboard.tables.total_cod') }}</th>
                      <th>{{ t('dashboard.tables.delivered') }}</th>
                      <th>{{ t('dashboard.tables.pending') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(customer, idx) in topCustomers" :key="customer.phone + idx">
                      <td>{{ customer.customer_name || '—' }}</td>
                      <td>{{ customer.phone }}</td>
                      <td>{{ customer.orders }}</td>
                      <td>{{ formatMoney(customer.total_cod) }} MAD</td>
                      <td>{{ customer.delivered }}</td>
                      <td>{{ customer.pending }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </BCardBody>
          </BCard>
        </BCol>
      </BRow>
    </div>
  </div>
</template>

<style scoped>
/*
 * Design tokens for the whole desktop tree. Custom properties inherit through
 * the DOM, so every child's scoped styles resolve these without importing
 * anything or restating the values.
 */
.ddash {
  --ddash-radius: 1.25rem;
  /* Same elevation as every `.card` in the app, so these surfaces — which are
     not Bootstrap cards — still belong to the same visual language. */
  --ddash-shadow: var(--vz-card-shadow, 0 1px 2px rgba(13, 42, 77, 0.08), 0 6px 16px rgba(13, 42, 77, 0.07));
  --ddash-muted: var(--vz-secondary-color, #878a99);

  padding-bottom: 1.5rem;
}

.ddash-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.ddash-heading {
  min-width: 0;
}

.ddash-title {
  margin: 0;
  color: var(--vz-heading-color, #495057);
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.ddash-subtitle {
  margin: 0.125rem 0 0;
  color: var(--ddash-muted);
  font-size: 0.8125rem;
}

.ddash-controls {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.625rem;
}

.ddash-select {
  width: auto;
  border-radius: 999px;
  box-shadow: var(--ddash-shadow);
}

.ddash-picker {
  width: auto;
}

.ddash-cta {
  border-radius: 999px;
}

.ddash-refresh {
  display: inline-flex;
  width: 2.5rem;
  height: 2.5rem;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 50%;
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--ddash-shadow);
  color: var(--vz-heading-color, #495057);
  font-size: 1.125rem;
}

.ddash-refresh:disabled {
  opacity: 0.6;
}

.ddash-alert {
  border-radius: var(--ddash-radius);
}

/* The right-hand column tracks the height of the two panels beside it, so the
   chart between the two metric cards takes whatever is left over. */
.ddash-side {
  display: flex;
  height: 100%;
  flex-direction: column;
  gap: 1.5rem;
}

.ddash-side-grow {
  min-height: 0;
  flex: 1 1 auto;
}

.ddash-band {
  margin-top: 1.5rem;
}

.ddash-band-title {
  margin: 0 0 0.75rem;
  color: var(--vz-heading-color, #495057);
  font-size: 1.0625rem;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.ddash-details-toggle {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.875rem 1.25rem;
  border: 0;
  border-radius: var(--ddash-radius);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--ddash-shadow);
  text-align: left;
}

.ddash-details-label {
  display: block;
  color: var(--vz-heading-color, #495057);
  font-size: 0.875rem;
  font-weight: 600;
}

.ddash-details-caption {
  display: block;
  color: var(--ddash-muted);
  font-size: 0.75rem;
}

.ddash-details-chevron {
  flex-shrink: 0;
  color: var(--ddash-muted);
  font-size: 1.25rem;
  transition: transform 0.2s ease;
}

.ddash-details-open {
  transform: rotate(180deg);
}

.ddash-details {
  margin-top: 1.5rem;
}

.ddash-spin {
  display: inline-block;
  animation: ddash-rotate 0.9s linear infinite;
}

@keyframes ddash-rotate {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .ddash-spin {
    animation: none;
  }

  .ddash-details-chevron {
    transition: none;
  }
}
</style>
