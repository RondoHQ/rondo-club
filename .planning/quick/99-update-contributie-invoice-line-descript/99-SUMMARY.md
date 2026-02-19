---
phase: quick-99
plan: 01
subsystem: payments
tags: [mollie, invoicing, contributie, membership-fees]

requires:
  - phase: quick-96
    provides: "Contributie invoice numbering with C prefix"
  - phase: quick-97
    provides: "Pro-rata and family discount line items on membership invoices"
provides:
  - "Invoice line items now show season year in main fee description"
  - "Pro-rata discount line explains why discount was applied"
affects: [bulk-invoice-creator, membership-invoicing]

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - includes/class-bulk-invoice-creator.php

key-decisions:
  - "Season injected from existing $season parameter — no additional lookup needed"
  - "Instapkorting explanation appended inline — no separate description field needed"

duration: 3min
completed: 2026-02-19
---

# Quick Task 99: Update Contributie Invoice Line Descriptions Summary

**Membership invoice line items now show season year ("Contributie 2025-2026 - Senioren") and pro-rata discount explanation ("Instapkorting (X%) - omdat je later in het seizoen start")**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-19T00:00:00Z
- **Completed:** 2026-02-19T00:03:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Main fee line description now includes season (e.g. "Contributie 2025-2026 - Senioren")
- Pro-rata discount line now explains the discount reason in plain Dutch
- Members reading their invoice immediately understand what season and why the discount was applied

## Task Commits

1. **Task 1: Update invoice line item descriptions** - `9307ff7c` (feat)

## Files Created/Modified
- `includes/class-bulk-invoice-creator.php` - Updated two description strings in `create_membership_invoice()`

## Decisions Made
- `$season` is already the second parameter of `create_membership_invoice()` (format: "2025-2026") — injected directly without additional lookup
- Explanation text appended to existing Instapkorting format string — no structural changes needed

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Invoice descriptions are now self-explanatory for members
- No blockers

---
*Phase: quick-99*
*Completed: 2026-02-19*
