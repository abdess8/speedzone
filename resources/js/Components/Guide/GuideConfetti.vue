<script setup>
import { computed } from 'vue';

/**
 * The little celebration on the closing step.
 *
 * CSS rather than a canvas library: it is forty absolutely positioned divs for
 * three seconds, which is not worth a dependency, and it costs nothing on the
 * screens that never reach it. Purely decorative, so it disappears entirely
 * when the reader asked for reduced motion.
 */

const props = defineProps({
  pieces: { type: Number, default: 40 },
});

const COLORS = ['#405189', '#0ab39c', '#f7b84b', '#f06548', '#299cdb', '#f672a7'];

const prefersReducedMotion =
  typeof window !== 'undefined' &&
  typeof window.matchMedia === 'function' &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const confetti = computed(() =>
  Array.from({ length: props.pieces }, (_, index) => ({
    id: index,
    style: {
      left: `${Math.random() * 100}%`,
      backgroundColor: COLORS[index % COLORS.length],
      animationDelay: `${Math.random() * 0.6}s`,
      animationDuration: `${2.2 + Math.random() * 1.4}s`,
      transform: `rotate(${Math.random() * 360}deg)`,
      width: `${6 + Math.random() * 6}px`,
      height: `${10 + Math.random() * 8}px`,
    },
  }))
);
</script>

<template>
  <div v-if="!prefersReducedMotion" class="guide-confetti" aria-hidden="true">
    <span v-for="piece in confetti" :key="piece.id" class="guide-confetti__piece" :style="piece.style"></span>
  </div>
</template>

<style scoped>
.guide-confetti {
  position: fixed;
  inset: 0;
  z-index: 2081;
  overflow: hidden;
  pointer-events: none;
}

.guide-confetti__piece {
  position: absolute;
  top: -5vh;
  border-radius: 2px;
  animation-name: guide-confetti-fall;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}

@keyframes guide-confetti-fall {
  0% {
    opacity: 0;
    transform: translateY(0) rotate(0deg);
  }
  10% {
    opacity: 1;
  }
  100% {
    opacity: 0;
    transform: translateY(108vh) rotate(540deg);
  }
}
</style>
