<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Layout from '@/Layouts/main.vue';
import PageHeader from '@/Components/page-header.vue';
import FilterPanel from '@/Components/FilterPanel.vue';
import ProductThumb from '../Partials/ProductThumb.vue';
import ReasonSheet from './Partials/ReasonSheet.vue';
import { formatMoneyRounded } from '@/common/formatMoney';

/**
 * Mass inventory: count a shelf, record what was found.
 *
 * One editable table rather than one form per reference, because an inventory is
 * done in a single pass with a scanner in one hand — sixty round trips through a
 * detail page is not a workflow. Arrow keys and Enter move down the column, so
 * the whole sheet can be filled without touching the mouse.
 *
 * Only the lines the counter actually typed into are submitted, and only those
 * whose count differs from the recorded quantity produce a ledger entry: an
 * inventory that confirms the screen is not an event.
 */

const { t } = useI18n();

const props = defineProps({
  products: { type: Object, default: () => ({ data: [], meta: {} }) },
  filters: { type: Object, default: () => ({}) },
  reasons: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  can: { type: Object, default: () => ({}) },
});

const filters = reactive({
  search: props.filters.search ?? '',
  category: props.filters.category ?? '',
  stock_status: props.filters.stock_status ?? '',
});

/**
 * Counting state, keyed by product id.
 *
 * Kept apart from the paginated rows so a filter change or a page turn does not
 * silently discard what has already been counted. The submit button says how
 * many lines are pending precisely so nothing is lost without being noticed.
 *
 * @type {Record<number, {counted: string, reason: string, note: string}>}
 */
const drafts = reactive({});

const rows = computed(() => props.products.data ?? []);
const meta = computed(() => props.products.meta ?? {});

const countedInputs = ref([]);
const reasonTarget = ref(null);
const submitting = ref(false);
/** Product ids in the order they were submitted, to map server errors back. */
let submittedOrder = [];
const lineErrors = ref({});

const activeFilterCount = computed(
  () => Object.values(filters).filter((value) => value !== '' && value !== null).length
);

function draftFor(productId) {
  if (!drafts[productId]) {
    drafts[productId] = { counted: '', reason: '', note: '' };
  }

  return drafts[productId];
}

/** A line the counter has typed a quantity into, whatever that quantity is. */
function isCounted(productId) {
  const value = drafts[productId]?.counted;

  return value !== undefined && value !== null && String(value).trim() !== '';
}

function deltaFor(row) {
  if (!isCounted(row.id)) {
    return null;
  }

  return Number(drafts[row.id].counted) - row.stock_quantity;
}

/** Lines that will be sent: everything touched, gap or not. */
const pendingLines = computed(() =>
  rows.value
    .filter((row) => isCounted(row.id))
    .map((row) => ({
      product_id: row.id,
      name: row.name,
      recorded: row.stock_quantity,
      counted: Number(drafts[row.id].counted),
      delta: Number(drafts[row.id].counted) - row.stock_quantity,
      reason: drafts[row.id].reason,
      note: drafts[row.id].note,
    }))
);

/** Lines that move the stock and therefore owe a motive. */
const adjustedLines = computed(() => pendingLines.value.filter((line) => line.delta !== 0));

const linesMissingReason = computed(() => adjustedLines.value.filter((line) => line.reason === ''));

const canSubmit = computed(
  () => props.can.adjust && pendingLines.value.length > 0 && linesMissingReason.value.length === 0 && !submitting.value
);

const stats = computed(() => [
  {
    key: 'products',
    label: t('stock.products.summary.products'),
    value: props.summary.products ?? 0,
    icon: 'ri-price-tag-3-line',
    tone: 'primary',
  },
  {
    key: 'units',
    label: t('stock.products.summary.units'),
    value: props.summary.units ?? 0,
    icon: 'ri-stack-line',
    tone: 'info',
  },
  {
    key: 'pending',
    label: t('stock.inventory.pending_lines', { count: pendingLines.value.length }),
    value: pendingLines.value.length,
    icon: 'ri-edit-2-line',
    tone: 'warning',
  },
  {
    key: 'stock_value',
    label: t('stock.products.summary.stock_value'),
    value: `${formatMoneyRounded(props.summary.stock_value ?? 0)} ${t('common.currency_mad')}`,
    icon: 'ri-wallet-3-line',
    tone: 'success',
  },
]);

