<script setup>
import { computed, ref } from 'vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
  form: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  /** Cities flagged as holding a stock depot; empty hides the fulfilment card. */
  hubCities: { type: Array, default: () => [] },
  currentLogoUrl: { type: String, default: null },
});

const previewUrl = ref(null);

/** Falls back to the stored logo until a new file is picked. */
const logoPreview = computed(() => previewUrl.value ?? props.currentLogoUrl);

const onLogoSelected = (event) => {
  const file = event.target.files?.[0] ?? null;
  props.form.logo = file;
  previewUrl.value = file ? URL.createObjectURL(file) : null;
};
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <BCard data-guide="store-identity" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stores.form.identity') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="8">
              <label class="form-label">
                {{ $t('stores.fields.name') }} <span class="text-danger">*</span>
              </label>
              <input
                type="text"
                class="form-control"
                v-model="form.name"
                :class="{ 'is-invalid': form.errors.name }"
                :placeholder="$t('stores.form.name_placeholder')"
              />
              <InputError :message="form.errors.name" />
            </BCol>

            <BCol md="4">
              <label class="form-label">{{ $t('stores.fields.category') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.category"
                :class="{ 'is-invalid': form.errors.category }"
                :placeholder="$t('stores.form.category_placeholder')"
              />
              <InputError :message="form.errors.category" />
            </BCol>

            <BCol md="8">
              <label class="form-label">{{ $t('stores.fields.website') }}</label>
              <input
                type="url"
                class="form-control"
                v-model="form.website"
                :class="{ 'is-invalid': form.errors.website }"
                placeholder="https://"
              />
              <InputError :message="form.errors.website" />
            </BCol>

            <BCol md="4">
              <label class="form-label d-block">{{ $t('common.status') }}</label>
              <div class="form-check form-switch fs-15 mt-2">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="storeActive"
                  v-model="form.is_active"
                />
                <label class="form-check-label" for="storeActive">
                  {{ form.is_active ? $t('common.active') : $t('common.inactive') }}
                </label>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard data-guide="store-branding" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stores.form.branding') }}</h5>
          <p class="text-muted mb-0 fs-13 mt-1">{{ $t('stores.form.branding_hint') }}</p>
        </BCardHeader>
        <BCardBody>
          <div class="d-flex align-items-center gap-3">
            <div class="store-logo-preview rounded border d-flex align-items-center justify-content-center">
              <img v-if="logoPreview" :src="logoPreview" :alt="form.name" class="store-logo-image" />
              <i v-else class="ri-store-2-line fs-24 text-muted"></i>
            </div>

            <div class="flex-grow-1">
              <input
                type="file"
                class="form-control"
                accept="image/png,image/jpeg,image/webp"
                :class="{ 'is-invalid': form.errors.logo }"
                @change="onLogoSelected"
              />
              <InputError :message="form.errors.logo" />
              <p class="text-muted fs-12 mb-0 mt-1">{{ $t('stores.form.logo_hint') }}</p>
            </div>
          </div>
        </BCardBody>
      </BCard>

      <BCard data-guide="store-contact" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stores.form.contact') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label">{{ $t('stores.fields.contact_name') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.contact_name"
                :class="{ 'is-invalid': form.errors.contact_name }"
              />
              <InputError :message="form.errors.contact_name" />
            </BCol>

            <BCol md="3">
              <label class="form-label">{{ $t('stores.fields.contact_phone') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.contact_phone"
                :class="{ 'is-invalid': form.errors.contact_phone }"
              />
              <InputError :message="form.errors.contact_phone" />
            </BCol>

            <BCol md="3">
              <label class="form-label">{{ $t('stores.fields.contact_email') }}</label>
              <input
                type="email"
                class="form-control"
                v-model="form.contact_email"
                :class="{ 'is-invalid': form.errors.contact_email }"
              />
              <InputError :message="form.errors.contact_email" />
            </BCol>

            <BCol md="4">
              <label class="form-label">{{ $t('stores.fields.city') }}</label>
              <select
                class="form-select"
                v-model="form.city_id"
                :class="{ 'is-invalid': form.errors.city_id }"
              >
                <option :value="null">{{ $t('common.select') }}</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
              </select>
              <InputError :message="form.errors.city_id" />
            </BCol>

            <BCol md="8">
              <label class="form-label">{{ $t('stores.fields.address') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.address"
                :class="{ 'is-invalid': form.errors.address }"
              />
              <InputError :message="form.errors.address" />
            </BCol>

            <BCol md="6">
              <label class="form-label">{{ $t('stores.fields.pickup_address_1') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.pickup_address_1"
                :class="{ 'is-invalid': form.errors.pickup_address_1 }"
              />
              <InputError :message="form.errors.pickup_address_1" />
            </BCol>

            <BCol md="6">
              <label class="form-label">{{ $t('stores.fields.pickup_address_2') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.pickup_address_2"
                :class="{ 'is-invalid': form.errors.pickup_address_2 }"
              />
              <InputError :message="form.errors.pickup_address_2" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard v-if="hubCities.length > 0" data-guide="store-fulfilment" no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('stores.form.fulfilment') }}</h5>
          <p class="text-muted mb-0 fs-13 mt-1">{{ $t('stores.form.fulfilment_hint') }}</p>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label" for="storeStockHub">{{ $t('stores.fields.stock_hub_city') }}</label>
              <select
                id="storeStockHub"
                class="form-select"
                v-model="form.stock_hub_city_id"
                :class="{ 'is-invalid': form.errors.stock_hub_city_id }"
              >
                <option :value="null">{{ $t('stores.form.no_stock_hub') }}</option>
                <option v-for="city in hubCities" :key="city.id" :value="city.id">{{ city.name }}</option>
              </select>
              <InputError :message="form.errors.stock_hub_city_id" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>

<style scoped>
.store-logo-preview {
  height: 72px;
  width: 72px;
  flex-shrink: 0;
  background-color: var(--vz-light);
}

.store-logo-image {
  max-height: 64px;
  max-width: 64px;
  object-fit: contain;
}
</style>
