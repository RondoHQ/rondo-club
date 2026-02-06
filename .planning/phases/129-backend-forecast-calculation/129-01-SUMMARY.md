---
phase: 129-backend-forecast-calculation
plan: 01
subsystem: api
tags: [rest-api, membership-fees, forecast, season-calculation]

# Dependency graph
requires:
  - phase: 128-contributie-ui
    provides: MembershipFees class with family discount and pro-rata calculation
provides:
  - get_next_season_key() method for calculating next season
  - /rondo/v1/fees?forecast=true endpoint for budget planning
  - Forecast mode with 100% pro-rata and no Nikki data
affects:
  - 130-frontend-forecast-ui (will consume forecast API)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Forecast calculation reuses existing family discount logic
    - Pro-rata override pattern for full-year assumptions

key-files:
  created: []
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php

key-decisions:
  - "Forecast ignores season parameter - always uses next season"
  - "100% pro-rata for all forecast members (full year assumption)"
  - "Nikki billing data omitted from forecast (not available for future)"

patterns-established:
  - "Forecast reuses calculate_fee_with_family_discount for consistency"
  - "Response includes forecast boolean flag for API consumers"

# Metrics
duration: 15min
completed: 2026-02-02
---

# Phase 129 Plan 01: Backend Forecast Calculation Summary

**Membership fees API extended with forecast parameter returning next season (2026-2027) projections with 100% pro-rata and family discounts**

## Performance

- **Duration:** 15 min
- **Started:** 2026-02-02T11:45:00Z
- **Completed:** 2026-02-02T12:00:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Added `get_next_season_key()` method to MembershipFees class
- Extended `/rondo/v1/fees` endpoint with `forecast=true` parameter
- Forecast returns next season (2026-2027) with 100% pro-rata for all members
- Family discounts correctly applied using existing address grouping logic
- Nikki billing fields omitted from forecast response
- Fixed name sorting bug in results (was using undefined `$a['name']`)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add get_next_season_key() method** - `b760e567` (feat)
2. **Task 2: Add forecast parameter to /fees endpoint** - `e9e4ac86` (feat)

## Files Created/Modified
- `includes/class-membership-fees.php` - Added get_next_season_key() method after get_season_key()
- `includes/class-rest-api.php` - Extended /fees endpoint with forecast parameter and updated get_fee_list() method

## Decisions Made
- **Forecast ignores season parameter:** When `forecast=true`, always uses `get_next_season_key()` regardless of `season` parameter. This ensures consistent behavior - forecast always shows next season.
- **Pro-rata override to 100%:** Forecast assumes all current members will be full-year members next season. Pro-rata is overridden to 1.0 after calculating family discounts.
- **Nikki data excluded:** Future season has no billing data, so `nikki_total` and `nikki_saldo` fields are omitted from forecast response (not set to null, completely absent).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed undefined variable in sort function**
- **Found during:** Task 2 (Add forecast parameter)
- **Issue:** Existing code referenced `$a['name']` in sort comparison, but `name` key doesn't exist in results array
- **Fix:** Changed to `$a['first_name'] . ' ' . $a['last_name']` for proper name comparison
- **Files modified:** includes/class-rest-api.php
- **Verification:** Sort now works correctly without PHP warnings
- **Committed in:** e9e4ac86 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Bug fix was necessary for correct sorting. No scope creep.

## Issues Encountered
None - implementation followed plan specification.

## Verification Results

Tested via WP-CLI on production:

1. **Season key:** `get_next_season_key()` returns "2026-2027" for current season "2025-2026"
2. **Forecast API:** Returns 779 members with season "2026-2027" and forecast=true
3. **Pro-rata check:** All forecast members have `prorata_percentage: 1` (100%)
4. **Family discounts:** 88 members with family discounts (positions 2+, rates 0.25/0.50)
5. **Nikki fields absent:** `nikki_total` and `nikki_saldo` not present in forecast response
6. **Normal endpoint:** Still works correctly with season "2025-2026", varied pro-rata, and Nikki fields present

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- API endpoint ready for frontend consumption in Phase 130
- Family discount groupings work correctly for forecast
- Response structure compatible with existing contributie page data model

---
*Phase: 129-backend-forecast-calculation*
*Completed: 2026-02-02*
