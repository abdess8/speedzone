<script setup>
import StatusTimeline from "@/Components/StatusTimeline.vue";

defineProps({
  history: { type: Array, default: () => [] },
});
</script>

<template>
  <StatusTimeline :history="history" />
</template>
