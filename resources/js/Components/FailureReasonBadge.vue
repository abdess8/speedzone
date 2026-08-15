<script setup>
import { computed } from "vue";

/**
 * The last failed delivery attempt, shown next to the status.
 *
 * A missed attempt leaves the order out for delivery on purpose — the parcel is
 * still on the round. The status badge alone therefore reads as if nothing had
 * happened, which is exactly the complaint this badge answers: it names the
 * motif of the last attempt without pretending the order has moved.
 */
const props = defineProps({
  order: { type: Object, required: true },
  showAttempts: { type: Boolean, default: true },
});

const visible = computed(
  () => !!props.order.failure_reason && props.order.status !== "DELIVERED"
);

const color = computed(() => props.order.failure_reason_color || "warning");

const attempts = computed(() => Number(props.order.failed_attempts_count ?? 0));
</script>

<template>
  <span
    v-if="visible"
    class="badge"
    :class="`bg-${color}-subtle text-${color}`"
    :title="order.failure_note || order.failure_reason_label"
  >
    <i v-if="order.failure_reason_icon" :class="order.failure_reason_icon" class="align-bottom me-1"></i>
    {{ order.failure_reason_label }}
    <template v-if="showAttempts && attempts > 1">({{ attempts }})</template>
  </span>
</template>
