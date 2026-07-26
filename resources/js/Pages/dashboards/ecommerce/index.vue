<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import KpiCard from '@/Components/Dashboard/KpiCard.vue';
import ChartCard from '@/Components/Dashboard/ChartCard.vue';
import { fetchDashboard, isCancelled, PERIOD_VALUES } from '@/services/DashboardService';
import getChartColorsArray from '@/common/getChartColorsArray';
import { formatMoney } from '@/common/formatMoney';
import flatPickr from 'vue-flatpickr-component';
import { French } from 'flatpickr/dist/l10n/fr.js';
import 'flatpickr/dist/flatpickr.css';
import SimpleBar from 'simplebar-vue';

const { t } = useI18n();
const page = usePage();

const userName = computed(() => page.props.auth?.user?.name ?? t('dashboard.default_team'));
const dateLocale = computed(() => (page.props.locale === 'en' ? 'en-US' : 'fr-FR'));

const loading = ref(true);
const error = ref(null);
const dashboard = ref(null);

const period = ref('last_30_days');
const customRange = ref('');

const flatpickrConfig = computed(() => ({
  mode: 'range',
  dateFormat: 'Y-m-d',
  locale: page.props.locale === 'fr' ? French : undefined,
}));

const periodOptions = computed(() =>
  PERIOD_VALUES.map((value) => ({
    value,
    label: t(`dashboard.periods.${value}`),
  }))
);

const parseCustomRange = (value) => {
  if (!value) return null;

  const separators = [' to ', ' au ', ' à '];
  for (const separator of separators) {
    if (value.includes(separator)) {
      const parts = value.split(separator);
      if (parts.length === 2) {
        return { from: parts[0].trim(), to: parts[1].trim() };
      }
    }
  }

  return null;
};

// Identifies the newest request so a superseded one can never write back.
let requestId = 0;

const loadDashboard = async () => {
  const currentRequest = ++requestId;

  loading.value = true;
  error.value = null;

  const params = { period: period.value };

  if (period.value === 'custom') {
    const range = parseCustomRange(customRange.value);
    if (!range) {
      loading.value = false;
      return;
    }
    params.from = range.from;
    params.to = range.to;
  }

  try {
    const data = await fetchDashboard(params);

    if (currentRequest !== requestId) return;

    dashboard.value = data;
  } catch (e) {
    // A superseded request is not a failure: the newer one owns the state.
    if (currentRequest !== requestId || isCancelled(e)) return;

    error.value = e?.response?.data?.message ?? e?.message ?? t('dashboard.errors.load_failed');
    dashboard.value = null;
  } finally {
    if (currentRequest === requestId) {
      loading.value = false;
    }
  }
};

watch(period, (value) => {
  if (value !== 'custom') {
    loadDashboard();
  }
});

watch(customRange, (value) => {
  if (period.value === 'custom' && parseCustomRange(value)) {
    loadDashboard();
  }
});

onMounted(loadDashboard);

const summary = computed(() => dashboard.value?.summary ?? {});
const charts = computed(() => dashboard.value?.charts ?? {});
const recentOrders = computed(() => dashboard.value?.recentOrders ?? []);
const recentActivities = computed(() => dashboard.value?.recentActivities ?? []);
const topCustomers = computed(() => dashboard.value?.topCustomers ?? []);
const paymentMethods = computed(() => dashboard.value?.paymentMethods ?? { labels: [], series: [] });
const agentPerformance = computed(() => charts.value?.deliveryAgentsPerformance ?? []);

const chartColors = getChartColorsArray(
  '["--vz-primary", "--vz-success", "--vz-warning", "--vz-info", "--vz-danger", "--vz-secondary"]'
);

const ordersByDayOptions = computed(() => ({
  chart: { type: 'line', height: 320, toolbar: { show: false } },
  stroke: { curve: 'smooth', width: 2 },
  dataLabels: { enabled: false },
  xaxis: { categories: charts.value?.ordersByDay?.labels ?? [] },
  colors: [chartColors[0]],
  grid: { strokeDashArray: 4 },
}));

const ordersByDaySeries = computed(() => [
  { name: t('dashboard.series.orders'), data: charts.value?.ordersByDay?.series ?? [] },
]);

