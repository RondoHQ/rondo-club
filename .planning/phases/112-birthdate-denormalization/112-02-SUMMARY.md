---
phase: 112-birthdate-denormalization
plan: 02
title: "Birth Year Filtering"
subsystem: api
tags: [php, rest-api, sql, wpdb, filtering, birth-year]

dependency-graph:
  requires:
    - phase: 112-01
      provides: _birthdate meta denormalization for efficient YEAR() queries
  provides:
    - birth-year-filter-endpoint
    - year-range-filtering
    - validated-year-parameters
  affects: [113-frontend-pagination]

tech-stack:
  added: []
  patterns:
    - LEFT JOIN for optional meta filtering
    - YEAR() function for date extraction
    - Parameter validation with ranges

file-tracking:
  created: []
  modified:
    - includes/class-rest-people.php

decisions:
  - id: 112-02-001
    decision: Single parameter treated as exact year match
    rationale: UI design uses single year for exact match, range requires both params
    date: 2026-01-29
  - id: 112-02-002
    decision: LEFT JOIN on _birthdate (not INNER)
    rationale: Keeps query structure consistent with other meta JOINs, WHERE makes it effectively inner
    date: 2026-01-29
  - id: 112-02-003
    decision: Validate years between 1900-2100
    rationale: Reasonable bounds prevent SQL injection via extreme values, covers all real use cases
    date: 2026-01-29

metrics:
  duration: 5 minutes
  completed: 2026-01-29
---

# Phase 112 Plan 02: Birth Year Filtering Summary

**REST API birth year filter using YEAR() on denormalized _birthdate meta with range and exact matching**

## Performance

- **Duration:** 5 minutes
- **Started:** 2026-01-29T14:12:52Z
- **Completed:** 2026-01-29T14:18:42Z
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments

- Added birth_year_from and birth_year_to parameters to `/stadion/v1/people/filtered` endpoint
- Implemented SQL filtering using YEAR() function on _birthdate meta
- Validated parameters reject years outside 1900-2100 range
- Supports both exact year (single parameter) and range filtering (both parameters)
- Deployed and tested on production with 1068 birthdates

## Task Commits

Each task was committed atomically:

1. **Task 1: Add birth year filter parameters to filtered endpoint** - `2dd5384` (feat)
2. **Task 2: Implement birth year filter in get_filtered_people** - `d28ab6f` (feat)
3. **Task 3: Deploy and test birth year filter** - (testing only, no commit)

## Files Created/Modified

- `includes/class-rest-people.php` - Added birth_year_from/birth_year_to parameters with validation, implemented LEFT JOIN on _birthdate meta with YEAR() WHERE clause

## Decisions Made

**Decision 112-02-001: Single parameter treated as exact year match**
- When only `birth_year_from` OR `birth_year_to` is provided (not both), filter by exact year using `YEAR(bd.meta_value) = %d`
- Rationale: UI design uses single year for exact match, range requires both parameters

**Decision 112-02-002: LEFT JOIN on _birthdate (not INNER)**
- Used `LEFT JOIN {$wpdb->postmeta} bd` instead of INNER JOIN
- Rationale: Keeps query structure consistent with other meta JOINs. WHERE clause with YEAR() makes it effectively inner since YEAR(NULL) fails comparison.

**Decision 112-02-003: Validate years between 1900-2100**
- Parameters reject values outside 1900-2100 range
- Rationale: Reasonable bounds prevent SQL injection via extreme values, covers all real use cases (oldest person ever: 1875-1997)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

**Issue: WP-CLI doesn't trigger REST API class initialization**
- **Problem:** `stadion_is_rest_request()` returns false in WP-CLI context, so REST classes aren't auto-loaded
- **Resolution:** Manually initialized `\Stadion\REST\People()` in WP-CLI eval tests before calling endpoint
- **Impact:** Testing methodology adjusted, no code changes needed. Production works correctly since real REST requests trigger proper initialization.

## Testing Results

All tests passed on production with 1068 people having _birthdate values:

| Test Case | Expected | Actual | Status |
|-----------|----------|--------|--------|
| Exact year (2010) | 57 people | 57 | ✅ |
| Range (2010-2015) | 276 people | 276 | ✅ |
| Single param (birth_year_to=2015) | 57 people | 57 | ✅ |
| Invalid year (1800) | Validation error | rest_invalid_param | ✅ |
| Combined filters (year + ownership) | AND logic | Filters combined | ✅ |
| Edge case (1950-1960) | 59 people | 59 | ✅ |

**SQL verification:**
- Direct database query: `COUNT(DISTINCT p.ID) ... WHERE YEAR(bd.meta_value) = 2010` → 57
- REST API query: `birth_year_from=2010` → 57 (matches)
- Range query: `birth_year_from=2010&birth_year_to=2015` → 276 (matches manual sum)

## Next Phase Readiness

- Birth year filter complete and functional on production
- Ready for Phase 113 frontend pagination to consume birth_year_from/birth_year_to parameters
- No blockers or concerns

**Notes for Phase 113:**
- Endpoint accepts optional `birth_year_from` and `birth_year_to` integer parameters
- Parameters can be used independently (exact match) or together (range)
- Validation: 1900 ≤ year ≤ 2100
- People without _birthdate are excluded from results when filter active
- Filter combines with labels, ownership, modified_days using AND logic

---
*Phase: 112-birthdate-denormalization*
*Completed: 2026-01-29*
