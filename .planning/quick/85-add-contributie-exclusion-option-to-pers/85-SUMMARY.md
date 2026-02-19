---
phase: quick-85
plan: 01
subsystem: payments
tags: [membership-fees, react, wordpress, acf, rest-api]

# Dependency graph
requires:
  - phase: 196-01
    provides: BulkInvoiceCreator and calculate_fee pipeline that this hooks into
  - phase: 197-01
    provides: FinancesCard and usePersonFee/feeData shape this builds on top of
provides:
  - Per-person opt-out from contributie calculation via _exclude_from_contributie post meta
  - financieel-gated REST exposure of exclusion flag and manually_excluded reason code
  - FinancesCard toggle UI for excluding/re-including persons
affects: [membership-fees, bulk-invoicing, contributie-list, google-sheets-export]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - auth_callback on register_post_meta for capability-gated writes
    - REST response field gated by current_user_can in rest_prepare_* filter
    - reason field on non-calculable fee response for frontend differentiation

key-files:
  created: []
  modified:
    - includes/class-post-types.php
    - includes/class-rest-people.php
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
    - src/components/FinancesCard.jsx

key-decisions:
  - "isExcluded derived from feeData.reason === 'manually_excluded' (not from person REST field) — feeData always loads for financieel users, avoids separate person field fetch"
  - "calculate_fee returns null for excluded persons — all downstream callers (bulk invoicing, Google Sheets, Contributie list) skip person automatically via existing null handling"
  - "get_person_fee exclusion check runs before former-member check — manually_excluded takes priority in the response"
  - "FinancesCard invalidates feeKeys.person(personId) after toggle via onSuccess — fee UI refreshes without page reload"

patterns-established:
  - "Reason code pattern: non-calculable REST responses include reason field (manually_excluded, no_valid_category) for frontend differentiation"

# Metrics
duration: 12min
completed: 2026-02-19
---

# Quick Task 85: Add Contributie Exclusion Option to Person Summary

**Per-person contributie opt-out with financieel-gated toggle in FinancesCard, _exclude_from_contributie post meta skipping calculate_fee for all downstream callers**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-02-19
- **Completed:** 2026-02-19
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- `_exclude_from_contributie` boolean meta registered on person CPT with `financieel` auth_callback for capability-gated writes
- `calculate_fee()` returns null early for excluded persons, affecting bulk invoicing, Google Sheets export, and Contributie list automatically
- `get_person_fee` REST endpoint returns `{ calculable: false, reason: 'manually_excluded' }` for clear frontend differentiation
- FinancesCard renders "Uitgesloten van contributie" notice with "Opnemen" re-include button when excluded, and "Uitsluiten van contributie" button at card bottom when not excluded
- Fee query invalidated on toggle for immediate UI refresh without page reload

## Task Commits

Each task was committed atomically:

1. **Task 1: Register meta field and expose via REST with financieel gating** - `98aa5837` (feat)
2. **Task 2: Add exclusion toggle in FinancesCard** - `04c0e705` (feat)

## Files Created/Modified

- `includes/class-post-types.php` - Registered `_exclude_from_contributie` boolean meta with financieel auth_callback
- `includes/class-rest-people.php` - Expose `exclude_from_contributie` in REST response only to financieel users
- `includes/class-membership-fees.php` - Early null return in calculate_fee when exclusion flag is set
- `includes/class-rest-api.php` - Exclusion check before former-member check in get_person_fee, returns reason: manually_excluded
- `src/components/FinancesCard.jsx` - Ban icon, useUpdatePerson, exclusion state UI, toggle button, fee query invalidation

## Decisions Made

- `isExcluded` derived from `feeData.reason === 'manually_excluded'` rather than from the person's `exclude_from_contributie` REST field — feeData always loads for financieel users and avoids needing to pass person data down to FinancesCard
- Exclusion check in `get_person_fee` placed before the former-member check so manually excluded persons get the clearest reason code
- Used `feeKeys.person(personId)` (without params) for invalidation to clear all cached variants for the person

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Finance admins can now exclude honorary members, staff on special arrangements, etc. from automated fee calculation and invoice generation
- The `reason: 'manually_excluded'` code is available for future use in bulk invoice creation UI to show skip reason

---
*Phase: quick-85*
*Completed: 2026-02-19*