/* ------------------------------------------------------------- counting */

function onCountedInput(row) {
  const draft = draftFor(row.id);

  // A line brought back to no gap no longer owes anything: leaving a stale
  // motive on it would journal a reason for a movement that never happened.
  if (Number(draft.counted) === row.stock_quantity) {
    draft.reason = '';
    draft.note = '';
  }

  delete lineErrors.value[row.id];
}

function resetLine(productId) {
  drafts[productId] = { counted: '', reason: '', note: '' };
  delete lineErrors.value[productId];
}

function resetAll() {
  Object.keys(drafts).forEach((key) => {
    delete drafts[key];
  });
  lineErrors.value = {};
}

/** Fill every untouched line with what the system already believes. */
function matchAll() {
  rows.value.forEach((row) => {
    if (!isCounted(row.id)) {
      draftFor(row.id).counted = String(row.stock_quantity);
    }
  });
}

/** Move the caret down (or up) the counted column, spreadsheet style. */
async function moveFocus(index, offset) {
  const target = index + offset;

  await nextTick();
  const input = countedInputs.value[target];

  if (input) {
    input.focus();
    input.select?.();
  }
}

/* --------------------------------------------------------------- reasons */

function openReason(row) {
  const delta = deltaFor(row);

  if (delta === null || delta === 0) {
    return;
  }

  reasonTarget.value = {
    product_id: row.id,
    name: row.name,
    recorded: row.stock_quantity,
    counted: Number(drafts[row.id].counted),
    delta,
    reason: drafts[row.id].reason,
    note: drafts[row.id].note,
  };
}

function applyReason({ reason, note }) {
  const draft = draftFor(reasonTarget.value.product_id);
  draft.reason = reason;
  draft.note = note;

  delete lineErrors.value[reasonTarget.value.product_id];
  reasonTarget.value = null;
}

function reasonMeta(productId) {
  const value = drafts[productId]?.reason;

  return props.reasons.find((option) => option.value === value) ?? null;
}

/* -------------------------------------------------------------- filtering */

const reload = () => {
  const params = {};

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null) {
      params[key] = value;
    }
  });

  router.get(route('stock.inventory'), params, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  Object.keys(filters).forEach((key) => {
    filters[key] = '';
  });
  reload();
};

const goToPage = (url) => {
  if (url) {
    router.visit(url, { preserveState: true, preserveScroll: true });
  }
};

/* ------------------------------------------------------------- submission */

/**
 * Where the counter is standing, if the browser is willing to say.
 *
 * Deliberately best-effort. A refused prompt, an insecure origin or a slow fix
 * must never cost somebody an inventory he has just spent an hour typing, so
 * every failure resolves to null and the sheet goes through without a position.
 * The server treats it the same way: corroboration, never a requirement.
 */
function currentPosition() {
  if (!navigator.geolocation) {
    return Promise.resolve(null);
  }

  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      ({ coords }) =>
        resolve({
          latitude: coords.latitude,
          longitude: coords.longitude,
          accuracy: coords.accuracy ?? null,
        }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
    );
  });
}

async function submit() {
  if (!canSubmit.value) {
    return;
  }

  const lines = pendingLines.value;

  const confirmation = await Swal.fire({
    icon: 'question',
    title: t('stock.inventory.confirm.title'),
    text: t('stock.inventory.confirm.text', { count: adjustedLines.value.length }),
    footer: t('stock.inventory.confirm.traced'),
    showCancelButton: true,
    confirmButtonText: t('stock.inventory.confirm.confirm'),
    cancelButtonText: t('common.cancel'),
    customClass: { confirmButton: 'btn btn-success', cancelButton: 'btn btn-light' },
    buttonsStyling: false,
  });

  if (!confirmation.isConfirmed) {
    return;
  }

  submittedOrder = lines.map((line) => line.product_id);
  submitting.value = true;
  lineErrors.value = {};

  const location = await currentPosition();

  router.post(
    route('stock.inventory.store'),
    {
      adjustments: lines.map((line) => ({
        product_id: line.product_id,
        counted_quantity: line.counted,
        reason: line.reason || null,
        note: line.note || null,
      })),
      location,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        resetAll();
      },
      onError: (serverErrors) => {
        applyServerErrors(serverErrors);
      },
      onFinish: () => {
        submitting.value = false;
      },
    }
  );
}

