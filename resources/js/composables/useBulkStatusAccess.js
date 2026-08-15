import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

/**
 * Whether to offer the bulk status editor, and for which entity.
 *
 * The grants are pairs (`orders.status_transition.{from}.{to}`), so there is no
 * single name to test for: the check is "does this user hold any pair on this
 * resource". Like everything in `usePermissions`, this only decides whether a
 * button renders — the page, the board and the write are each gated server side
 * by StatusTransitionAccessService.
 */
export function useBulkStatusAccess() {
  const { permissions, isSuperAdmin } = usePermissions();

  const holdsAnyPair = (resource) => {
    if (isSuperAdmin.value) {
      return true;
    }

    return permissions.value.some((name) => name.startsWith(`${resource}.status_transition.`));
  };

  const canBulkEditOrders = computed(() => holdsAnyPair('orders'));
  const canBulkEditReturns = computed(() => holdsAnyPair('returns'));
  const canBulkEdit = computed(() => canBulkEditOrders.value || canBulkEditReturns.value);

  return { canBulkEdit, canBulkEditOrders, canBulkEditReturns };
}
