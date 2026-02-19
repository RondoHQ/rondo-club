---
phase: 194-payment-plan-manager-webhook-extension
plan: 01
subsystem: payments
tags: [mollie, webhook, installments, php]

# Dependency graph
requires:
  - phase: 193-public-payment-landing-page
    provides: PublicPaymentPage with installment meta storage and reverse-lookup meta (_mollie_pid_)
provides:
  - InstallmentPaymentService: shared static class for Mollie installment payment creation
  - MollieWebhook: dual-path handler (installment reverse-lookup + legacy full-payment)
  - Automatic N+1 installment payment creation after each webhook confirmation
  - All-paid gating: invoice transitions to rondo_paid only when all installments confirmed
affects: [195-scheduler, 196-bulk-creation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dual-path webhook: installment reverse-lookup first (_mollie_pid_), legacy _mollie_payment_id fallback"
    - "Shared service extraction (DRY): InstallmentPaymentService::create_payment() used by both PublicPaymentPage and MollieWebhook"
    - "Idempotency at two levels: installment status check + N+1 payment ID existence check"

key-files:
  created:
    - includes/class-installment-payment-service.php
  modified:
    - includes/class-mollie-webhook.php
    - includes/class-public-payment-page.php
    - functions.php
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "InstallmentPaymentService reads amount from _installment_N_amount + _installment_N_admin_fee meta; falls back to ACF total_amount for full plan (no admin fee)"
  - "handle_installment_paid writes betaald BEFORE the all-paid loop so single-installment (full plan) works correctly in one pass"
  - "N+1 creation wrapped in try/catch — never propagates errors; current installment paid status is already committed"
  - "Idempotency for N+1: guard on empty _installment_{next}_mollie_payment_id before calling service"

patterns-established:
  - "Reverse-lookup meta pattern: _mollie_pid_{payment_id} = installment_number for O(1) webhook routing"
  - "All-paid loop: read count from meta, iterate 1..count, break on first non-betaald"
  - "Shared service via static method — no instantiation needed, PSR-4 autoloader resolves via use statement in functions.php"

# Metrics
duration: 4min
completed: 2026-02-19
---

# Phase 194 Plan 01: Mollie Webhook Installment Extension Summary

**Dual-path Mollie webhook with installment reverse-lookup, all-paid gating, and automatic N+1 payment creation; shared InstallmentPaymentService extracted from PublicPaymentPage (DRY)**

## Performance

- **Duration:** 4 min
- **Started:** 2026-02-19T07:49:33Z
- **Completed:** 2026-02-19T07:53:33Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Created `InstallmentPaymentService` with a single static `create_payment()` method — single source of truth for Mollie installment payment creation
- Removed private `create_installment_payment()` from `PublicPaymentPage`; replaced with `InstallmentPaymentService::create_payment()` call
- Extended `MollieWebhook` with dual-path lookup: installment reverse-lookup (`_mollie_pid_{id}`) checked first; legacy `_mollie_payment_id` preserved as fallback
- `handle_installment_paid()`: marks installment `betaald`, gates invoice completion on all-paid check, creates next installment automatically
- Version bumped to 27.3.0, deployed to production

## Task Commits

Each task was committed atomically:

1. **Task 1: Extract InstallmentPaymentService and refactor PublicPaymentPage** - `b2dcc510` (feat)
2. **Task 2: Extend MollieWebhook with dual-path installment handling** - `8766a7c8` (feat)

## Files Created/Modified
- `includes/class-installment-payment-service.php` - New shared service; `create_payment(int $invoice_id, int $installment_number)` reads meta, builds Mollie payload, stores results
- `includes/class-mollie-webhook.php` - Dual-path webhook; new `handle_installment_paid()` private method
- `includes/class-public-payment-page.php` - Removed `create_installment_payment()`; delegates to service
- `functions.php` - Added `use Rondo\Finance\InstallmentPaymentService;`
- `style.css` - Version 27.2.0 → 27.3.0
- `package.json` - Version 27.2.0 → 27.3.0
- `CHANGELOG.md` - Added 27.3.0 entry

## Decisions Made
- `InstallmentPaymentService` reads amount from stored `_installment_N_amount` + `_installment_N_admin_fee` meta; falls back to ACF `total_amount` for `full` plan (no admin fee for single payment)
- `handle_installment_paid` writes `betaald` before the all-paid loop — this ensures the `full` plan (count=1) works: write first, then loop reads it back and finds all `betaald`
- N+1 payment creation is wrapped in try/catch — never propagates; current installment's paid status is already committed to DB before N+1 creation attempt
- Idempotency for N+1: guard checks `_installment_{next}_mollie_payment_id` is empty before calling the service to prevent duplicate payment creation on duplicate webhooks

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 195 (Scheduler): Webhook handler is ready. Scheduler can use `InstallmentPaymentService::create_payment()` if needed for retry logic.
- Phase 196 (Bulk Creation): `PublicPaymentPage::generate_token()` is unchanged; bulk creation can call it as planned.
- All installment meta patterns (`_installment_N_*`, `_mollie_pid_*`) are consistent across Phase 193 and 194.

---
*Phase: 194-payment-plan-manager-webhook-extension*
*Completed: 2026-02-19*
