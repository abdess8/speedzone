const moneyFormatter = new Intl.NumberFormat("fr-FR", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
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
