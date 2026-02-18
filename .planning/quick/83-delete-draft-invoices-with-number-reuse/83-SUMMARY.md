---
phase: quick-83
plan: 01
subsystem: api, ui
tags: [rest-api, invoices, react, tanstack-query, delete]

# Dependency graph
requires:
  - phase: 180
    provides: "Invoice CRUD REST API and React invoice pages"
provides:
  - "DELETE /rondo/v1/invoices/{id} endpoint for draft invoices"
  - "useDeleteInvoice React hook"
  - "Red delete button on FactuurDetail for draft invoices"
affects: [invoices, discipline-cases]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Destructive action button with red styling and confirmation dialog"]

key-files:
  modified:
    - "includes/class-rest-invoices.php"
    - "src/api/client.js"
    - "src/hooks/useInvoices.js"
    - "src/pages/Finance/FactuurDetail.jsx"

key-decisions:
  - "Draft-only guard: only rondo_draft invoices can be deleted (400 for others)"
  - "Force delete (skip trash) so invoice number is freed for generate_next() reuse"
  - "Reset linked discipline cases is_charged to empty on deletion"

patterns-established:
  - "Red destructive button: border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 styling"

# Metrics
duration: 178s
completed: 2026-02-18
---

# Quick Task 83: Delete Draft Invoices with Number Reuse Summary

**DELETE endpoint for draft invoices with file cleanup, discipline case reset, number reuse, and red UI delete button**

## Performance

- **Duration:** 178s (2m 58s)
- **Started:** 2026-02-18T19:29:53Z
- **Completed:** 2026-02-18T19:32:51Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Backend DELETE endpoint with draft-only guard, PDF/QR cleanup, payment data cleanup, and discipline case reset
- Frontend delete button with confirmation dialog, spinner, and post-delete navigation to invoices list
- Invoice number automatically becomes available for reuse via force delete (skip trash)

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend DELETE endpoint and API client method** - `2f509ee3` (feat)
2. **Task 2: React hook and delete button in FactuurDetail** - `7c69c269` (feat)

## Files Created/Modified
- `includes/class-rest-invoices.php` - DELETE route registration and delete_invoice() method with full cleanup
- `src/api/client.js` - deleteInvoice API client method
- `src/hooks/useInvoices.js` - useDeleteInvoice mutation hook with query invalidation
- `src/pages/Finance/FactuurDetail.jsx` - Red delete button for draft invoices with confirmation dialog

## Decisions Made
- Draft-only guard: only rondo_draft invoices can be deleted (400 error for non-draft)
- Force delete (skip trash) so invoice number is freed for generate_next() reuse
- Reset linked discipline cases is_charged to empty on deletion (same pattern as reset_payment_state)
- Red destructive styling matches the orange reset button pattern but in red

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

---
*Quick Task: 83-delete-draft-invoices-with-number-reuse*
*Completed: 2026-02-18*
