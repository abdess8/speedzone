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
const STOCK_READ = ['stock.view', 'stock.receive_inbound', 'stock.admin_override'];
// A collector holds nothing else in this module, so the shipment list is the only
// entry he needs — and the only one the parent menu should appear for on his account.
const INBOUND_READ = [...STOCK_READ, 'stock.create_inbound', 'stock.collect_inbound'];

/**
 * Reverse logistics pipeline, in the order the parcel travels it. Mirrors
 * `App\Enums\ReturnStatus::pipeline()`; the values are the ones the returns
 * index filters on, so a mismatch shows up as an empty list rather than a
 * silent wrong result.
 */
const RETURN_STATUS_VIEWS = [
  { key: 'created', status: 'CREATED' },
  { key: 'received_at_hub', status: 'RECEIVED_AT_HUB' },
  { key: 'in_transit_to_depot', status: 'IN_TRANSIT_TO_DEPOT' },
  { key: 'arrived_vendor_hub', status: 'ARRIVED_VENDOR_HUB' },
  { key: 'in_delivery_to_vendor', status: 'IN_DELIVERY_TO_VENDOR' },
  { key: 'delivered_to_vendor', status: 'DELIVERED_TO_VENDOR' },
];

/** Naming a driver for the last mile, in bulk. */
const RETURN_DISPATCH = [
  'returns.manage',
  'returns.update_status',
  'returns.transition.to_in_delivery_to_vendor',
];

/** Sidebar children filtering the returns list down to one workflow step. */
const returnStatusChildren = () => [
  {
    key: 'returns-all',
    labelKey: 'sidebar.returns_views.all',
    href: '/returns',
    permissions: RETURN_READ,
  },
  {
    key: 'returns-hand-back',
    labelKey: 'sidebar.returns_views.hand_back',
    href: '/returns/hand-back',
    permissions: RETURN_DISPATCH,
  },
  ...RETURN_STATUS_VIEWS.map(({ key, status }) => ({
    key: `returns-${key}`,
    labelKey: `sidebar.returns_views.${key}`,
    href: `/returns?status=${status}`,
    permissions: RETURN_READ,
  })),
];

/** The seller shortcut is resolved server side in canAccessReturnsModule(). */
const canSeeReturns = ({ canAny, user }) =>
  user?.can_view_returns === true || canAny(RETURN_READ);

