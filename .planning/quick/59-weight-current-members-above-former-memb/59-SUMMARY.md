---
phase: quick-59
plan: 01
subsystem: api
tags: [search, scoring, former-members, acf]

# Dependency graph
requires:
  - phase: v23.0
    provides: former_member ACF field and NULL-safe exclusion pattern
provides:
  - Former member penalty in global search scoring (-50 points)
  - Current members prioritized over former members in search results
affects: [search, people-api]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Post-processing score adjustment pattern for membership status"
    - "ACF field string comparison ('1' not 1) for checkbox values"

key-files:
  created: []
  modified:
    - includes/class-rest-api.php

key-decisions:
  - "-50 penalty ensures current members with partial matches (score 60) rank above former members with exact matches (score 100)"
  - "Penalty applied after scoring but before sorting to maintain clean separation of concerns"

patterns-established:
  - "Score adjustment pattern: apply membership-based penalties after all match scoring is complete, before final sort"

# Metrics
duration: 51s
completed: 2026-02-12
---

# Quick Task 59: Weight Current Members Above Former Members in Search

**Global search now applies -50 score penalty to former members, ensuring current members with partial matches rank above former members with exact matches**

## Performance

- **Duration:** 51 seconds
- **Started:** 2026-02-12T14:54:59Z
- **Completed:** 2026-02-12T14:55:50Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Former members receive -50 score penalty in global search results
- Current members with score 60+ (contains matches) now rank above former members with score 100 (exact matches)
- Former members still appear in results when relevant (not filtered out)
- Clean separation between match quality scoring and membership status prioritization

## Task Commits

Each task was committed atomically:

1. **Task 1: Apply former member penalty to search scores** - `d1c54abe` (feat)

## Files Created/Modified
- `includes/class-rest-api.php` - Added former member penalty loop in global_search() method (lines 1806-1813)

## Decisions Made
- **Penalty amount (-50 points):** Chosen to ensure current members with "first name contains" match (score 60) rank above former members with "first name exact" match (score 100), while preserving the existing match quality hierarchy
- **Application timing:** Apply penalty after all person queries complete but before sorting, maintaining clean separation between match scoring logic and membership status logic
- **String comparison:** Use strict string comparison (`=== '1'`) to match ACF checkbox storage format

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation was straightforward. The former_member ACF field uses string storage ('1' for checked), which was correctly handled with strict string comparison.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Search functionality now properly prioritizes current members. The -50 penalty is tuned to the current scoring system:
- First name exact: 100
- Last name exact: 100
- First name contains: 60
- Last name contains: 60
- Custom fields: 30

If these scores change in future, the penalty amount may need adjustment to maintain the desired ranking behavior.

## Self-Check: PASSED

- FOUND: includes/class-rest-api.php (modified file verified)
- FOUND: d1c54abe (task commit verified)
- Former member penalty code confirmed at line 1806-1813

---
*Phase: quick-59*
*Completed: 2026-02-12*
