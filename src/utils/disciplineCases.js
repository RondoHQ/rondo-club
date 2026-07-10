/**
 * Returns true when a discipline case is not applicable for charging:
 * zero administrative fee AND not charged.
 * @param {Object} acf - ACF fields of a discipline case
 * @returns {boolean}
 */
export function isDoorbelastNVT(acf) {
  return !acf.is_charged && (parseFloat(acf.administrative_fee) || 0) === 0;
}

/**
 * Returns true when a discipline case is marked as charging exception.
 * @param {Object} acf - ACF fields of a discipline case
 * @returns {boolean}
 */
export function isDoorbelastException(acf) {
  return acf?.is_charged === 'exception';
}

/**
 * Return the user-facing charging status for a discipline case.
 * Shared by the table and exports so both representations stay aligned.
 * @param {Object} acf - ACF fields of a discipline case
 * @returns {string}
 */
export function getDoorbelastLabel(acf = {}) {
  if (isDoorbelastException(acf)) return 'Uitzondering';
  if (isDoorbelastNVT(acf)) return 'n.v.t.';
  if (acf.is_charged === 'sportlink') return 'Ja, Sportlink';
  if (acf.is_charged === 'rondo') return 'Ja, Rondo';
  if (acf.is_charged) return 'Ja';
  return 'Nee';
}
