<script setup>
import InputError from "@/Components/InputError.vue";

defineProps({
  form: { type: Object, required: true },
});
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ $t('cities.form.info') }}</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="8">
              <label class="form-label">{{ $t('cities.table.name') }} <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.name"
                :class="{ 'is-invalid': form.errors.name }"
                :placeholder="$t('cities.form.name_placeholder')"
              />
              <InputError :message="form.errors.name" />
            </BCol>
            <BCol md="4">
              <label class="form-label">{{ $t('cities.table.code') }}</label>
              <input
                type="text"
                class="form-control text-uppercase"
                v-model="form.code"
                :class="{ 'is-invalid': form.errors.code }"
                :placeholder="$t('cities.form.code_placeholder')"
              />
              <InputError :message="form.errors.code" />
            </BCol>
            <BCol md="8">
              <label class="form-label">{{ $t('cities.table.region') }}</label>
              <input
                type="text"
                class="form-control"
                v-model="form.region"
                :class="{ 'is-invalid': form.errors.region }"
                :placeholder="$t('cities.form.region_placeholder')"
              />
              <InputError :message="form.errors.region" />
            </BCol>
            <BCol md="4">
              <label class="form-label d-block">{{ $t('common.status') }}</label>
              <div class="form-check form-switch fs-15 mt-2">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="cityActive"
                  v-model="form.is_active"
                />
                <label class="form-check-label" for="cityActive">
                  {{ form.is_active ? $t('common.active') : $t('common.inactive') }}
                </label>
              </div>
            </BCol>
            <BCol md="12">
              <label class="form-label d-block">{{ $t('cities.form.stock_hub') }}</label>
              <div class="form-check form-switch fs-15">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="cityStockHub"
                  v-model="form.is_stock_hub"
                />
                <label class="form-check-label" for="cityStockHub">
                  {{ form.is_stock_hub ? $t('cities.form.stock_hub_on') : $t('cities.form.stock_hub_off') }}
                </label>
              </div>
              <p class="text-muted fs-12 mb-0 mt-1">{{ $t('cities.form.stock_hub_hint') }}</p>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
