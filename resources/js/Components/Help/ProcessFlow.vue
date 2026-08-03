<script setup>
import { computed, onBeforeUnmount, ref, watch } from "vue";

/**
 * Animated reading of one end-to-end workflow.
 *
 * The parcel's journey is a sequence, and a static list of badges hides that:
 * readers scan it as a menu of states rather than a path with an order. Walking
 * the steps one at a time — on a timer, or by clicking — makes the sequence the
 * primary thing the component says.
 *
 * The active step's card is the detail panel, so the reader never has to hold a
 * status code in his head while looking elsewhere for what it means.
 */
const props = defineProps({
  title: { type: String, required: true },
  summary: { type: String, default: "" },
  tone: { type: String, default: "primary" },
  steps: { type: Array, default: () => [] },
  /** Advance on a timer as soon as the flow is shown. */
  autoplay: { type: Boolean, default: false },
  intervalMs: { type: Number, default: 2600 },
});

const activeIndex = ref(0);
const playing = ref(false);
let timer = null;

const activeStep = computed(() => props.steps[activeIndex.value] ?? null);
const atEnd = computed(() => activeIndex.value >= props.steps.length - 1);

const progress = computed(() => {
  if (props.steps.length < 2) return 0;

  return (activeIndex.value / (props.steps.length - 1)) * 100;
});

function stopTimer() {
  if (timer !== null) {
    window.clearInterval(timer);
    timer = null;
  }
}

function pause() {
  playing.value = false;
  stopTimer();
}

function play() {
  if (props.steps.length < 2) return;

  // Replaying from the end restarts rather than sitting on the last step.
  if (atEnd.value) activeIndex.value = 0;

  playing.value = true;
  stopTimer();

  timer = window.setInterval(() => {
    if (atEnd.value) {
      pause();

      return;
    }

    activeIndex.value += 1;
  }, props.intervalMs);
}

function toggle() {
  playing.value ? pause() : play();
}

/** A deliberate click always wins over the timer. */
function select(index) {
  pause();
  activeIndex.value = index;
}

const prefersReducedMotion = () =>
  typeof window !== "undefined" &&
  window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

watch(
  () => props.autoplay,
  (enabled) => {
    if (enabled && !prefersReducedMotion()) {
      play();
    } else {
      pause();
    }
  },
  { immediate: true }
);

watch(() => props.steps, () => {
  activeIndex.value = 0;
  pause();
});

onBeforeUnmount(stopTimer);
</script>

<template>
  <div class="process-flow">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
      <div>
        <h5 class="mb-1 fs-15 d-flex align-items-center gap-2">
          <span class="flow-dot" :class="`bg-${tone}`" aria-hidden="true"></span>
          {{ title }}
        </h5>
        <p v-if="summary" class="text-muted mb-0 fs-13">{{ summary }}</p>
      </div>

      <button
        v-if="steps.length > 1"
        type="button"
        class="btn btn-sm btn-soft-primary"
        @click="toggle"
      >
        <i :class="playing ? 'ri-pause-line' : atEnd ? 'ri-restart-line' : 'ri-play-line'" class="align-bottom"></i>
        <span class="d-none d-sm-inline ms-1">
          {{ playing ? $t('help.processes.pause') : atEnd ? $t('help.processes.replay') : $t('help.processes.play') }}
        </span>
      </button>
    </div>

    <!-- The rail is decorative: the same sequence is conveyed by the ordered
         list of buttons underneath, each of which is individually reachable. -->
    <div class="flow-rail" aria-hidden="true">
      <div class="flow-rail-fill" :class="`bg-${tone}`" :style="{ width: `${progress}%` }"></div>
    </div>

    <ol class="flow-steps" :aria-label="title">
      <li v-for="(step, index) in steps" :key="step.key" class="flow-step">
        <button
          type="button"
          class="flow-step-button"
          :class="{
            active: index === activeIndex,
            done: index < activeIndex,
          }"
          :aria-current="index === activeIndex ? 'step' : undefined"
          @click="select(index)"
        >
          <span class="flow-step-icon" :class="`text-${step.color}`">
            <i :class="step.icon"></i>
          </span>
          <span class="flow-step-label">{{ step.label }}</span>
        </button>
      </li>
    </ol>

    <Transition name="flow-detail" mode="out-in">
      <div v-if="activeStep" :key="activeStep.key" class="flow-detail" :class="`border-${activeStep.color}`">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge" :class="`bg-${activeStep.color}-subtle text-${activeStep.color}`">
            <i :class="activeStep.icon" class="me-1"></i>{{ activeStep.label }}
          </span>
          <span class="text-muted fs-12">
            {{ $t('help.processes.step_of', { current: activeIndex + 1, total: steps.length }) }}
          </span>
        </div>

        <p class="mb-2">{{ activeStep.description }}</p>

        <div class="text-muted fs-13">
          <i class="ri-user-settings-line me-1"></i>
          <span class="fw-medium">{{ $t('help.processes.legend_actor') }} :</span>
          {{ activeStep.actor }}
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.flow-dot {
  display: inline-block;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
}

.flow-rail {
  position: relative;
  height: 3px;
  border-radius: 3px;
  margin-bottom: 0.85rem;
  background: var(--vz-border-color);
}

.flow-rail-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.5s ease;
}

.flow-steps {
  display: flex;
  overflow-x: auto;
  margin: 0 0 1rem;
  padding: 0;
  gap: 0.5rem;
  list-style: none;
  scrollbar-width: thin;
}

.flow-step {
  flex: 1 1 0;
  min-width: 7.5rem;
}

.flow-step-button {
  display: flex;
  width: 100%;
  height: 100%;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  padding: 0.65rem 0.4rem;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.6rem;
  background: var(--vz-card-bg);
  text-align: center;
  transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
}

.flow-step-button:hover {
  border-color: var(--vz-primary);
}

.flow-step-button.done {
  opacity: 0.65;
}

.flow-step-button.active {
  border-color: var(--vz-primary);
  background: rgba(var(--vz-primary-rgb), 0.06);
  transform: translateY(-3px);
}

.flow-step-icon {
  font-size: 1.35rem;
  line-height: 1;
}

.flow-step-label {
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.2;
}

.flow-detail {
  padding: 1rem;
  border: 1px solid var(--vz-border-color);
  border-left-width: 4px;
  border-radius: 0.6rem;
  background: var(--vz-light-bg-subtle, rgba(var(--vz-light-rgb), 0.4));
}

.flow-detail-enter-active,
.flow-detail-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}

.flow-detail-enter-from {
  opacity: 0;
  transform: translateY(6px);
}

.flow-detail-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
  .flow-rail-fill,
  .flow-step-button,
  .flow-detail-enter-active,
  .flow-detail-leave-active {
    transition: none;
  }

  .flow-step-button.active {
    transform: none;
  }
}
</style>
