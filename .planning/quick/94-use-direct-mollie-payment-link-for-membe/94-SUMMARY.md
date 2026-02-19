---
phase: quick-94
plan: 01
subsystem: payments
tags: [mollie, invoices, installments, php, betaling]

# Dependency graph
requires:
  - phase: quick-93
    provides: toggle_installments REST endpoint and _disable_installments meta
provides:
  - Conditional token generation in BulkInvoiceCreator — skip betaling page for installment-disabled invoices
  - Token lifecycle management in toggle_installments — clear/generate token on disable/enable
affects: [betaling-page, bulk-invoice-creator, rest-invoices, membership-invoices]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Conditional token generation: check meta state before calling generate_token() to avoid unnecessary betaling page"
    - "Token lifecycle: disabling installments clears token+payment_link; re-enabling generates fresh token"

key-files:
  created: []
  modified:
    - includes/class-bulk-invoice-creator.php
    - includes/class-rest-invoices.php

key-decisions:
  - "Move _disable_installments write BEFORE generate_token conditional — ensures meta is set when conditional is evaluated"
  - "Disabling installments via toggle clears both _payment_token meta and payment_link ACF field — both must be cleared for send_invoice() to use direct Mollie link"
  - "Add PublicPaymentPage use import to class-rest-invoices.php — preferred over fully-qualified class name for consistency"

patterns-established:
  - "Token lifecycle pattern: any code that sets _disable_installments should also manage the payment token accordingly"

# Metrics
duration: 5min
completed: 2026-02-19
---

# Quick Task 94: Use Direct Mollie Payment Link for Membership Invoices Summary

**Membership invoices with installments disabled skip the betaling page entirely — bulk creation now omits token generation and toggling installments clears/generates the token accordingly**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-02-19
- **Completed:** 2026-02-19
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Bulk-created membership invoices (always `_disable_installments=1`) no longer get a betaling page token or payment_link — send_invoice() sends direct Mollie link instead
- Toggling installments off via the REST API now clears `_payment_token` and `payment_link` from the invoice
- Toggling installments back on generates a fresh betaling page token via `PublicPaymentPage::generate_token()`

## Task Commits

Each task was committed atomically:

1. **Task 1: Skip token generation in BulkInvoiceCreator when installments disabled** - `1eb0a621` (feat)
2. **Task 2: Manage payment token lifecycle in toggle_installments** - `f7486ab5` (feat)

## Files Created/Modified
- `includes/class-bulk-invoice-creator.php` - Reordered to set `_disable_installments` before conditional token generation; wrapped `generate_token()` in `if ( ! get_post_meta(...) )` block
- `includes/class-rest-invoices.php` - Added `PublicPaymentPage` use import; extended `toggle_installments()` to clear token on disable and generate token on re-enable

## Decisions Made
- Move `_disable_installments` write BEFORE the `generate_token` conditional — the conditional checks that meta so it must be set first
- Clear both `_payment_token` meta and `payment_link` ACF field when disabling — both are used by the email/betaling system
- Preferred `use` import over fully-qualified class name — consistent with other imports in the file

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Membership invoice flow now matches discipline invoice flow for installment-disabled invoices
- Both flow through direct Mollie link rather than betaling page when installments are off

---
*Phase: quick-94*
*Completed: 2026-02-19*
