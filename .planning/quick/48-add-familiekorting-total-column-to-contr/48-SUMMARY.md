---
phase: quick-48
plan: 01
subsystem: api, ui
tags: [fees, contributie, family-discount, aggregation]

requires:
  - phase: v21.0
    provides: fee calculation system with family_discount_amount in fee cache
provides:
  - family_discount aggregation in /fees/summary endpoint
  - Familiekorting column in ContributieOverzicht table
affects: [contributie, fee-summary]

tech-stack:
  added: []
  patterns: [negative-value display pattern for discount columns]

key-files:
  created: []
  modified:
    - includes/class-rest-api.php
    - src/pages/Contributie/ContributieOverzicht.jsx

key-decisions:
  - "Display family discount as negative values with minus prefix when > 0, otherwise show 0"

patterns-established:
  - "Discount columns: show '- EUR X' when value > 0, 'EUR 0,00' when zero"

duration: 4min
completed: 2026-02-10
---

# Quick Task 48: Add Familiekorting Total Column to Contributie Overzicht

**Family discount aggregation in /fees/summary API and new Familiekorting column between Basis totaal and Netto totaal in the Overzicht table**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-10T20:44:32Z
- **Completed:** 2026-02-10T20:48:32Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Added `family_discount` field to per-category aggregation in `/rondo/v1/fees/summary` endpoint
- Added Familiekorting column to the Contributie Overzicht table showing per-category and grand total family discounts
- Values display as negative amounts when > 0, making the Basis totaal - Familiekorting = Netto totaal relationship visible

## Task Commits

Each task was committed atomically:

1. **Task 1: Add family_discount aggregation to /fees/summary endpoint** - `8309cee7` (feat)
2. **Task 2: Add Familiekorting column to ContributieOverzicht table** - `f82d0882` (feat)

## Files Created/Modified
- `includes/class-rest-api.php` - Added family_discount to aggregate initialization, accumulation, and rounding in get_fee_summary
- `src/pages/Contributie/ContributieOverzicht.jsx` - Added Familiekorting column header, per-category cell, and grand total cell

## Decisions Made
- Display family discount as negative values with "- " prefix when amount > 0, show EUR 0,00 when zero
- Used `?? 0` null coalescing for backward compatibility with cached data that may not have family_discount_amount

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

---
*Quick Task: 48*
*Completed: 2026-02-10*
