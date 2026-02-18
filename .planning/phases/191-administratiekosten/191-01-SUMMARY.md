---
phase: 191-administratiekosten
plan: 01
subsystem: payments
tags: [finance, invoices, rest-api, react, wordpress-options]

# Dependency graph
requires:
  - phase: 190-finance-settings
    provides: FinanceConfig class, finance settings REST endpoint, FinanceSettings.jsx with Betaling tab
provides:
  - Configurable admin_fee setting in FinanceConfig (OPTION_ADMIN_FEE, get_admin_fee, DEFAULTS)
  - admin_fee REST arg registration in /rondo/v1/finance/settings POST route
  - Server-side Administratiekosten line item injection in create_invoice() when fee > 0
  - Admin fee included in invoice total_amount at creation time
  - Admin fee UI field in Finance Settings Betaling tab (euro prefix, help text)
affects: [invoices, discipline-cases, pdf-generation, email-sending]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - FinanceConfig option pattern: const OPTION_*, DEFAULTS entry, getter, get_all_settings, get_setting, update_settings
    - Server-side line item injection in create_invoice() after $rows loop, before update_field calls

key-files:
  created: []
  modified:
    - includes/class-finance-config.php
    - includes/class-rest-api.php
    - includes/class-rest-invoices.php
    - src/pages/Finance/FinanceSettings.jsx
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "[Phase 191]: admin_fee injected server-side in create_invoice() — backend is single source of truth, prevents tampering"
  - "[Phase 191]: Hardcoded 'Administratiekosten' description — configurable label adds complexity without clear benefit"
  - "[Phase 191]: Gate injection on admin_fee > 0 — no zero-value line items on invoices"
  - "[Phase 191]: total_amount update_field moved to after admin fee injection so total always includes the fee"

patterns-established:
  - "Non-discipline line items: discipline_case=null, existing PDF/email fallback paths already handle them"
  - "Server-side fee injection pattern: get_admin_fee() in create_invoice(), add to $rows and $total_amount before update_field calls"

# Metrics
duration: 4min
completed: 2026-02-18
---

# Phase 191 Plan 01: Administratiekosten Summary

**Configurable administration fee stored in FinanceConfig, exposed in Finance Settings Betaling tab, and auto-injected as Administratiekosten line item (with total) on invoice creation when fee > 0**

## Performance

- **Duration:** ~4 min
- **Started:** 2026-02-18T11:51:45Z
- **Completed:** 2026-02-18T11:55:45Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- FinanceConfig extended with OPTION_ADMIN_FEE, get_admin_fee() getter, DEFAULTS['admin_fee']=0.00, get_all_settings() entry, get_setting() case, update_settings() handler
- REST API args in /rondo/v1/finance/settings updated with admin_fee (type: number, not required)
- create_invoice() injects Administratiekosten line item server-side when fee > 0, adds to total_amount before update_field calls
- Finance Settings Betaling tab shows Administratiekosten number input with euro prefix and help text (all 4 JSX state locations updated)
- Version bumped to 27.1.2, changelog updated

## Task Commits

Each task was committed atomically:

1. **Task 1: Add admin_fee to FinanceConfig, REST args, and create_invoice() injection** - `0c287a61` (feat)
2. **Task 2: Add admin fee UI field in Finance Settings and bump version** - `b022ed6d` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `includes/class-finance-config.php` - Added OPTION_ADMIN_FEE constant, admin_fee default, get_admin_fee() getter, get_all_settings/get_setting/update_settings entries
- `includes/class-rest-api.php` - Registered admin_fee as REST arg in finance settings POST route
- `includes/class-rest-invoices.php` - Restructured create_invoice() to inject Administratiekosten line item after $rows loop; moved update_field('total_amount'...) to after injection
- `src/pages/Finance/FinanceSettings.jsx` - Added admin_fee to useState, useEffect, handleSubmit payload, and Betaling tab UI input field
- `style.css` - Version bumped to 27.1.2
- `package.json` - Version bumped to 27.1.2
- `CHANGELOG.md` - Added [27.1.2] entry with Added: Configurable administration fee

## Decisions Made
- Server-side injection chosen over frontend injection: backend is single source of truth, fee amount can't be tampered, frontend doesn't need separate knowledge of the fee
- Hardcoded "Administratiekosten" description: configurable label would add complexity without clear benefit
- Gate on `admin_fee > 0` prevents zero-value line items from appearing on invoices
- total_amount update_field moved after admin fee injection to ensure total always includes the fee

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - the plan's code snippets were accurate and all existing paths (PDF, email, delete, reset) already handle non-discipline line items with null discipline_case correctly.

## User Setup Required

None - no external service configuration required. Admin fee defaults to 0 (disabled). Configure in Financien > Instellingen > Betaling tab.

## Next Phase Readiness

- Administration fee feature complete and deployed to production
- Admin can configure fee in Finance Settings > Betaling tab
- Invoice creation automatically adds Administratiekosten line item when fee > 0
- PDF and email rendering already handles the new row via existing fallback paths

---
*Phase: 191-administratiekosten*
*Completed: 2026-02-18*
