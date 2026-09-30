<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import InputError from '@/Components/InputError.vue';
import EntityDetailSheet from '@/Components/EntityDetailSheet.vue';

const props = defineProps({
  stores: { type: Array, default: () => [] },
  sellers: { type: Array, default: () => [] },
  integration: { type: Object, default: null },
  syncs: { type: Array, default: () => [] },
  options: { type: Object, default: () => ({ intervals: [], import_statuses: [] }) },
  can: { type: Object, default: () => ({}) },
  defaults: { type: Object, default: () => ({}) },
  admin: { type: Boolean, default: false },
});

const { t } = useI18n();

const connected = computed(() => props.integration?.status === 'connected');
const canManage = computed(() => props.can?.manage === true);
const tab = ref((() => {
  const params = new URLSearchParams((usePage().url || '').split('?')[1] || '');
  const requested = params.get('tab');

  if (connected.value && ['overview', 'history', 'settings', 'connection'].includes(requested)) {
    return requested;
  }

  return connected.value ? 'overview' : 'connection';
})());
const selectedSellerId = ref(props.defaults?.seller_id ?? '');
const selectedSeller = computed(
  () => props.sellers.find((seller) => Number(seller.id) === Number(selectedSellerId.value)) ?? null
);

watch(
  () => props.integration?.status,
  (status) => {
    if (status === 'connected' && tab.value === 'connection' && connected.value) {
      return;
    }

    if (status !== 'connected') {
      tab.value = 'connection';
    }
  },
);

const form = useForm({
  store_id: props.integration?.store_id ?? props.defaults?.store_id ?? props.stores[0]?.id ?? '',
  shop_slug: props.integration?.shop_slug ?? '',
  client_id: props.integration?.client_id ?? '',
  client_secret: '',
  access_token: '',
});

const mappingTargets = [
  'customer_first_name',
  'customer_last_name',
  'customer_phone',
  'city_id',
  'sector_id',
  'customer_address',
  'payment_method',
  'order_amount',
  'notes',
  'is_fragile',
  'can_be_opened',
  'option_exchange',
  'delivery_included',
];

const mappingFrom = (integration) =>
  Object.fromEntries(
    mappingTargets.map((field) => [field, integration?.field_mapping?.[field] || ''])
  );

const statusMappingFrom = (integration) => {
  const saved = integration?.status_mapping && !Array.isArray(integration.status_mapping)
    ? integration.status_mapping
    : {};

  return Object.fromEntries(
    (props.options.order_statuses ?? []).map((status) => [status.value, saved[status.value] || ''])
  );
};

const settingsForm = useForm({
  auto_sync_enabled: props.integration?.auto_sync_enabled ?? false,
  sync_interval_minutes: props.integration?.sync_interval_minutes ?? 15,
  import_status: props.integration?.import_status ?? 'unfulfilled',
  field_mapping: mappingFrom(props.integration),
  status_mapping: statusMappingFrom(props.integration),
});

watch(
  () => props.integration,
  (integration) => {
    if (!integration) {
      return;
    }

    settingsForm.auto_sync_enabled = integration.auto_sync_enabled ?? false;
    settingsForm.sync_interval_minutes = integration.sync_interval_minutes ?? 15;
    settingsForm.import_status = integration.import_status ?? 'unfulfilled';
    settingsForm.field_mapping = mappingFrom(integration);
    settingsForm.status_mapping = statusMappingFrom(integration);
  },
);

const hasExistingToken = computed(() => props.integration?.has_access_token === true);
const hasExistingClientSecret = computed(() => props.integration?.has_client_secret === true);
const hasExistingCredentials = computed(
  () => hasExistingToken.value || hasExistingClientSecret.value
);
const isSyncing = computed(() => props.integration?.is_syncing === true || syncing.value);
const syncing = ref(false);
const selectedSync = ref(null);

