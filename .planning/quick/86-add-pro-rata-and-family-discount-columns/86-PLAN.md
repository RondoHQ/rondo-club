# Quick Task 86: Add pro-rata and family discount columns to Nog te factureren page

## Goal
Show base fee, family discount, and pro-rata percentage columns on the "Nog te factureren" tab so users see the full fee breakdown, not just the final amount.

## Tasks

### Task 1: Add columns to NogTeFactureren.jsx
**File:** `src/pages/Contributie/NogTeFactureren.jsx`

Update the table to show these columns (matching ContributieList patterns):

| Voornaam | Achternaam | Categorie | Basis | Gezin | Pro-rata | Bedrag | Status | Actie |

Changes:
- **FeeRow:** Add three cells between Categorie and Bedrag:
  - Basis (right-aligned, `formatCurrency(member.base_fee, 2)`)
  - Gezin (right-aligned, green text `-formatPercentage(rate)` or gray dash)
  - Pro-rata (right-aligned, amber text `formatPercentage(pct)` or gray `100%`)
- **Table header:** Add three `SortableHeader` columns for `base_fee`, `family_discount_rate`, `prorata_percentage`
- **Sort logic:** Add `base_fee`, `family_discount_rate`, `prorata_percentage` numeric cases
- **Footer:** Add base fee total cell, two empty spacer cells, keep final fee total
- **Imports:** Add `formatPercentage` to the formatters import

## Verification
- `npm run build` passes
- `npm run lint` passes
- Deploy to production
