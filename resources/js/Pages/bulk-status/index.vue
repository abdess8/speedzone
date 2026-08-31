<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import WizardSteps from './Partials/WizardSteps.vue';
import TransitionBadge from './Partials/TransitionBadge.vue';
import BulkQrScanner from './Partials/BulkQrScanner.vue';
import ConfirmSheet from './Partials/ConfirmSheet.vue';
import ResultPanel from './Partials/ResultPanel.vue';

/**
 * Bulk status editing, as a progressive form: type → target → filters →
 * selection → confirmation → result.
 *
 * Nothing about the workflow is hard-coded here. The entities, the target
 * statuses and the source statuses that lead to each of them all arrive from
 * the server, already narrowed to what this user is granted, and the board is
 * fetched per target rather than filtered client side — so no combination the
 * server would refuse can ever be assembled on screen.
 */
const { t } = useI18n();

const props = defineProps({
  entities: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  result: { type: Object, default: null },
});

/** Loaded lazily and capped: a batch is an operational act, not an export. */
const MAX_SELECTION = 500;

const step = ref('entity');
const entityType = ref(props.filters.entity_type ?? null);
const toStatus = ref(props.filters.to_status ?? null);

const board = reactive({
  items: [],
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  sources: [],
  loading: false,
});

const boardFilters = reactive({
  search: props.filters.search ?? '',
  source_status: props.filters.source_status ?? '',
});

/** Keyed by id so a selection survives paging, filtering and scanning alike. */
const selection = ref(new Map());

const showScanner = ref(false);
const showConfirm = ref(false);
const processing = ref(false);
/** Set by the mobile quick action: open the camera as soon as a target exists. */
const autoScan = ref(String(props.filters.scan ?? '') === '1');

const entity = computed(() => props.entities.find((row) => row.value === entityType.value) ?? null);
const target = computed(
  () => entity.value?.targets.find((row) => row.value === toStatus.value) ?? null
);
const selected = computed(() => [...selection.value.values()]);

const reachable = computed(() => {
  const steps = ['entity'];

  if (entityType.value) steps.push('target');
  if (toStatus.value) steps.push('selection');
  if (props.result) steps.push('result');

  return steps;
});

const pageIds = computed(() => board.items.map((item) => item.id));
const allPageSelected = computed(
  () => board.items.length > 0 && pageIds.value.every((id) => selection.value.has(id))
);

const toast = (icon, title) =>
  Swal.fire({ toast: true, position: 'top-end', icon, title, timer: 3000, showConfirmButton: false });

const fetchBoard = async (page = 1) => {
  if (!entityType.value || !toStatus.value) {
    return;
  }

  board.loading = true;

  try {
    const { data } = await axios.get(route('bulk-status.items'), {
      params: {
        entity_type: entityType.value,
        to_status: toStatus.value,
        source_status: boardFilters.source_status || undefined,
        search: boardFilters.search || undefined,
        page,
        per_page: 25,
      },
    });

    board.items = data.data;
    board.meta = data.meta;
    board.sources = data.source_options;
  } catch (error) {
    toast('error', error.response?.data?.message ?? t('bulk_status.errors.target_forbidden'));
  } finally {
    board.loading = false;
  }
};

const chooseEntity = (value) => {
  entityType.value = value;
  toStatus.value = null;
  selection.value = new Map();
  step.value = 'target';
};

const chooseTarget = (value) => {
  toStatus.value = value;
  // A transition the previous target allowed says nothing about this one.
  selection.value = new Map();
  boardFilters.source_status = '';
  step.value = 'selection';
  fetchBoard(1);

  if (autoScan.value) {
    autoScan.value = false;
    showScanner.value = true;
  }
};

const toggle = (item) => {
  const next = new Map(selection.value);

  next.has(item.id) ? next.delete(item.id) : next.set(item.id, item);
  selection.value = next;
};

const togglePage = () => {
  const next = new Map(selection.value);

  allPageSelected.value
    ? pageIds.value.forEach((id) => next.delete(id))
    : board.items.forEach((item) => next.set(item.id, item));

  selection.value = next;
};

/**
 * "Select all" means every eligible item, not every visible one — which is the
 * whole point on a board of 240 parcels. Walked page by page against the same
 * endpoint so the server keeps deciding what qualifies.
 */
