---
phase: 189-restinvoices-provider-routing
plan: 01
subsystem: payments
tags: [mollie, rabobank, invoices, php, provider-routing]

# Dependency graph
requires:
  - phase: 186-mollie-config
    provides: FinanceConfig::get_active_payment_provider() method
  - phase: 187-mollie-payment-service
    provides: MolliePayment::create_payment_link() service class
  - phase: 182-rabobank-payment
    provides: RabobankOAuth and RabobankPayment classes (unchanged)
provides:
  - Provider routing in RestInvoices::send_invoice() — Mollie or Rabobank based on active provider setting
  - Complete payment abstraction layer wiring for v27.0 Mollie milestone
affects: [phase-190, deployment, send-invoice-flow]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "if ('mollie' === $active_provider) / else pattern — Mollie explicit match, all unknowns fall to Rabobank"
    - "Non-blocking payment link creation — errors logged via error_log(), invoice sending continues"

key-files:
  created: []
  modified:
    - includes/class-rest-invoices.php

key-decisions:
  - "Mollie matched explicitly, Rabobank is the else branch — any unknown provider (including default 'rabobank') routes to Rabobank path for backward compatibility"
  - "Finance config instantiated as $finance_config (separate from the later $config used for payment_term_days) — no variable collision"

patterns-established:
  - "Provider routing: explicit match for new providers, else branch preserves old behavior"

# Metrics
duration: 66s
completed: 2026-02-18
---

# Phase 189 Plan 01: RestInvoices Provider Routing Summary

**Two-branch provider routing in send_invoice() — Mollie calls MolliePayment::create_payment_link(), all other providers fall through to the original Rabobank OAuth + payment path unchanged**

## Performance

- **Duration:** ~1 min
- **Started:** 2026-02-18T~14:15Z
- **Completed:** 2026-02-18T~14:16Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Added `use Rondo\Finance\MolliePayment;` import to RestInvoices, grouped with other Finance imports
- Replaced the hard-coded Rabobank payment block in `send_invoice()` with a two-branch conditional based on `FinanceConfig::get_active_payment_provider()`
- Mollie branch calls `MolliePayment::create_payment_link()`, Rabobank else branch preserves original code byte-for-byte
- Both branches are non-blocking — errors logged, invoice sending continues to PDF + email
- Rabobank classes (`class-rabobank-payment.php`, `class-rabobank-oauth.php`) not modified

## Task Commits

Each task was committed atomically:

1. **Task 1: Add MolliePayment use import** - `038c2c5a` (feat)
2. **Task 2: Replace hard-coded Rabobank block with provider routing** - `205eb5e0` (feat)

## Files Created/Modified

- `includes/class-rest-invoices.php` - Added MolliePayment import + two-branch provider routing in send_invoice()

## Decisions Made

- Mollie matched explicitly (`if ('mollie' === $active_provider)`), Rabobank is the `else` branch — any unknown value (including the default `'rabobank'`) routes to Rabobank for backward compatibility with existing sites
- `$finance_config` variable used for provider lookup (separate from the later `$config` used for payment term days around line 707) — coexist without collision

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- v27.0 Mollie payment integration is now fully wired end-to-end: FinanceConfig (186) → MollieClient (186) → MolliePayment service (187) → MollieWebhook (188) → RestInvoices routing (189)
- Ready for deployment and production testing
- Sites with `active_payment_provider = 'rabobank'` (the default) see zero behavioral change

## Self-Check: PASSED

- FOUND: includes/class-rest-invoices.php
- FOUND: commit 038c2c5a (Task 1: add MolliePayment use import)
- FOUND: commit 205eb5e0 (Task 2: replace Rabobank block with provider routing)

---
*Phase: 189-restinvoices-provider-routing*
*Completed: 2026-02-18*
