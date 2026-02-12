---
phase: quick-64
plan: 01
subsystem: contributie-fees
tags: [contributie, membership-fees, transparency, ui-enhancement]
dependency_graph:
  requires: [membership-fees-system]
  provides: [transparent-fee-breakdown]
  affects: [contributie-overzicht-ui]
tech_stack:
  added: []
  patterns: [fee-calculation-transparency]
key_files:
  created: []
  modified:
    - includes/class-rest-api.php
    - src/pages/Contributie/ContributieOverzicht.jsx
decisions:
  - "Display full fee calculation chain in overzicht table for transparency"
  - "Show pro-rata as negative value (like family discount) for consistency"
  - "Forecast mode shows zero pro-rata since it assumes full-season membership"
metrics:
  duration_seconds: 165
  tasks_completed: 2
  files_modified: 2
  commits: 2
  completed_date: 2026-02-12
---

# Quick Task 64: Using the new membership fees system, rework contributie overzicht

**One-liner:** Transparent fee breakdown showing full calculation chain (Basis -> Familiekorting -> Na korting -> Pro-rata -> Netto) in contributie overzicht table.

## What Was Built

Reworked the Contributie Overzicht tab to display the complete fee calculation chain, adding two intermediate columns to show how fees flow from base amount through discounts to final amount.

**Before:** Table showed 5 columns (Categorie, Leden, Basis totaal, Familiekorting, Netto totaal) but jumped directly from family discount to net total, hiding the pro-rata reduction step.

**After:** Table shows 7 columns exposing all calculation steps:
1. Categorie
2. Leden
3. Basis totaal (base fee)
4. Familiekorting (family discount, shown as negative)
5. **Na korting** (fee after discount - NEW)
6. **Pro-rata** (pro-rata reduction, shown as negative - NEW)
7. Netto totaal (final fee, bold)

Mathematical relationships are now transparent:
- `Basis totaal - Familiekorting = Na korting`
- `Na korting - Pro-rata = Netto totaal`

## Implementation Details

### Backend (PHP)

**File:** `includes/class-rest-api.php`

Updated `get_fee_summary()` method to aggregate two additional fields per category:

1. **`fee_after_discount`**: Base fee minus family discount
   - Forecast path: Equals final_fee (no pro-rata in forecasts)
   - Non-forecast path: Retrieved from cached fee data with fallback calculation for older caches

2. **`prorata_amount`**: The pro-rata reduction applied
   - Forecast path: Always 0 (forecast assumes full-season membership)
   - Non-forecast path: Computed as `fee_after_discount - final_fee`

Both fields are rounded to 2 decimal places to avoid floating point artifacts.

### Frontend (React)

**File:** `src/pages/Contributie/ContributieOverzicht.jsx`

1. **Grand totals reducer:** Added `feeAfterDiscount` and `prorataAmount` accumulators
2. **Table headers:** Added "Na korting" and "Pro-rata" column headers
3. **Data rows:** Display `agg.fee_after_discount` and `agg.prorata_amount`
4. **Footer row:** Show grand totals for new columns
5. **Formatting:** Pro-rata displayed as negative value (`- EUR X.XX`) when > 0, matching family discount pattern

**Styling:** Intermediate columns use `text-gray-500 dark:text-gray-400`, only final "Netto totaal" column is bold for emphasis.

## Tasks Completed

| Task | Description | Commit | Files Modified |
|------|-------------|--------|----------------|
| 1 | Add fee_after_discount and prorata_amount to get_fee_summary() endpoint | 6e20cf45 | includes/class-rest-api.php |
| 2 | Update ContributieOverzicht table to show full calculation chain | 878844cc | src/pages/Contributie/ContributieOverzicht.jsx |

## Deviations from Plan

None - plan executed exactly as written.

## Verification Steps

1. Navigate to /contributie -> Overzicht tab
2. Verify 7-column table renders with all headers
3. Math check: For any row, `Basis totaal - Familiekorting = Na korting` and `Na korting - Pro-rata = Netto totaal`
4. Toggle to forecast mode - Pro-rata column should show EUR 0,00 for all categories
5. Grand totals row sums correctly across all categories

## Success Criteria

- [x] Full fee calculation chain visible in overzicht table (5 financial columns)
- [x] Mathematical relationships hold: base - discount = after_discount, after_discount - prorata = netto
- [x] Forecast mode works correctly (pro-rata is zero)
- [x] No build or lint regressions
- [x] Deployed to production

## Self-Check: PASSED

**Created files:** None (enhancement to existing feature)

**Modified files:**
- [x] includes/class-rest-api.php - FOUND
- [x] src/pages/Contributie/ContributieOverzicht.jsx - FOUND

**Commits:**
- [x] 6e20cf45 - FOUND: "feat(quick-64): add fee_after_discount and prorata_amount to fee summary endpoint"
- [x] 878844cc - FOUND: "feat(quick-64): show full fee calculation chain in contributie overzicht"

**Build:**
- [x] `npm run build` - PASSED (no errors)
- [x] `npm run lint` - PASSED (no new warnings/errors introduced)

**Deployment:**
- [x] Deployed to production at https://rondo.svawc.nl/

## Impact

**User experience:** Administrators can now see exactly how membership fees are calculated, understanding the contribution of each discount type (family discount vs. pro-rata reduction) to the final amount.

**Transparency:** The complete calculation chain is visible, making fee audits and explanations to members straightforward.

**Data integrity:** Mathematical relationships are exposed in the UI, allowing visual verification that calculations are correct.

## Technical Notes

**Cache compatibility:** The implementation includes a fallback calculation for `fee_after_discount` to support older cached fee records that predate this field (added in `calculate_full_fee` method, line 1702 of class-membership-fees.php).

**Forecast behavior:** Forecast mode assumes full-season membership (100% pro-rata), so `fee_after_discount` equals `final_fee` and `prorata_amount` is zero. This correctly reflects that forecasts predict next season's fees without mid-season adjustments.

**Display convention:** Pro-rata is shown as a negative value (reduction) to match the family discount column formatting, creating visual consistency for all discount/reduction columns.
