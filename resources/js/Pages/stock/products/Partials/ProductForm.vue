<script setup>
import { computed, ref, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import ProductThumb from '../../Partials/ProductThumb.vue';
import { formatMoney as money } from '@/common/formatMoney';

/**
 * Create/edit form for one catalog reference.
 *
 * Deliberately does not expose `stock_quantity`. Availability is the output of
 * the ledger — a reception credits it, an inventory corrects it — and letting a
 * product form write it directly would produce a quantity with no movement
 * behind it, which is exactly the state the audit trail exists to prevent.
 */

const props = defineProps({
  form: { type: Object, required: true },
  /** Categories already in use, offered as datalist suggestions. */
  categories: { type: Array, default: () => [] },
  /** Current photo when editing; replaced only if a new file is chosen. */
  currentPhotoUrl: { type: String, default: null },
  /** Present when editing, so the read-only availability can be shown. */
  stockQuantity: { type: Number, default: null },
});

const fileInput = ref(null);
const preview = ref(null);

const photoUrl = computed(() => preview.value ?? props.currentPhotoUrl);

const margin = computed(() => {
  const price = Number(props.form.unit_price);
  const cost = Number(props.form.cost_price);

  if (!Number.isFinite(price) || !Number.isFinite(cost) || props.form.cost_price === '' || props.form.cost_price === null) {
    return null;
  }

  return price - cost;
});

const marginRate = computed(() => {
  const price = Number(props.form.unit_price);

  if (margin.value === null || !Number.isFinite(price) || price <= 0) {
    return null;
  }

  return (margin.value / price) * 100;
});

const marginTone = computed(() => {
  if (margin.value === null) return 'muted';

  return margin.value < 0 ? 'danger' : 'success';
});

function selectPhoto(event) {
  const [file] = event.target.files ?? [];

  if (!file) {
    return;
  }

  props.form.photo = file;

  // Revoked on the next pick rather than on unmount: the page navigates away
  // right after a successful save, and the browser drops the blob with it.
  if (preview.value) {
    URL.revokeObjectURL(preview.value);
  }

  preview.value = URL.createObjectURL(file);
}

function clearPhoto() {
  props.form.photo = null;

  if (preview.value) {
    URL.revokeObjectURL(preview.value);
    preview.value = null;
  }

  if (fileInput.value) {
    fileInput.value.value = '';
  }
}

// A server-side rejection of the file leaves the preview showing something that
// was never stored, which reads as a silent success.
watch(
  () => props.form.errors?.photo,
  (message) => {
    if (message) {
      clearPhoto();
    }
  }
);
</script>

<template>
  <BRow>
    <BCol xl="8">
      <BCard data-guide="product-identity" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stock.products.form.identity') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="12">
              <label class="form-label" for="product-name">
                {{ $t('stock.products.form.name') }} <span class="text-danger">*</span>
              </label>
              <input
                id="product-name"
                v-model="form.name"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': form.errors.name }"
                :placeholder="$t('stock.products.form.name_placeholder')"
              />
              <InputError :message="form.errors.name" />
            </BCol>

            <BCol md="6">
              <label class="form-label" for="product-sku">{{ $t('stock.products.form.sku') }}</label>
              <input
                id="product-sku"
                v-model="form.sku"
                type="text"
                class="form-control text-uppercase"
                :class="{ 'is-invalid': form.errors.sku }"
                autocomplete="off"
              />
              <div class="form-text">{{ $t('stock.products.form.sku_hint') }}</div>
              <InputError :message="form.errors.sku" />
            </BCol>

            <BCol md="6">
              <label class="form-label" for="product-barcode">{{ $t('stock.products.form.barcode') }}</label>
              <div class="input-group">
                <span class="input-group-text"><i class="ri-barcode-line"></i></span>
                <input
                  id="product-barcode"
                  v-model="form.barcode"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.barcode }"
                  inputmode="numeric"
                  autocomplete="off"
                />
              </div>
              <div class="form-text">{{ $t('stock.products.form.barcode_hint') }}</div>
              <InputError :message="form.errors.barcode" />
            </BCol>

            <BCol md="6">
              <label class="form-label" for="product-category">{{ $t('stock.products.form.category') }}</label>
              <input
                id="product-category"
                v-model="form.category"
                type="text"
                class="form-control"
                :class="{ 'is-invalid': form.errors.category }"
                list="product-category-options"
                :placeholder="$t('stock.products.form.category_placeholder')"
              />
              <datalist id="product-category-options">
                <option v-for="category in categories" :key="category" :value="category"></option>
              </datalist>
              <InputError :message="form.errors.category" />
            </BCol>

            <BCol v-if="stockQuantity !== null" md="6">
              <label class="form-label">{{ $t('stock.products.columns.stock') }}</label>
              <div class="form-control bg-light fw-semibold">{{ stockQuantity }}</div>
              <div class="form-text">{{ $t('stock.products.form.stock_readonly') }}</div>
            </BCol>

            <BCol md="12">
              <label class="form-label" for="product-description">{{ $t('stock.products.form.description') }}</label>
              <textarea
                id="product-description"
                v-model="form.description"
                class="form-control"
                rows="3"
                :class="{ 'is-invalid': form.errors.description }"
              ></textarea>
              <InputError :message="form.errors.description" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard data-guide="product-logistics" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stock.products.form.logistics') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label d-block">{{ $t('stock.products.form.fragility') }}</label>
              <BRow class="g-2">
                <BCol cols="6">
                  <div class="form-check card-radio h-100">
                    <input
                      id="product-unbreakable"
                      class="form-check-input"
                      type="radio"
                      :value="false"
                      v-model="form.is_fragile"
                    />
                    <label class="form-check-label w-100 text-center py-2" for="product-unbreakable">
                      <span class="fs-20 d-block mb-1"><i class="ri-shield-check-line text-success"></i></span>
                      <span class="fs-13 fw-medium d-block">{{ $t('stock.products.form.unbreakable') }}</span>
                      <small class="text-muted d-block">{{ $t('stock.products.form.unbreakable_hint') }}</small>
                    </label>
                  </div>
                </BCol>
                <BCol cols="6">
                  <div class="form-check card-radio h-100">
                    <input
                      id="product-fragile"
                      class="form-check-input"
                      type="radio"
                      :value="true"
                      v-model="form.is_fragile"
                    />
                    <label class="form-check-label w-100 text-center py-2" for="product-fragile">
                      <span class="fs-20 d-block mb-1"><i class="ri-alarm-warning-line text-warning"></i></span>
                      <span class="fs-13 fw-medium d-block">{{ $t('stock.products.form.fragile') }}</span>
                      <small class="text-muted d-block">{{ $t('stock.products.form.fragile_hint') }}</small>
                    </label>
                  </div>
                </BCol>
              </BRow>
              <InputError :message="form.errors.is_fragile" />
            </BCol>

            <BCol md="6">
              <label class="form-label" for="product-weight">{{ $t('stock.products.form.weight') }}</label>
              <div class="input-group">
                <input
                  id="product-weight"
                  v-model="form.weight_grams"
                  type="number"
                  min="0"
                  step="1"
                  class="form-control"
                  :class="{ 'is-invalid': form.errors.weight_grams }"
                />
                <span class="input-group-text">g</span>
              </div>
              <InputError :message="form.errors.weight_grams" />
            </BCol>

            <BCol md="4">
              <label class="form-label" for="product-length">{{ $t('stock.products.form.length') }}</label>
              <input
                id="product-length"
                v-model="form.length_cm"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                :class="{ 'is-invalid': form.errors.length_cm }"
              />
              <InputError :message="form.errors.length_cm" />
            </BCol>
            <BCol md="4">
              <label class="form-label" for="product-width">{{ $t('stock.products.form.width') }}</label>
              <input
                id="product-width"
                v-model="form.width_cm"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                :class="{ 'is-invalid': form.errors.width_cm }"
              />
              <InputError :message="form.errors.width_cm" />
            </BCol>
            <BCol md="4">
              <label class="form-label" for="product-height">{{ $t('stock.products.form.height') }}</label>
              <input
                id="product-height"
                v-model="form.height_cm"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                :class="{ 'is-invalid': form.errors.height_cm }"
              />
              <InputError :message="form.errors.height_cm" />
            </BCol>

            <BCol cols="12">
              <p class="text-muted fs-13 mb-0">
                <i class="ri-information-line align-bottom me-1"></i>
                {{ $t('stock.products.form.dimensions_hint') }}
              </p>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>

    <BCol xl="4">
      <BCard data-guide="product-pricing" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stock.products.form.pricing') }}</h5>
        </BCardHeader>
        <BCardBody>
          <div class="mb-3">
            <label class="form-label" for="product-unit-price">
              {{ $t('stock.products.form.unit_price') }} <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <input
                id="product-unit-price"
                v-model="form.unit_price"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                :class="{ 'is-invalid': form.errors.unit_price }"
              />
              <span class="input-group-text">{{ $t('common.currency_mad') }}</span>
            </div>
            <InputError :message="form.errors.unit_price" />
          </div>

          <div class="mb-3">
            <label class="form-label" for="product-cost-price">{{ $t('stock.products.form.cost_price') }}</label>
            <div class="input-group">
              <input
                id="product-cost-price"
                v-model="form.cost_price"
                type="number"
                min="0"
                step="0.01"
                class="form-control"
                :class="{ 'is-invalid': form.errors.cost_price }"
              />
              <span class="input-group-text">{{ $t('common.currency_mad') }}</span>
            </div>
            <div class="form-text">{{ $t('stock.products.form.cost_price_hint') }}</div>
            <InputError :message="form.errors.cost_price" />
          </div>

          <div v-if="margin !== null" class="border-top pt-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted">{{ $t('stock.products.form.margin_preview') }}</span>
              <span class="fs-16 fw-semibold" :class="`text-${marginTone}`">{{ money(margin) }}</span>
            </div>
            <div v-if="marginRate !== null" class="d-flex justify-content-between align-items-center">
              <span class="text-muted">{{ $t('stock.products.form.margin_rate') }}</span>
              <span class="fw-medium" :class="`text-${marginTone}`">{{ marginRate.toFixed(1) }} %</span>
            </div>
          </div>
        </BCardBody>
      </BCard>

      <BCard data-guide="product-media" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stock.products.form.media') }}</h5>
        </BCardHeader>
        <BCardBody>
          <div class="d-flex align-items-center gap-3 mb-3">
            <ProductThumb :name="form.name" :photo-url="photoUrl" :size="72" />
            <div class="flex-grow-1">
              <button type="button" class="btn btn-sm btn-soft-primary" @click="fileInput?.click()">
                <i class="ri-image-add-line align-bottom me-1"></i>
                {{ currentPhotoUrl || preview ? $t('stock.products.form.photo_replace') : $t('stock.products.form.photo') }}
              </button>
              <button
                v-if="preview"
                type="button"
                class="btn btn-sm btn-ghost-danger ms-1"
                @click="clearPhoto"
              >
                <i class="ri-close-line align-bottom"></i>
              </button>
            </div>
          </div>

          <input
            ref="fileInput"
            type="file"
            class="d-none"
            accept="image/jpeg,image/png,image/webp"
            @change="selectPhoto"
          />

          <p class="text-muted fs-13 mb-0">{{ $t('stock.products.form.photo_hint') }}</p>
          <InputError :message="form.errors.photo" />

          <div class="form-check form-switch mt-3 pt-3 border-top">
            <input id="product-active" v-model="form.is_active" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="product-active">
              {{ $t('stock.products.form.is_active') }}
            </label>
            <div class="form-text">{{ $t('stock.products.form.is_active_hint') }}</div>
          </div>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
