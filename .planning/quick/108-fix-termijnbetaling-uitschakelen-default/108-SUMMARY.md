---
phase: 108-fix-termijnbetaling-uitschakelen-default
plan: 01
subsystem: payments
tags: [bulk-invoice, installments, mollie, membership-fees]

requires:
  - phase: 196-01
    provides: BulkInvoiceCreator with PublicPaymentPage token generation and _disable_installments meta
provides:
  - Bulk-created membership invoices with installments enabled by default (no forced _disable_installments meta)
  - PublicPaymentPage::generate_token() called unconditionally for all bulk-created invoices
affects: [bulk-invoice-creation, membership-fees, installments]

tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - includes/class-bulk-invoice-creator.php

key-decisions:
  - "Removed forced _disable_installments=1 default — no meta set means installments enabled (opt-out model)"
  - "PublicPaymentPage::generate_token() now always called — token needed for payment page regardless of installment choice"

patterns-established: []

duration: 3min
completed: 2026-02-19
---

# Quick Task 108: Fix Termijnbetaling Uitschakelen Default

**Removed forced `_disable_installments = '1'` from bulk invoice creation so installments are enabled by default and payment page token is always generated**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-19T00:00:00Z
- **Completed:** 2026-02-19T00:03:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- Removed `update_post_meta( $post_id, '_disable_installments', '1' )` from `create_invoice_for_person()`
- Removed the conditional guard around `PublicPaymentPage::generate_token()` — token now always generated
- Bulk-created membership invoices now open in FactuurDetail with "Termijnbetaling uitschakelen" unchecked by default

## Task Commits

1. **Task 1: Remove forced installment disable and unconditional token generation** - `f2b55241` (fix)

## Files Created/Modified

- `includes/class-bulk-invoice-creator.php` - Removed forced `_disable_installments` meta and made token generation unconditional

## Decisions Made

- No meta set = installments enabled (opt-out model): removing the forced `'1'` value means the checkbox starts unchecked, and users can explicitly check it to disable installments per-invoice
- Token must always be generated: the public payment page token is needed whether installments are enabled or disabled, as the page shows the appropriate payment options

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Fix deployed to production at https://rondo.svawc.nl/
- New bulk-created membership invoices will have installments enabled by default
- Existing invoices with `_disable_installments = '1'` meta are unaffected — their stored meta remains

---
*Phase: 108-fix-termijnbetaling-uitschakelen-default*
*Completed: 2026-02-19*
