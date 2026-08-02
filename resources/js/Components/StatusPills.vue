<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';

/**
 * Status filter as a scrollable row of pills, for list screens on mobile.
 *
 * Filtering by status is by far the most frequent thing done to these lists,
 * yet on a phone it costs four taps: open the filter sheet, open the select,
 * pick a value, apply. A pill row makes it one tap and, unlike a closed select,
 * shows what the list is currently narrowed to without being opened.
 *
 * The pills only ever express one status at a time, which is exactly what the
 * server accepts on the `status` query parameter. Everything finer stays in the
 * filter sheet.
 */
const props = defineProps({
  /**
   * Selected status, `''` for "all". Pass `null` when something the pills
   * cannot express is narrowing the list, so that none of them reads as active.
   */
  modelValue: { type: [String, Number], default: '' },
  /** @type {import('vue').PropType<Array<{value: string, label: string, color: string}>>} */
  options: { type: Array, default: () => [] },
  allLabel: { type: String, required: true },
  label: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const strip = ref(null);

const pills = computed(() => [
  { value: '', label: props.allLabel, color: 'primary' },
  ...props.options.map((option) => ({
    value: option.value,
    label: option.label,
    color: option.color || 'secondary',
  })),
]);

const isActive = (pill) =>
  props.modelValue !== null && String(pill.value) === String(props.modelValue);

/**
 * A status chosen elsewhere — a bookmarked URL, the filter sheet, a sidebar
 * shortcut — can sit far down a row of seventeen pills, where it is invisible
 * and reads as "no filter applied". Bring it into view instead.
 */
async function revealActive(behavior) {
  await nextTick();

  const active = strip.value?.querySelector('[data-active="true"]');

  active?.scrollIntoView({ behavior, block: 'nearest', inline: 'center' });
}

onMounted(() => revealActive('auto'));

watch(() => props.modelValue, () => revealActive('smooth'));

function select(pill) {
  if (isActive(pill)) {
    return;
  }

  emit('update:modelValue', pill.value);
  emit('change', pill.value);
}
</script>

<template>
  <div class="status-pills-bar">
    <div ref="strip" class="status-pills" role="group" :aria-label="label || allLabel">
      <button
        v-for="pill in pills"
        :key="pill.value || '__all__'"
        type="button"
        class="status-pill"
        :class="isActive(pill) ? `text-bg-${pill.color} status-pill-active` : 'status-pill-idle'"
        :data-active="isActive(pill)"
        :aria-pressed="isActive(pill)"
        @click="select(pill)"
      >
        {{ pill.label }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.status-pills-bar {
  border-bottom: 1px dashed var(--vz-border-color, #e9ebec);
}

.status-pills {
  display: flex;
  overflow-x: auto;
  gap: 0.375rem;
  /* The padding lives on the scroller, not the bar, so the row can run to both
     card edges instead of stopping short in a padded box. */
  padding: 0.75rem 1rem;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

.status-pills::-webkit-scrollbar {
  display: none;
}

.status-pill {
  flex: 0 0 auto;
  /* Comfortably above the 44px touch target once the row's padding is counted,
     without turning the bar into a second toolbar. */
  min-height: 34px;
  padding: 0.3125rem 0.75rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.35;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
  white-space: nowrap;
}

.status-pill-idle {
  background-color: var(--vz-light, #f3f6f9);
  border-color: var(--vz-border-color, #e9ebec);
  color: var(--vz-secondary-color, #878a99);
}

/* `text-bg-*` supplies the fill and a foreground Bootstrap has already checked
   for contrast, which a flat `text-white` would not survive on warning yellow. */
.status-pill-active {
  border-color: transparent;
  font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
  .status-pill {
    transition: none;
  }
}
</style>
