---
phase: 192-data-model-foundation
plan: 01
subsystem: database
tags: [acf, wordpress-options, wp-cli, invoices, membership-fees, installments]

# Dependency graph
requires: []
provides:
  - invoice_type ACF select field on rondo_invoice CPT (discipline/membership)
  - FinanceConfig::get_installment_admin_fee() and OPTION_INSTALLMENT_ADMIN_FEE constant
  - MembershipFees::get_billing_method() and set_billing_method() with nikki/rondo validation
  - Installment meta schema documented in FinanceConfig class docblock
  - Reverse-lookup meta pattern documented (_mollie_pid_{id} = installment_number)
  - WP-CLI backfill command: wp prm invoices backfill_invoice_type
  - Production: all 2 existing invoices backfilled with invoice_type=discipline
affects:
  - 193-membership-invoice-creation
  - 194-installment-plan
  - 195-scheduler
  - 196-bulk-creation
  - 197-facturen-list

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Flat numbered post meta for installments (_installment_N_*) — avoids ACF repeater overhead
    - Reverse-lookup meta pattern (_mollie_pid_{id} = N) for O(1) webhook matching
    - Per-season WordPress option keys (rondo_billing_method_{season}) for billing configuration

key-files:
  created: []
  modified:
    - acf-json/group_invoice_fields.json
    - includes/class-finance-config.php
    - includes/class-membership-fees.php
    - includes/class-wp-cli.php

key-decisions:
  - "invoice_type ACF field has allow_null=1 and required=0 so existing invoices pass validation before backfill"
  - "invoice_type defaults to 'discipline' so new invoices created before explicit type selection default correctly"
  - "Installment admin fee is SEPARATE from discipline invoice admin fee (OPTION_ADMIN_FEE vs OPTION_INSTALLMENT_ADMIN_FEE)"
  - "Billing method stored per-season via WordPress options with key rondo_billing_method_{season}"
  - "Backfill command uses get_field/update_field (ACF) not raw post_meta to respect ACF field key mapping"

patterns-established:
  - "WP-CLI commands follow RONDO_{Name}_CLI_Command pattern, registered as 'wp prm {noun}'"
  - "Backfill commands include --dry-run flag for safe preview"

# Metrics
duration: 3min
completed: 2026-02-18
---

# Phase 192 Plan 01: Data Model Foundation Summary

**invoice_type ACF select field (discipline/membership) added to rondo_invoice, FinanceConfig installment admin fee option, MembershipFees per-season billing method toggle, installment schema documented, and 2 existing invoices backfilled on production via WP-CLI**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-18T10:26:51Z
- **Completed:** 2026-02-18T10:30:00Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Added `invoice_type` ACF select field (discipline/membership) as first field in `group_invoice_fields`, allowing invoices to be distinguished by type for downstream filtering (Phase 197)
- Added `OPTION_INSTALLMENT_ADMIN_FEE` constant, `get_installment_admin_fee()` getter, and `update_settings` handler to `FinanceConfig`, with installment meta schema and reverse-lookup pattern documented in class docblock
- Added `get_billing_method()` and `set_billing_method()` to `MembershipFees` enabling per-season nikki/rondo billing toggle (BILL-01)
- Added `RONDO_Invoices_CLI_Command` with `backfill_invoice_type` command; deployed and ran on production — 2 existing invoices updated with `invoice_type=discipline`

## Task Commits

Each task was committed atomically:

1. **Task 1: Add invoice_type ACF field, installment admin fee config, and billing method toggle** - `02a56085` (feat)
2. **Task 2: Add WP-CLI backfill command and deploy** - `5a9fa6dc` (feat)

## Files Created/Modified

- `acf-json/group_invoice_fields.json` - Added `field_invoice_type` select field as first field in group, allow_null=1, default=discipline
- `includes/class-finance-config.php` - Added OPTION_INSTALLMENT_ADMIN_FEE constant, DEFAULTS entry, get_installment_admin_fee() getter, get_all_settings/get_setting/update_settings coverage, class docblock with installment schema and reverse-lookup pattern
- `includes/class-membership-fees.php` - Added get_billing_method() and set_billing_method() after get_season_key()
- `includes/class-wp-cli.php` - Added RONDO_Invoices_CLI_Command class with backfill_invoice_type method, registered as 'wp prm invoices'

## Decisions Made

- `invoice_type` ACF field set to `allow_null=1` and `required=0` because existing invoices had no value — backfill populates them after field registration
- Installment admin fee kept as a separate option (`OPTION_INSTALLMENT_ADMIN_FEE`) from discipline invoice admin fee (`OPTION_ADMIN_FEE`) because they serve different purposes: one per-invoice, one per-installment
- Billing method uses per-season option keys (`rondo_billing_method_{season}`) so historical seasons remain unaffected when switching future seasons

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All data model foundation artifacts are in place for Phase 193 (Membership Invoice Creation)
- `invoice_type` field queryable on all invoices — existing ones backfilled, new ones default to 'discipline'
- `FinanceConfig::get_installment_admin_fee()` returns 0.00 on production (ready to configure)
- `MembershipFees::get_billing_method()` returns 'nikki' on production (default)
- Installment meta schema documented in code — Phase 194 implements first actual writes

---
*Phase: 192-data-model-foundation*
*Completed: 2026-02-18*
