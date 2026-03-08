---
phase: 210-backend-normalization-ui
plan: 01
subsystem: api
tags: [php, acf, e164, phone-normalization]

requires:
  - phase: 209-data-model-migration
    provides: Fixed ACF contact fields (mobile_1, mobile_2, telephone_1, telephone_2)
provides:
  - E.164 phone normalization on ACF save for all 4 phone fields
affects: [sync, whatsapp-links, tel-links, search]

tech-stack:
  added: []
  patterns: [acf-update-value-hook-normalization]

key-files:
  created: [includes/class-phone-normalizer.php]
  modified: [functions.php]

key-decisions:
  - "Used Rondo\\Core namespace consistent with AutoTitle pattern"
  - "Placed PhoneNormalizer instantiation alongside AutoTitle in admin/REST/cron block"

patterns-established:
  - "ACF field normalization: hook acf/update_value/name={field} for data cleanup on save"

requirements-completed: [DATA-02]

duration: 3min
completed: 2026-03-08
---

# Phase 210 Plan 01: Phone Normalization Summary

**E.164 phone normalization via ACF update_value hooks for mobile_1, mobile_2, telephone_1, telephone_2**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-08T15:50:29Z
- **Completed:** 2026-03-08T15:53:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- PhoneNormalizer class normalizes Dutch phone numbers to E.164 format on save
- Handles all Dutch input formats (06-xxx, 020-xxx, +31xxx, 0031xxx)
- Strips Unicode invisible characters before normalization
- Empty values pass through unchanged

## Task Commits

Each task was committed atomically:

1. **Task 1: Create PhoneNormalizer class** - `b7c2b4b8` (feat)
2. **Task 2: Load PhoneNormalizer in functions.php** - `35f23ae7` (feat)

## Files Created/Modified
- `includes/class-phone-normalizer.php` - PhoneNormalizer class with E.164 normalization logic and ACF hooks
- `functions.php` - Import and instantiate PhoneNormalizer in rondo_init()

## Decisions Made
- Used `Rondo\Core` namespace to match AutoTitle pattern (both use acf/update_value hooks)
- Placed instantiation in the admin/REST/cron block alongside AutoTitle since normalization only matters during saves

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phone normalization active for all saves via admin and REST API
- Ready for frontend UI work in plan 02 and bulk normalization migration in plan 03

---
*Phase: 210-backend-normalization-ui*
*Completed: 2026-03-08*
