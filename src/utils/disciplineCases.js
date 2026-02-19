/**
 * Returns true when a discipline case is not applicable for charging:
 * zero administrative fee AND not charged.
 * @param {Object} acf - ACF fields of a discipline case
 * @returns {boolean}
 */
export function isDoorbelastNVT(acf) {
  return !acf.is_charged && (parseFloat(acf.administrative_fee) || 0) === 0;
}
