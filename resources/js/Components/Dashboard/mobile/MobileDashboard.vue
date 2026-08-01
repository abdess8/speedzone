<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import HeroCard from './HeroCard.vue';
import StatStrip from './StatStrip.vue';
import TaskCarousel from './TaskCarousel.vue';
import OverviewCard from './OverviewCard.vue';
import TrendCard from './TrendCard.vue';
import RecentOrdersList from './RecentOrdersList.vue';
import BreakdownCard from './BreakdownCard.vue';
import { PERIOD_VALUES } from '@/services/DashboardService';
import { formatMoneyRounded } from '@/common/formatMoney';

/**
 * The dashboard as a phone screen rather than a shrunken desktop one.
 *
 * The desktop view answers every question at once — 23 metrics, 8 charts, an
 * eleven-column table — which works when they are all in the visual field
 * simultaneously. On a phone the same content becomes several screens of
 * scrolling with no hierarchy, so this view keeps the same data source and
 * re-ranks it: the money owed first, then the work waiting, then the trend,
 * then the detail.
 *
 * State stays with the page. This component only renders and asks.
 */
const props = defineProps({
  dashboard: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  period: { type: String, required: true },
});

const emit = defineEmits(['update:period', 'refresh']);

const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);

const summary = computed(() => props.dashboard?.summary ?? {});
const charts = computed(() => props.dashboard?.charts ?? {});
const recentOrders = computed(() => (props.dashboard?.recentOrders ?? []).slice(0, 5));

const number = (value) => Number(value) || 0;

/*
 * Period stepper
 * --------------
 * `custom` needs a date picker and has no natural neighbour in an ordered list,
 * so the arrows walk the fixed windows only. Landing on `custom` from the
 * desktop view still shows its label; the arrows simply move off it.
 */
const steppablePeriods = PERIOD_VALUES.filter((value) => value !== 'custom');

const periodIndex = computed(() => steppablePeriods.indexOf(props.period));

const periodLabel = computed(() => t(`dashboard.periods.${props.period}`));

const canGoPrevious = computed(() => periodIndex.value !== 0);
const canGoNext = computed(() => periodIndex.value < steppablePeriods.length - 1);

function step(offset) {
  // An unknown period (i.e. `custom`) enters the list at whichever end the
  // arrow points to, rather than being treated as index -1.
  const from = periodIndex.value === -1 ? (offset > 0 ? -1 : steppablePeriods.length) : periodIndex.value;
  const next = steppablePeriods[from + offset];

  if (next) {
    emit('update:period', next);
  }
}

/*
 * Secondary metrics
 * -----------------
 * Each card's caption is a second real measure, not a period-over-period
 * delta: the API returns one window at a time, so a trend arrow here would be
 * fabricated.
 */
const stats = computed(() => [
  {
    key: 'delivered',
    label: t('dashboard.mobile.stats.delivered'),
    value: String(number(summary.value.delivered_orders)),
    caption: t('dashboard.mobile.stats.success_caption', {
      rate: number(summary.value.delivery_success_rate),
    }),
    tone: 'success',
    icon: 'ri-checkbox-circle-line',
    href: '/orders?status_group=delivered',
  },
  {
    key: 'in_transit',
    label: t('dashboard.mobile.stats.in_transit'),
    value: String(number(summary.value.in_transit)),
    caption: t('dashboard.mobile.stats.out_for_delivery_caption', {
      count: number(summary.value.out_for_delivery),
    }),
    tone: 'primary',
    icon: 'ri-truck-line',
    href: '/orders?status_group=delivery',
  },
  {
    key: 'returns',
    label: t('dashboard.mobile.stats.returns'),
    value: String(number(summary.value.returned_orders)),
    caption: t('dashboard.mobile.stats.failed_caption', {
      count: number(summary.value.failed_deliveries),
    }),
    tone: 'danger',
    icon: 'ri-arrow-go-back-line',
    href: '/orders?status_group=failed',
  },
  {
    key: 'collected',
    label: t('dashboard.mobile.stats.collected'),
    value: formatMoneyRounded(summary.value.cod_collected),
    caption: t('dashboard.mobile.stats.orders_caption', {
      count: number(summary.value.orders_in_period),
    }),
    tone: 'warning',
    icon: 'ri-hand-coin-line',
  },
]);

/*
 * Work waiting on someone. An empty bucket is dropped rather than shown as a
 * reassuring zero, so the strip only ever holds things that need doing.
 */
const tasks = computed(() =>
  [
    {
      key: 'pending_pickup',
      label: t('dashboard.mobile.tasks.pending_pickup'),
      count: number(summary.value.pending_pickup),
      tone: 'info',
      icon: 'ri-inbox-unarchive-line',
      href: '/orders?status_group=pickup',
    },
    {
      key: 'failed',
      label: t('dashboard.mobile.tasks.failed'),
      count: number(summary.value.failed_deliveries),
      tone: 'danger',
      icon: 'ri-close-circle-line',
      href: '/orders?status_group=failed',
    },
    {
      key: 'transfers',
      label: t('dashboard.mobile.tasks.transfers'),
      count: number(summary.value.pending_transfers),
      tone: 'warning',
      icon: 'ri-route-line',
      href: '/transfers',
    },
    {
      key: 'at_agency',
      label: t('dashboard.mobile.tasks.at_agency'),
      count: number(summary.value.orders_at_agency),
      tone: 'primary',
      icon: 'ri-building-line',
      href: '/orders',
    },
  ].filter((task) => task.count > 0)
);

