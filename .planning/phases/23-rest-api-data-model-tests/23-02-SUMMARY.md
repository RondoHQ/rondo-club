---
phase: 23-rest-api-data-model-tests
plan: 02
subsystem: testing
tags: [phpunit, rest-api, search, dashboard, reminders, todos, wp-browser]

# Dependency graph
requires:
  - phase: 21-phpunit-setup
    provides: PHPUnit infrastructure, StadionTestCase base class, factory helpers
  - phase: 22-access-control-tests
    provides: Test patterns for access control, createApprovedStadionUser() helper
provides:
  - SearchDashboardTest with 20 tests verifying /stadion/v1/ custom endpoints
  - REST API testing patterns (doRestRequest helper, REST server initialization)
affects: [23-03-timeline-tests]

# Tech tracking
tech-stack:
  added: []
  patterns: [REST server initialization for tests, doRestRequest helper method]

key-files:
  created:
    - tests/Wpunit/SearchDashboardTest.php
  modified: []

key-decisions:
  - "Manually instantiate STADION_REST_API class in tests to bypass stadion_is_rest_request() check"
  - "Use string 'true' for boolean REST params (matches query string behavior)"
  - "Unique user logins per test to avoid conflicts in parallel test execution"

patterns-established:
  - "doRestRequest() helper for internal REST API testing"
  - "REST server initialization: new WP_REST_Server() + do_action('rest_api_init')"
  - "createTodo() helper for creating stadion_todo comments"

issues-created: []

# Metrics
duration: 12min
completed: 2026-01-13
---

# Phase 23 Plan 02: SearchDashboardTest Summary

**20 tests verifying /stadion/v1/ custom endpoints: search, dashboard, reminders, and todos with access control enforcement**

## Performance

- **Duration:** 12 min
- **Started:** 2026-01-13
- **Completed:** 2026-01-13
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Created SearchDashboardTest with 20 comprehensive tests and 44 assertions
- Verified search endpoint returns correct results with user isolation
- Verified dashboard endpoint provides accurate per-user counts
- Verified reminders endpoint respects days_ahead filtering
- Verified todos endpoint filters by completion status and enforces access control
- All endpoints properly block unapproved users (403) and logged-out users (401)

## Task Commits

Each task was committed atomically:

1. **Task 1: Search endpoint tests** - `eedb2b6` (feat)
   - 7 tests covering basic search, isolation, cross-CPT search, validation, access control

2. **Task 2: Dashboard, reminders, and todos endpoint tests** - `4d650fb` (feat)
   - 13 tests covering dashboard counts, reminders filtering, todos completion status

## Files Created/Modified

- `tests/Wpunit/SearchDashboardTest.php` - 598 lines, 20 tests covering 4 endpoints

## Test Coverage by Endpoint

### Search (/stadion/v1/search)
- `test_search_returns_matching_person` - Basic search functionality
- `test_search_isolation_between_users` - User A cannot see User B's contacts
- `test_search_across_post_types` - Returns both people and companies
- `test_search_validation_empty_query` - Empty query returns 400
- `test_search_validation_single_character` - Min 2 chars required
- `test_search_blocked_for_unapproved_user` - 403 for unapproved
- `test_search_blocked_for_logged_out_user` - 401 for logged out

### Dashboard (/stadion/v1/dashboard)
- `test_dashboard_returns_correct_counts` - Accurate people/companies/dates counts
- `test_dashboard_isolation_between_users` - Users see only their own counts
- `test_dashboard_empty_for_new_user` - New user gets zero counts
- `test_dashboard_blocked_for_unapproved_user` - 403 for unapproved

### Reminders (/stadion/v1/reminders)
- `test_reminders_returns_upcoming_dates` - Returns dates within range
- `test_reminders_filters_by_days_ahead` - Respects days_ahead parameter
- `test_reminders_validation_zero_days` - days_ahead=0 returns 400
- `test_reminders_validation_days_too_large` - days_ahead>365 returns 400
- `test_reminders_blocked_for_unapproved_user` - 403 for unapproved

### Todos (/stadion/v1/todos)
- `test_todos_returns_uncompleted_todos` - Default returns only incomplete
- `test_todos_returns_all_with_completed_filter` - completed=true includes all
- `test_todos_isolation_between_users` - User A cannot see User B's todos
- `test_todos_blocked_for_unapproved_user` - 403 for unapproved

## Decisions Made

- **REST server initialization:** Manually create WP_REST_Server and trigger rest_api_init action since test environment doesn't automatically set REST_REQUEST
- **STADION_REST_API instantiation:** Must be done explicitly in tests since stadion_is_rest_request() returns false in test environment
- **Boolean parameter handling:** Use string 'true' instead of boolean true for REST params to match real HTTP query string behavior

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] REST routes not registered (404 errors)**
- **Found during:** Initial test execution
- **Issue:** All REST endpoints returned 404 because routes weren't registered
- **Fix:** Added explicit `new \STADION_REST_API()` and `do_action('rest_api_init')` in set_up()
- **Verification:** All 20 tests pass

**2. [Rule 1 - Bug Fix] Boolean parameter handling**
- **Found during:** test_todos_returns_all_with_completed_filter
- **Issue:** Boolean true passed to set_param() wasn't being recognized by REST API validation
- **Fix:** Changed to string 'true' to match HTTP query string behavior
- **Verification:** Test passes with correct filtering

**3. [Rule 1 - Bug Fix] User login conflicts**
- **Found during:** test_dashboard_empty_for_new_user returning 401
- **Issue:** User login 'newuser' conflicting with other tests
- **Fix:** Changed to unique login 'emptyuser'
- **Verification:** Test passes

---

**Total deviations:** 3 auto-fixed (1 blocking, 2 bug fixes)
**Impact on plan:** All fixes necessary to run tests correctly. No scope creep.

## Issues Encountered

None beyond the auto-fixed deviations above.

## Verification Checklist

- [x] `vendor/bin/codecept run Wpunit SearchDashboardTest` passes all tests (20 tests, 44 assertions)
- [x] Search endpoint tested with isolation
- [x] Dashboard counts correct per user
- [x] Reminders filtered by days_ahead
- [x] Unapproved users blocked from endpoints (403)

## Next Phase Readiness

- SearchDashboardTest complete and passing
- REST API testing patterns established for future endpoint tests
- doRestRequest() helper available for reuse
- Ready for 23-03 if timeline endpoint tests are planned

---
*Phase: 23-rest-api-data-model-tests*
*Completed: 2026-01-13*
