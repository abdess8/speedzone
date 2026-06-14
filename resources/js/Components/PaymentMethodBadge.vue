<script setup>
defineProps({
  label: { type: String, required: true },
  emoji: { type: String, default: "" },
  color: { type: String, default: "secondary" },
});
</script>

<template>
  <span class="badge" :class="`bg-${color}-subtle text-${color}`">
    <span v-if="emoji" class="me-1">{{ emoji }}</span>{{ label }}
  </span>
</template>