/*
 * Orders overview
 * ---------------
 * "In progress" is everything not yet resolved either way, so the three
 * segments add up to the same total the columns report.
 */
const inProgress = computed(
  () =>
    number(summary.value.pending_pickup) +
    number(summary.value.in_transit) +
    number(summary.value.out_for_delivery) +
    number(summary.value.orders_at_agency)
);

const unresolved = computed(
  () =>
    number(summary.value.failed_deliveries) +
    number(summary.value.returned_orders) +
    number(summary.value.cancelled_orders)
);

const overviewColumns = computed(() => [
  {
    key: 'total',
    label: t('dashboard.mobile.overview.total'),
    value: number(summary.value.orders_in_period),
  },
  {
    key: 'delivered',
    label: t('dashboard.mobile.overview.delivered'),
    value: number(summary.value.delivered_orders),
  },
  {
    key: 'in_progress',
    label: t('dashboard.mobile.overview.in_progress'),
    value: inProgress.value,
  },
]);

const overviewSegments = computed(() => [
  { key: 'delivered', value: number(summary.value.delivered_orders), tone: 'success' },
  { key: 'in_progress', value: inProgress.value, tone: 'primary' },
  { key: 'unresolved', value: unresolved.value, tone: 'danger' },
]);

/** Success rate while things are going well; the recovery backlog when not. */
const overviewFootnote = computed(() => {
  if (unresolved.value > 0) {
    return {
      text: t('dashboard.mobile.overview.at_risk', { count: unresolved.value }),
      tone: 'danger',
      icon: 'ri-error-warning-fill',
    };
  }

  return {
    text: t('dashboard.mobile.overview.on_track', {
      rate: number(summary.value.delivery_success_rate),
    }),
    tone: 'success',
    icon: 'ri-checkbox-circle-fill',
  };
});

/*
 * Daily trend
 * -----------
 * `ordersByDay` always ends on the selected range's last day, which is only
 * "today" for some periods — so the headline names the day the API returned
 * instead of assuming it.
 */
const ordersByDay = computed(() => charts.value.ordersByDay ?? { labels: [], series: [] });

const trend = computed(() => {
  const series = ordersByDay.value.series ?? [];
  const labels = ordersByDay.value.labels ?? [];
  const lastIndex = series.length - 1;

  if (lastIndex < 0) {
    return { latest: 0, previous: 0, latestLabel: '', previousLabel: '', delta: 0, percent: null };
  }

  const latest = number(series[lastIndex]);
  const previous = lastIndex > 0 ? number(series[lastIndex - 1]) : 0;

  return {
    latest,
    previous,
    latestLabel: labels[lastIndex] ?? '',
    previousLabel: lastIndex > 0 ? labels[lastIndex - 1] ?? '' : '',
    delta: latest - previous,
    // Growth from zero has no percentage, so the card falls back to the count.
    percent: previous > 0 ? Math.round(((latest - previous) / previous) * 100) : null,
  };
});

/*
 * Breakdown
 * ---------
 * Status and city share one card and one segmented control, because they are
 * read the same way and never at the same time.
 */
const breakdownTabs = computed(() => [
  { key: 'status', label: t('dashboard.mobile.breakdown.by_status') },
  { key: 'city', label: t('dashboard.mobile.breakdown.by_city') },
]);

const breakdownDatasets = computed(() => ({
  status: charts.value.ordersByStatus ?? { labels: [], series: [] },
  city: charts.value.ordersByCity ?? { labels: [], series: [] },
}));

const breakdownShare = (count, total) =>
  t('dashboard.mobile.breakdown.share', { count, total });
</script>