/**
 * Project `adjustments.3.reason` back onto the row it belongs to.
 *
 * The index is the position in the submitted batch, which is not the position on
 * screen once the sheet has been filtered — hence the snapshot taken at submit.
 */
function applyServerErrors(serverErrors) {
  const mapped = {};
  const orphans = [];

  for (const [key, message] of Object.entries(serverErrors ?? {})) {
    const text = Array.isArray(message) ? message[0] : message;
    const match = /^adjustments\.(\d+)\./.exec(key);
    const productId = match ? submittedOrder[Number(match[1])] : null;

    if (productId) {
      mapped[productId] = text;
    } else {
      orphans.push(text);
    }
  }

  lineErrors.value = mapped;

  if (orphans.length > 0) {
    Swal.fire({ icon: 'error', title: t('stock.page_title'), text: orphans.join('\n') });
  }
}

// A pending count is worth more than a scroll position: a filter change or a
// page turn keeps the drafts, and this warns before the tab itself goes away.
function guardUnload(event) {
  if (pendingLines.value.length > 0) {
    event.preventDefault();
    event.returnValue = '';
  }
}

watch(
  () => pendingLines.value.length,
  (count) => {
    if (count > 0) {
      window.addEventListener('beforeunload', guardUnload);
    } else {
      window.removeEventListener('beforeunload', guardUnload);
    }
  }
);

onMounted(() => {
  const success = usePage().props?.flash?.success;

  if (success) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: success,
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
    });
  }
});
</script>

