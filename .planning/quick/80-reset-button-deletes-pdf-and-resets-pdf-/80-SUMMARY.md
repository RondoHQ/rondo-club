---
phase: quick-80
plan: 01
subsystem: api
tags: [php, rest-api, invoicing, pdf]

# Dependency graph
requires:
  - phase: quick-76
    provides: "reset_payment_state endpoint and UI"
provides:
  - "clear_pdf() method for deleting invoice PDFs from disk"
  - "Full invoice reset: payment data, PDF, QR code, sent/due dates"
affects: [invoicing, finance]

# Tech tracking
tech-stack:
  added: []
  patterns: ["clear_pdf follows clear_qr_code pattern for file cleanup"]

key-files:
  created: []
  modified:
    - "includes/class-rest-invoices.php"
    - "src/pages/Finance/FactuurDetail.jsx"

key-decisions:
  - "Follow clear_qr_code() pattern for clear_pdf() to maintain consistency"
  - "Delete both ACF field and post meta for sent_date/due_date to cover both read paths"

patterns-established:
  - "File cleanup methods (clear_qr_code, clear_pdf) follow identical pattern: read ACF field, build full path, unlink if exists, clear field"

# Metrics
duration: 94s
completed: 2026-02-18
---

# Quick 80: Reset Button Deletes PDF and Resets PDF Summary

**Reset button now fully clears invoice to draft state: deletes PDF from disk, clears pdf_path/sent_date/due_date fields**

## Performance

- **Duration:** 94s
- **Started:** 2026-02-18T19:02:08Z
- **Completed:** 2026-02-18T19:03:42Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments
- Added `clear_pdf()` private method following the same pattern as `clear_qr_code()`
- Extended `reset_payment_state()` to call `clear_pdf()` and clear sent_date/due_date
- Updated confirmation dialog text to inform users that PDF will also be deleted

## Task Commits

Each task was committed atomically:

1. **Task 1: Add PDF deletion and date clearing to reset_payment_state** - `a5b685ae` (feat)

## Files Created/Modified
- `includes/class-rest-invoices.php` - Added `clear_pdf()` method, extended `reset_payment_state()` with PDF cleanup and date clearing
- `src/pages/Finance/FactuurDetail.jsx` - Updated confirmation dialog to mention PDF deletion

## Decisions Made
- Followed `clear_qr_code()` pattern exactly for `clear_pdf()` to maintain codebase consistency
- Clearing both ACF fields (`update_field`) and post meta (`delete_post_meta`) for sent_date/due_date because `format_invoice` reads them via `get_post_meta`

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Invoice reset is now fully comprehensive: clears payment data, QR code, PDF, and sending dates
- Ready for production testing of the full reset flow

## Self-Check: PASSED

- FOUND: includes/class-rest-invoices.php
- FOUND: src/pages/Finance/FactuurDetail.jsx
- FOUND: commit a5b685ae

---
*Phase: quick-80*
*Completed: 2026-02-18*
