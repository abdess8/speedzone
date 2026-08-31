/**
 * Storefronts a vendor can plug into the platform.
 *
 * Declared client-side because the topbar shortcut renders on every page and
 * the list is static: shipping it through the Inertia props of every response
 * would cost bytes on each navigation to say the same four things.
 *
 * `status` is what the catalogue screen reads. It stays `soon` until the
 * matching connector actually exists — offering a "Connect" button that leads
 * nowhere is worse than saying the work is not done.
 */
export const ECOMMERCE_PLATFORMS = [
  {
    key: 'shopify',
    name: 'Shopify',
    icon: 'ri-shopping-bag-3-fill',
    color: '#95bf47',
    status: 'soon',
  },
  {
    key: 'youcan',
    name: 'YouCan',
    icon: 'ri-store-2-fill',
    color: '#6a4cff',
    status: 'soon',
  },
  {
    key: 'woocommerce',
    name: 'WooCommerce',
    icon: 'ri-shopping-cart-2-fill',
    color: '#7f54b3',
    status: 'soon',
  },
  {
    key: 'prestashop',
    name: 'PrestaShop',
    icon: 'ri-store-3-fill',
    color: '#df0067',
    status: 'soon',
  },
];

export function findPlatform(key) {
  return ECOMMERCE_PLATFORMS.find((platform) => platform.key === key) ?? null;
}
