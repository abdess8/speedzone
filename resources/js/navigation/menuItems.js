/**
 * Declarative sidebar definition.
 *
 * Each entry lists the permissions that reveal it, using **any-of** semantics so
 * it lines up with both `EnsurePermission` and the `viewAny` policy methods. The
 * permission strings here must stay identical to the ones on the matching route
 * in `routes/web.php`; that pairing is what makes "hidden in the sidebar" and
 * "403 on direct URL" two views of the same rule.
 *
 * Item shape:
 *   key         unique id, also used as the Bootstrap collapse target
 *   labelKey    vue-i18n key
 *   icon        Remix icon class (top level only)
 *   href        target URL, or `route` for a Ziggy route name
 *   permissions any-of list; an empty list means "always visible"
 *   visible     optional escape hatch receiving the permission context
 *   children    submenu rendered as a Bootstrap collapse
 *   footer      pins the entry to the bottom of the sidebar
 */

const ORDER_READ = ['orders.read.all', 'orders.read.own', 'orders.read.assigned'];
const PICKUP_READ = [
  'pickup_requests.read.all',
  'pickup_requests.read.own',
  'pickup_requests.read.assigned',
];
const TRANSFER_READ = ['transfers.read', 'transfers.read.assigned'];
const RETURN_READ = [
  'returns.read.all',
  'returns.read.own',
  'returns.create_request',
  'returns.create',
  'returns.update_status',
  'returns.manage',
];
const SUPPORT_READ = ['support.read.all', 'support.read.own', 'support.manage'];

