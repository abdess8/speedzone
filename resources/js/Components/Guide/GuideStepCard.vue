<script setup>
import { computed } from 'vue';

/**
 * The contents of one guide step.
 *
 * Rendered as-is inside a tooltip on desktop and inside a bottom sheet on
 * mobile: the two devices differ in where the card sits, never in what it says
 * or in which controls it offers, so both get the same component rather than
 * two copies that drift apart.
 */

const props = defineProps({
  title: { type: String, default: '' },
  body: { type: String, default: '' },
  /** Shown instead of the "Next" affordance while the step is gated. */
  hint: { type: String, default: '' },
  /** True while the reader still owes the step an action. */
  waiting: { type: Boolean, default: false },
  stepNumber: { type: Number, default: 1 },
  totalSteps: { type: Number, default: 1 },
  isFirst: { type: Boolean, default: false },
  isLast: { type: Boolean, default: false },
  /** `welcome` | `step` | `finish` */
  kind: { type: String, default: 'step' },
  ctaLabel: { type: String, default: '' },
  labels: { type: Object, required: true },
});

const emit = defineEmits(['next', 'previous', 'quit', 'cta']);

const isMilestone = computed(() => props.kind === 'welcome' || props.kind === 'finish');

const percent = computed(() =>
  props.totalSteps > 1 ? Math.round((props.stepNumber / props.totalSteps) * 100) : 100
);

const primaryLabel = computed(() => {
  if (props.kind === 'welcome') {
    return props.labels.start;
  }

  return props.isLast ? props.labels.finish : props.labels.next;
});
</script>

<template>
  <div class="guide-card" :class="`guide-card--${kind}`">
    <div class="guide-card__head">
      <div v-if="isMilestone" class="guide-card__badge" :class="`guide-card__badge--${kind}`">
        <i :class="kind === 'finish' ? 'ri-trophy-line' : 'ri-compass-3-line'"></i>
      </div>

      <div class="flex-grow-1">
        <p v-if="!isMilestone" class="guide-card__eyebrow mb-1">
          {{ labels.progress }}
        </p>
        <h5 class="guide-card__title mb-0">{{ title }}</h5>
      </div>

      <button
        type="button"
        class="btn btn-sm btn-ghost-secondary guide-card__close"
        :aria-label="labels.quit"
        @click="emit('quit')"
      >
        <i class="ri-close-line"></i>
      </button>
    </div>

    <p class="guide-card__body">{{ body }}</p>

    <!-- Says out loud that the tour is deliberately stuck, and on what. Without
         it a disabled "Next" reads as a broken guide. -->
    <div v-if="waiting && hint" class="guide-card__hint">
      <span class="guide-card__pulse" aria-hidden="true"></span>
      <div>
        <strong class="d-block">{{ labels.waiting }}</strong>
        {{ hint }}
      </div>
    </div>

    <div v-if="!isMilestone" class="guide-card__progress">
      <div class="progress" style="height: 4px">
        <div
          class="progress-bar bg-primary"
          role="progressbar"
          :style="{ width: `${percent}%` }"
          :aria-valuenow="stepNumber"
          aria-valuemin="1"
          :aria-valuemax="totalSteps"
        ></div>
      </div>
    </div>

    <div class="guide-card__actions">
      <button type="button" class="btn btn-sm btn-ghost-danger px-2" @click="emit('quit')">
        {{ labels.quitShort }}
      </button>

      <div class="d-flex align-items-center gap-2">
        <button
          v-if="!isFirst && !isMilestone"
          type="button"
          class="btn btn-sm btn-light"
          @click="emit('previous')"
        >
          <i class="ri-arrow-left-s-line align-middle"></i>
          {{ labels.previous }}
        </button>

        <button
          v-if="kind === 'finish' && ctaLabel"
          type="button"
          class="btn btn-sm btn-light"
          @click="emit('cta')"
        >
          {{ ctaLabel }}
        </button>

        <button
          type="button"
          class="btn btn-sm btn-primary"
          :disabled="waiting"
          @click="emit('next')"
        >
          {{ primaryLabel }}
          <i v-if="!isLast" class="ri-arrow-right-s-line align-middle"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.guide-card {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.guide-card__head {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.guide-card__badge {
  display: flex;
  width: 2.5rem;
  height: 2.5rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 1.25rem;
}

.guide-card__badge--welcome {
  background: rgba(var(--vz-primary-rgb), 0.12);
  color: var(--vz-primary);
}

.guide-card__badge--finish {
  background: rgba(var(--vz-success-rgb), 0.14);
  color: var(--vz-success);
}

.guide-card__eyebrow {
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.guide-card__title {
  font-size: 1rem;
  line-height: 1.35;
}

.guide-card__close {
  margin: -0.25rem -0.5rem 0 0;
  flex: 0 0 auto;
  padding: 0.25rem 0.5rem;
}

.guide-card__body {
  margin: 0;
  color: var(--vz-body-color);
  font-size: 0.875rem;
  line-height: 1.6;
}

.guide-card__hint {
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.5rem;
  background: rgba(var(--vz-warning-rgb), 0.12);
  color: var(--vz-body-color);
  font-size: 0.8125rem;
}

.guide-card__hint strong {
  color: var(--vz-warning);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.guide-card__pulse {
  position: relative;
  top: 0.35rem;
  width: 0.5rem;
  height: 0.5rem;
  flex: 0 0 auto;
  border-radius: 50%;
  background: var(--vz-warning);
  animation: guide-pulse 1.4s ease-in-out infinite;
}

.guide-card__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

@keyframes guide-pulse {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.35;
    transform: scale(1.35);
  }
}

@media (prefers-reduced-motion: reduce) {
  .guide-card__pulse {
    animation: none;
  }
}
</style>
