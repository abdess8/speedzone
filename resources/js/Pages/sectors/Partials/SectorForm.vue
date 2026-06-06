<script setup>
import { computed } from "vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
  form: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
});

const cityOptions = computed(() =>
  props.cities.map((city) => ({
    value: city.id,
    label: city.region ? `${city.name} — ${city.region}` : city.name,
  }))
);
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">Sector Information</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="12">
              <label class="form-label">City <span class="text-danger">*</span></label>
              <Multiselect
                v-model="form.city_id"
                :options="cityOptions"
                :searchable="true"
                :close-on-select="true"
                placeholder="Search and select a city…"
                :class="{ 'is-invalid': form.errors.city_id }"
              />
              <InputError :message="form.errors.city_id" />
            </BCol>
            <BCol md="8">
              <label class="form-label">Sector Name <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.name"
                :class="{ 'is-invalid': form.errors.name }"
                placeholder="e.g. Maarif"
              />
              <InputError :message="form.errors.name" />
            </BCol>
            <BCol md="4">
              <label class="form-label">Delivery Price <span class="text-danger">*</span></label>
              <div class="input-group">
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  class="form-control"
                  v-model="form.delivery_price"
                  :class="{ 'is-invalid': form.errors.delivery_price }"
                />
                <span class="input-group-text">MAD</span>
              </div>
              <InputError :message="form.errors.delivery_price" />
            </BCol>
            <BCol md="12">
              <div class="form-check form-switch fs-15 mt-2">
                <input class="form-check-input" type="checkbox" role="switch" id="sectorActive" v-model="form.is_active" />
                <label class="form-check-label" for="sectorActive">{{ form.is_active ? "Active" : "Inactive" }}</label>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