export const menuItems = [
  {
    key: 'dashboard',
    labelKey: 'sidebar.dashboard',
    icon: 'ri-dashboard-2-line',
    // Not "/": that path serves the public marketing site, for guests and signed-in
    // users alike. The application dashboard lives at /dashboard.
    href: '/dashboard',
    permissions: ['dashboard.view'],
  },
  {
    key: 'orders',
    labelKey: 'sidebar.orders',
    icon: 'ri-shopping-basket-2-line',
    permissions: ORDER_READ,
    // Shortcuts into the stages operators actually chase, rather than making
    // them re-enter a status filter on every visit. `status_group` is resolved
    // server side by OrderQueryService::STATUS_GROUPS, because "being picked up"
    // and "not delivered" each span more than one status.
    children: [
      {
        key: 'orders-all',
        labelKey: 'sidebar.orders_views.all',
        href: '/orders',
        permissions: ORDER_READ,
      },
      {
        key: 'orders-pickup',
        labelKey: 'sidebar.orders_views.pickup',
        href: '/orders?status_group=pickup',
        permissions: ORDER_READ,
      },
      {
        key: 'orders-delivery',
        labelKey: 'sidebar.orders_views.delivery',
        href: '/orders?status_group=delivery',
        permissions: ORDER_READ,
      },
      {
        key: 'orders-failed',
        labelKey: 'sidebar.orders_views.failed',
        href: '/orders?status_group=failed',
        permissions: ORDER_READ,
      },
      {
        key: 'orders-delivered',
        labelKey: 'sidebar.orders_views.delivered',
        href: '/orders?status_group=delivered',
        permissions: ORDER_READ,
      },
      {
        key: 'orders-import',
        labelKey: 'sidebar.orders_views.import',
        href: '/orders/import',
        permissions: ['orders.create'],
      },
    ],
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
    key: 'preparation',
    labelKey: 'sidebar.preparation',
    icon: 'ri-inbox-unarchive-line',
    href: '/orders/preparation',
    permissions: ['orders.transition.to_prepared'],
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
    permissions: RETURN_READ,
    visible: canSeeReturns,
    children: returnStatusChildren(),
  },
  {
    key: 'stock',
    labelKey: 'sidebar.stock',
    icon: 'ri-archive-2-line',
    permissions: INBOUND_READ,
    children: [
      {
        key: 'stock-products',
        labelKey: 'sidebar.stock_views.products',
        href: '/products',
        permissions: STOCK_READ,
      },
      {
        key: 'stock-import',
        labelKey: 'sidebar.stock_views.import',
        href: '/products/import',
        permissions: ['stock.create_product'],
      },
      {
        key: 'stock-inventory',
        labelKey: 'sidebar.stock_views.inventory',
        href: '/stock/inventory',
        permissions: ['stock.view', 'stock.admin_override'],
      },
      {
        key: 'stock-receptions',
        labelKey: 'sidebar.stock_views.receptions',
        href: '/stock-receptions',
        permissions: INBOUND_READ,
      },
      {
        key: 'stock-movements',
        labelKey: 'sidebar.stock_views.movements',
        href: '/stock/movements',
        permissions: ['stock.admin_override'],
      },
    ],
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
    key: 'my-shop',
    labelKey: 'sidebar.my_shop',
    icon: 'ri-store-2-line',
    permissions: ['stores.read', 'team.read'],
    children: [
      {
        key: 'stores',
        labelKey: 'sidebar.stores',
        href: '/stores',
        permissions: ['stores.read'],
      },
      {
        key: 'team',
        labelKey: 'sidebar.team',
        href: '/team',
        permissions: ['team.read'],
      },
      {
        key: 'team-roles',
        labelKey: 'sidebar.team_roles',
        href: '/team/roles',
        permissions: ['team_roles.manage'],
      },
    ],
  },
  {
    key: 'delivery-zones',
    labelKey: 'sidebar.delivery_zones',
    icon: 'ri-map-pin-line',
    permissions: ['sectors.read', 'driver_zones.read'],
    children: [
      {
        key: 'cities',
        labelKey: 'sidebar.settings.cities',
        href: '/cities',
        permissions: ['cities.read'],
      },
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
    // Everything that administers *people*: the accounts themselves, the ones
    // still waiting to be let in, and the B2B partners with their assignments.
    // Configuration knobs live in the topbar gear instead, which is why this
    // group replaced the old Settings menu rather than sitting next to it.
    key: 'user-admin',
    labelKey: 'sidebar.user_admin',
    icon: 'ri-team-line',
    footer: true,
    permissions: [],
    children: [
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
    ],
  },
  {
    key: 'help-center',
    labelKey: 'sidebar.help_center',
    icon: 'ri-book-open-line',
    footer: true,
    // All three document rules the reader already lives under, and the guide
    // catalogue filters itself per role.
    permissions: [],
    children: [
      {
        key: 'help-partnership',
        labelKey: 'sidebar.help_views.partnership',
        route: 'help.partnership',
        permissions: [],
      },
      {
        key: 'help-processes',
        labelKey: 'sidebar.help_views.processes',
        route: 'help.processes',
        permissions: [],
      },
      {
        key: 'guides',
        labelKey: 'sidebar.guides',
        href: '/guides',
        permissions: [],
      },
    ],
  },
];

/**
 * Sidebar section captions, Slack-style.
 *
 * `keys` lists the top-level entries that belong under each caption; anything
 * not claimed by a section falls through to the last one. A caption disappears
 * automatically when every entry it groups is hidden by permissions.
 */
export const menuSections = [
  { key: 'shortcuts', labelKey: 'sidebar.sections.shortcuts', keys: ['dashboard', 'orders'] },
  {
    key: 'operations',
    labelKey: 'sidebar.sections.operations',
    keys: ['partner-orders', 'preparation', 'pickups', 'transfers', 'returns', 'stock'],
  },
  {
    key: 'finance',
    labelKey: 'sidebar.sections.finance',
    keys: ['invoices', 'driver-billing', 'driver-finance'],
  },
  { key: 'workspace', labelKey: 'sidebar.sections.workspace', keys: ['support', 'delivery-zones'] },
];

/**
 * Mobile bottom navigation bar.
 *
 * Capped at five tabs — past that the targets stop being thumb-sized — so the
 * entries that do not fit are reached through the `overflow` tab, which lists
 * whatever is left of the sidebar tree. Tabs with `children` open a sheet rather
 * than navigating, because "Operations" is a group and not a destination.
 */
export const mobileTabs = [
  {
    key: 'home',
    labelKey: 'sidebar.bottom_nav.home',
    icon: 'ri-home-5-line',
    activeIcon: 'ri-home-5-fill',
    href: '/dashboard',
    permissions: ['dashboard.view'],
  },
  {
    key: 'orders',
    labelKey: 'sidebar.bottom_nav.orders',
    icon: 'ri-shopping-basket-2-line',
    activeIcon: 'ri-shopping-basket-2-fill',
    href: '/orders',
    permissions: ORDER_READ,
  },
  {
    key: 'operations',
    labelKey: 'sidebar.bottom_nav.operations',
    icon: 'ri-truck-line',
    activeIcon: 'ri-truck-fill',
    permissions: [
      ...PICKUP_READ,
      ...TRANSFER_READ,
      ...RETURN_READ,
      ...INBOUND_READ,
      'orders.transition.to_prepared',
    ],
    children: [
      {
        // On the bench itself, phone in hand: this is where the QR scanner lives.
        key: 'preparation',
        labelKey: 'sidebar.preparation',
        icon: 'ri-inbox-unarchive-line',
        href: '/orders/preparation',
        permissions: ['orders.transition.to_prepared'],
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
        permissions: RETURN_READ,
        visible: canSeeReturns,
        children: returnStatusChildren(),
      },
      {
        key: 'stock',
        labelKey: 'sidebar.stock',
        icon: 'ri-archive-2-line',
        permissions: INBOUND_READ,
        children: [
          {
            key: 'stock-products',
            labelKey: 'sidebar.stock_views.products',
            href: '/products',
            permissions: STOCK_READ,
          },
          {
            key: 'stock-inventory',
            labelKey: 'sidebar.stock_views.inventory',
            href: '/stock/inventory',
            permissions: ['stock.view', 'stock.admin_override'],
          },
          {
            key: 'stock-receptions',
            labelKey: 'sidebar.stock_views.receptions',
            href: '/stock-receptions',
            permissions: INBOUND_READ,
          },
        ],
      },
    ],
  },
  {
    key: 'wallet',
    labelKey: 'sidebar.bottom_nav.wallet',
    icon: 'ri-wallet-3-line',
    activeIcon: 'ri-wallet-3-fill',
    permissions: [
      'driver_invoices.read.own',
      'driver_invoices.read.all',
      'invoices.read.own',
      'invoices.read.all',
    ],
    /**
     * "My cash" means a different screen per role: the driver's own wallet, the
     * back-office driver ledger, or the seller's invoices.
     */
    resolve: ({ can }) => {
      if (can('driver_invoices.read.own') && !can('driver_invoices.read.all')) {
        return '/driver-finance';
      }

      if (can('driver_invoices.read.all')) {
        return '/driver-invoices';
      }

      return can('invoices.read.own') || can('invoices.read.all') ? '/invoices' : null;
    },
  },
  {
    key: 'more',
    labelKey: 'sidebar.bottom_nav.more',
    icon: 'ri-menu-line',
    activeIcon: 'ri-menu-line',
    permissions: [],
    overflow: true,
  },
];

/** Sidebar entries already reachable from a tab, so "More" does not repeat them. */
const TABBED_MENU_KEYS = ['dashboard', 'orders', 'pickups', 'transfers', 'returns'];

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
 * Prune a branch against the permission context, at any depth.
 *
 * Returns null when the entry itself is hidden, or when it is a group left
 * without a single reachable destination — a collapse or a sheet with nothing
 * inside is a dead end the user can still tap.
 */
function pruneItem(item, context) {
  if (!isVisible(item, context)) {
    return null;
  }

  if (!item.children) {
    return item;
  }

  const children = item.children
    .map((child) => pruneItem(child, context))
    .filter(Boolean);

  return children.length > 0 ? { ...item, children } : null;
}

/**
 * Filter the tree down to what the current user may see.
 *
 * @param {object} context result of `usePermissions()` flattened to plain values
 * @returns {Array} the visible menu items, children already pruned
 */
export function resolveMenuItems(context) {
  return menuItems.map((item) => pruneItem(item, context)).filter(Boolean);
}

/**
 * Group the visible sidebar entries under their section caption.
 *
 * Entries no section claims land in the last one rather than vanishing, so
 * adding a menu item without touching `menuSections` still renders it.
 *
 * @returns {{key: string, labelKey: string, items: Array}[]} sections, empty ones removed
 */
export function resolveMenuSections(context) {
  const items = resolveMenuItems(context);
  const claimed = new Set(menuSections.flatMap((section) => section.keys));

  return menuSections
    .map((section, index) => {
      const isLast = index === menuSections.length - 1;

      return {
        ...section,
        items: items.filter(
          (item) =>
            !item.footer &&
            (section.keys.includes(item.key) || (isLast && !claimed.has(item.key)))
        ),
      };
    })
    .filter((section) => section.items.length > 0);
}

/** Entries pinned to the bottom of the sidebar, e.g. Settings. */
export function resolveFooterItems(context) {
  return resolveMenuItems(context).filter((item) => item.footer);
}

/**
 * Resolve the bottom navigation bar for the current user.
 *
 * A tab is dropped when its permissions do not match, when `resolve()` finds no
 * destination for this role, or when it is a group whose every child is hidden.
 * The overflow tab receives whatever sidebar entries no tab already covers, and
 * disappears when that list is empty.
 */
export function resolveMobileTabs(context) {
  return mobileTabs.reduce((visible, tab) => {
    if (!isVisible(tab, context)) {
      return visible;
    }

    if (tab.overflow) {
      const items = resolveMenuItems(context).filter(
        (item) => !TABBED_MENU_KEYS.includes(item.key)
      );

      if (items.length > 0) {
        visible.push({ ...tab, children: items });
      }

      return visible;
    }

    if (tab.children) {
      const pruned = pruneItem(tab, context);

      if (pruned) {
        visible.push(pruned);
      }

      return visible;
    }

    const href = tab.resolve ? tab.resolve(context) : tab.href;

    if (href) {
      visible.push({ ...tab, href });
    }

    return visible;
  }, []);
}
