<script setup>
import { computed, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import ProductThumb from '../../Partials/ProductThumb.vue';

/**
 * Shipment slip a vendor fills in before sending stock to the depot.
 *
 * Out-of-stock references are offered here without any warning, unlike in the
 * order pick-list: sending stock in is precisely what a vendor does about an
 * empty shelf.
 */

const props = defineProps({
  form: { type: Object, required: true },
  /** @type {Array<Object>} catalog options as shaped by Product::toPickOption() */
  products: { type: Array, default: () => [] },
  /** Cities holding one of our depots, the only legal destinations. */
  hubCities: { type: Array, default: () => [] },
  /** Depot the shop already warehouses in; locks the destination once set. */
  shopDepotCityId: { type: Number, default: null },
});

const RESULT_LIMIT = 8;

const term = ref('');
const open = ref(false);
const highlighted = ref(0);
const searchInput = ref(null);

const index = computed(() =>
  props.products.map((product) => ({
    product,
    haystack: [product.name, product.sku, product.barcode].filter(Boolean).join(' ').toLowerCase(),
  }))
);

const pickedIds = computed(() => new Set((props.form.items ?? []).map((item) => item.product_id)));

const results = computed(() => {
  const needle = term.value.trim().toLowerCase();
  const matches = needle === '' ? index.value : index.value.filter((entry) => entry.haystack.includes(needle));

  return matches
    .filter((entry) => !pickedIds.value.has(entry.product.id))
    .slice(0, RESULT_LIMIT)
    .map((entry) => entry.product);
});

const totalUnits = computed(() =>
  (props.form.items ?? []).reduce((total, line) => total + (Number(line.quantity_sent) || 0), 0)
);

/**
 * A shop warehouses in a single depot, so the choice only exists for its very
 * first shipment. Afterwards the field is shown but frozen, which explains the
 * destination instead of silently removing it.
 */
const depotLocked = computed(() => props.shopDepotCityId !== null);

const lockedDepotName = computed(
  () => props.hubCities.find((city) => city.id === props.shopDepotCityId)?.name ?? ''
);

function add(product) {
  props.form.items = [
    ...(props.form.items ?? []),
    {
      product_id: product.id,
      name: product.name,
      sku: product.sku,
      photo_url: product.photo_url,
      initials: product.initials,
      stock_quantity: product.stock_quantity,
      quantity_sent: 1,
      note: '',
    },
  ];

  term.value = '';
  open.value = false;
  highlighted.value = 0;
  searchInput.value?.focus();
}

function remove(productId) {
  props.form.items = (props.form.items ?? []).filter((item) => item.product_id !== productId);
}

/** Enter picks the highlighted result, which is what a barcode scanner sends. */
function onEnter() {
  const target = results.value[highlighted.value] ?? results.value[0];

  if (target) {
    add(target);
  }
}

function move(offset) {
  if (results.value.length === 0) {
    return;
  }

  highlighted.value = (highlighted.value + offset + results.value.length) % results.value.length;
}

/** Per-line server errors arrive as `items.3.quantity_sent`. */
function lineError(position, field) {
  return props.form.errors?.[`items.${position}.${field}`] ?? '';
}
</script>

<template>
  <BRow>
    <BCol xl="8">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stock.receptions.sections.items') }}</h5>
        </BCardHeader>
        <BCardBody>
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
                :class="{ 'picklist-option--active': position === highlighted }"
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
                <span class="badge bg-light text-body border flex-shrink-0">
                  {{ $t('stock.picklist.available', { count: product.stock_quantity }) }}
                </span>
              </button>
            </div>
          </div>

          <InputError :message="form.errors.items" />

          <div v-if="(form.items ?? []).length === 0" class="text-center text-muted py-4">
            {{ $t('stock.receptions.form.no_items') }}
          </div>

          <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>{{ $t('stock.receptions.columns.product') }}</th>
                  <th class="text-center" style="width: 150px">{{ $t('stock.receptions.form.quantity_sent') }}</th>
                  <th>{{ $t('stock.receptions.form.item_note') }}</th>
                  <th style="width: 48px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, position) in form.items" :key="line.product_id">
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
                        <span class="d-block text-muted fs-12">{{ line.sku }}</span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <input
                      v-model="line.quantity_sent"
                      type="number"
                      min="1"
                      step="1"
                      inputmode="numeric"
                      class="form-control form-control-sm text-center"
                      :class="{ 'is-invalid': lineError(position, 'quantity_sent') }"
                    />
                    <InputError :message="lineError(position, 'quantity_sent')" />
                  </td>
                  <td>
                    <input
                      v-model="line.note"
                      type="text"
                      class="form-control form-control-sm"
                      :placeholder="$t('stock.receptions.form.item_note')"
                    />
                  </td>
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
        </BCardBody>
      </BCard>
    </BCol>

    <BCol xl="4">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stock.receptions.sections.shipping') }}</h5>
        </BCardHeader>
        <BCardBody>
          <div class="mb-3">
            <label class="form-label" for="reception-destination">
              {{ $t('stock.receptions.form.destination_city') }} <span class="text-danger">*</span>
            </label>
            <select
              id="reception-destination"
              v-model="form.destination_city_id"
              class="form-select"
              :class="{ 'is-invalid': form.errors.destination_city_id }"
              :disabled="depotLocked"
            >
              <option :value="null">{{ $t('stock.receptions.form.destination_city_placeholder') }}</option>
              <option v-for="city in hubCities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
            <InputError :message="form.errors.destination_city_id" />
            <p v-if="depotLocked" class="text-muted fs-12 mb-0 mt-1">
              {{ $t('stock.receptions.form.destination_locked', { city: lockedDepotName }) }}
            </p>
            <p v-else-if="hubCities.length === 0" class="text-danger fs-12 mb-0 mt-1">
              {{ $t('stock.receptions.form.no_hub_cities') }}
            </p>
            <p v-else class="text-muted fs-12 mb-0 mt-1">
              {{ $t('stock.receptions.form.destination_hint') }}
            </p>
          </div>

          <div class="mb-3">
            <label class="form-label" for="reception-sent-at">{{ $t('stock.receptions.form.sent_at') }}</label>
            <input
              id="reception-sent-at"
              v-model="form.sent_at"
              type="date"
              class="form-control"
              :class="{ 'is-invalid': form.errors.sent_at }"
            />
            <InputError :message="form.errors.sent_at" />
          </div>

          <div class="mb-3">
            <label class="form-label" for="reception-notes">{{ $t('stock.receptions.form.sending_notes') }}</label>
            <textarea
              id="reception-notes"
              v-model="form.sending_notes"
              class="form-control"
              rows="4"
              :class="{ 'is-invalid': form.errors.sending_notes }"
              :placeholder="$t('stock.receptions.form.sending_notes_placeholder')"
            ></textarea>
            <InputError :message="form.errors.sending_notes" />
          </div>

          <div class="d-flex justify-content-between align-items-center border-top pt-3">
            <span class="text-muted">{{ $t('stock.receptions.form.total_units') }}</span>
            <span class="fs-18 fw-bold text-primary">{{ totalUnits }}</span>
          </div>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
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

.picklist-option--active {
  background: rgba(var(--vz-primary-rgb), 0.08);
}

.min-w-0 {
  min-width: 0;
}
</style>
