import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Client-side mirror of the server permission checks.
 *
 * The flat permission-name array and the `isSuperAdmin` flag are shared on every
 * Inertia response by HandleInertiaRequests.
 *
 * This is a *rendering* concern only: hiding a button here is a UX decision, and
 * every action it guards is independently enforced by a policy (row level) and
 * by the `permission:` route middleware (URL level). Never rely on it for
 * security.
 */
export function usePermissions() {
  const page = usePage();

  const permissions = computed(() => page.props.permissions ?? []);
  const isSuperAdmin = computed(() => page.props.isSuperAdmin === true);
  const user = computed(() => page.props.auth?.user ?? null);
  const roles = computed(() => user.value?.roles ?? []);

  /**
   * Exact permission-name check, short-circuited for super admins to mirror the
   * `Gate::before` bypass on the server.
   */
  function can(permission) {
    if (isSuperAdmin.value) {
      return true;
    }

    return permissions.value.includes(permission);
  }

  /**
   * Any-of check, matching the semantics of the `permission:` middleware and of
   * the `viewAny` policy methods.
   */
  function canAny(...candidates) {
    const flat = candidates.flat().filter(Boolean);

    if (flat.length === 0) {
      return false;
    }

    return flat.some((permission) => can(permission));
  }

  function canAll(...candidates) {
    const flat = candidates.flat().filter(Boolean);

    return flat.length > 0 && flat.every((permission) => can(permission));
  }

  function hasRole(role) {
    return roles.value.includes(role);
  }

  const isDriver = computed(() => roles.value.includes('Driver'));
  const isSeller = computed(
    () => user.value?.is_seller === true || roles.value.includes('Seller')
  );

  /**
   * Plain-value snapshot handed to the navigation definitions.
   *
   * `menuItems.js` stays free of Vue reactivity, and — more importantly — the
   * desktop sidebar and the mobile bottom bar resolve their visibility from the
   * *same* object, so an entry can never be hidden in one and shown in the other.
   */
  const navigationContext = computed(() => ({
    can,
    canAny: (candidates) => canAny(candidates),
    isSuperAdmin: isSuperAdmin.value,
    user: user.value,
    roles: roles.value,
    isDriver: isDriver.value,
    isSeller: isSeller.value,
  }));

  return {
    permissions,
    isSuperAdmin,
    user,
    roles,
    can,
    canAny,
    canAll,
    hasRole,
    navigationContext,
    isAdmin: computed(() => isSuperAdmin.value),
    isDriver,
    isSeller,
    isDispatcher: computed(() => roles.value.includes('Dispatcher')),
  };
}
