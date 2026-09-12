/**
 * Storefronts a vendor can plug into the platform.
 *
 * Declared client-side because the topbar shortcut renders on every page and
 * the list is static: shipping it through the Inertia props of every response
 * would cost bytes on each navigation to say the same four things.
 *
 * `available` is what the shortcut reads. It stays false until the matching
 * connector actually exists — offering a "Connect" button that leads nowhere
 * is worse than saying the work is not done. Connection status for a given
 * shop lives on the catalogue screen, which loads it from the server.
 */
export const ECOMMERCE_PLATFORMS = [
  {
    key: 'youcan',
    name: 'YouCan',
    icon: 'ri-store-2-fill',
    color: '#6a4cff',
    available: true,
  },
  {
    key: 'shopify',
    name: 'Shopify',
    icon: 'ri-shopping-bag-3-fill',
    color: '#95bf47',
    available: false,
  },
  {
    key: 'woocommerce',
    name: 'WooCommerce',
    icon: 'ri-shopping-cart-2-fill',
    color: '#7f54b3',
    available: false,
  },
  {
    key: 'prestashop',
    name: 'PrestaShop',
    icon: 'ri-store-3-fill',
    color: '#df0067',
    available: false,
  },
];

export function findPlatform(key) {
  return ECOMMERCE_PLATFORMS.find((platform) => platform.key === key) ?? null;
}
