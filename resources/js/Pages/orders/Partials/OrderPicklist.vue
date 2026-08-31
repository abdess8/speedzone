<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/Components/InputError.vue';
import ProductThumb from '../../stock/Partials/ProductThumb.vue';
import { formatMoney as money } from '@/common/formatMoney';

/**
 * Smart pick-list: build an order out of the vendor's own stock.
 *
 * Three things this screen has to get right, in order of how often they bite:
 *
 *  1. Search has to answer a keystroke. The catalog travels with the page, so
 *     matching is done here over an in-memory index rather than per-keypress
 *     against the server.
 *  2. An empty shelf has to be visible, not hidden. A reference with no stock is
 *     listed, disabled, and says so — hiding it makes the seller wonder whether
 *     he mistyped the name, which is a worse question than "it is out of stock".
 *  3. The total is arithmetic, not typing. Quantity × unit price, less the
 *     global discount; the amount field stops accepting a hand-typed figure for
 *     as long as the basket drives it.
 *
 * The figure computed here is a preview. StoreOrderRequest recomputes it from
 * the product rows, because a tab left open since last week would otherwise
 * invoice last week's price.
 */

const props = defineProps({
  /** Inertia form; `items` and `discount_amount` are owned by this component. */
  form: { type: Object, required: true },
  /** @type {Array<Object>} catalog options as shaped by Product::toPickOption() */
  products: { type: Array, default: () => [] },
  /** Cash orders collect the amount; card orders only declare a value. */
  isCashPayment: { type: Boolean, default: true },
});

const { t } = useI18n();

/** How many results the dropdown shows before asking for a narrower term. */
const RESULT_LIMIT = 8;

const enabled = ref(Array.isArray(props.form.items) && props.form.items.length > 0);
const term = ref('');
const open = ref(false);
const highlighted = ref(0);
const searchInput = ref(null);
const notice = ref('');

/** Search index built once: lower-cased name, SKU and barcode per reference. */
const index = computed(() =>
  props.products.map((product) => ({
    product,
    haystack: [product.name, product.sku, product.barcode]
      .filter(Boolean)
      .join(' ')
      .toLowerCase(),
  }))
);

const pickedIds = computed(() => new Set((props.form.items ?? []).map((item) => item.product_id)));

const results = computed(() => {
  const needle = term.value.trim().toLowerCase();

  const matches = needle === ''
    ? index.value
    : index.value.filter((entry) => entry.haystack.includes(needle));

  return matches.slice(0, RESULT_LIMIT).map((entry) => entry.product);
});

/**
 * Availability left for a reference, discounting what is already in the basket.
 *
 * Without this the seller can add ten of a product that has eight in stock by
 * pressing the stepper, and only learn about it when the server refuses.
 */
function remainingFor(product) {
  const line = (props.form.items ?? []).find((item) => item.product_id === product.id);

  return product.stock_quantity - (line?.quantity ?? 0);
}

function isPickable(product) {
  return !product.is_blocked && product.stock_quantity > 0;
}

/* ------------------------------------------------------------- selection */

function flash(message) {
  notice.value = message;
  window.setTimeout(() => {
    if (notice.value === message) {
      notice.value = '';
    }
  }, 3000);
}

function add(product) {
  if (!isPickable(product)) {
    return;
  }

  const existing = (props.form.items ?? []).find((item) => item.product_id === product.id);

  if (existing) {
    increment(existing, 1);
    flash(t('stock.picklist.already_added'));
  } else {
    props.form.items = [
      ...(props.form.items ?? []),
      {
        product_id: product.id,
        name: product.name,
        sku: product.sku,
        photo_url: product.photo_url,
        initials: product.initials,
        unit_price: product.unit_price,
        stock_quantity: product.stock_quantity,
        is_fragile: product.is_fragile,
        quantity: 1,
      },
    ];
  }

  term.value = '';
  open.value = false;
  highlighted.value = 0;
  searchInput.value?.focus();
}

