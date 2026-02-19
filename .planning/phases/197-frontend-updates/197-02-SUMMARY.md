---
phase: 197-frontend-updates
plan: 02
subsystem: ui, api
tags: [react, php, wordpress, invoices, installments]

# Dependency graph
requires:
  - phase: 197-01
    provides: invoice_type and installment_plan fields established in API; Phase 197-01 filter foundation
  - phase: 195-installment-email-scheduler
    provides: flat numbered post meta for installments (_installment_N_amount, _installment_N_status, _installment_N_due_date, _installment_N_paid_at, _installment_N_sent_at)
provides:
  - GET /rondo/v1/invoices/{id} returns installment_plan, installment_count, installments[] with per-installment status
  - FactuurDetail shows Termijnen card with per-installment table (number, due date, amount, status badge, paid date)
  - InstallmentStatusBadge component with Openstaand/Verstuurd/Betaald/Verlopen states
  - Betaalplan field in Factuurgegevens card for multi-installment invoices
  - Version 28.1.0 deployed to production
affects: [any future invoice detail consumers]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Installment data lazily built in format_invoice_detail only — not in format_invoice (list view) to keep list responses lean"
    - "Empty installments guard: loop only runs when count >= 1 && plan && plan !== 'full'"

key-files:
  created: []
  modified:
    - includes/class-rest-invoices.php
    - src/pages/Finance/FactuurDetail.jsx
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "Installment data added to format_invoice_detail only (not format_invoice list) — keeps list API responses lean, detail page is the only consumer"
  - "Loop guard: count >= 1 && plan && plan !== 'full' — discipline (no plan meta) and full-plan invoices produce empty installments[]"
  - "InstallmentStatusBadge uses px-2 py-0.5 (smaller than StatusBadge py-1) — matches table cell density"
  - "planLabels in FactuurDetail matches Facturen.jsx planLabels — DRY concern deferred as they serve different display purposes"

patterns-established:
  - "Installment loop pattern: for n=1 to count, read _installment_N_* meta keys, compose amount as base + admin_fee"

# Metrics
duration: 3min
completed: 2026-02-19
---

# Phase 197 Plan 02: Installment Timeline Summary

**Installment timeline card on invoice detail page with per-installment status badges, amounts, and due dates; v28.1.0 deployed to production**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-19T11:59:45Z
- **Completed:** 2026-02-19T12:02:47Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Backend `format_invoice_detail()` now returns `installment_plan`, `installment_count`, and `installments[]` array for quarterly_3 and monthly_8 invoices
- Full-plan and discipline invoices return an empty `installments[]` array — no timeline section rendered
- FactuurDetail shows a "Termijnen" card with a 5-column table (termijn number, vervaldatum, bedrag, status, betaald op)
- InstallmentStatusBadge component renders Openstaand/Verstuurd/Betaald/Verlopen states with distinct colors
- "Betaalplan" field added to Factuurgegevens card showing plan name (3 termijnen / 8 termijnen) when applicable
- Version bumped to 28.1.0, changelog updated, deployed to production

## Task Commits

Each task was committed atomically:

1. **Task 1: Add installment data to invoice detail API and installment timeline to FactuurDetail** - `96be332b` (feat)
2. **Task 2: Version bump to 28.1.0, update changelog, deploy to production** - `e27dcc4b` (chore)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` - Added installment_plan, installment_count, installments[] to format_invoice_detail
- `/Users/joostdevalk/Code/rondo/rondo-club/src/pages/Finance/FactuurDetail.jsx` - Added installmentStatusColors/Labels, planLabels, InstallmentStatusBadge, Termijnen card, Betaalplan field
- `/Users/joostdevalk/Code/rondo/rondo-club/style.css` - Version bumped to 28.1.0
- `/Users/joostdevalk/Code/rondo/rondo-club/package.json` - Version bumped to 28.1.0
- `/Users/joostdevalk/Code/rondo/rondo-club/CHANGELOG.md` - v28.1.0 entry added

## Decisions Made
- Installment data added to `format_invoice_detail` only, not `format_invoice` (list) — list response stays lean, per-installment data only needed on detail page
- Loop guard `count >= 1 && plan && plan !== 'full'` ensures discipline invoices (no plan meta) and full-plan invoices produce empty arrays cleanly
- `InstallmentStatusBadge` uses smaller padding (`py-0.5`) than `StatusBadge` (`py-1`) to suit the table cell density

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 197 (both plans) complete — v28.1.0 deployed to production
- Facturen filters (type + payment_plan) and installment timeline fully operational
- Phase 197 is the final phase of v28.0 milestone — milestone complete

## Self-Check: PASSED

- includes/class-rest-invoices.php: FOUND
- src/pages/Finance/FactuurDetail.jsx: FOUND
- style.css: FOUND
- package.json: FOUND
- CHANGELOG.md: FOUND
- .planning/phases/197-frontend-updates/197-02-SUMMARY.md: FOUND
- Commit 96be332b (Task 1): FOUND
- Commit e27dcc4b (Task 2): FOUND
- PHP syntax check: PASSED (no syntax errors)
- npm run lint: PASSED (0 warnings)
- npm run build: PASSED
- Deployed to production: PASSED

---
*Phase: 197-frontend-updates*
*Completed: 2026-02-19*
