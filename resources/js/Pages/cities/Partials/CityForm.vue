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
          <h5 class="card-title mb-0">City Information</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="8">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.name"
                :class="{ 'is-invalid': form.errors.name }"
                placeholder="e.g. Casablanca"
              />
              <InputError :message="form.errors.name" />
            </BCol>
            <BCol md="4">
              <label class="form-label">Code</label>
              <input
                type="text"
                class="form-control text-uppercase"
                v-model="form.code"
                :class="{ 'is-invalid': form.errors.code }"
                placeholder="e.g. CASA"
              />
              <InputError :message="form.errors.code" />
            </BCol>
            <BCol md="8">
              <label class="form-label">Region</label>
              <input
                type="text"
                class="form-control"
                v-model="form.region"
                :class="{ 'is-invalid': form.errors.region }"
                placeholder="e.g. Casablanca-Settat"
              />
              <InputError :message="form.errors.region" />
            </BCol>
            <BCol md="4">
              <label class="form-label d-block">Status</label>
              <div class="form-check form-switch fs-15 mt-2">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="cityActive"
                  v-model="form.is_active"
                />
                <label class="form-check-label" for="cityActive">
                  {{ form.is_active ? "Active" : "Inactive" }}
                </label>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
