---
phase: 169-contributie-logic
plan: 01
subsystem: api
tags: [membership-fees, former-members, rest-api, google-sheets, cache-invalidation]

# Dependency graph
requires:
  - phase: 166-backend-foundation
    provides: former_member field and marking logic in backend
  - phase: 167-core-filtering
    provides: NULL-safe filtering pattern for former_member field
  - phase: 168-visibility-controls
    provides: is_former_member flag in API responses
provides:
  - Former member season eligibility logic (is_former_member_in_season method)
  - Fee calculation handling for former members based on lid-sinds
  - Former member filtering in fee list, forecast, and Google Sheets export
  - Fee cache invalidation on former_member field changes
  - Contributie Logic documentation section
affects: [google-sheets, fee-calculation, family-discount, cache-invalidation, v23.0]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Season eligibility check: lid-sinds before season end date (July 1)"
    - "Former members use normal pro-rata calculation based on lid-sinds"
    - "Forecast exclusion pattern: former members never included in next season"
    - "Family discount excludes ineligible former members from grouping"

key-files:
  created: []
  modified:
    - includes/class-membership-fees.php
    - includes/class-rest-api.php
    - includes/class-rest-google-sheets.php
    - includes/class-fee-cache-invalidator.php
    - ../developer/src/content/docs/features/former-members.md
    - CHANGELOG.md
    - package.json
    - style.css

key-decisions:
  - "Former members use normal pro-rata based on lid-sinds (leaving doesn't create second pro-rata)"
  - "Season eligibility determined by lid-sinds before season end (July 1 of end year)"
  - "Former members excluded from forecast entirely (won't be members next season)"
  - "Family discount calculation excludes ineligible former members to prevent incorrect reductions"

patterns-established:
  - "Season eligibility check: is_former_member_in_season() validates lid-sinds against season end date"
  - "Cache invalidation pattern: ACF field update hooks trigger fee cache clearing"

# Metrics
duration: 1min 24s
completed: 2026-02-09
---

# Phase 169 Plan 01: Contributie Logic Summary

**Former member fee logic: eligible members (lid-sinds before season end) appear in contributie list with normal pro-rata, ineligible members excluded from fees and forecasts**

## Performance

- **Duration:** 1 min 24 sec
- **Started:** 2026-02-09T19:28:34Z
- **Completed:** 2026-02-09T19:29:58Z
- **Tasks:** 2
- **Files modified:** 8 (4 in rondo-club, 1 in developer, 3 for versioning)

## Accomplishments
- Former member season eligibility logic determines fee list inclusion based on lid-sinds date
- Fee calculations treat former members identically to active members (pro-rata from lid-sinds)
- Forecast endpoint excludes all former members (won't be members next season)
- Family discount calculation excludes ineligible former members to prevent incorrect reductions
- Google Sheets export applies identical former member rules
- Fee cache automatically invalidates when former_member field changes
- Documentation explains contributie logic with season eligibility criteria and API response fields

## Task Commits

Each task was committed atomically:

1. **Task 1: Add former member fee logic to backend** - `3dca9a37` (feat)
2. **Task 2: Update documentation and version** - `ebf7b4f8` (docs)

## Files Created/Modified
- `includes/class-membership-fees.php` - Added is_former_member_in_season() method, modified build_family_groups() to exclude ineligible former members, added is_former_member flag to fee responses, updated diagnostics
- `includes/class-rest-api.php` - Added former member filtering in get_fee_list() and get_person_fee(), excluded former members from forecast
- `includes/class-rest-google-sheets.php` - Added former member filtering in fetch_fee_data() matching REST API behavior
- `includes/class-fee-cache-invalidator.php` - Added former_member field hook for cache invalidation
- `../developer/src/content/docs/features/former-members.md` - Added "Contributie Logic" section with season eligibility, pro-rata, forecast, family discount, cache invalidation, API responses, and Google Sheets export documentation
- `CHANGELOG.md` - Added [23.2.0] entry with all former member fee logic changes
- `package.json` - Bumped version to 23.2.0
- `style.css` - Bumped version to 23.2.0

## Decisions Made

1. **Former members use normal pro-rata based on lid-sinds** - Leaving the club doesn't create a second pro-rata calculation. If a member joined in September and left in January, they pay the September-onward fee. This keeps fee calculation simple and consistent with how active members are charged.

2. **Season eligibility: lid-sinds before season end (July 1)** - A former member qualifies for a season's fee list if their lid-sinds date is before July 1 of the season's end year. This covers members who joined before or during the season and later left. Members who joined after the season ended are excluded (they weren't active during that period).

3. **Forecast excludes former members entirely** - Former members never appear in fee forecasts because they won't be members in the next season. This prevents them from inflating budget projections.

4. **Family discount excludes ineligible former members** - The build_family_groups() method skips former members who aren't eligible for the current season. This prevents former members who left before the season from incorrectly reducing family discounts for remaining family members.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - execution completed without issues. The previous executor agent crashed after Task 1 was committed, but Task 2 files were already modified (just not committed). This continuation agent completed the build, deploy, and commits.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Phase 169 complete - this was the final phase of the v23.0 Former Members milestone. All former member functionality is now complete:
- Phase 166: Backend foundation (former_member field, marking logic in rondo-sync)
- Phase 167: Core filtering (database-level filtering)
- Phase 168: Visibility controls (UI toggle, visual indicators)
- Phase 169: Contributie logic (fee calculations)

Ready for v23.0 milestone completion and next milestone planning.

## Self-Check: PASSED

All files verified:
- includes/class-membership-fees.php: FOUND
- includes/class-rest-api.php: FOUND
- includes/class-rest-google-sheets.php: FOUND
- includes/class-fee-cache-invalidator.php: FOUND
- ../developer/src/content/docs/features/former-members.md: FOUND
- CHANGELOG.md: FOUND
- package.json: FOUND
- style.css: FOUND

All commits verified:
- 3dca9a37: FOUND
- ebf7b4f8: FOUND

---
*Phase: 169-contributie-logic*
*Completed: 2026-02-09*
