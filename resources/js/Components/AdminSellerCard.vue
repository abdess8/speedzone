<script setup>
import InputError from '@/Components/InputError.vue';

defineProps({
  modelValue: { type: [Number, String], default: '' },
  sellers: { type: Array, default: () => [] },
  seller: { type: Object, default: null },
  error: { type: String, default: '' },
  label: { type: String, required: true },
  placeholder: { type: String, default: '' },
  help: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <BRow>
    <BCol xl="8" class="mx-auto">
      <BCard no-body>
        <BCardHeader>
          <h5 class="card-title mb-0">{{ label }}</h5>
        </BCardHeader>
        <BCardBody>
          <div v-if="readonly && seller">
            <div class="fw-semibold">{{ seller.name }}</div>
            <div class="text-muted fs-13 mb-0">{{ seller.email }}</div>
          </div>
          <template v-else>
            <label class="form-label" for="admin_seller_id">
              {{ label }}
              <span class="text-danger">*</span>
            </label>
            <select
              id="admin_seller_id"
              class="form-select"
              :class="{ 'is-invalid': error }"
              :value="modelValue"
              @change="$emit('update:modelValue', $event.target.value)"
            >
              <option value="">{{ placeholder }}</option>
              <option v-for="item in sellers" :key="item.id" :value="item.id">
                {{ item.name }} — {{ item.email }}
              </option>
            </select>
            <InputError :message="error" />
            <p v-if="help" class="text-muted fs-13 mb-0 mt-1">{{ help }}</p>
          </template>
        </BCardBody>
      </BCard>
    </BCol>
  </BRow>
</template>