<template>
  <Layout>
    <PageHeader :title="$t('stock.inventory.title')" :pageTitle="$t('stock.page_title')" />

    <BRow class="g-2 g-lg-3 mb-1">
      <BCol v-for="stat in stats" :key="stat.key" cols="6" lg="3">
        <BCard no-body class="h-100">
          <BCardBody class="p-3">
            <div class="d-flex align-items-center gap-2">
              <span class="avatar-xs flex-shrink-0">
                <span class="avatar-title rounded" :class="`bg-${stat.tone}-subtle text-${stat.tone}`">
                  <i :class="stat.icon"></i>
                </span>
              </span>
              <div class="min-w-0">
                <p class="text-muted text-truncate fs-12 mb-0">{{ stat.label }}</p>
                <h5 class="mb-0 fs-16">{{ stat.value }}</h5>
              </div>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <BCard no-body>
      <FilterPanel :active-count="activeFilterCount" @apply="reload" @reset="resetFilters">
        <template #title>
          <h5 class="card-title mb-0">{{ $t('stock.inventory.table_title') }}</h5>
          <p class="text-muted fs-13 mb-0">{{ $t('stock.inventory.subtitle') }}</p>
        </template>

        <template #actions>
          <Link :href="route('products.index')" class="btn btn-soft-secondary">
            <i class="ri-archive-2-line align-bottom"></i>
            <span class="d-none d-xl-inline ms-1">{{ $t('stock.products.title') }}</span>
          </Link>
          <button
            v-if="can.adjust"
            type="button"
            class="btn btn-soft-primary"
            :title="$t('stock.inventory.match_all_hint')"
            @click="matchAll"
          >
            <i class="ri-check-double-line align-bottom"></i>
            <span class="d-none d-sm-inline ms-1">{{ $t('stock.inventory.match_all') }}</span>
          </button>
        </template>

        <BCol md="5">
          <label class="form-label">{{ $t('common.search') }}</label>
          <input
            v-model="filters.search"
            type="search"
            class="form-control"
            :placeholder="$t('stock.products.filters.search')"
            @keyup.enter="reload"
          />
        </BCol>
        <BCol md="4">
          <label class="form-label">{{ $t('stock.products.columns.stock') }}</label>
          <select v-model="filters.stock_status" class="form-select">
            <option value="">{{ $t('stock.products.filters.stock_status') }}</option>
            <option value="in">{{ $t('stock.products.filters.in_stock') }}</option>
            <option value="out">{{ $t('stock.products.filters.out_of_stock') }}</option>
          </select>
        </BCol>
      </FilterPanel>

      <BCardBody>
        <p class="text-muted fs-13 d-none d-lg-block">
          <i class="ri-information-line align-bottom me-1"></i>
          {{ $t('stock.inventory.hint_desktop') }}
        </p>
        <p class="text-muted fs-13 d-lg-none">
          <i class="ri-information-line align-bottom me-1"></i>
          {{ $t('stock.inventory.hint_mobile') }}
        </p>

        <!-- Mobile: one card per reference, reconciled with a thumb -->
        <div class="d-lg-none vstack gap-2">
          <div
            v-for="row in rows"
            :key="row.id"
            class="card count-card mb-0"
            :class="{
              'count-card--up': deltaFor(row) > 0,
              'count-card--down': deltaFor(row) < 0,
              'count-card--match': deltaFor(row) === 0,
              'count-card--invalid': lineErrors[row.id],
            }"
          >
            <div class="card-body p-3">
              <div class="d-flex align-items-center gap-2 mb-2">
                <ProductThumb :name="row.name" :photo-url="row.photo_url" :initials="row.initials" :size="40" />
                <div class="min-w-0 flex-grow-1">
                  <p class="fw-semibold fs-14 mb-0 text-truncate">{{ row.name }}</p>
                  <span class="text-muted fs-12">{{ row.sku }}</span>
                </div>
                <button
                  v-if="isCounted(row.id)"
                  type="button"
                  class="btn btn-sm btn-ghost-secondary"
                  :title="$t('stock.inventory.reset_line')"
                  @click="resetLine(row.id)"
                >
                  <i class="ri-close-line"></i>
                </button>
              </div>

              <div class="d-flex align-items-end gap-2">
                <div class="text-center flex-shrink-0">
                  <p class="text-muted fs-11 mb-1">{{ $t('stock.inventory.columns.recorded') }}</p>
                  <span class="badge bg-light text-body fs-13">{{ row.stock_quantity }}</span>
                </div>

                <div class="flex-grow-1">
                  <label class="text-muted fs-11 mb-1 d-block" :for="`counted-m-${row.id}`">
                    {{ $t('stock.inventory.columns.counted') }}
                  </label>
                  <input
                    :id="`counted-m-${row.id}`"
                    v-model="draftFor(row.id).counted"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    class="form-control form-control-lg text-center"
                    :disabled="!can.adjust"
                    :placeholder="$t('stock.inventory.counted_placeholder')"
                    @input="onCountedInput(row)"
                  />
                </div>

                <div class="text-center flex-shrink-0" style="min-width: 64px">
                  <p class="text-muted fs-11 mb-1">{{ $t('stock.inventory.columns.delta') }}</p>
                  <span
                    class="fw-bold fs-15"
                    :class="deltaFor(row) === null ? 'text-muted' : deltaFor(row) < 0 ? 'text-danger' : deltaFor(row) > 0 ? 'text-success' : 'text-muted'"
                  >
                    {{ deltaFor(row) === null ? '—' : deltaFor(row) > 0 ? `+${deltaFor(row)}` : deltaFor(row) }}
                  </span>
                </div>
              </div>

              <button
                v-if="deltaFor(row) !== null && deltaFor(row) !== 0"
                type="button"
                class="btn btn-sm w-100 mt-2"
                :class="reasonMeta(row.id) ? `btn-soft-${reasonMeta(row.id).color}` : 'btn-soft-danger'"
                @click="openReason(row)"
              >
                <i :class="`${reasonMeta(row.id)?.icon ?? 'ri-question-line'} align-bottom me-1`"></i>
                {{ reasonMeta(row.id)?.label ?? $t('stock.inventory.set_reason') }}
              </button>

              <p v-if="lineErrors[row.id]" class="text-danger fs-12 mb-0 mt-2">{{ lineErrors[row.id] }}</p>
            </div>
          </div>

          <p v-if="rows.length === 0" class="text-center text-muted py-4 mb-0">
            {{ $t('stock.inventory.empty') }}
          </p>
        </div>

        <!-- Desktop: dense sheet, filled top to bottom without the mouse -->
        <div class="table-responsive d-none d-lg-block count-sheet">
          <table class="table align-middle table-nowrap mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ $t('stock.inventory.columns.product') }}</th>
                <th>{{ $t('stock.products.columns.sku') }}</th>
                <th class="text-end">{{ $t('stock.inventory.columns.recorded') }}</th>
                <th class="text-center" style="width: 130px">{{ $t('stock.inventory.columns.counted') }}</th>
                <th class="text-end" style="width: 90px">{{ $t('stock.inventory.columns.delta') }}</th>
                <th style="width: 240px">{{ $t('stock.inventory.columns.reason') }}</th>
                <th style="width: 48px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="rows.length === 0">
                <td colspan="7" class="text-center text-muted py-5">{{ $t('stock.inventory.empty') }}</td>
              </tr>

              <tr
                v-for="(row, index) in rows"
                :key="row.id"
                :class="{
                  'count-row--up': deltaFor(row) > 0,
                  'count-row--down': deltaFor(row) < 0,
                  'count-row--match': deltaFor(row) === 0,
                  'count-row--invalid': lineErrors[row.id],
                }"
              >
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <ProductThumb :name="row.name" :photo-url="row.photo_url" :initials="row.initials" :size="32" />
                    <span class="text-truncate" style="max-width: 320px">{{ row.name }}</span>
                  </div>
                </td>
                <td><code class="text-body">{{ row.sku }}</code></td>
                <td class="text-end text-muted">{{ row.stock_quantity }}</td>
                <td>
                  <input
                    :ref="(element) => (countedInputs[index] = element)"
                    v-model="draftFor(row.id).counted"
                    type="number"
                    min="0"
                    step="1"
                    class="form-control form-control-sm text-center count-input"
                    :disabled="!can.adjust"
                    :placeholder="$t('stock.inventory.counted_placeholder')"
                    @input="onCountedInput(row)"
                    @keydown.enter.prevent="moveFocus(index, 1)"
                    @keydown.down.prevent="moveFocus(index, 1)"
                    @keydown.up.prevent="moveFocus(index, -1)"
                  />
                </td>
                <td
                  class="text-end fw-semibold"
                  :class="deltaFor(row) === null ? 'text-muted' : deltaFor(row) < 0 ? 'text-danger' : deltaFor(row) > 0 ? 'text-success' : 'text-muted'"
                >
                  {{ deltaFor(row) === null ? '—' : deltaFor(row) > 0 ? `+${deltaFor(row)}` : deltaFor(row) }}
                </td>
                <td>
                  <button
                    v-if="deltaFor(row) !== null && deltaFor(row) !== 0"
                    type="button"
                    class="btn btn-sm w-100 text-truncate"
                    :class="reasonMeta(row.id) ? `btn-soft-${reasonMeta(row.id).color}` : 'btn-soft-danger'"
                    :title="drafts[row.id]?.note || ''"
                    @click="openReason(row)"
                  >
                    <i :class="`${reasonMeta(row.id)?.icon ?? 'ri-error-warning-line'} align-bottom me-1`"></i>
                    {{ reasonMeta(row.id)?.label ?? $t('stock.inventory.reason_missing') }}
                  </button>
                  <span v-else-if="deltaFor(row) === 0" class="text-muted fs-13">
                    {{ $t('stock.inventory.no_change') }}
                  </span>
                  <span v-if="lineErrors[row.id]" class="d-block text-danger fs-12">{{ lineErrors[row.id] }}</span>
                </td>
                <td>
                  <button
                    v-if="isCounted(row.id)"
                    type="button"
                    class="btn btn-sm btn-ghost-secondary"
                    :title="$t('stock.inventory.reset_line')"
                    @click="resetLine(row.id)"
                  >
                    <i class="ri-close-line"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="meta.total" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
          <div class="text-muted">{{ meta.from }}–{{ meta.to }} / {{ meta.total }}</div>
          <ul v-if="meta.links" class="pagination pagination-sm mb-0">
            <li
              v-for="(link, index) in meta.links"
              :key="index"
              class="page-item"
              :class="{ active: link.active, disabled: !link.url }"
            >
              <button class="page-link" :disabled="!link.url" @click="goToPage(link.url)" v-html="link.label"></button>
            </li>
          </ul>
        </div>
      </BCardBody>
    </BCard>

    <!-- Sticky action bar: the sheet is long, and the save button has to stay
         where the counter's hand already is. -->
    <Transition name="count-bar">
      <div v-if="pendingLines.length > 0" class="count-bar">
        <div class="count-bar__inner">
          <div class="min-w-0">
            <p class="mb-0 fw-semibold">{{ $t('stock.inventory.pending_lines', { count: pendingLines.length }) }}</p>
            <p v-if="linesMissingReason.length > 0" class="mb-0 fs-12 text-danger">
              {{ $t('stock.inventory.errors.reason_required') }}
            </p>
            <p v-else class="mb-0 fs-12 text-muted">
              {{ $t('stock.inventory.confirm.text', { count: adjustedLines.length }) }}
            </p>
          </div>

          <div class="hstack gap-2 flex-shrink-0">
            <button type="button" class="btn btn-light" @click="resetAll">
              <i class="ri-refresh-line align-bottom"></i>
              <span class="d-none d-sm-inline ms-1">{{ $t('stock.inventory.reset_all') }}</span>
            </button>
            <button type="button" class="btn btn-success" :disabled="!canSubmit" @click="submit">
              <span v-if="submitting" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="ri-save-3-line align-bottom me-1"></i>
              <span class="d-none d-sm-inline">{{ $t('stock.inventory.apply') }}</span>
              <span class="d-sm-none">{{ $t('stock.inventory.apply_short') }}</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <ReasonSheet
      :show="reasonTarget !== null"
      :line="reasonTarget"
      :reasons="reasons"
      @close="reasonTarget = null"
      @confirm="applyReason"
    />
  </Layout>
