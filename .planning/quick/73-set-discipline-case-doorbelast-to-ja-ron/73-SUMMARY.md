# Quick Task 73: Set discipline case doorbelast when invoice sent

## Summary
When an invoice is sent, linked discipline cases now have their `is_charged` field
set to `"rondo"`. The ACF field was changed from `true_false` to `select` with three
options to track the source of the charge.

## Cross-repo changes

### rondo-club
- `includes/class-rest-invoices.php` — After sending invoice, iterate line items and
  set `is_charged` to `"rondo"` on linked discipline cases
- `acf-json/group_discipline_case_fields.json` — Changed from `true_false` to `select`
  with options: "" (Nee), "sportlink" (Ja, Sportlink), "rondo" (Ja, Rondo)

### rondo-sync
- `steps/submit-rondo-club-discipline.js` — Send `"sportlink"` instead of boolean `true`

## Frontend compatibility
All frontend code uses truthy checks (`acf.is_charged ? 'Ja' : 'Nee'`) which work
correctly with both old boolean values and new string values.
