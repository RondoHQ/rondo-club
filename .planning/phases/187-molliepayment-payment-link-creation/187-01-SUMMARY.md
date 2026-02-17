---
phase: 187-molliepayment-payment-link-creation
plan: 01
subsystem: payments
tags: [mollie, php, wordpress, payments, invoices]

# Dependency graph
requires:
  - phase: 186-sdk-financeconfig-mollieclient
    provides: MollieClient wrapper class and FinanceConfig::get_mollie_api_key()
provides:
  - MolliePayment service class with create_payment_link(int invoice_id) method
  - Idempotent Mollie payment creation via Payments API
  - Checkout URL and payment ID stored on invoice post
affects:
  - 188-mollie-webhook (needs webhook URL format)
  - 189-invoice-send-routing (calls MolliePayment::create_payment_link() directly)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pure service class with no constructor hooks — instantiated directly by callers"
    - "Idempotency via _mollie_payment_id meta + payment_link ACF field dual-check"
    - "webhookUrl omitted when site URL contains localhost or .local"
    - "number_format(float, 2, '.', '') for locale-safe Mollie amount formatting"

key-files:
  created:
    - includes/class-mollie-payment.php
  modified:
    - functions.php

key-decisions:
  - "MolliePayment is a pure service class — no REST routes, no constructor hooks, called directly by Phase 189"
  - "Idempotency checks both _mollie_payment_id AND payment_link before skipping API call — handles partial write failures"
  - "webhookUrl omitted for localhost/.local environments (Phase 188 webhook endpoint not yet deployed)"
  - "use import added proactively in Phase 187 for cleanliness; no instantiation in rondo_init()"

patterns-established:
  - "Mollie payment service pattern: pure service, caller constructs, no WP hooks registered on instantiation"

# Metrics
duration: 2min
completed: 2026-02-17
---

# Phase 187 Plan 01: MolliePayment Service Summary

**MolliePayment pure service class with idempotent create_payment_link() using Mollie Payments API, storing checkout URL and payment ID on the invoice**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-02-17T21:46:04Z
- **Completed:** 2026-02-17T21:48:34Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Created `MolliePayment` service class in `Rondo\Finance` namespace with single public method `create_payment_link(int $invoice_id)`
- Implemented idempotency: returns existing checkout URL if both `_mollie_payment_id` and `payment_link` are already set
- Conditionally omits `webhookUrl` when site URL contains `localhost` or `.local`
- Added `use Rondo\Finance\MolliePayment` import to `functions.php` without instantiating in `rondo_init()`

## Task Commits

Each task was committed atomically:

1. **Task 1: Create MolliePayment service class** - `c887c811` (feat)
2. **Task 2: Add MolliePayment use import to functions.php** - `f7cf5673` (feat)

## Files Created/Modified
- `includes/class-mollie-payment.php` - Mollie payment service class, pure service with no constructor hooks
- `functions.php` - Added `use Rondo\Finance\MolliePayment` import (1 line added)

## Decisions Made
- MolliePayment is a pure service class with no constructor hooks and no REST routes — it will be called directly by Phase 189's `RestInvoices::send_invoice()` routing logic. No instantiation in `rondo_init()`.
- Idempotency checks both `_mollie_payment_id` AND `payment_link` — if payment ID exists but URL is empty, falls through to create a new payment (handles partial write failures gracefully).
- `webhookUrl` omitted when site URL contains `localhost` or `.local` — the Phase 188 webhook endpoint isn't deployed yet and Mollie would reject a non-reachable URL.
- Amount formatted with `number_format((float) $amount, 2, '.', '')` — 4-argument form avoids locale-specific decimal separator issues.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `MolliePayment::create_payment_link()` is complete and ready for Phase 188 (webhook handler) and Phase 189 (invoice send routing)
- The webhook URL format `rondo/v1/mollie/webhook` is baked in — Phase 188 must register this exact route

---
*Phase: 187-molliepayment-payment-link-creation*
*Completed: 2026-02-17*

## Self-Check: PASSED

- includes/class-mollie-payment.php: FOUND
- 187-01-SUMMARY.md: FOUND
- Commit c887c811 (Task 1): FOUND
- Commit f7cf5673 (Task 2): FOUND