const selectAllEligible = async () => {
  board.loading = true;

  try {
    const next = new Map(selection.value);
    let page = 1;
    let lastPage = 1;

    do {
      const { data } = await axios.get(route('bulk-status.items'), {
        params: {
          entity_type: entityType.value,
          to_status: toStatus.value,
          source_status: boardFilters.source_status || undefined,
          search: boardFilters.search || undefined,
          page,
          per_page: 100,
        },
      });

      data.data.forEach((item) => {
        if (next.size < MAX_SELECTION) {
          next.set(item.id, item);
        }
      });

      lastPage = data.meta.last_page;
      page += 1;
    } while (page <= lastPage && next.size < MAX_SELECTION);

    selection.value = next;

    if (next.size >= MAX_SELECTION && board.meta.total > MAX_SELECTION) {
      toast('info', t('bulk_status.selection.selected', { count: next.size }));
    }
  } finally {
    board.loading = false;
  }
};

const clearSelection = () => {
  selection.value = new Map();
};

const addScanned = (item) => {
  if (selection.value.has(item.id)) {
    toast('info', t('bulk_status.scan.already', { reference: item.reference }));

    return;
  }

  const next = new Map(selection.value);
  next.set(item.id, item);
  selection.value = next;
};

const submit = (comment) => {
  processing.value = true;

  router.post(
    route('bulk-status.store'),
    {
      entity_type: entityType.value,
      to_status: toStatus.value,
      comment: comment || null,
      items: selected.value.map((item) => ({ id: item.id, from_status: item.from_status.value })),
    },
    {
      // The wizard keeps its own state across the round trip: landing back on
      // step one after a 200-parcel batch would lose the operator's place.
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        clearSelection();
        showConfirm.value = false;
        step.value = 'result';
        fetchBoard(1);
      },
      onFinish: () => {
        processing.value = false;
      },
    }
  );
};

const restart = () => {
  step.value = entityType.value ? 'selection' : 'entity';
  fetchBoard(1);
};

let searchTimer = null;
watch(
  () => boardFilters.search,
  () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchBoard(1), 350);
  }
);

watch(() => boardFilters.source_status, () => fetchBoard(1));

