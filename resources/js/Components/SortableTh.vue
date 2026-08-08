<script setup>
import { computed } from 'vue';

/**
 * A sortable table header.
 *
 * The click target is a real button rather than the cell itself: a `<th>` with
 * a click handler is invisible to the keyboard and to a screen reader, and the
 * warehouse screens are used with a scanner in one hand and Tab in the other.
 * `aria-sort` on the cell is what announces the current order.
 */
const props = defineProps({
  field: { type: String, required: true },
  sort: { type: String, default: '' },
  direction: { type: String, default: 'desc' },
  /** `start`, `center` or `end`, mirroring the alignment of the column's cells. */
  align: { type: String, default: 'start' },
});

const emit = defineEmits(['sort']);

const active = computed(() => props.sort === props.field);

const ariaSort = computed(() => {
  if (!active.value) {
    return 'none';
  }

  return props.direction === 'asc' ? 'ascending' : 'descending';
});

const icon = computed(() => {
  if (!active.value) {
    return 'ri-arrow-up-down-line text-muted';
  }

  return props.direction === 'asc' ? 'ri-sort-asc' : 'ri-sort-desc';
});
</script>

<template>
  <th scope="col" :aria-sort="ariaSort">
    <button
      type="button"
      class="table-sort"
      :class="[`justify-content-${align}`, { 'table-sort--active': active }]"
      @click="emit('sort', field)"
    >
      <span><slot /></span>
      <i :class="icon" class="ms-1 flex-shrink-0"></i>
    </button>
  </th>
</template>

<style scoped>
.table-sort {
  display: flex;
  align-items: center;
  gap: 0.125rem;
  width: 100%;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: inherit;
  white-space: nowrap;
}

.table-sort:hover,
.table-sort--active {
  color: var(--vz-primary, currentColor);
}

.table-sort:focus-visible {
  outline: 2px solid var(--vz-primary, currentColor);
  outline-offset: 2px;
  border-radius: 0.125rem;
}
</style>
