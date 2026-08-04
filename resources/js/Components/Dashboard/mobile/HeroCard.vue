<script setup>
import { computed } from 'vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { formatMoneyRounded } from '@/common/formatMoney';

/**
 * The one figure the screen exists to answer, above everything else.
 *
 * For a COD network that figure is the cash still owed by the field, so it
 * takes the full-bleed coloured panel while every other metric is relegated to
 * a card below it. The period stepper sits here too: changing it re-scopes the
 * whole screen, so it belongs next to the number it governs rather than in a
 * toolbar the thumb cannot reach.
 */
const props = defineProps({
  user: { type: Object, default: null },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  amount: { type: [Number, String], default: 0 },
  currency: { type: String, default: 'MAD' },
  label: { type: String, required: true },
  periodLabel: { type: String, default: '' },
  loading: { type: Boolean, default: false },
  refreshLabel: { type: String, required: true },
  previousLabel: { type: String, required: true },
  nextLabel: { type: String, required: true },
  canGoPrevious: { type: Boolean, default: true },
  canGoNext: { type: Boolean, default: true },
});

defineEmits(['previous', 'next', 'refresh']);

const formattedAmount = computed(() => formatMoneyRounded(props.amount));
</script>

<template>
  <section class="mdash-hero">
    <div class="mdash-hero-row">
      <UserAvatar :user="user" :size="38" class="mdash-hero-avatar" />

      <div class="mdash-hero-identity">
        <p class="mdash-hero-title">{{ title }}</p>
        <p v-if="subtitle" class="mdash-hero-subtitle">{{ subtitle }}</p>
      </div>

      <div class="mdash-stepper">
        <button
          type="button"
          class="mdash-stepper-arrow"
          :disabled="!canGoPrevious"
          :aria-label="previousLabel"
          @click="$emit('previous')"
        >
          <i class="ri-arrow-left-s-line"></i>
        </button>
        <span class="mdash-stepper-label">{{ periodLabel }}</span>
        <button
          type="button"
          class="mdash-stepper-arrow"
          :disabled="!canGoNext"
          :aria-label="nextLabel"
          @click="$emit('next')"
        >
          <i class="ri-arrow-right-s-line"></i>
        </button>
      </div>
    </div>

    <div class="mdash-hero-figure">
      <div v-if="loading" class="mdash-hero-skeleton" aria-hidden="true"></div>
      <p v-else class="mdash-hero-amount">
        {{ formattedAmount }}<span v-if="currency" class="mdash-hero-currency">{{ currency }}</span>
      </p>
      <p class="mdash-hero-label">{{ label }}</p>
    </div>

    <button
      type="button"
      class="mdash-hero-refresh"
      :disabled="loading"
      :aria-label="refreshLabel"
      @click="$emit('refresh')"
    >
      <i class="ri-refresh-line" :class="{ 'mdash-spin': loading }"></i>
    </button>
  </section>
</template>

<style scoped>
.mdash-hero {
  position: relative;
  overflow: hidden;
  /* The strip of stat cards below is pulled up over this padding, so the two
     read as one object the way a card peeking out from under another does. */
  padding: 1.125rem 1.25rem 3.5rem;
  border-radius: var(--mdash-radius-lg);
  background: linear-gradient(140deg, #1b62c4 0%, #df2222 52%, #08356f 100%);
  color: #fff;
}

/* Soft light source in the top-right, so the panel is not a flat rectangle. */
.mdash-hero::after {
  position: absolute;
  top: -45%;
  right: -25%;
  width: 17rem;
  height: 17rem;
  background: radial-gradient(circle, rgba(223, 34, 34, 0.38) 0%, rgba(223, 34, 34, 0) 68%);
  border-radius: 50%;
  content: '';
  pointer-events: none;
}

.mdash-hero-row {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 0.625rem;
}

.mdash-hero-avatar {
  flex-shrink: 0;
  border-radius: 50%;
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.35);
}

.mdash-hero-identity {
  min-width: 0;
  flex-grow: 1;
}

.mdash-hero-title,
.mdash-hero-subtitle {
  overflow: hidden;
  margin: 0;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mdash-hero-title {
  font-size: 0.9375rem;
  font-weight: 600;
}

.mdash-hero-subtitle {
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.6875rem;
}

.mdash-stepper {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 0.125rem;
  padding: 0.125rem;
  border-radius: 999px;
  background-color: rgba(255, 255, 255, 0.16);
}

.mdash-stepper-arrow {
  display: inline-flex;
  width: 1.5rem;
  height: 1.5rem;
  align-items: center;
  justify-content: center;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: #fff;
  font-size: 1rem;
  line-height: 1;
}

.mdash-stepper-arrow:disabled {
  opacity: 0.35;
}

.mdash-stepper-label {
  padding: 0 0.25rem;
  font-size: 0.6875rem;
  font-weight: 600;
  white-space: nowrap;
}

.mdash-hero-figure {
  position: relative;
  z-index: 1;
  margin-top: 1.125rem;
}

.mdash-hero-amount {
  display: flex;
  align-items: baseline;
  margin: 0;
  font-size: 2.375rem;
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.mdash-hero-currency {
  margin-left: 0.375rem;
  font-size: 0.9375rem;
  font-weight: 600;
  opacity: 0.75;
}

.mdash-hero-label {
  margin: 0.25rem 0 0;
  color: rgba(255, 255, 255, 0.75);
  font-size: 0.75rem;
}

.mdash-hero-skeleton {
  width: 9rem;
  height: 2.5rem;
  border-radius: 0.5rem;
  background-color: rgba(255, 255, 255, 0.2);
  animation: mdash-pulse 1.4s ease-in-out infinite;
}

.mdash-hero-refresh {
  position: absolute;
  z-index: 1;
  top: 4.75rem;
  right: 1.25rem;
  display: inline-flex;
  width: 2.375rem;
  height: 2.375rem;
  align-items: center;
  justify-content: center;
  border: 0;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.18);
  color: #fff;
  font-size: 1.125rem;
}

.mdash-hero-refresh:disabled {
  opacity: 0.6;
}

.mdash-spin {
  display: inline-block;
  animation: mdash-rotate 0.9s linear infinite;
}

@keyframes mdash-rotate {
  to {
    transform: rotate(360deg);
  }
}

@keyframes mdash-pulse {
  50% {
    opacity: 0.45;
  }
}

@media (prefers-reduced-motion: reduce) {
  .mdash-spin,
  .mdash-hero-skeleton {
    animation: none;
  }
}
</style>
