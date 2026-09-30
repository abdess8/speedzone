<script setup>
defineProps({
  order: { type: Object, required: true },
});
</script>

<template>
  <span
    v-if="order.creation_source && order.creation_source !== 'manual'"
    class="badge d-inline-flex align-items-center gap-1"
    :class="`bg-${order.creation_source_color}-subtle text-${order.creation_source_color}`"
  >
    <i
      v-if="order.creation_source_icon"
      class="fs-13"
      :class="order.creation_source_icon"
      :style="order.creation_source_icon_color ? { color: order.creation_source_icon_color } : undefined"
    ></i>
    {{ order.creation_source_label }}
  </span>
</template>
