---
phase: 197-frontend-updates
plan: 01
subsystem: ui, api
tags: [react, php, wordpress, invoices, filters]

# Dependency graph
requires:
  - phase: 196-bulk-invoice-creation
    provides: invoice_type ACF field and _installment_plan post meta established by bulk invoice creation
provides:
  - GET /rondo/v1/invoices accepts type (membership|discipline) and payment_plan (full|quarterly_3|monthly_8) query params
  - Invoice list response includes invoice_type and installment_plan fields
  - Facturen page has three filter dropdowns (status, type, payment plan)
  - Type badge column in Facturen table (desktop only)
affects: [197-02, any future invoice list consumers]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "URL-based filter composition via updateFilter(key, value) generic callback"
    - "meta_query initialized as [] then conditionally appended; AND relation added when multiple clauses"
    - "Legacy data inclusive filter: discipline type uses OR clause for null/empty invoice_type"

key-files:
  created: []
  modified:
    - includes/class-rest-invoices.php
    - src/pages/Finance/Facturen.jsx

key-decisions:
  - "Discipline type filter includes legacy invoices (null/empty invoice_type) via OR meta_query clause — existing invoices before invoice_type field backfill remain visible"
  - "URL param 'plan' maps to API param 'payment_plan' — keeps URL clean, avoids underscore in query string"
  - "Type column is hidden on mobile (hidden sm:table-cell) — consistent with date columns, avoids crowding small screens"
  - "updateFilter generic callback replaces single-purpose updateStatusFilter — DRY, handles all filter keys uniformly"
  - "meta_query initialized as empty array before all filter blocks, unsetting if empty — avoids WP_Query warnings on no filters"

patterns-established:
  - "Generic filter callback: updateFilter(key, value) deletes key if empty, sets if not, replaces URL state"
  - "PHP filter composition: initialize meta_query = [], append each filter, add relation=AND when count > 1, unset if empty"

# Metrics
duration: 3min
completed: 2026-02-19
---

# Phase 197 Plan 01: Facturen Filters Summary

**Invoice type and payment plan filters added to Facturen list — type/payment_plan query params in PHP API, three filter dropdowns (status/type/plan) in React UI, and a Type badge column visible on desktop**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-19T11:53:33Z
- **Completed:** 2026-02-19T11:57:23Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Backend `/rondo/v1/invoices` now accepts `type` and `payment_plan` query params with validation, composed with existing `status` and `person_id` filters via AND meta_query
- Legacy discipline invoices (null/empty invoice_type) correctly included when filtering for type=discipline via OR clause
- Invoice list response includes `invoice_type` and `installment_plan` fields in every row
- Facturen page filter bar extended from 1 dropdown to 3 (status, type, payment plan) using a generic URL-based `updateFilter` callback
- Type badge column added to table (desktop: Contributie=purple, Tuchtrecht=amber; mobile: hidden)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add type/payment_plan filters to invoice list API** - `7311b978` (feat)
2. **Task 2: Add type and payment plan filter dropdowns to Facturen list page** - `65e7ff70` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` - Added type/payment_plan route args, refactored meta_query composition in get_invoice_list, added invoice_type/installment_plan to format_invoice
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Finance/Facturen.jsx` - Added typeFilter/planFilter from URL params, generic updateFilter callback, two new filter dropdowns, Type column with colored badges

## Decisions Made
- Discipline type filter uses OR clause to include legacy invoices with null/empty invoice_type — ensures existing invoices remain visible before any backfill
- URL param named `plan` (not `payment_plan`) to keep URL clean; mapped to `payment_plan` in the API call
- Type column hidden on mobile (hidden sm:table-cell) — consistent with date columns, avoids crowding small screens
- Generic `updateFilter(key, value)` replaces single-purpose `updateStatusFilter` — DRY principle, handles all filter keys uniformly
- `meta_query` initialized as empty array before all filter blocks, unset if empty at the end — avoids WP_Query warnings

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Facturen filter foundation complete — types and plans filter correctly in both backend and frontend
- Production deployed at https://rondo.svawc.nl/
- Ready for Phase 197-02 (next plan in phase)

## Self-Check: PASSED

- includes/class-rest-invoices.php: FOUND
- src/pages/Finance/Facturen.jsx: FOUND
- .planning/phases/197-frontend-updates/197-01-SUMMARY.md: FOUND
- Commit 7311b978 (Task 1): FOUND
- Commit 65e7ff70 (Task 2): FOUND
- PHP syntax check: PASSED (no syntax errors)
- npm run lint: PASSED (0 warnings)
- npm run build: PASSED
- Deployed to production: PASSED

---
*Phase: 197-frontend-updates*
*Completed: 2026-02-19*
