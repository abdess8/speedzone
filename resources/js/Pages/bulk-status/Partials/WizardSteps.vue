<script setup>
import { computed } from 'vue';

/**
 * The progressive trail: type → target → selection → confirmation → result.
 *
 * A completed step stays clickable so the operator can widen his target without
 * starting over; a step he has not reached yet is inert, because reaching it
 * out of order would show him a board built from nothing.
 */
const props = defineProps({
  current: { type: String, required: true },
  reachable: { type: Array, default: () => [] },
});

defineEmits(['go']);

const steps = ['entity', 'target', 'selection', 'confirmation', 'result'];

const currentIndex = computed(() => steps.indexOf(props.current));
</script>

<template>
  <ol class="wizard-steps list-unstyled d-flex align-items-center gap-2 mb-0 flex-wrap">
    <li
      v-for="(step, index) in steps"
      :key="step"
      class="wizard-step d-flex align-items-center gap-2"
      :class="{
        'wizard-step--done': index < currentIndex,
        'wizard-step--current': step === current,
      }"
    >
      <button
        type="button"
        class="wizard-step-dot"
        :disabled="!reachable.includes(step)"
        @click="reachable.includes(step) && $emit('go', step)"
      >
        <i v-if="index < currentIndex" class="ri-check-line"></i>
        <span v-else>{{ index + 1 }}</span>
      </button>
      <span class="wizard-step-label d-none d-md-inline">{{ $t(`bulk_status.steps.${step}`) }}</span>
      <i v-if="index < steps.length - 1" class="ri-arrow-right-s-line text-muted"></i>
    </li>
  </ol>
</template>

<style scoped>
.wizard-step-dot {
  display: flex;
  width: 1.75rem;
  height: 1.75rem;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--vz-border-color, #e9ebec);
  border-radius: 999px;
  background-color: transparent;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.75rem;
  font-weight: 600;
}

.wizard-step-dot:disabled {
  cursor: default;
}

.wizard-step--done .wizard-step-dot {
  border-color: var(--vz-success, #0ab39c);
  background-color: var(--vz-success, #0ab39c);
  color: #fff;
}

.wizard-step--current .wizard-step-dot {
  border-color: var(--vz-primary, #0d4a9d);
  background-color: var(--vz-primary, #0d4a9d);
  color: #fff;
}

.wizard-step-label {
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.8125rem;
}

.wizard-step--current .wizard-step-label {
  color: var(--vz-body-color);
  font-weight: 600;
}
</style>