function increment(line, offset) {
  const next = line.quantity + offset;

  if (next < 1) {
    return;
  }

  if (next > line.stock_quantity) {
    flash(t('stock.picklist.max_quantity', { count: line.stock_quantity }));

    return;
  }

  line.quantity = next;
}

/** Clamp a typed quantity: the input is faster than the stepper, and unbounded. */
function normalizeQuantity(line) {
  const parsed = Math.trunc(Number(line.quantity));

  if (!Number.isFinite(parsed) || parsed < 1) {
    line.quantity = 1;

    return;
  }

  if (parsed > line.stock_quantity) {
    line.quantity = line.stock_quantity;
    flash(t('stock.picklist.max_quantity', { count: line.stock_quantity }));

    return;
  }

  line.quantity = parsed;
}

function remove(productId) {
  props.form.items = (props.form.items ?? []).filter((item) => item.product_id !== productId);
}

/* -------------------------------------------------------------- keyboard */

/**
 * Enter adds the highlighted result — which, with a single match, is what a
 * barcode scanner produces: the code, then a carriage return.
 */
function onEnter() {
  const target = results.value[highlighted.value] ?? results.value[0];

  if (target && isPickable(target)) {
    add(target);
  }
}

function move(offset) {
  if (results.value.length === 0) {
    return;
  }

  highlighted.value = (highlighted.value + offset + results.value.length) % results.value.length;
}

async function focusSearch() {
  await nextTick();
  searchInput.value?.focus();
}

/* ----------------------------------------------------------------- totals */

const itemsTotal = computed(() =>
  (props.form.items ?? []).reduce((total, line) => total + line.unit_price * line.quantity, 0)
);

const discount = computed(() => {
  const parsed = Number(props.form.discount_amount);

  return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
});

const netTotal = computed(() => Math.max(0, itemsTotal.value - discount.value));

const totalUnits = computed(() =>
  (props.form.items ?? []).reduce((total, line) => total + line.quantity, 0)
);

/**
 * Mirror the computed total into the amount the order actually stores.
 *
 * Which field receives it follows the payment method, exactly as the manual form
 * does: a cash order declares what the driver collects, a card order only
 * declares a value.
 */
watch(
  [netTotal, () => props.isCashPayment, enabled],
  () => {
    if (!enabled.value || (props.form.items ?? []).length === 0) {
      return;
    }

    if (props.isCashPayment) {
      props.form.order_amount = netTotal.value.toFixed(2);
      props.form.order_value = '';
    } else {
      props.form.order_value = netTotal.value.toFixed(2);
      props.form.order_amount = '';
    }
  },
  { immediate: true }
);

/** Leaving stock mode hands the amount back to the seller, empty. */
watch(enabled, (active) => {
  if (active) {
    focusSearch();

    return;
  }

  props.form.items = [];
  props.form.discount_amount = '';
  props.form.order_amount = '';
  props.form.order_value = '';
});

// A rejected basket comes back with `items` errors; reopening the section is the
// only way the seller can see which line to fix.
watch(
  () => props.form.errors?.items,
  (message) => {
    if (message) {
      enabled.value = true;
    }
  }
);
</script>