<template>
  <div class="mdash">
    <BAlert v-if="error" variant="danger" show class="mdash-alert">
      {{ error }}
      <BButton variant="link" class="p-0 ms-1" @click="$emit('refresh')">
        {{ t('dashboard.retry') }}
      </BButton>
    </BAlert>

    <HeroCard
      :user="user"
      :title="t('dashboard.mobile.title')"
      :subtitle="t('dashboard.mobile.subtitle')"
      :amount="summary.cash_to_collect"
      :currency="t('dashboard.mobile.currency')"
      :label="t('dashboard.mobile.cash_headline')"
      :period-label="periodLabel"
      :loading="loading"
      :refresh-label="t('dashboard.mobile.refresh')"
      :previous-label="t('dashboard.mobile.previous_period')"
      :next-label="t('dashboard.mobile.next_period')"
      :can-go-previous="canGoPrevious"
      :can-go-next="canGoNext"
      @previous="step(-1)"
      @next="step(1)"
      @refresh="$emit('refresh')"
    />

    <!-- The lift lives on a wrapper: setting it on the strip itself would race
         the strip's own `margin` shorthand, which resets the top margin. -->
    <div class="mdash-strip-overlap">
      <StatStrip :items="stats" :loading="loading" />
    </div>

    <section v-if="!loading" class="mdash-section">
      <h2 class="mdash-section-title">{{ t('dashboard.mobile.tasks.title') }}</h2>

      <TaskCarousel
        v-if="tasks.length"
        :items="tasks"
        :open-label="t('dashboard.mobile.tasks.open')"
      />

      <!-- An empty backlog is worth stating: silence here reads as "not loaded
           yet" rather than "nothing to do". -->
      <p v-else class="mdash-tasks-empty">
        <i class="ri-checkbox-circle-fill text-success"></i>
        {{ t('dashboard.mobile.tasks.empty') }}
      </p>
    </section>

    <OverviewCard
      class="mdash-section"
      :title="t('dashboard.mobile.overview.title')"
      href="/orders"
      :columns="overviewColumns"
      :segments="overviewSegments"
      :footnote="overviewFootnote.text"
      :footnote-tone="overviewFootnote.tone"
      :footnote-icon="overviewFootnote.icon"
      :empty-label="t('dashboard.mobile.overview.empty')"
      :loading="loading"
    />

    <TrendCard
      class="mdash-section"
      :title="t('dashboard.mobile.trend.title')"
      :caption="t('dashboard.mobile.trend.caption')"
      :headline="t('dashboard.mobile.trend.count', { count: trend.latest })"
      :headline-suffix="t('dashboard.mobile.trend.on_day', { day: trend.latestLabel })"
      :comparison="
        trend.previousLabel
          ? t('dashboard.mobile.trend.previous_day', {
              count: trend.previous,
              day: trend.previousLabel,
            })
          : ''
      "
      :delta="trend.delta"
      :delta-percent="trend.percent"
      :steady-label="t('dashboard.mobile.trend.stable')"
      :labels="ordersByDay.labels"
      :series="ordersByDay.series"
      :loading="loading"
      :empty-label="t('dashboard.empty.chart')"
    />

    <RecentOrdersList
      class="mdash-section"
      :title="t('dashboard.mobile.recent.title')"
      :orders="recentOrders"
      :currency="t('dashboard.mobile.currency')"
      :view-all-label="t('dashboard.mobile.recent.view_all')"
      :empty-label="t('dashboard.empty.orders')"
      :loading="loading"
    />

    <BreakdownCard
      class="mdash-section"
      :title="t('dashboard.mobile.breakdown.title')"
      :total-label="t('dashboard.mobile.breakdown.total')"
      :all-label="t('dashboard.mobile.breakdown.all')"
      :tabs="breakdownTabs"
      :datasets="breakdownDatasets"
      :share-label="breakdownShare"
      :empty-label="t('dashboard.empty.chart')"
      :loading="loading"
    />
  </div>
</template>

<style scoped>
/*
 * Design tokens for the whole mobile tree. Custom properties inherit through
 * the DOM, so every child's scoped styles resolve these without importing
 * anything or restating the values.
 */
.mdash {
  --mdash-radius: 1.125rem;
  --mdash-radius-lg: 1.5rem;
  /* Distance from the viewport edge to this content: `.page-content` and the
     layout's `container-fluid` contribute half the grid gutter each. Strips
     cancel it to bleed to the screen edge, then pad it back so their first card
     still lines up with the cards above and below. */
  --mdash-gutter: 1.5rem;
  /* Same elevation as every `.card` in the app, so these surfaces — which are
     not Bootstrap cards — still belong to the same visual language. */
  --mdash-shadow: var(--vz-card-shadow, 0 1px 2px rgba(13, 42, 77, 0.08), 0 6px 16px rgba(13, 42, 77, 0.07));
  --mdash-muted: var(--vz-secondary-color, #878a99);

  padding-top: 0.25rem;
}

.mdash-alert {
  border-radius: var(--mdash-radius);
}

/*
 * Lifts the strip over the hero's reserved bottom padding, so the two overlap
 * the way a card tucked under another does.
 *
 * The stacking is explicit because the hero is `position: relative`: without a
 * positive z-index here the hero paints over the cards it is supposed to sit
 * behind, hiding their top edge. The lift also absorbs the strip's own top
 * padding, which only exists to keep the card shadows inside the scroller.
 */
.mdash-strip-overlap {
  position: relative;
  z-index: 2;
  margin: -3.375rem 0 -0.75rem;
}

.mdash-section {
  display: block;
  margin-top: 1rem;
}

.mdash-section-title {
  margin: 0 0 0.625rem;
  color: var(--vz-heading-color, #495057);
  font-size: 1.0625rem;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.mdash-tasks-empty {
  display: flex;
  align-items: center;
  gap: 0.4375rem;
  margin: 0;
  padding: 0.875rem 1rem;
  border-radius: var(--mdash-radius);
  background-color: var(--vz-card-bg, #fff);
  box-shadow: var(--mdash-shadow);
  color: var(--mdash-muted);
  font-size: 0.8125rem;
}
</style>
