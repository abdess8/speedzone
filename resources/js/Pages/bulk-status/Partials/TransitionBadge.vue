<script setup>
/**
 * "En cours de livraison → Livré", as one indivisible chip.
 *
 * The target status alone is never shown anywhere in this feature: an operator
 * about to move two hundred parcels has to read the route being taken, not just
 * the destination, or the confirmation screen tells him nothing he can check.
 */
defineProps({
  from: { type: Object, required: true },
  to: { type: Object, required: true },
  size: { type: String, default: 'md' },
});
</script>

<template>
  <span class="d-inline-flex align-items-center gap-1 flex-wrap" :class="{ 'fs-11': size === 'sm' }">
    <span class="badge" :class="`bg-${from.color}-subtle text-${from.color}`">
      <i v-if="from.icon" :class="from.icon" class="me-1"></i>{{ from.label }}
    </span>
    <i class="ri-arrow-right-line text-muted"></i>
    <span class="badge" :class="`bg-${to.color}-subtle text-${to.color}`">
      <i v-if="to.icon" :class="to.icon" class="me-1"></i>{{ to.label }}
    </span>
  </span>
</template>
