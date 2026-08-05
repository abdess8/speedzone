<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Layout from '@/Layouts/main.vue';
import DesktopDashboard from '@/Components/Dashboard/desktop/DesktopDashboard.vue';
import MobileDashboard from '@/Components/Dashboard/mobile/MobileDashboard.vue';
import { useIsMobile } from '@/composables/useMediaQuery';
import { useChatbotBus } from '@/composables/useChatbotBus';
import { fetchDashboard, isCancelled } from '@/services/DashboardService';

const { t } = useI18n();
const { onDataChanged } = useChatbotBus();

/**
 * The page owns the data and the period; the two views own their markup.
 *
 * The desktop layout is a wide grid of panels and a detailed section behind a
 * toggle, which on a phone is a wall to scroll rather than a dashboard.
 * Switching on a media query instead of `d-none` classes means a phone never
 * mounts the charts it will not show.
 */
const isMobile = useIsMobile();

const loading = ref(true);
const error = ref(null);
const dashboard = ref(null);

/**
 * Which families of panels this role may read.
 *
 * Decided on the server, which also leaves the corresponding figures out of the
 * payload — so a panel hidden here has no data behind it to leak either way.
 * Empty while loading, which keeps the guarded panels out of the first paint
 * instead of flashing them and taking them back.
 */
const widgets = computed(() => dashboard.value?.widgets ?? {});

const period = ref('last_30_days');
const customRange = ref('');

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

// The assistant can change an order's status from anywhere on the page, and the
// KPIs would otherwise keep showing the figures from before it did.
onDataChanged(loadDashboard);

onMounted(loadDashboard);
</script>

<template>
  <Layout>
    <MobileDashboard
      v-if="isMobile"
      v-model:period="period"
      :dashboard="dashboard"
      :widgets="widgets"
      :loading="loading"
      :error="error ?? ''"
      @refresh="loadDashboard"
    />

    <DesktopDashboard
      v-else
      v-model:period="period"
      v-model:custom-range="customRange"
      :dashboard="dashboard"
      :widgets="widgets"
      :loading="loading"
      :error="error ?? ''"
      @refresh="loadDashboard"
    />
  </Layout>
</template>