export const menuItems = [
  {
    key: 'dashboard',
    labelKey: 'sidebar.dashboard',
    icon: 'ri-dashboard-2-line',
    href: '/',
    permissions: [],
  },
  {
    key: 'orders',
    labelKey: 'sidebar.orders',
    icon: 'ri-shopping-basket-2-line',
    href: '/orders',
    permissions: ORDER_READ,
  },
  {
    key: 'partner-orders',
    labelKey: 'sidebar.partner_orders',
    icon: 'ri-links-line',
    href: '/partner-orders',
    permissions: ['partners.read', 'partners.deliveries.manage'],
    // Drivers handle partner deliveries without holding any partner permission.
    visible: ({ canAny, isDriver }) =>
      isDriver || canAny(['partners.read', 'partners.deliveries.manage']),
  },
  {
    key: 'pickups',
    labelKey: 'sidebar.pickups',
    icon: 'ri-truck-line',
    href: '/pickup-requests',
    permissions: PICKUP_READ,
  },
  {
    key: 'transfers',
    labelKey: 'sidebar.transfers',
    icon: 'ri-route-line',
    href: '/transfers',
    permissions: TRANSFER_READ,
  },
  {
    key: 'returns',
    labelKey: 'sidebar.returns',
    icon: 'ri-arrow-go-back-line',
    href: '/returns',
    permissions: RETURN_READ,
    // The server already resolves the seller shortcut in canAccessReturnsModule().
    visible: ({ canAny, user }) => user?.can_view_returns === true || canAny(RETURN_READ),
  },
  {
    key: 'invoices',
    labelKey: 'sidebar.invoices',
    icon: 'ri-bill-line',
    permissions: ['invoices.read.all', 'invoices.read.own'],
    children: [
      {
        key: 'invoices-list',
        labelKey: 'sidebar.invoices',
        href: '/invoices',
        permissions: ['invoices.read.all', 'invoices.read.own'],
      },
      {
        key: 'invoices-pending',
        labelKey: 'sidebar.pending_billing',
        href: '/invoices/pending',
        permissions: ['invoices.generate', 'invoices.read.all'],
      },
    ],
  },
  {
    key: 'driver-billing',
    labelKey: 'sidebar.driver_billing',
    icon: 'ri-e-bike-2-line',
    permissions: ['driver_invoices.read.all'],
    children: [
      {
        key: 'driver-invoices-list',
        labelKey: 'sidebar.driver_invoices',
        href: '/driver-invoices',
        permissions: ['driver_invoices.read.all'],
      },
      {
        key: 'driver-invoices-pending',
        labelKey: 'sidebar.driver_pending_billing',
        href: '/driver-invoices/pending',
        permissions: ['driver_invoices.generate', 'driver_invoices.read.all'],
      },
      {
        key: 'driver-invoices-payments',
        labelKey: 'sidebar.driver_payments',
        href: '/driver-invoices/payments',
        permissions: ['driver_invoices.pay', 'driver_invoices.read.all'],
      },
    ],
  },
  {
    key: 'driver-finance',
    labelKey: 'sidebar.driver_finance',
    icon: 'ri-wallet-3-line',
    href: '/driver-finance',
    permissions: ['driver_invoices.read.own'],
    // The driver's own wallet; staff use the Driver Billing section instead.
    visible: ({ can }) =>
      can('driver_invoices.read.own') && !can('driver_invoices.read.all'),
  },
  {
    key: 'support',
    labelKey: 'sidebar.support',
    icon: 'ri-customer-service-2-line',
    href: '/support-tickets',
    permissions: SUPPORT_READ,
  },
  {
    key: 'delivery-zones',
    labelKey: 'sidebar.delivery_zones',
    icon: 'ri-map-pin-line',
    permissions: ['sectors.read', 'driver_zones.read'],
    children: [
      {
        key: 'sectors',
        labelKey: 'sidebar.sectors',
        href: '/sectors',
        permissions: ['sectors.read'],
      },
      {
        key: 'driver-zones',
        labelKey: 'sidebar.driver_zones',
        href: '/driver-zones',
        permissions: ['driver_zones.read'],
      },
    ],
  },
  {
    key: 'settings',
    labelKey: 'sidebar.settings.title',
    icon: 'ri-settings-3-line',
    footer: true,
    permissions: [],
    children: [
      {
        key: 'profile',
        labelKey: 'sidebar.settings.profile',
        route: 'profile.show',
        permissions: [],
      },
      {
        key: 'users',
        labelKey: 'sidebar.settings.users',
        href: '/users',
        permissions: ['users.read'],
      },
      {
        key: 'pending-sellers',
        labelKey: 'sidebar.settings.pending_sellers',
        route: 'admin.pending-users.index',
        permissions: ['users.read'],
      },
      {
        key: 'roles',
        labelKey: 'sidebar.settings.roles_permissions',
        href: '/roles',
        permissions: ['roles.read'],
      },
      {
        key: 'cities',
        labelKey: 'sidebar.settings.cities',
        href: '/cities',
        permissions: ['cities.read'],
      },
      {
        key: 'partners',
        labelKey: 'sidebar.settings.partners',
        href: '/partners',
        permissions: ['partners.read'],
      },
      {
        key: 'partner-assignments',
        labelKey: 'sidebar.settings.partner_assignments',
        route: 'partner-assignments.index',
        permissions: ['partners.update'],
      },
      {
        key: 'api-integrations',
        labelKey: 'sidebar.settings.api_integrations',
        route: 'api-integrations.index',
        permissions: ['partners.read'],
      },
    ],
  },
];

/**
 * Resolve one item against the current user's permissions.
 *
 * A parent that declares children is only shown when at least one child
 * survives filtering, which prevents empty collapse sections.
 */
function isVisible(item, context) {
  if (typeof item.visible === 'function' && !item.visible(context)) {
    return false;
  }

  const permissions = item.permissions ?? [];

  if (permissions.length > 0 && !context.canAny(permissions)) {
    return false;
  }

  return true;
}

/**
 * Filter the tree down to what the current user may see.
 *
 * @param {object} context result of `usePermissions()` flattened to plain values
 * @returns {Array} the visible menu items, children already pruned
 */
export function resolveMenuItems(context) {
  return menuItems.reduce((visible, item) => {
    if (!isVisible(item, context)) {
      return visible;
    }

    if (!item.children) {
      visible.push(item);

      return visible;
    }

    const children = item.children.filter((child) => isVisible(child, context));

    if (children.length > 0) {
      visible.push({ ...item, children });
    }

    return visible;
  }, []);
}
