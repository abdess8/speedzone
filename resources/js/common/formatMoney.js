const moneyFormatter = new Intl.NumberFormat("fr-FR", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const roundedMoneyFormatter = new Intl.NumberFormat("fr-FR", {
  maximumFractionDigits: 0,
});

/**
 * Parse and normalize a monetary amount.
 *
 * @param {number|string|null|undefined} value
 * @returns {number}
 */
export function formatAmount(value) {
  const amount = Number(value);
  return Number.isFinite(amount) ? amount : 0;
}

/**
 * Format a monetary amount for display.
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatMoney(value) {
  return moneyFormatter.format(formatAmount(value));
}

/**
 * Format a monetary amount without centimes.
 *
 * Headline figures on a phone are read at a glance rather than reconciled, and
 * the two decimals cost horizontal space a 360 px screen does not have.
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatMoneyRounded(value) {
  return roundedMoneyFormatter.format(formatAmount(value));
}

/**
 * Format money or return a placeholder for empty values.
 *
 * @param {number|string|null|undefined} value
 * @param {string} empty
 * @returns {string}
 */
export function formatMoneyOrEmpty(value, empty = "—") {
  if (value == null || value === "") {
    return empty;
  }

  return formatMoney(value);
}
