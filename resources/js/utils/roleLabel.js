const VENDOR_PREFIX = 'vendor.';

/**
 * Human readable name for a role.
 *
 * Mirrors App\Support\RoleLabel. Vendor roles carry a namespaced internal name
 * (`vendor.8.preparateur-colis`) that has no translation entry, and whose dots
 * would make vue-i18n look for a nested key — translating it blindly is what
 * used to print the raw name in the topbar and the user table.
 *
 * @param {string|{name?: string, label?: string}|null} role name or role object
 * @param {(key: string) => string} t the i18n translate function
 */
export function roleLabel(role, t) {
  if (!role) {
    return '';
  }

  if (typeof role === 'object') {
    if (role.label) {
      return role.label;
    }

    return roleLabel(role.name, t);
  }

  if (role.startsWith(VENDOR_PREFIX)) {
    return humanise(role.slice(role.lastIndexOf('.') + 1));
  }

  const key = `roles.${role}`;
  const translated = t(key);

  return translated !== key ? translated : role;
}

/** `preparateur-colis` → `Preparateur Colis`, for a role whose label is gone. */
function humanise(slug) {
  return slug
    .split(/[-_]/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}