</template>

<style scoped>
.count-sheet {
  max-height: 68vh;
}

.count-sheet thead th {
  position: sticky;
  top: 0;
  z-index: 2;
}

.count-input {
  min-width: 90px;
}

/* Row tints are painted as a background image so they survive the striped and
   hover rules of the base table without an !important war. */
.count-row--up > td {
  background-image: linear-gradient(rgba(var(--vz-success-rgb), 0.08), rgba(var(--vz-success-rgb), 0.08));
}

.count-row--down > td {
  background-image: linear-gradient(rgba(var(--vz-danger-rgb), 0.08), rgba(var(--vz-danger-rgb), 0.08));
}

.count-row--match > td {
  background-image: linear-gradient(rgba(0, 0, 0, 0.025), rgba(0, 0, 0, 0.025));
}

.count-row--invalid > td {
  background-image: linear-gradient(rgba(var(--vz-danger-rgb), 0.16), rgba(var(--vz-danger-rgb), 0.16));
}

.count-card {
  border-left: 3px solid transparent;
}

.count-card--up {
  border-left-color: var(--vz-success);
}

.count-card--down {
  border-left-color: var(--vz-danger);
}

.count-card--match {
  border-left-color: var(--vz-border-color);
}

.count-card--invalid {
  border-left-color: var(--vz-danger);
  background: rgba(var(--vz-danger-rgb), 0.04);
}

.count-bar {
  position: sticky;
  bottom: 0;
  z-index: 5;
  padding-bottom: env(safe-area-inset-bottom, 0);
}

.count-bar__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 1rem;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.75rem;
  background: var(--vz-card-bg, #fff);
  box-shadow: 0 -0.25rem 1rem rgba(0, 0, 0, 0.12);
}

.count-bar-enter-active,
.count-bar-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.count-bar-enter-from,
.count-bar-leave-to {
  opacity: 0;
  transform: translateY(0.75rem);
}

.min-w-0 {
  min-width: 0;
}

@media (prefers-reduced-motion: reduce) {
  .count-bar-enter-active,
  .count-bar-leave-active {
    transition: none;
  }
}
</style>
