---
phase: 124-fee-calculation-engine
plan: 01
subsystem: api
tags: [php, membership-fees, calculation-engine, acf]

# Dependency graph
requires:
  - phase: 123-settings-backend-foundation
    provides: MembershipFees class with get_fee() and settings storage
provides:
  - parse_age_group() method for leeftijdsgroep to category mapping
  - get_current_teams() method for work_history team extraction
  - is_recreational_team() method for recreant/walking football detection
  - is_donateur() method for donateur-only member detection
  - calculate_fee() method for complete fee determination
affects: [124-02, 125-family-discount, 126-pro-rata]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Age group parsing with suffix normalization"
    - "Work history team extraction pattern"
    - "Fee priority: Youth > Senior/Recreant > Donateur"

key-files:
  created: []
  modified:
    - includes/class-membership-fees.php

key-decisions:
  - "JO23 treated as senior (same as Senioren)"
  - "Seniors with only recreational teams get recreant fee"
  - "Members with teams but no leeftijdsgroep excluded (data issue)"
  - "Donateur only applies when no valid age group and no teams"

patterns-established:
  - "parse_age_group: Strip Meiden/Vrouwen suffix, parse Onder X or JO format"
  - "get_current_teams: Check is_current flag OR future end_date"
  - "calculate_fee: Return array with category, base_fee, leeftijdsgroep, person_id"

# Metrics
duration: 4min
completed: 2026-01-31
---

# Phase 124 Plan 01: Fee Calculation Engine Summary

**Age group parsing, team detection, and calculate_fee() method for determining member fee categories based on leeftijdsgroep, team membership, and work functions**

## Performance

- **Duration:** 4 min
- **Started:** 2026-01-31T15:20:00Z
- **Completed:** 2026-01-31T15:24:00Z
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments
- Implemented parse_age_group() to map Dutch age groups (Onder X, JO formats, Senioren) to fee categories
- Implemented get_current_teams() using work_history ACF repeater field pattern
- Implemented is_recreational_team() to detect recreant/walking football teams
- Implemented is_donateur() to detect donateur-only members
- Implemented calculate_fee() with correct priority: Youth > Senior/Recreant > Donateur

## Task Commits

Each task was committed atomically:

1. **Task 1: Add age group parsing and team detection methods** - `eaa60924` (feat)
2. **Task 2: Add main calculate_fee method** - `d8634dca` (feat)
3. **Task 3: Build and deploy** - (no commit - deployment only, dist gitignored)

## Files Created/Modified
- `includes/class-membership-fees.php` - Added 5 new methods: parse_age_group(), get_current_teams(), is_recreational_team(), is_donateur(), calculate_fee()

## Decisions Made
- **JO23 as senior:** Added JO23 format detection treating it as senior (matches Senioren)
- **JO format support:** Added parsing for JO6-JO19 format in addition to Onder X format
- **Team validation:** Verify post_type is 'team' before including in results
- **Recreant logic:** Only use recreant fee if ALL teams are recreational; any regular team means senior fee (higher fee wins)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Fee calculation engine complete
- Ready for Phase 124-02: REST API endpoints to expose calculate_fee() for all members
- Ready for Phase 125: Family discount logic to apply on top of base fees
- Ready for Phase 126: Pro-rata calculation for mid-season joins

---
*Phase: 124-fee-calculation-engine*
*Completed: 2026-01-31*
