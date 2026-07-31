<script setup>
import BottomSheet from './BottomSheet.vue';

/**
 * Detail view for any entity, opened from an {@see EntityCard}.
 *
 * Rendered from the paginated row already in memory, so tapping a card costs no
 * request. Pages that need more than the list carries link out to the full
 * screen from the footer slot instead of fetching inside the sheet.
 */
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  statusLabel: { type: String, default: '' },
  statusColor: { type: String, default: 'secondary' },
  /** @type {{label: string, value: string|number|null, emphasis?: boolean}[]} */
  rows: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);
</script>

<template>
  <BottomSheet :show="show" :title="title" :subtitle="subtitle" @close="emit('close')">
    <div v-if="statusLabel || $slots.badges" class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <span v-if="statusLabel" class="badge" :class="`bg-${statusColor}-subtle text-${statusColor}`">
        {{ statusLabel }}
      </span>
      <slot name="badges"></slot>
    </div>

    <slot name="before-rows"></slot>

    <dl class="mb-0">
      <div
        v-for="row in rows"
        :key="row.label"
        class="d-flex justify-content-between align-items-start gap-3 py-2 sheet-row"
      >
        <dt class="text-muted fw-normal fs-13 flex-shrink-0">{{ row.label }}</dt>
        <dd class="mb-0 text-end fs-13" :class="row.emphasis ? 'fw-semibold' : 'fw-medium'">
          {{ row.value ?? '—' }}
        </dd>
      </div>
    </dl>

    <slot></slot>

    <template v-if="$slots.actions" #footer>
      <div class="d-flex gap-2">
        <slot name="actions"></slot>
      </div>
    </template>
  </BottomSheet>
</template>

<style scoped>
.sheet-row + .sheet-row {
  border-top: 1px solid var(--vz-border-color, #e9ebec);
}
</style>
