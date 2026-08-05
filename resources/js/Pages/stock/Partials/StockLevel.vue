<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Availability of one reference, as a number plus what that number means.
 *
 * The count alone is not actionable: 3 is comfortable for a pallet of boxes and
 * an emergency for a best-seller, so the badge carries the interpretation the
 * server already made (`is_low_stock`, `is_out_of_stock`) rather than the screen
 * re-deriving a threshold it does not own.
 */
const props = defineProps({
  quantity: { type: Number, default: 0 },
  isLowStock: { type: Boolean, default: false },
  isOutOfStock: { type: Boolean, default: false },
  /** Hides the wording and keeps the coloured figure, for dense tables. */
  compact: { type: Boolean, default: false },
});

const { t } = useI18n();

const tone = computed(() => {
  if (props.isOutOfStock) return 'danger';
  if (props.isLowStock) return 'warning';

  return 'success';
});

const label = computed(() => {
  if (props.isOutOfStock) return t('stock.products.badges.out_of_stock');
  if (props.isLowStock) return t('stock.products.badges.low_stock');

  return '';
});
</script>

<template>
  <span class="d-inline-flex align-items-center gap-2">
    <span class="fw-semibold" :class="`text-${tone}`">{{ quantity }}</span>
    <span v-if="!compact && label" class="badge" :class="`bg-${tone}-subtle text-${tone}`">
      {{ label }}
    </span>
  </span>
</template>
