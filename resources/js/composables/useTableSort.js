import { ref } from 'vue';

/**
 * Client half of a server-sorted table.
 *
 * Holds the current column and direction, and hands them back so the page can
 * fold them into whatever request it already makes for its filters. Clicking
 * the active column flips the direction; clicking another starts it ascending,
 * which is what a reader who just picked "Name" expects to see.
 *
 * @param {{sort?: string, direction?: string}} initial values the server echoed back
 * @param {() => void} reload the page's own "re-request with current filters"
 */
export function useTableSort(initial, reload) {
  const sort = ref(initial?.sort ?? '');
  const direction = ref(initial?.direction ?? 'desc');

  function sortBy(field) {
    if (sort.value === field) {
      direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
      sort.value = field;
      direction.value = 'asc';
    }

    reload();
  }

  function sortIcon(field) {
    if (sort.value !== field) {
      return 'ri-arrow-up-down-line text-muted';
    }

    return direction.value === 'asc' ? 'ri-sort-asc' : 'ri-sort-desc';
  }

  return { sort, direction, sortBy, sortIcon };
}