const orderStatuses = computed(() =>
  (props.options.import_statuses ?? []).filter((status) => status.group === 'order'),
);
const fulfillmentStatuses = computed(() =>
  (props.options.import_statuses ?? []).filter((status) => status.group === 'fulfillment'),
);
const paymentStatuses = computed(() =>
  (props.options.import_statuses ?? []).filter((status) => status.group === 'payment'),
);
const speedzoneStatuses = computed(() => props.options.order_statuses ?? []);
const exportStatuses = computed(() => props.options.export_statuses ?? []);
const exportGroups = computed(() => [
  'fulfillment',
  'tracking',
  'order',
]);
const statusMappingOpen = ref(false);
const fieldMappingOpen = ref(false);
const mappedStatusCount = computed(
  () => Object.values(settingsForm.status_mapping || {}).filter((value) => value).length
);
const mappedFieldCount = computed(
  () => Object.values(settingsForm.field_mapping || {}).filter((value) => value).length
);

const latest = computed(() => props.integration?.latest_sync ?? null);
const reviewSync = computed(() => props.integration?.review_sync ?? null);
const mappingRequired = new Set([
  'customer_first_name',
  'customer_last_name',
  'customer_phone',
  'city_id',
  'customer_address',
  'order_amount',
]);

const usedSources = computed(() => {
  const taken = new Set();

  Object.entries(settingsForm.field_mapping || {}).forEach(([field, source]) => {
    if (source) {
      taken.add(source);
    }
  });

  return taken;
});

const submit = () => {
  form.post(route('integrations.shopify.store'));
};

const saveSettings = () => {
  if (!props.integration) {
    return;
  }

  settingsForm.put(route('integrations.settings.update', props.integration.id));
};

const syncNow = () => {
  if (!props.integration || isSyncing.value) {
    return;
  }

  syncing.value = true;
  router.post(route('integrations.sync', props.integration.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      syncing.value = false;
    },
  });
};

const retryFailed = (sync) => {
  if (!props.integration || isSyncing.value || !sync?.id) {
    return;
  }

  syncing.value = true;
  router.post(route('integrations.sync.retry', [props.integration.id, sync.id]), {}, {
    preserveScroll: true,
    onFinish: () => {
      syncing.value = false;
    },
  });
};

const formatDate = (value) => {
  if (!value) {
    return null;
  }

  return new Date(value).toLocaleString();
};

const formatDuration = (seconds) => {
  if (seconds == null) {
    return '—';
  }

  if (seconds < 60) {
    return `${seconds}s`;
  }

  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;

  return rest ? `${minutes}m ${rest}s` : `${minutes}m`;
};

const skipLabel = (reason) => t(`integrations.sync.skips.${reason}`);

const syncRows = computed(() => {
  const sync = selectedSync.value;

  if (!sync) {
    return [];
  }

  return [
    { label: t('integrations.sync.columns.trigger'), value: sync.trigger_label },
    { label: t('integrations.sync.detail.fetched'), value: sync.fetched_count },
    { label: t('integrations.sync.detail.created'), value: sync.created_count, emphasis: true },
    { label: t('integrations.sync.detail.updated'), value: sync.updated_count },
    { label: t('integrations.sync.detail.skipped'), value: sync.skipped_count },
    { label: t('integrations.sync.detail.errors'), value: sync.error_count },
    { label: t('integrations.sync.detail.duration'), value: formatDuration(sync.duration_seconds) },
    { label: t('integrations.sync.columns.started'), value: formatDate(sync.started_at) },
    { label: t('integrations.sync.detail.error'), value: sync.error_message },
  ];
});

const showFlash = () => {
  const flash = usePage().props?.flash ?? {};
  const message = flash.success || flash.error;

  if (!message) {
    return;
  }

  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: flash.error ? 'error' : 'success',
    title: message,
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
  });
};

onMounted(showFlash);

watch(
  () => form.store_id,
  (storeId) => {
    if (!storeId || Number(storeId) === Number(props.integration?.store_id)) {
      return;
    }

    if (Number(storeId) === Number(props.defaults?.store_id)) {
      return;
    }

    router.get(route('integrations.shopify'), { store_id: storeId });
  },
);

watch(selectedSellerId, (sellerId) => {
  if (!props.admin || Number(sellerId) === Number(props.defaults?.seller_id)) {
    return;
  }

  router.get(route('integrations.shopify'), sellerId ? { seller_id: sellerId } : {});
});
</script>