onMounted(() => {
  if (props.result) {
    step.value = 'result';

    return;
  }

  // Deep link from the orders or returns toolbar: skip the step already made.
  if (entityType.value && toStatus.value) {
    step.value = 'selection';
    fetchBoard(1);

    if (autoScan.value) {
      autoScan.value = false;
      showScanner.value = true;
    }
  } else if (entityType.value) {
    step.value = 'target';
  } else if (props.entities.length === 1) {
    chooseEntity(props.entities[0].value);
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('bulk_status.title')" :pageTitle="$t('bulk_status.page_title')" />

    <BCard no-body>
      <BCardBody class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <WizardSteps :current="step" :reachable="reachable" @go="step = $event" />

        <TransitionBadge
          v-if="target && step !== 'entity' && step !== 'target'"
          :from="{ label: entity.label, color: 'secondary', icon: entity.icon }"
          :to="target"
        />
      </BCardBody>
    </BCard>

    <!-- Step 1 — what is being edited -->
    <BCard v-if="step === 'entity'" no-body>
      <BCardBody>
        <h5 class="mb-1">{{ $t('bulk_status.entity_step.title') }}</h5>
        <p class="text-muted">{{ $t('bulk_status.entity_step.help') }}</p>

        <div class="row g-3">
          <BCol v-for="row in entities" :key="row.value" md="6">
            <button
              type="button"
              class="btn btn-outline-light text-start w-100 p-3 choice-card"
              :class="{ active: entityType === row.value }"
              @click="chooseEntity(row.value)"
            >
              <div class="d-flex align-items-center gap-3">
                <span class="avatar-sm flex-shrink-0">
                  <span class="avatar-title bg-primary-subtle text-primary rounded fs-20">
                    <i :class="row.icon"></i>
                  </span>
                </span>
                <div>
                  <div class="fw-semibold text-body">{{ row.label }}</div>
                  <div class="text-muted small">
                    {{ $t('bulk_status.target_step.sources', {
                      statuses: row.targets.map((item) => item.label).join(', '),
                    }) }}
                  </div>
                </div>
              </div>
            </button>
          </BCol>
        </div>
      </BCardBody>
    </BCard>

    <!-- Step 2 — target status -->
    <BCard v-else-if="step === 'target'" no-body>
      <BCardBody>
        <h5 class="mb-1">{{ $t('bulk_status.target_step.title') }}</h5>
        <p class="text-muted">{{ $t('bulk_status.target_step.help') }}</p>

        <p v-if="!entity || entity.targets.length === 0" class="text-muted mb-0">
          {{ $t('bulk_status.target_step.empty') }}
        </p>

        <div v-else class="row g-3">
          <BCol v-for="option in entity.targets" :key="option.value" md="6" xl="4">
            <button
              type="button"
              class="btn btn-outline-light text-start w-100 p-3 choice-card h-100"
              :class="{ active: toStatus === option.value }"
              @click="chooseTarget(option.value)"
            >
              <span class="badge mb-2" :class="`bg-${option.color}-subtle text-${option.color}`">
                <i :class="option.icon" class="me-1"></i>{{ option.label }}
              </span>
              <div class="text-muted small">
                {{ $t('bulk_status.target_step.sources', {
                  statuses: option.sources.map((source) => source.label).join(', '),
                }) }}
              </div>
            </button>
          </BCol>
        </div>
      </BCardBody>
    </BCard>

    <!-- Steps 3 & 4 — filters and selection -->
    <BCard v-else-if="step === 'selection'" no-body>
      <BCardBody class="border-bottom">
        <div class="row g-2 align-items-end">
          <BCol lg="5">
            <label class="form-label">{{ $t('common.search') }}</label>
            <div class="input-group">
              <span class="input-group-text"><i class="ri-search-line"></i></span>
              <input
                v-model="boardFilters.search"
                type="search"
                class="form-control"
                :placeholder="$t('bulk_status.selection.search')"
              />
            </div>
          </BCol>

          <BCol lg="4">
            <label class="form-label">{{ $t('bulk_status.selection.source_filter') }}</label>
            <select v-model="boardFilters.source_status" class="form-select">
              <option value="">{{ $t('bulk_status.selection.all_sources') }}</option>
              <option v-for="source in board.sources" :key="source.value" :value="source.value">
                {{ source.label }}
              </option>
            </select>
          </BCol>

          <BCol lg="3" class="d-grid">
            <button type="button" class="btn btn-soft-primary" @click="showScanner = true">
              <i class="ri-qr-scan-2-line align-bottom me-1"></i>{{ $t('bulk_status.scan.button') }}
            </button>
          </BCol>
        </div>
      </BCardBody>

      <BCardBody class="bg-light border-bottom-dashed py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <div class="form-check mb-0">
            <input
              id="bulk-select-page"
              class="form-check-input"
              type="checkbox"
              :checked="allPageSelected"
              @change="togglePage"
            />
            <label class="form-check-label" for="bulk-select-page">
              {{ $t('bulk_status.selection.select_page') }}
            </label>
          </div>

          <button
            v-if="board.meta.total > board.items.length"
            type="button"
            class="btn btn-link btn-sm p-0"
            @click="selectAllEligible"
          >
            {{ $t('bulk_status.selection.select_all') }}
          </button>

          <span class="text-muted">
            {{ $t('bulk_status.selection.eligible', { count: board.meta.total }) }}
          </span>
          <span class="fw-semibold">
            · {{ $t('bulk_status.selection.selected', { count: selected.length }) }}
          </span>

          <button
            v-if="selected.length"
            type="button"
            class="btn btn-link btn-sm text-danger p-0 ms-auto"
            @click="clearSelection"
          >
            {{ $t('bulk_status.selection.clear') }}
          </button>
        </div>
      </BCardBody>

      <BCardBody>
        <div v-if="board.loading" class="text-center text-muted py-5">
          <span class="spinner-border spinner-border-sm me-2"></span>
          {{ $t('bulk_status.selection.loading') }}
        </div>

        <p v-else-if="board.items.length === 0" class="text-muted text-center py-5 mb-0">
          {{ $t('bulk_status.selection.empty') }}
        </p>

        <template v-else>
          <!-- Desktop -->
          <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="width: 32px">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="allPageSelected"
                      @change="togglePage"
                    />
                  </th>
                  <th>{{ $t('bulk_status.columns.reference') }}</th>
                  <th>{{ $t('bulk_status.columns.current_status') }}</th>
                  <th>{{ $t('bulk_status.columns.new_status') }}</th>
                  <th>{{ $t('bulk_status.columns.customer') }}</th>
                  <th>{{ $t('bulk_status.columns.details') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in board.items"
                  :key="item.id"
                  :class="{ 'table-active': selection.has(item.id) }"
                  @click="toggle(item)"
                >
                  <td @click.stop>
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="selection.has(item.id)"
                      @change="toggle(item)"
                    />
                  </td>
                  <td class="fw-medium">{{ item.reference }}</td>
                  <td>
                    <span
                      class="badge"
                      :class="`bg-${item.from_status.color}-subtle text-${item.from_status.color}`"
                    >
                      {{ item.from_status.label }}
                    </span>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="`bg-${item.to_status.color}-subtle text-${item.to_status.color}`"
                    >
                      {{ item.to_status.label }}
                    </span>
                  </td>
                  <td>
                    <div>{{ item.customer.name ?? $t('common.empty_value') }}</div>
                    <div class="text-muted fs-12">{{ item.customer.phone }}</div>
                  </td>
                  <td class="text-muted fs-12">
                    <span v-for="detail in item.details" :key="detail.label" class="me-2">
                      {{ detail.label }}: <span class="text-body">{{ detail.value }}</span>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Mobile -->
          <div class="d-lg-none vstack gap-2">
            <button
              v-for="item in board.items"
              :key="item.id"
              type="button"
              class="btn btn-outline-light text-start p-3 choice-card"
              :class="{ active: selection.has(item.id) }"
              @click="toggle(item)"
            >
              <div class="d-flex align-items-start gap-2">
                <input
                  class="form-check-input mt-1 flex-shrink-0"
                  type="checkbox"
                  :checked="selection.has(item.id)"
                  tabindex="-1"
                />
                <div class="flex-grow-1 min-w-0">
                  <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold text-body">{{ item.reference }}</span>
                  </div>
                  <TransitionBadge :from="item.from_status" :to="item.to_status" size="sm" />
                  <div class="text-muted fs-12 mt-1">
                    {{ item.customer.name }} · {{ item.customer.phone }}
                  </div>
                  <div class="text-muted fs-12">
                    <span v-for="detail in item.details" :key="detail.label" class="me-2">
                      {{ detail.label }}: {{ detail.value }}
                    </span>
                  </div>
                </div>
              </div>
            </button>
          </div>

          <div
            v-if="board.meta.last_page > 1"
            class="d-flex justify-content-between align-items-center mt-3"
          >
            <button
              type="button"
              class="btn btn-sm btn-light"
              :disabled="board.meta.current_page <= 1"
              @click="fetchBoard(board.meta.current_page - 1)"
            >
              <i class="ri-arrow-left-s-line"></i>
            </button>
            <span class="text-muted small">
              {{ board.meta.current_page }} / {{ board.meta.last_page }}
            </span>
            <button
              type="button"
              class="btn btn-sm btn-light"
              :disabled="board.meta.current_page >= board.meta.last_page"
              @click="fetchBoard(board.meta.current_page + 1)"
            >
              <i class="ri-arrow-right-s-line"></i>
            </button>
          </div>
        </template>
      </BCardBody>
    </BCard>

    <!-- Step 6 — result -->
    <ResultPanel v-else-if="step === 'result' && result" :result="result" @restart="restart" />

    <!-- Sticky action bar, so the count and the way forward follow the scroll. -->
    <div v-if="step === 'selection' && selected.length" class="bulk-action-bar">
      <div class="d-flex align-items-center justify-content-between gap-3">
        <span class="fw-semibold">
          {{ $t('bulk_status.selection.selected', { count: selected.length }) }}
        </span>
        <button type="button" class="btn btn-primary" @click="showConfirm = true">
          <i class="ri-check-double-line align-bottom me-1"></i>
          {{ $t('bulk_status.confirm.submit') }}
        </button>
      </div>
    </div>

    <BulkQrScanner
      v-if="entityType && toStatus"
      :show="showScanner"
      :entity-type="entityType"
      :to-status="toStatus"
      :target="target"
      @close="showScanner = false"
      @select="addScanned"
    />

    <ConfirmSheet
      :show="showConfirm"
      :items="selected"
      :target="target"
      :entity-label="entity?.label ?? ''"
      :processing="processing"
      @close="showConfirm = false"
      @confirm="submit"
    />
  </Layout>
</template>

<style scoped>
.choice-card {
  border: 1px solid var(--vz-border-color, #e9ebec);
}

.choice-card.active {
  border-color: var(--vz-primary, #0d4a9d);
  background-color: var(--vz-primary-bg-subtle, rgba(13, 74, 157, 0.1));
}

.min-w-0 {
  min-width: 0;
}

.bulk-action-bar {
  position: sticky;
  z-index: 1030;
  bottom: 0;
  margin-top: 1rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--vz-border-color, #e9ebec);
  border-radius: 0.5rem;
  background-color: var(--vz-card-bg, #fff);
  box-shadow: 0 -0.25rem 1rem rgba(0, 0, 0, 0.08);
}

/* The mobile tab bar owns the bottom edge. */
@media (max-width: 767.98px) {
  .bulk-action-bar {
    bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px));
  }
}
</style>
