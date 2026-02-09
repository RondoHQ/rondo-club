---
phase: 160-configurable-family-discount
plan: 01
subsystem: api
tags: [wordpress-options, membership-fees, rest-api, validation]

# Dependency graph
requires:
  - phase: 155-fee-category-data-model
    provides: Per-season WordPress option storage pattern
  - phase: 157-fee-category-rest-api
    provides: Settings API validation pattern with errors/warnings
provides:
  - Configurable family discount percentages stored per season
  - get_family_discount_config() with copy-forward pattern
  - REST API endpoints for family discount CRUD
  - Validation for discount percentages (0-100 range)
affects: [160-02, fee-calculation, settings-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Separate WordPress option per config type (rondo_family_discount_{season})
    - Copy-forward from previous season for discount config
    - Optional season parameter throughout calculation chain

key-files:
  created: []
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php

key-decisions:
  - "Store family discount in separate option (rondo_family_discount_{season}) instead of mixing with categories"
  - "Copy-forward discount config when new season has no config (matches category pattern)"
  - "Validate percentages 0-100, warn if second >= third (allow flexibility)"

patterns-established:
  - "Config helpers pattern: get_*_config() reads with copy-forward, save_*_config() persists"
  - "Season parameter flows through entire calculation chain for forecast mode"

# Metrics
duration: 3min
completed: 2026-02-09
---

# Phase 160 Plan 01: Configurable Family Discount Backend Summary

**Family discount percentages configurable per season via WordPress options with copy-forward pattern and REST API validation**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-09T11:10:47Z
- **Completed:** 2026-02-09T11:14:09Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Family discount percentages now stored in rondo_family_discount_{season} WordPress option
- Copy-forward pattern: new seasons inherit previous season's discount config automatically
- REST API endpoints extended to include family_discount in GET/POST /membership-fees/settings
- Validation ensures percentages are 0-100, warns if second >= third child discount
- Backward compatible: defaults to 25%/50% when no config exists

## Task Commits

Each task was committed atomically:

1. **Task 1: Add family discount config helpers and update get_family_discount_rate()** - `6101eed8` (feat)
2. **Task 2: Extend REST API settings endpoints with family discount** - `d8f2eccc` (feat)

## Files Created/Modified
- `includes/class-membership-fees.php` - Added get_family_discount_config() with copy-forward, save_family_discount_config(), updated get_family_discount_rate() to read from config
- `includes/class-rest-api.php` - Extended GET/POST /membership-fees/settings with family_discount, added validate_family_discount_config()

## Decisions Made

**Storage separation:** Store family_discount in separate WordPress option (rondo_family_discount_{season}) instead of mixing with categories. Rationale: save_categories_for_season() replaces entire option, mixing would cause family_discount to be lost or appear as a category.

**Copy-forward:** When a season has no discount config, copy from previous season before falling back to defaults. Rationale: Discount policy rarely changes year-to-year, reduces admin burden.

**Validation warnings:** Warn (not error) if second child discount >= third child discount. Rationale: Allows admin flexibility while guiding typical use case.

**Error code change:** Changed WP_Error code from 'invalid_categories' to 'invalid_settings' since validation now covers both categories and family discount.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Backend is complete and ready for UI implementation (Plan 02). REST API endpoints return family_discount for both seasons, accept updates with validation, and persist to database. Existing fee calculations continue to work with defaults.

## Self-Check: PASSED

Files verified:
- includes/class-membership-fees.php: FOUND
- includes/class-rest-api.php: FOUND

Commits verified:
- 6101eed8: FOUND
- d8f2eccc: FOUND

All task deliverables confirmed present.

---
*Phase: 160-configurable-family-discount*
*Completed: 2026-02-09*
