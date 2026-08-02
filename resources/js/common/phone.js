/**
 * Phone number helpers for the field-agent contact shortcuts.
 *
 * Customer numbers are captured as the seller typed them — "06 12 34 56 78",
 * "+212612345678", "0612-345-678" — which `tel:` tolerates but `wa.me` does not:
 * WhatsApp only accepts bare international digits.
 */

/** Morocco. Numbers are stored without a country code far more often than with one. */
const DEFAULT_COUNTRY_CODE = '212';

/**
 * Strip a number down to digits, dropping any international prefix notation.
 *
 * @param {string|null|undefined} phone
 * @returns {string} digits only, possibly empty
 */
function digitsOf(phone) {
  return String(phone ?? '').replace(/\D/g, '');
}

/**
 * Convert a number to the bare international form `wa.me` expects.
 *
 * @param {string|null|undefined} phone
 * @param {string} countryCode dialling code assumed for national numbers
 * @returns {string|null} e.g. `212612345678`, or null when unusable
 */
export function toInternationalDigits(phone, countryCode = DEFAULT_COUNTRY_CODE) {
  let digits = digitsOf(phone);

  if (digits === '') {
    return null;
  }

  // "00212…" and "+212…" both reduce to "00212…"/"212…" once punctuation is gone.
  if (digits.startsWith('00')) {
    digits = digits.slice(2);
  }

  if (digits.startsWith(countryCode)) {
    return digits;
  }

  // National form: the trunk "0" is replaced by the dialling code, never kept.
  if (digits.startsWith('0')) {
    return countryCode + digits.slice(1);
  }

  return digits;
}

/**
 * Build a WhatsApp deep link with a pre-filled message.
 *
 * @param {string|null|undefined} phone
 * @param {string} message
 * @param {string} countryCode
 * @returns {string|null} null when the number cannot be dialled
 */
export function whatsAppUrl(phone, message = '', countryCode = DEFAULT_COUNTRY_CODE) {
  const digits = toInternationalDigits(phone, countryCode);

  if (!digits) {
    return null;
  }

  const query = message ? `?text=${encodeURIComponent(message)}` : '';

  return `https://wa.me/${digits}${query}`;
}

/**
 * Build a `tel:` link.
 *
 * Kept in the local format the recipient recognises rather than normalised,
 * since dialers handle national numbers fine and drivers read the number aloud.
 *
 * @param {string|null|undefined} phone
 * @returns {string|null}
 */
export function telUrl(phone) {
  const digits = digitsOf(phone);

  return digits === '' ? null : `tel:${String(phone).replace(/[^\d+]/g, '')}`;
}
