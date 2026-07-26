<script setup>
defineProps({
  title: { type: String, required: true },
  loading: { type: Boolean, default: false },
  empty: { type: Boolean, default: false },
  emptyMessage: { type: String, default: 'No data for this period.' },
  height: { type: [Number, String], default: 320 },
});
</script>

<template>
  <BCard no-body class="h-100">
    <BCardHeader class="align-items-center d-flex border-0">
      <BCardTitle class="mb-0 flex-grow-1">{{ title }}</BCardTitle>
      <slot name="actions" />
    </BCardHeader>
    <BCardBody>
      <div v-if="loading" class="placeholder-glow" :style="{ minHeight: `${height}px` }">
        <span class="placeholder col-12 mb-2"></span>
        <span class="placeholder col-10 mb-2"></span>
        <span class="placeholder col-8"></span>
      </div>
      <div
        v-else-if="empty"
        class="text-center text-muted d-flex align-items-center justify-content-center"
        :style="{ minHeight: `${height}px` }"
      >
        <div>
          <i class="ri-bar-chart-box-line fs-1 d-block mb-2 opacity-50"></i>
          {{ emptyMessage }}
        </div>
      </div>
      <slot v-else />
    </BCardBody>
  </BCard>
</template>
