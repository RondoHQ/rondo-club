---
phase: 22-access-control-tests
plan: 01
subsystem: testing
tags: [phpunit, access-control, user-isolation, wp-browser]

# Dependency graph
requires:
  - phase: 21-phpunit-setup
    provides: PHPUnit infrastructure, CaelisTestCase base class, factory helpers
provides:
  - UserIsolationTest with 18 tests verifying user_can_access_post() and query filtering
  - Test patterns for PRM_Access_Control class
affects: [22-02-visibility-tests, 22-03-workspace-tests]

# Tech tracking
tech-stack:
  added: []
  patterns: [createApprovedCaelisUser() helper, integer casting for DB results]

key-files:
  created:
    - tests/Wpunit/UserIsolationTest.php
  modified: []

key-decisions:
  - "Cast DB results to integers for assertions (wpdb returns strings)"
  - "Test all three controlled post types (person, company, important_date) individually"
  - "Test both positive (author access) and negative (non-author denied) cases"

patterns-established:
  - "createApprovedCaelisUser() - helper method for creating approved Caelis users"
  - "Integer casting pattern: array_map('intval', $db_results) for ID comparisons"
  - "Test structure: separate test methods for each post type and scenario"

issues-created: []

# Metrics
duration: 6min
completed: 2026-01-13
---

# Phase 22 Plan 01: UserIsolationTest Summary

**18 tests verifying user isolation in access control - author check and query filtering for all controlled post types**

## Performance

- **Duration:** 6 min
- **Started:** 2026-01-13T22:55:00Z
- **Completed:** 2026-01-13T23:01:00Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Created UserIsolationTest with 18 comprehensive tests and 46 assertions
- Verified user_can_access_post() correctly enforces author-based access control
- Verified query filtering (WP_Query, REST API) restricts results to user's own posts
- Covered all three controlled post types: person, company, important_date
- Tested edge cases: unapproved users, trashed posts, logged-out users

## Task Commits

Each task was committed atomically:

1. **Task 1 & 2: UserIsolationTest complete** - `d01bb6f` (feat)
   - Combined both tasks in single commit as they share the same test file

## Files Created/Modified

- `tests/Wpunit/UserIsolationTest.php` - 496 lines, 18 tests covering user isolation

## Decisions Made

- **Integer casting for DB results:** Database queries return string IDs, but test fixtures return integers. Added `array_map('intval', ...)` pattern for correct comparisons.
- **Combined commit:** Both tasks modify the same file, so committed together rather than splitting artificially.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Recreated test database**
- **Found during:** Initial test run
- **Issue:** Test database tables were missing (caelis_test.wp_options not found)
- **Fix:** Recreated test database with `mysql -u root -e "DROP DATABASE IF EXISTS caelis_test; CREATE DATABASE caelis_test;"`
- **Verification:** All tests run successfully

**2. [Rule 1 - Bug Fix] Fixed type comparison for DB results**
- **Found during:** First test run (3 failures)
- **Issue:** `get_accessible_post_ids()` returns string IDs from `$wpdb->get_col()`, but factory methods return integer IDs, causing `assertContains()` failures
- **Fix:** Added `array_map('intval', ...)` to cast DB results to integers before comparison
- **Verification:** All 18 tests now pass

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug fix)
**Impact on plan:** Both fixes necessary to run tests. Type casting is a legitimate test concern, not a code change. No scope creep.

## Issues Encountered

None beyond the auto-fixed deviations above.

## Next Phase Readiness

- UserIsolationTest complete and passing
- Ready for 22-02 (VisibilityRulesTest) and 22-03 (WorkspacePermissionsTest)
- Test patterns established for future access control tests
- createApprovedCaelisUser() helper available for reuse

---
*Phase: 22-access-control-tests*
*Completed: 2026-01-13*
