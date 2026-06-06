<script setup>
import { computed, watch } from "vue";
import InputError from "@/Components/InputError.vue";

const props = defineProps({
  form: { type: Object, required: true },
  cities: { type: Array, default: () => [] },
  paymentMethods: { type: Array, default: () => [] },
});

const money = (value) =>
  new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
    Number(value || 0)
  );

const totalAmount = computed(
  () => Number(props.form.order_amount || 0) + Number(props.form.delivery_price || 0)
);

// When the destination city changes, pre-fill the delivery price with the
// city's default (only when the field is still empty / untouched).
watch(
  () => props.form.city_id,
  (cityId) => {
    const city = props.cities.find((c) => c.id === Number(cityId));
    if (city && (props.form.delivery_price === "" || props.form.delivery_price === null)) {
      props.form.delivery_price = city.delivery_price;
    }
  }
);
</script>

<template>
  <BRow>
    <BCol xl="8">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">Customer Information</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="6">
              <label class="form-label">First Name <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.customer_first_name"
                :class="{ 'is-invalid': form.errors.customer_first_name }"
              />
              <InputError :message="form.errors.customer_first_name" />
            </BCol>
            <BCol md="6">
              <label class="form-label">Last Name <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.customer_last_name"
                :class="{ 'is-invalid': form.errors.customer_last_name }"
              />
              <InputError :message="form.errors.customer_last_name" />
            </BCol>
            <BCol md="6">
              <label class="form-label">Phone <span class="text-danger">*</span></label>
              <input
                type="text"
                class="form-control"
                v-model="form.customer_phone"
                :class="{ 'is-invalid': form.errors.customer_phone }"
              />
              <InputError :message="form.errors.customer_phone" />
            </BCol>
            <BCol md="6">
              <label class="form-label">Delivery City <span class="text-danger">*</span></label>
              <select
                class="form-select"
                v-model="form.city_id"
                :class="{ 'is-invalid': form.errors.city_id }"
              >
                <option :value="null" disabled>Select a city</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">
                  {{ city.name }}<span v-if="city.region"> — {{ city.region }}</span>
                </option>
              </select>
              <InputError :message="form.errors.city_id" />
            </BCol>
            <BCol md="12">
              <label class="form-label">Address <span class="text-danger">*</span></label>
              <textarea
                class="form-control"
                rows="2"
                v-model="form.customer_address"
                :class="{ 'is-invalid': form.errors.customer_address }"
              ></textarea>
              <InputError :message="form.errors.customer_address" />
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>

      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">Package Information</h5>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="12">
              <label class="form-label">Notes</label>
              <textarea
                class="form-control"
                rows="2"
                placeholder="Printed on the shipping label"
                v-model="form.notes"
                :class="{ 'is-invalid': form.errors.notes }"
              ></textarea>
              <InputError :message="form.errors.notes" />
            </BCol>
            <BCol md="6">
              <div class="form-check form-switch fs-15">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="isFragile"
                  v-model="form.is_fragile"
                />
                <label class="form-check-label" for="isFragile">Fragile package</label>
              </div>
            </BCol>
            <BCol md="6">
              <div class="form-check form-switch fs-15">
                <input
                  class="form-check-input"
                  type="checkbox"
                  role="switch"
                  id="canBeOpened"
                  v-model="form.can_be_opened"
                />
                <label class="form-check-label" for="canBeOpened">Can be opened by customer</label>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </BCol>

    <BCol xl="4">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">Payment & Amounts</h5>
        </BCardHeader>
        <BCardBody>
          <div class="mb-3">
            <label class="form-label d-block">Payment Method <span class="text-danger">*</span></label>
            <div
              v-for="method in paymentMethods"
              :key="method.value"
              class="form-check form-check-inline"
            >
              <input
                class="form-check-input"
                type="radio"
                :id="`pm-${method.value}`"
                :value="method.value"
                v-model="form.payment_method"
              />
              <label class="form-check-label" :for="`pm-${method.value}`">{{ method.label }}</label>
            </div>
            <InputError :message="form.errors.payment_method" />
          </div>

          <div class="mb-3">
            <label class="form-label">Order Amount <span class="text-danger">*</span></label>
            <div class="input-group">
              <input
                type="number"
                step="0.01"
                min="0"
                class="form-control"
                v-model="form.order_amount"
                :class="{ 'is-invalid': form.errors.order_amount }"
              />
              <span class="input-group-text">MAD</span>
            </div>
            <InputError :message="form.errors.order_amount" />
          </div>

          <div class="mb-3">
            <label class="form-label">Delivery Price</label>
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
          </div>

          <div class="d-flex justify-content-between align-items-center border-top pt-3">
            <span class="fs-15 fw-medium">Total Amount</span>
            <span class="fs-18 fw-bold text-primary">{{ money(totalAmount) }} MAD</span>
          </div>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
