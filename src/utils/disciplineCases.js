/**
 * Returns true when a discipline case is not applicable for charging:
 * zero administrative fee AND not charged.
 * @param {Object} fields - canonical fields of a discipline case
 * @returns {boolean}
 */
export function isDoorbelastNVT(fields) {
  return !fields.is_charged && (parseFloat(fields.administrative_fee) || 0) === 0;
}

/**
 * Returns true when a discipline case is marked as charging exception.
 * @param {Object} fields - canonical fields of a discipline case
 * @returns {boolean}
 */
export function isDoorbelastException(fields) {
  return fields?.is_charged === 'exception';
}

/**
 * Return the user-facing charging status for a discipline case.
 * Shared by the table and exports so both representations stay aligned.
 * @param {Object} fields - canonical fields of a discipline case
 * @returns {string}
 */
export function getDoorbelastLabel(fields = {}) {
  if (isDoorbelastException(fields)) return 'Uitzondering';
  if (isDoorbelastNVT(fields)) return 'n.v.t.';
  if (fields.is_charged === 'sportlink') return 'Ja, Sportlink';
  if (fields.is_charged === 'rondo') return 'Ja, Rondo';
  if (fields.is_charged) return 'Ja';
  return 'Nee';
}
