---
phase: 196-bulk-invoice-creation
plan: 01
subsystem: payments
tags: [wp-cron, bulk-invoices, membership-fees, php, rest-api, installment-plans]

# Dependency graph
requires:
  - phase: 193-public-payment-landing-page
    provides: PublicPaymentPage::generate_token() static utility for token generation
  - phase: 192-data-model-foundation
    provides: rondo_invoice CPT, invoice ACF fields, InvoiceNumbering::generate_next()
  - phase: 194-mollie-payment-integration
    provides: InstallmentPaymentService for Mollie checkout creation

provides:
  - BulkInvoiceCreator class with WP-Cron batch job (50/batch) and idempotency
  - POST /rondo/v1/fees/bulk-create-invoices — start async bulk job
  - GET /rondo/v1/fees/bulk-invoice-job — poll job progress
  - POST /rondo/v1/fees/create-membership-invoice — single-member invoice creation
  - GET/POST /rondo/v1/fees/billing-settings — billing method + installment plan toggles
  - billing_method, installment_plan_3_enabled, installment_plan_8_enabled in fee list/summary/person-fee responses
  - Installment plan 3 and 8 toggle support with conditional visibility on PublicPaymentPage

affects:
  - Phase 197 (frontend Contributie page — reads billing_method and plan toggles from REST API)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WP-Cron single-event chaining for batch processing without PHP timeout risk
    - Job state stored in WordPress option (autoload=false) with person_ids array stripped from responses
    - Idempotency via meta_query on person+_invoice_season+invoice_type before creation
    - Per-season WordPress options pattern for feature toggles (rondo_installment_plan_3_enabled_{season})

key-files:
  created:
    - includes/class-bulk-invoice-creator.php
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
    - includes/class-public-payment-page.php
    - functions.php

key-decisions:
  - "BulkInvoiceCreator registers cron hook in constructor — needs both REST (start/progress) and cron (batch) — loaded unconditionally after REST/cron conditional block"
  - "BATCH_SIZE=50 — balances throughput vs PHP memory pressure on SiteGround shared hosting"
  - "person_ids array stripped from REST responses (can be 500+ ints) — stored in option only for cron pickup"
  - "Installment plan toggles default true (both plans enabled) — admin must explicitly disable"
  - "Plan toggle check in handle_plan_selection guards against form bypass when plan is disabled"

patterns-established:
  - "Per-season feature flags: get_option('rondo_{feature}_enabled_{season}', true) pattern"
  - "Async job state: WordPress option with autoload=false, job struct includes person_ids array, responses strip it"

# Metrics
duration: 5min
completed: 2026-02-19
---

# Phase 196 Plan 01: Bulk Invoice Creation Summary

**WP-Cron batch job for async membership invoice creation (50/batch), REST endpoints for start/progress/single-create, per-season installment plan toggles with conditional PublicPaymentPage rendering**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-19T10:34:56Z
- **Completed:** 2026-02-19T10:39:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- Created BulkInvoiceCreator PHP class with WP-Cron chained single-events, idempotency guard, and job state stored in WordPress options
- Added five new REST endpoints: bulk job start, job progress, single-member create, billing settings GET and POST
- Extended fee list, fee summary, and person fee endpoints with billing_method and plan toggle state
- Added installment plan 3 and 8 toggle methods to MembershipFees and conditional plan rendering to PublicPaymentPage
- Deployed to production at https://rondo.svawc.nl/

## Task Commits

Each task was committed atomically:

1. **Task 1: Create BulkInvoiceCreator class and REST endpoints** - `50ec6a97` (feat)
2. **Task 2: Add installment plan toggles and PublicPaymentPage conditionals** - `ebdb588e` (feat)

## Files Created/Modified

- `includes/class-bulk-invoice-creator.php` — New class: WP-Cron batch processor, start_job(), run_batch(), create_membership_invoice(), get_job_status()
- `includes/class-membership-fees.php` — Added get/set_installment_plan_3_enabled and get/set_installment_plan_8_enabled methods
- `includes/class-rest-api.php` — 5 new route registrations + 4 callback methods; billing_method + plan toggles in 3 fee endpoints
- `includes/class-public-payment-page.php` — Conditional plan form rendering + disabled plan validation in POST handler
- `functions.php` — Added BulkInvoiceCreator use statement and instantiation

## Decisions Made

- BulkInvoiceCreator loaded outside the `if ( $is_rest )` block (after it) so cron requests also pick up the hook — the cron callback must be registered on all requests, not only REST
- person_ids array stripped from all REST responses to keep payloads small; only stored in the WordPress option for the cron batch processor to read
- Installment plan toggles default to `true` so existing deployments are unaffected without explicit configuration
- Plan validation in `handle_plan_selection()` uses the invoice post_date to derive the season (same logic as render_page) — consistent with how the page determines the season

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required. All toggles default to enabled.

## Next Phase Readiness

- BulkInvoiceCreator is deployed and functional
- Frontend (Phase 197) can read billing_method and plan toggle state from existing fee endpoints
- billing-settings REST endpoint ready for frontend settings UI
- bulk-create-invoices and bulk-invoice-job endpoints ready for frontend bulk action UI

---
*Phase: 196-bulk-invoice-creation*
*Completed: 2026-02-19*
