# Quick Task 86: Add pro-rata and family discount columns to Nog te factureren page

## Result: COMPLETE

## Changes

### `src/pages/Contributie/NogTeFactureren.jsx`
- Added `formatPercentage` import
- Added three columns between Categorie and Bedrag: **Basis** (base fee), **Gezin** (family discount %), **Pro-rata** (pro-rata %)
- Family discount shows green `-X%` or gray dash; pro-rata shows amber `X%` or gray `100%`
- Pro-rata rows get subtle amber background tint (matching ContributieList pattern)
- Added sortable headers for all three new columns
- Added numeric sort cases for `base_fee`, `family_discount_rate`, `prorata_percentage`
- Footer shows base fee total and final fee total with appropriate spacing

## Commit
`817c57f2` — feat: add base fee, family discount and pro-rata columns to Nog te factureren