<template>
  <Layout>
    <PageHeader
      :title="connected ? $t('integrations.shopify.manage_title') : $t('integrations.shopify.title')"
      :pageTitle="$t('integrations.title')"
    />

    <BRow>
      <BCol :xl="connected ? 12 : 8">
        <BCard no-body>
          <BCardHeader class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
            <div>
              <h5 class="card-title mb-1">Shopify</h5>
              <p class="text-muted mb-0 fs-13">
                <span v-if="admin && (selectedSeller || integration?.seller)">
                  {{ $t('integrations.admin.for_user', { name: (selectedSeller || integration?.seller)?.name }) }}
                  <span v-if="connected"> · </span>
                </span>
                <span v-if="connected">{{ integration.shop_name || integration.shop_slug }}</span>
                <span v-else-if="!(admin && (selectedSeller || integration?.seller))">
                  {{ $t('integrations.shopify.lead') }}
                </span>
              </p>
            </div>
            <BButton
              v-if="connected && canManage"
              variant="primary"
              :disabled="isSyncing"
              :title="$t('integrations.shopify.sync_now_help')"
              @click="syncNow"
            >
              <span v-if="isSyncing" class="spinner-border spinner-border-sm me-1" role="status"></span>
              <i v-else class="ri-refresh-line align-bottom me-1"></i>
              {{ isSyncing ? $t('integrations.sync.running') : $t('integrations.sync.now') }}
            </BButton>
          </BCardHeader>

          <ul v-if="connected" class="nav nav-tabs-custom nav-justified card-header-tabs border-bottom-0 px-3" role="tablist">
            <li class="nav-item">
              <button type="button" class="nav-link" :class="{ active: tab === 'overview' }" @click="tab = 'overview'">
                <i class="ri-dashboard-line align-bottom me-1"></i>
                {{ $t('integrations.shopify.tabs.overview') }}
              </button>
            </li>
            <li class="nav-item">
              <button type="button" class="nav-link" :class="{ active: tab === 'history' }" @click="tab = 'history'">
                <i class="ri-history-line align-bottom me-1"></i>
                {{ $t('integrations.shopify.tabs.history') }}
                <span class="badge bg-light text-body ms-1">{{ syncs.length }}</span>
              </button>
            </li>
            <li v-if="canManage" class="nav-item">
              <button type="button" class="nav-link" :class="{ active: tab === 'settings' }" @click="tab = 'settings'">
                <i class="ri-settings-3-line align-bottom me-1"></i>
                {{ $t('integrations.shopify.tabs.settings') }}
              </button>
            </li>
            <li v-if="canManage" class="nav-item">
              <button type="button" class="nav-link" :class="{ active: tab === 'connection' }" @click="tab = 'connection'">
                <i class="ri-plug-line align-bottom me-1"></i>
                {{ $t('integrations.shopify.tabs.connection') }}
              </button>
            </li>
          </ul>

          <BCardBody>
            <BAlert
              v-if="integration?.last_error"
              :model-value="true"
              variant="danger"
              class="mb-3"
            >
              {{ integration.last_error }}
            </BAlert>

            <div v-if="connected && tab === 'overview'">
              <BAlert
                v-if="reviewSync && reviewSync.reviewable_count > 0"
                :model-value="true"
                variant="warning"
                class="mb-3"
              >
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="flex-grow-1">
                    {{ $t('integrations.sync.review.pending_banner', { count: reviewSync.reviewable_count }) }}
                  </span>
                  <Link
                    v-if="canManage"
                    :href="route('integrations.shopify.review', reviewSync.id)"
                    class="btn btn-sm btn-warning"
                  >
                    {{ $t('integrations.sync.review.open') }}
                  </Link>
                </div>
              </BAlert>
              <BRow class="g-3">
                <BCol md="4">
                  <div class="border rounded p-3 h-100">
                    <div class="text-muted fs-13">{{ $t('common.status') }}</div>
                    <div class="fw-semibold mt-1">
                      <span class="badge bg-success-subtle text-success">{{ $t('integrations.sync.health_ok') }}</span>
                    </div>
                    <div v-if="integration.shop_slug" class="text-muted fs-12 mt-2">{{ integration.shop_slug }}</div>
                  </div>
                </BCol>
                <BCol md="4">
                  <div class="border rounded p-3 h-100">
                    <div class="text-muted fs-13">{{ $t('integrations.sync.last_sync') }}</div>
                    <div class="fw-semibold mt-1">
                      {{ formatDate(integration.last_synced_at) || $t('integrations.sync.never') }}
                    </div>
                    <div v-if="latest" class="mt-2">
                      <span class="badge" :class="`bg-${latest.status_color}-subtle text-${latest.status_color}`">
                        {{ latest.status_label }}
                      </span>
                    </div>
                  </div>
                </BCol>
                <BCol md="4">
                  <div class="border rounded p-3 h-100">
                    <div class="text-muted fs-13">{{ $t('integrations.sync.created_link', { count: latest?.created_count ?? 0 }) }}</div>
                    <div class="fw-semibold mt-1">
                      <Link
                        v-if="latest && latest.created_count > 0"
                        :href="route('orders.index', { ecommerce_sync_id: latest.id })"
                      >
                        {{ $t('integrations.sync.created_link', { count: latest.created_count }) }}
                      </Link>
                      <span v-else>{{ latest?.created_count ?? 0 }}</span>
                    </div>
                    <div v-if="integration.next_sync_at && integration.auto_sync_enabled" class="text-muted fs-12 mt-2">
                      {{ $t('integrations.sync.next_sync') }}: {{ formatDate(integration.next_sync_at) }}
                    </div>
                  </div>
                </BCol>
              </BRow>
            </div>

            <div v-else-if="connected && tab === 'history'">
              <p v-if="syncs.length === 0" class="text-muted mb-0">{{ $t('integrations.sync.empty_history') }}</p>
              <div v-else class="table-responsive">
                <table class="table align-middle table-nowrap mb-0">
                  <thead class="table-light text-muted">
                    <tr class="fs-11 text-uppercase">
                      <th>{{ $t('integrations.sync.columns.started') }}</th>
                      <th>{{ $t('integrations.sync.columns.trigger') }}</th>
                      <th>{{ $t('integrations.sync.columns.status') }}</th>
                      <th class="text-end">{{ $t('integrations.sync.columns.created') }}</th>
                      <th class="text-end">{{ $t('integrations.sync.columns.updated') }}</th>
                      <th class="text-end">{{ $t('integrations.sync.columns.skipped') }}</th>
                      <th class="text-end">{{ $t('integrations.sync.columns.errors') }}</th>
                      <th class="text-end">{{ $t('integrations.sync.columns.duration') }}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="sync in syncs"
                      :key="sync.id"
                      role="button"
                      @click="selectedSync = sync"
                    >
                      <td>{{ formatDate(sync.started_at) }}</td>
                      <td>{{ sync.trigger_label }}</td>
                      <td>
                        <span class="badge" :class="`bg-${sync.status_color}-subtle text-${sync.status_color}`">
                          {{ sync.status_label }}
                        </span>
                      </td>
                      <td class="text-end">
                        <Link
                          v-if="sync.created_count > 0"
                          :href="route('orders.index', { ecommerce_sync_id: sync.id })"
                          @click.stop
                        >
                          {{ sync.created_count }}
                        </Link>
                        <span v-else>{{ sync.created_count }}</span>
                      </td>
                      <td class="text-end">{{ sync.updated_count }}</td>
                      <td class="text-end">{{ sync.skipped_count }}</td>
                      <td class="text-end">{{ sync.error_count }}</td>
                      <td class="text-end">{{ formatDuration(sync.duration_seconds) }}</td>
                      <td class="text-end">
                        <div class="d-flex justify-content-end gap-1" @click.stop>
                          <button
                            v-if="canManage && sync.can_retry"
                            type="button"
                            class="btn btn-sm btn-ghost-warning"
                            :disabled="isSyncing"
                            :title="$t('integrations.sync.retry_help')"
                            @click="retryFailed(sync)"
                          >
                            <i class="ri-restart-line align-bottom me-1"></i>
                            {{ $t('integrations.sync.retry_failed') }}
                          </button>
                          <Link
                            v-if="canManage && sync.reviewable_count > 0"
                            :href="route('integrations.shopify.review', sync.id)"
                            class="btn btn-sm btn-ghost-primary"
                          >
                            {{ $t('integrations.sync.review.open') }}
                          </Link>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <form v-else-if="connected && tab === 'settings' && canManage" @submit.prevent="saveSettings">
              <BRow class="g-3">
                <BCol md="6">
                  <div class="form-check form-switch">
                    <input
                      id="auto_sync_enabled"
                      v-model="settingsForm.auto_sync_enabled"
                      class="form-check-input"
                      type="checkbox"
                    />
                    <label class="form-check-label" for="auto_sync_enabled">
                      {{ $t('integrations.sync.auto') }}
                    </label>
                  </div>
                  <div class="form-text">{{ $t('integrations.shopify.auto_help') }}</div>
                </BCol>
                <BCol md="6">
                  <label class="form-label">{{ $t('integrations.sync.interval') }}</label>
                  <select v-model.number="settingsForm.sync_interval_minutes" class="form-select">
                    <option v-for="interval in options.intervals" :key="interval.value" :value="interval.value">
                      {{ interval.label }}
                    </option>
                  </select>
                </BCol>
                <BCol md="12">
                  <label class="form-label">{{ $t('integrations.sync.import_status') }}</label>
                  <select v-model="settingsForm.import_status" class="form-select">
                    <optgroup :label="$t('integrations.shopify.import_groups.order')">
                      <option v-for="status in orderStatuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                      </option>
                    </optgroup>
                    <optgroup :label="$t('integrations.shopify.import_groups.fulfillment')">
                      <option v-for="status in fulfillmentStatuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                      </option>
                    </optgroup>
                    <optgroup :label="$t('integrations.shopify.import_groups.payment')">
                      <option v-for="status in paymentStatuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                      </option>
                    </optgroup>
                  </select>
                  <div class="form-text">{{ $t('integrations.shopify.import_status_help') }}</div>
                </BCol>
                <BCol md="12">
                  <div class="border rounded">
                    <button
                      type="button"
                      class="btn btn-light w-100 d-flex align-items-center justify-content-between text-start px-3 py-2"
                      :aria-expanded="statusMappingOpen"
                      @click="statusMappingOpen = !statusMappingOpen"
                    >
                      <span>
                        <span class="fw-semibold">{{ $t('integrations.shopify.status_mapping.title') }}</span>
                        <span class="badge bg-primary-subtle text-primary ms-2">
                          {{ $t('integrations.shopify.status_mapping.mapped_count', { count: mappedStatusCount }) }}
                        </span>
                      </span>
                      <i
                        class="fs-18 text-muted pointer-events-none"
                        :class="statusMappingOpen ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                      ></i>
                    </button>
                    <div v-show="statusMappingOpen" class="p-3 border-top">
                      <p class="text-muted fs-13">{{ $t('integrations.shopify.status_mapping.help') }}</p>
                      <div class="table-responsive border rounded">
                        <table class="table align-middle table-nowrap mb-0">
                          <thead class="table-light text-muted">
                            <tr>
                              <th style="width: 40%">{{ $t('integrations.shopify.status_mapping.speedzone') }}</th>
                              <th>{{ $t('integrations.shopify.status_mapping.shopify') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="status in speedzoneStatuses" :key="status.value">
                              <td>
                                <span class="fw-medium">{{ status.label }}</span>
                                <div class="text-muted fs-12">{{ status.value }}</div>
                              </td>
                              <td>
                                <select
                                  v-model="settingsForm.status_mapping[status.value]"
                                  class="form-select form-select-sm"
                                >
                                  <option value="">{{ $t('integrations.shopify.status_mapping.none') }}</option>
                                  <optgroup
                                    v-for="group in exportGroups"
                                    :key="group"
                                    :label="$t(`integrations.shopify.export_groups.${group}`)"
                                  >
                                    <option
                                      v-for="target in exportStatuses.filter((item) => item.group === group)"
                                      :key="target.value"
                                      :value="target.value"
                                    >
                                      {{ target.label }}
                                    </option>
                                  </optgroup>
                                </select>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <InputError :message="settingsForm.errors.status_mapping" />
                    </div>
                  </div>
                </BCol>
                <BCol md="12">
                  <div class="border rounded">
                    <button
                      type="button"
                      class="btn btn-light w-100 d-flex align-items-center justify-content-between text-start px-3 py-2"
                      :aria-expanded="fieldMappingOpen"
                      @click="fieldMappingOpen = !fieldMappingOpen"
                    >
                      <span>
                        <span class="fw-semibold">{{ $t('integrations.sync.mapping.title') }}</span>
                        <span class="badge bg-primary-subtle text-primary ms-2">
                          {{ $t('integrations.shopify.status_mapping.mapped_count', { count: mappedFieldCount }) }}
                        </span>
                      </span>
                      <i
                        class="fs-18 text-muted pointer-events-none"
                        :class="fieldMappingOpen ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"
                      ></i>
                    </button>
                    <div v-show="fieldMappingOpen" class="p-3 border-top">
                      <p class="text-muted fs-13">{{ $t('integrations.shopify.mapping_help') }}</p>
                      <div class="table-responsive border rounded">
                        <table class="table align-middle table-nowrap mb-0">
                          <thead class="table-light text-muted">
                            <tr>
                              <th style="width: 40%">{{ $t('orders.import.mapping.system_field') }}</th>
                              <th>{{ $t('integrations.sync.mapping.source_field') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="field in mappingTargets" :key="field">
                              <td>
                                <span class="fw-medium">{{ $t(`orders.import.fields.${field}`) }}</span>
                                <span v-if="mappingRequired.has(field)" class="text-danger ms-1">*</span>
                              </td>
                              <td>
                                <select
                                  v-model="settingsForm.field_mapping[field]"
                                  class="form-select form-select-sm"
                                >
                                  <option value="">{{ $t('orders.import.mapping.not_mapped') }}</option>
                                  <option
                                    v-for="source in integration.source_fields"
                                    :key="source.key"
                                    :value="source.key"
                                    :disabled="usedSources.has(source.key) && settingsForm.field_mapping[field] !== source.key"
                                  >
                                    {{ source.label }}
                                  </option>
                                </select>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </BCol>
              </BRow>
              <div class="d-flex justify-content-end mt-4">
                <BButton type="submit" variant="primary" :disabled="settingsForm.processing">
                  {{ $t('integrations.sync.save_settings') }}
                </BButton>
              </div>
            </form>

            <form v-if="!connected || tab === 'connection'" @submit.prevent="submit">
              <BAlert v-if="admin && stores.length === 0" :model-value="true" variant="info" class="mb-3">
                {{ $t('integrations.shopify.pick_user') }}
              </BAlert>
              <BRow class="g-3">
                <BCol v-if="admin" md="6">
                  <label class="form-label" for="seller_id">
                    {{ $t('integrations.admin.user_field') }}
                    <span class="text-danger">*</span>
                  </label>
                  <select
                    id="seller_id"
                    v-model="selectedSellerId"
                    class="form-select"
                  >
                    <option value="">{{ $t('integrations.admin.user_placeholder') }}</option>
                    <option v-for="seller in sellers" :key="seller.id" :value="seller.id">
                      {{ seller.name }} — {{ seller.email }}
                    </option>
                  </select>
                  <div class="form-text">{{ $t('integrations.admin.user_help') }}</div>
                </BCol>

                <BCol md="6">
                  <label class="form-label">
                    {{ $t('integrations.shopify.fields.store') }}
                    <span class="text-danger">*</span>
                  </label>
                  <select
                    v-model="form.store_id"
                    class="form-select"
                    :class="{ 'is-invalid': form.errors.store_id }"
                    :disabled="admin && stores.length === 0"
                  >
                    <option v-for="store in stores" :key="store.id" :value="store.id">
                      {{ store.name }}
                    </option>
                  </select>
                  <div class="form-text">{{ $t('integrations.shopify.fields.store_help') }}</div>
                  <InputError :message="form.errors.store_id" />
                </BCol>

                <template v-if="!admin || stores.length > 0">
                <BCol md="12">
                  <label class="form-label">
                    {{ $t('integrations.shopify.fields.shop_slug') }}
                    <span class="text-danger">*</span>
                  </label>
                  <input
                    v-model="form.shop_slug"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.shop_slug }"
                    :placeholder="$t('integrations.shopify.fields.shop_slug_placeholder')"
                    autocomplete="off"
                  />
                  <div class="form-text">{{ $t('integrations.shopify.fields.shop_slug_help') }}</div>
                  <InputError :message="form.errors.shop_slug" />
                </BCol>

                <BCol md="6">
                  <label class="form-label">
                    {{ $t('integrations.shopify.fields.client_id') }}
                    <span v-if="!hasExistingCredentials" class="text-danger">*</span>
                  </label>
                  <input
                    v-model="form.client_id"
                    type="text"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.client_id }"
                    :placeholder="$t('integrations.shopify.fields.client_id_placeholder')"
                    autocomplete="off"
                  />
                  <div class="form-text">{{ $t('integrations.shopify.fields.client_id_help') }}</div>
                  <InputError :message="form.errors.client_id" />
                </BCol>

                <BCol md="6">
                  <label class="form-label">
                    {{ $t('integrations.shopify.fields.client_secret') }}
                    <span v-if="!hasExistingClientSecret && !hasExistingToken" class="text-danger">*</span>
                  </label>
                  <input
                    v-model="form.client_secret"
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.client_secret }"
                    autocomplete="off"
                  />
                  <div v-if="hasExistingClientSecret" class="form-text">
                    {{ $t('integrations.shopify.fields.client_secret_keep') }}
                  </div>
                  <InputError :message="form.errors.client_secret" />
                </BCol>

                <BCol md="12">
                  <label class="form-label">
                    {{ $t('integrations.shopify.fields.access_token') }}
                  </label>
                  <input
                    v-model="form.access_token"
                    type="password"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.access_token }"
                    autocomplete="off"
                  />
                  <div class="form-text">
                    {{
                      hasExistingToken
                        ? $t('integrations.shopify.fields.access_token_keep')
                        : $t('integrations.shopify.fields.access_token_help')
                    }}
                  </div>
                  <InputError :message="form.errors.access_token" />
                </BCol>
                </template>
              </BRow>

              <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('integrations.index')" class="btn btn-light">
                  {{ $t('common.cancel') }}
                </Link>
                <BButton
                  v-if="canManage"
                  type="submit"
                  variant="primary"
                  :disabled="form.processing || stores.length === 0"
                >
                  <i class="ri-login-circle-line align-bottom me-1"></i>
                  {{ $t('integrations.shopify.submit') }}
                </BButton>
              </div>
            </form>
          </BCardBody>
        </BCard>
      </BCol>

      <BCol v-if="!connected" xl="4">
        <BCard no-body>
          <BCardHeader>
            <h5 class="card-title mb-0">{{ $t('integrations.shopify.howto.title') }}</h5>
          </BCardHeader>
          <BCardBody>
            <ol class="fs-13 text-muted mb-0 ps-3">
              <li class="mb-2">{{ $t('integrations.shopify.howto.step1') }}</li>
              <li class="mb-2">{{ $t('integrations.shopify.howto.step2') }}</li>
              <li class="mb-2">{{ $t('integrations.shopify.howto.step3') }}</li>
              <li>{{ $t('integrations.shopify.howto.step4') }}</li>
            </ol>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <EntityDetailSheet
      :show="selectedSync !== null"
      :title="selectedSync ? $t('integrations.sync.detail.title', { id: selectedSync.id }) : ''"
      :status-label="selectedSync?.status_label"
      :status-color="selectedSync?.status_color"
      :rows="syncRows"
      @close="selectedSync = null"
    >
      <div v-if="selectedSync?.skipped_reasons?.length" class="mt-3">
        <div class="fw-semibold fs-13 mb-2">{{ $t('integrations.sync.detail.skips') }}</div>
        <ul class="fs-13 text-muted mb-0 ps-3">
          <li v-for="(skip, index) in selectedSync.skipped_reasons" :key="index">
            {{ skip.ref || skip.external_order_id }} — {{ skipLabel(skip.reason) }}
            <span v-if="skip.detail"> ({{ skip.detail }})</span>
          </li>
        </ul>
      </div>
      <template v-if="canManage && selectedSync && (selectedSync.can_retry || selectedSync.reviewable_count > 0)" #actions>
        <button
          v-if="selectedSync.can_retry"
          type="button"
          class="btn btn-warning"
          :disabled="isSyncing"
          :title="$t('integrations.sync.retry_help')"
          @click="retryFailed(selectedSync)"
        >
          <i class="ri-restart-line align-bottom me-1"></i>
          {{ $t('integrations.sync.retry_failed') }}
        </button>
        <Link
          v-if="selectedSync.reviewable_count > 0"
          :href="route('integrations.shopify.review', selectedSync.id)"
          class="btn btn-primary"
        >
          {{ $t('integrations.sync.review.open') }}
        </Link>
      </template>
    </EntityDetailSheet>
  </Layout>
</template>