<template>
  <BCard no-body>
    <BCardHeader class="d-flex flex-wrap align-items-center gap-2">
      <div class="flex-grow-1">
        <h5 class="card-title mb-0">{{ $t('stock.picklist.title') }}</h5>
        <p class="text-muted fs-13 mb-0">
          {{ enabled ? $t('stock.picklist.hint') : $t('stock.picklist.toggle_off_hint') }}
        </p>
      </div>

      <div class="form-check form-switch mb-0">
        <input id="use-stock" v-model="enabled" class="form-check-input" type="checkbox" />
        <label class="form-check-label" for="use-stock">{{ $t('stock.picklist.toggle_hint') }}</label>
      </div>
    </BCardHeader>

    <BCardBody v-if="enabled">
      <div v-if="products.length === 0" class="text-center text-muted py-4">
        <div class="fs-32 mb-2 text-body-tertiary"><i class="ri-archive-2-line"></i></div>
        <p class="mb-0">{{ $t('stock.picklist.empty_catalog') }}</p>
      </div>

      <template v-else>
        <!-- Search: an in-memory autocomplete that also swallows a barcode
             scanner's trailing Enter. -->
        <div class="position-relative mb-3">
          <div class="search-box">
            <input
              ref="searchInput"
              v-model="term"
              type="search"
              class="form-control search"
              :placeholder="$t('stock.picklist.search_placeholder')"
              autocomplete="off"
              @focus="open = true"
              @input="open = true; highlighted = 0"
              @keydown.down.prevent="move(1)"
              @keydown.up.prevent="move(-1)"
              @keydown.enter.prevent="onEnter"
              @keydown.esc="open = false"
            />
            <i class="ri-search-line search-icon"></i>
          </div>

          <!-- Closing on blur has to survive the click that follows it, so the
               dismissal is deferred by one tick's worth of mousedown. -->
          <div v-if="open" class="picklist-backdrop" @mousedown="open = false"></div>

          <div v-if="open" class="picklist-results shadow">
            <p v-if="results.length === 0" class="text-muted text-center fs-13 mb-0 py-3">
              {{ $t('stock.picklist.no_results', { term }) }}
            </p>

            <button
              v-for="(product, position) in results"
              :key="product.id"
              type="button"
              class="picklist-option"
              :class="{
                'picklist-option--active': position === highlighted,
                'picklist-option--disabled': !isPickable(product),
              }"
              :disabled="!isPickable(product)"
              @mouseenter="highlighted = position"
              @click="add(product)"
            >
              <ProductThumb
                :name="product.name"
                :photo-url="product.photo_url"
                :initials="product.initials"
                :size="36"
              />

              <span class="min-w-0 flex-grow-1 text-start">
                <span class="d-block fw-medium text-truncate">{{ product.name }}</span>
                <span class="d-block text-muted fs-12 text-truncate">
                  {{ product.sku }}<template v-if="product.barcode"> · {{ product.barcode }}</template>
                </span>
              </span>

              <span class="text-end flex-shrink-0">
                <span class="d-block fw-semibold">{{ money(product.unit_price) }}</span>
                <span
                  v-if="product.is_blocked"
                  class="badge bg-danger-subtle text-danger"
                >
                  {{ $t('stock.picklist.blocked') }}
                </span>
                <span
                  v-else-if="product.stock_quantity <= 0"
                  class="badge bg-danger-subtle text-danger"
                >
                  {{ $t('stock.picklist.out_of_stock') }}
                </span>
                <span v-else class="badge bg-success-subtle text-success">
                  {{ $t('stock.picklist.available', { count: remainingFor(product) }) }}
                </span>
              </span>
            </button>
          </div>
        </div>

        <div v-if="notice" class="alert alert-warning py-2 fs-13">
          <i class="ri-information-line align-bottom me-1"></i>{{ notice }}
        </div>

        <InputError :message="form.errors.items" />

        <div v-if="(form.items ?? []).length === 0" class="text-center text-muted py-4">
          <p class="mb-1">{{ $t('stock.picklist.empty_selection') }}</p>
          <p class="fs-13 mb-0">{{ $t('stock.picklist.empty_selection_hint') }}</p>
        </div>

        <div v-else class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ $t('stock.products.columns.product') }}</th>
                <th class="text-end">{{ $t('stock.picklist.unit_price') }}</th>
                <th class="text-center" style="width: 160px">{{ $t('stock.picklist.quantity') }}</th>
                <th class="text-end">{{ $t('stock.picklist.line_total') }}</th>
                <th style="width: 48px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in form.items" :key="line.product_id">
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <ProductThumb
                      :name="line.name"
                      :photo-url="line.photo_url"
                      :initials="line.initials"
                      :size="36"
                    />
                    <div class="min-w-0">
                      <span class="d-block fw-medium text-truncate">{{ line.name }}</span>
                      <span class="d-block text-muted fs-12">
                        {{ line.sku }} ·
                        {{ $t('stock.picklist.stock_after', { count: line.stock_quantity - line.quantity }) }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="text-end">{{ money(line.unit_price) }}</td>
                <td>
                  <div class="input-group input-group-sm flex-nowrap">
                    <button type="button" class="btn btn-light" @click="increment(line, -1)">
                      <i class="ri-subtract-line"></i>
                    </button>
                    <input
                      v-model="line.quantity"
                      type="number"
                      min="1"
                      :max="line.stock_quantity"
                      class="form-control text-center"
                      @change="normalizeQuantity(line)"
                    />
                    <button
                      type="button"
                      class="btn btn-light"
                      :disabled="line.quantity >= line.stock_quantity"
                      @click="increment(line, 1)"
                    >
                      <i class="ri-add-line"></i>
                    </button>
                  </div>
                </td>
                <td class="text-end fw-semibold">{{ money(line.unit_price * line.quantity) }}</td>
                <td>
                  <button
                    type="button"
                    class="btn btn-sm btn-ghost-danger"
                    :title="$t('stock.picklist.remove')"
                    @click="remove(line.product_id)"
                  >
                    <i class="ri-delete-bin-line"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="(form.items ?? []).length > 0" class="border-top mt-3 pt-3">
          <BRow class="g-3 align-items-end">
            <BCol md="5">
              <label class="form-label" for="order-discount">{{ $t('stock.picklist.discount') }}</label>
              <div class="input-group">
                <input
                  id="order-discount"
                  v-model="form.discount_amount"
                  type="number"
                  min="0"
                  step="0.01"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.discount_amount }"
                />
                <span class="input-group-text">{{ $t('common.currency_mad') }}</span>
              </div>
              <InputError :message="form.errors.discount_amount" />
            </BCol>

            <BCol md="7">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">
                  {{ $t('stock.picklist.items_total') }}
                  <span class="fs-12">({{ totalUnits }})</span>
                </span>
                <span class="fw-medium">{{ money(itemsTotal) }}</span>
              </div>
              <div v-if="discount > 0" class="d-flex justify-content-between align-items-center text-danger">
                <span>{{ $t('stock.picklist.discount') }}</span>
                <span>− {{ money(discount) }}</span>
              </div>
              <div class="d-flex justify-content-between align-items-center border-top mt-2 pt-2">
                <span class="fw-medium">
                  {{ isCashPayment ? $t('orders.form.order_amount') : $t('orders.form.order_value') }}
                </span>
                <span class="fs-18 fw-bold text-primary">{{ money(netTotal) }}</span>
              </div>
            </BCol>
          </BRow>
        </div>
      </template>
    </BCardBody>
  </BCard>
</template>

<style scoped>
.picklist-backdrop {
  position: fixed;
  inset: 0;
  z-index: 4;
}

.picklist-results {
  position: absolute;
  z-index: 5;
  top: calc(100% + 0.25rem);
  right: 0;
  left: 0;
  max-height: 22rem;
  overflow-y: auto;
  padding: 0.25rem;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.5rem;
  background: var(--vz-card-bg, #fff);
}

.picklist-option {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem;
  border: 0;
  border-radius: 0.375rem;
  background: transparent;
  color: inherit;
  text-align: left;
}

.picklist-option--active:not(.picklist-option--disabled) {
  background: rgba(var(--vz-primary-rgb), 0.08);
}

/* Out of stock stays listed and readable — just clearly unavailable. */
.picklist-option--disabled {
  cursor: not-allowed;
  background: rgba(var(--vz-danger-rgb), 0.05);
  opacity: 0.75;
}

.min-w-0 {
  min-width: 0;
}
</style>
