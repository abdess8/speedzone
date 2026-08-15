<script setup>
import { computed, onBeforeUnmount, ref } from "vue";

defineProps({
  text: { type: String, required: true },
  // Shown under the help text in a monospace line: the technical identifier an
  // integrator needs, kept out of the way of everybody else.
  meta: { type: String, default: "" },
  label: { type: String, default: "" },
});

const BUBBLE_WIDTH = 300;
const VIEWPORT_MARGIN = 12;
const GAP = 8;

const trigger = ref(null);
const open = ref(false);
const position = ref({ top: 0, left: 0 });

// Scrolling would leave the bubble behind, so it closes rather than chasing the
// trigger on every frame.
const hide = () => {
  open.value = false;
  window.removeEventListener("scroll", hide, true);
  window.removeEventListener("resize", hide);
};

/**
 * The bubble is placed in viewport coordinates rather than relative to the
 * checkbox: the permission list is a grid of bordered cards, and a bubble
 * anchored inside one of them is clipped by the card next to it.
 */
const show = () => {
  const rect = trigger.value?.getBoundingClientRect();

  if (!rect) return;

  const furthestLeft = window.innerWidth - BUBBLE_WIDTH - VIEWPORT_MARGIN;
  const centred = rect.left + rect.width / 2 - BUBBLE_WIDTH / 2;

  position.value = {
    top: rect.bottom + GAP,
    left: Math.max(VIEWPORT_MARGIN, Math.min(centred, furthestLeft)),
  };

  open.value = true;
  window.addEventListener("scroll", hide, true);
  window.addEventListener("resize", hide);
};

const toggle = () => (open.value ? hide() : show());

const style = computed(() => ({
  top: `${position.value.top}px`,
  left: `${position.value.left}px`,
  width: `${BUBBLE_WIDTH}px`,
}));

onBeforeUnmount(hide);
</script>

<template>
  <span class="info-hint">
    <button
      ref="trigger"
      type="button"
      class="info-hint__trigger"
      :aria-label="label || text"
      :aria-expanded="open"
      @click.prevent.stop="toggle"
      @mouseenter="show"
      @mouseleave="hide"
      @focus="show"
      @blur="hide"
      @keydown.esc="hide"
    >
      <i class="ri-information-line" aria-hidden="true"></i>
    </button>

    <Teleport to="body">
      <span v-if="open" class="info-hint__bubble" role="tooltip" :style="style">
        {{ text }}
        <code v-if="meta" class="info-hint__meta">{{ meta }}</code>
      </span>
    </Teleport>
  </span>
</template>

<style scoped>
.info-hint {
  display: inline-flex;
  line-height: 1;
  vertical-align: middle;
}

.info-hint__trigger {
  border: 0;
  background: none;
  padding: 0;
  color: var(--vz-secondary-color, #878a99);
  font-size: 0.9rem;
  line-height: 1;
  cursor: help;
}

.info-hint__trigger:hover,
.info-hint__trigger:focus-visible {
  color: var(--vz-primary, #405189);
}
</style>

<style>
.info-hint__bubble {
  position: fixed;
  z-index: 1080;
  display: block;
  padding: 0.6rem 0.75rem;
  border-radius: 0.35rem;
  background: var(--vz-dark, #212529);
  color: #fff;
  font-size: 0.75rem;
  font-weight: 400;
  line-height: 1.45;
  text-align: start;
  text-transform: none;
  box-shadow: 0 5px 18px rgba(30, 32, 37, 0.25);
  pointer-events: none;
}

.info-hint__meta {
  display: block;
  margin-top: 0.4rem;
  padding-top: 0.4rem;
  border-top: 1px solid rgba(255, 255, 255, 0.18);
  color: rgba(255, 255, 255, 0.65);
  font-size: 0.68rem;
}
</style>