const ordersByStatusOptions = computed(() => ({
  chart: { type: 'donut', height: 320 },
  labels: charts.value?.ordersByStatus?.labels ?? [],
  legend: { position: 'bottom' },
  colors: chartColors,
  dataLabels: { dropShadow: { enabled: false } },
}));

const ordersByStatusSeries = computed(() => charts.value?.ordersByStatus?.series ?? []);

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
  yaxis: {
    labels: {
      formatter: (v) => `${Math.round(v)} MAD`,
    },
  },
  colors: [chartColors[2]],
  grid: { strokeDashArray: 4 },
}));

const monthlyRevenueSeries = computed(() => [
  { name: t('dashboard.series.revenue'), data: charts.value?.monthlyRevenue?.series ?? [] },
]);

const successGauge = computed(() => charts.value?.deliverySuccessRate ?? {});
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
  chart: { type: 'bar', height: Math.max(280, agentPerformance.value.length * 36), toolbar: { show: false } },
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
  <Layout>
    <BRow>
      <BCol>
        <div class="h-100">
          <BRow class="mb-3 pb-1">
            <BCol cols="12">
              <div class="d-flex align-items-lg-center flex-lg-row flex-column">
                <div class="flex-grow-1">
                  <h4 class="fs-16 mb-1">{{ t('dashboard.greeting', { name: userName }) }}</h4>
                  <p class="text-muted mb-0">{{ t('dashboard.subtitle') }}</p>
                </div>
                <div class="mt-3 mt-lg-0">
                  <BRow class="g-3 mb-0 align-items-center">
                    <BCol sm="auto">
                      <BFormSelect v-model="period" class="form-select shadow-sm" :disabled="loading">
                        <option v-for="opt in periodOptions" :key="opt.value" :value="opt.value">
                          {{ opt.label }}
                        </option>
                      </BFormSelect>
                    </BCol>
                    <BCol v-if="period === 'custom'" sm="auto">
                      <div class="input-group">
                        <flat-pickr
                          v-model="customRange"
                          :config="flatpickrConfig"
                          class="form-control border-0 dash-filter-picker shadow"
                          :placeholder="t('dashboard.select_date_range')"
                        />
                        <div class="input-group-text bg-primary border-primary text-white">
                          <i class="ri-calendar-2-line"></i>
                        </div>
                      </div>
                    </BCol>
                    <BCol sm="auto">
                      <Link :href="route('orders.create')" class="btn btn-secondary">
                        <i class="ri-add-circle-line align-middle me-1"></i>
                        {{ t('dashboard.create_shipment') }}
                      </Link>
                    </BCol>
                    <BCol sm="auto">
                      <BButton variant="soft-primary" size="sm" :disabled="loading" @click="loadDashboard">
                        <i class="ri-refresh-line"></i>
                      </BButton>
                    </BCol>
                  </BRow>
                </div>
              </div>
            </BCol>
          </BRow>

          <BAlert v-if="error" variant="danger" show class="mb-3">
            {{ error }}
            <BButton variant="link" class="p-0 ms-2" @click="loadDashboard">{{ t('dashboard.retry') }}</BButton>
          </BAlert>

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
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.cash_to_collect')" :value="summary.cash_to_collect" suffix=" MAD" :decimals="2" icon="ri-money-dollar-box-line" icon-class="text-warning" icon-bg="bg-warning-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.cod_collected')" :value="summary.cod_collected" suffix=" MAD" :decimals="2" icon="ri-hand-coin-line" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.delivery_success_rate')" :value="summary.delivery_success_rate ?? 0" suffix="%" :decimals="1" icon="ri-percent-line" icon-class="text-success" icon-bg="bg-success-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.average_delivery_time')" :value="summary.average_delivery_time_hours ?? 0" suffix=" h" :decimals="1" icon="ri-timer-line" icon-class="text-info" icon-bg="bg-info-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.active_sellers')" :value="summary.active_sellers" icon="ri-store-2-line" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.active_delivery_agents')" :value="summary.active_delivery_agents" icon="bx bx-id-card" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.new_customers')" :value="summary.new_customers" icon="ri-user-add-line" icon-class="text-info" icon-bg="bg-info-subtle" :loading="loading" />
            </BCol>
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
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.pending_transfers')" :value="summary.pending_transfers" icon="ri-truck-line" icon-class="text-warning" icon-bg="bg-warning-subtle" :loading="loading" :link="route('transfers.index')" :link-label="t('dashboard.view_transfers')" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.orders_at_agency')" :value="summary.orders_at_agency" icon="ri-building-line" icon-class="text-primary" icon-bg="bg-primary-subtle" :loading="loading" />
            </BCol>
            <BCol xl="3" md="6">
              <KpiCard :title="t('dashboard.kpis.failed_deliveries')" :value="summary.failed_deliveries" icon="ri-close-circle-line" icon-class="text-danger" icon-bg="bg-danger-subtle" :loading="loading" />
            </BCol>
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
            <BCol xl="4">
              <ChartCard :title="t('dashboard.charts.orders_by_status')" :loading="loading" :empty="!ordersByStatusSeries.length" :empty-message="t('dashboard.empty.chart')">
                <apexchart type="donut" height="320" :series="ordersByStatusSeries" :options="ordersByStatusOptions" />
              </ChartCard>
            </BCol>
          </BRow>

          <BRow class="mt-1">
            <BCol xl="6">
              <ChartCard :title="t('dashboard.charts.orders_by_city')" :loading="loading" :empty="!ordersByCitySeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
                <apexchart type="bar" height="320" :series="ordersByCitySeries" :options="ordersByCityOptions" />
              </ChartCard>
            </BCol>
            <BCol xl="6">
              <ChartCard :title="t('dashboard.charts.payment_methods')" :loading="loading" :empty="!paymentMethodsSeries.length" :empty-message="t('dashboard.empty.chart')">
                <apexchart type="pie" height="300" :series="paymentMethodsSeries" :options="paymentMethodsOptions" />
                <p v-if="paymentMethods.note" class="text-muted fs-12 mb-0 mt-2">{{ paymentMethods.note }}</p>
              </ChartCard>
            </BCol>
          </BRow>

          <BRow class="mt-1">
            <BCol xl="8">
              <ChartCard :title="t('dashboard.charts.monthly_revenue')" :loading="loading" :empty="!monthlyRevenueSeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
                <apexchart type="area" height="320" :series="monthlyRevenueSeries" :options="monthlyRevenueOptions" />
              </ChartCard>
            </BCol>
            <BCol xl="4">
              <ChartCard :title="t('dashboard.charts.delivery_success_rate')" :loading="loading" :empty="successGauge.rate == null" :empty-message="t('dashboard.empty.chart')">
                <apexchart type="radialBar" height="300" :series="successGaugeSeries" :options="successGaugeOptions" />
                <div class="text-center text-muted fs-13">{{ deliveredFailedLabel }}</div>
              </ChartCard>
            </BCol>
          </BRow>

          <BRow class="mt-1">
            <BCol xl="6">
              <ChartCard :title="t('dashboard.charts.orders_per_seller')" :loading="loading" :empty="!sellersChartSeries[0]?.data?.length" :empty-message="t('dashboard.empty.chart')">
                <apexchart type="bar" height="320" :series="sellersChartSeries" :options="sellersChartOptions" />
              </ChartCard>
            </BCol>
            <BCol xl="6">
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

          <BRow class="mt-1 mb-4">
            <BCol xl="6">
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

            <BCol xl="6">
              <ChartCard :title="t('dashboard.charts.orders_by_status_summary')" :loading="loading" :empty="!ordersByStatusSeries.length" :empty-message="t('dashboard.empty.chart')">
                <div class="row g-3">
                  <div
                    v-for="(label, idx) in charts.ordersByStatus?.labels ?? []"
                    :key="label"
                    class="col-6 col-md-4"
                  >
                    <div class="border rounded p-3 text-center">
                      <h5 class="mb-1">{{ charts.ordersByStatus?.series?.[idx] ?? 0 }}</h5>
                      <p class="text-muted mb-0 fs-13">{{ label }}</p>
                    </div>
                  </div>
                </div>
              </ChartCard>
            </BCol>
          </BRow>
        </div>
      </BCol>
    </BRow>
  </Layout>
</template>
