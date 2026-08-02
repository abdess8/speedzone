<script setup>
import { CountTo } from 'vue3-count-to';

defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  suffix: { type: String, default: '' },
  prefix: { type: String, default: '' },
  icon: { type: String, default: 'ri-box-3-line' },
  iconClass: { type: String, default: 'text-primary' },
  iconBg: { type: String, default: 'bg-primary-subtle' },
  loading: { type: Boolean, default: false },
  decimals: { type: Number, default: 0 },
  link: { type: String, default: '' },
  linkLabel: { type: String, default: '' },
});
</script>

<template>
  <BCard no-body class="card-animate dashboard-logistics-card">
    <BCardBody>
      <div v-if="loading" class="placeholder-glow">
        <span class="placeholder col-6 mb-3"></span>
        <span class="placeholder col-4 fs-20"></span>
      </div>
      <template v-else>
        <div class="d-flex align-items-center">
          <div class="flex-grow-1 overflow-hidden">
            <p class="text-uppercase fw-medium text-muted text-truncate mb-0">{{ title }}</p>
          </div>
        </div>
        <div class="d-flex align-items-end justify-content-between mt-4">
          <div>
            <h4 class="fs-20 fw-semibold ff-secondary mb-4">
              <span v-if="prefix">{{ prefix }}</span>
              <count-to
                :start-val="0"
                :end-val="Number(value) || 0"
                :duration="1200"
                :decimals="decimals"
              />
              <span v-if="suffix" class="fs-14">{{ suffix }}</span>
            </h4>
            <Link v-if="link" :href="link" class="text-decoration-underline">{{ linkLabel }}</Link>
          </div>
          <div class="avatar-sm flex-shrink-0">
            <span class="avatar-title rounded fs-3" :class="iconBg">
              <i :class="[icon, iconClass]"></i>
            </span>
          </div>
        </div>
      </template>
    </BCardBody>
  </BCard>
</template>
