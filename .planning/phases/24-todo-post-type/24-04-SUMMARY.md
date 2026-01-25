# Plan 24-04 Summary: Update Tests for Todo CPT

**Phase:** 24 - Todo Post Type
**Status:** COMPLETED
**Duration:** ~15 minutes

## Goal

Update PHPUnit tests to cover the new todo CPT system, verify dashboard integration works correctly with the new data model.

## Completed Tasks

### Task 1: Verify dashboard counts todos from CPT correctly
- **Status:** Already completed in 24-03
- `count_open_todos()` in `class-rest-api.php` queries `stadion_todo` CPT with access control filtering

### Task 2: Create PHPUnit tests for todo CPT
- **Status:** Completed
- Created `tests/Wpunit/TodoCptTest.php` with 16 tests covering:
  - CPT registration (post type exists, REST support)
  - Access control (user isolation, admin visibility)
  - REST API CRUD (GET /todos, GET /people/{id}/todos, POST, PUT, DELETE)
  - Dashboard integration (open_todos_count)
  - Completion filter (excludes/includes completed)
- Updated `SearchDashboardTest.php` to use CPT-based todos:
  - Changed `createTodo()` helper to create `stadion_todo` posts instead of comments
  - Added `STADION_REST_Todos` instantiation for route registration

### Task 3: Run full test suite and verify no regressions
- **Status:** Completed
- All 146 tests pass (364 assertions)
- No regressions introduced

## Key Decisions

1. **Keep existing tests in SearchDashboardTest.php** - Rather than removing the todo tests from SearchDashboardTest.php, we updated them to work with the new CPT system. This maintains test coverage for the `/stadion/v1/todos` endpoint from multiple test classes.

2. **Create dedicated TodoCptTest.php** - Added comprehensive tests specifically for the todo CPT in a new test class for clearer organization.

## Files Changed

| File | Change |
|------|--------|
| `tests/Wpunit/TodoCptTest.php` | Created: 504 lines, 16 tests |
| `tests/Wpunit/SearchDashboardTest.php` | Updated: CPT-based `createTodo()` helper |
| `CHANGELOG.md` | Added v1.78.0 entry |
| `style.css` | Version bump 1.77.0 -> 1.78.0 |
| `package.json` | Version bump 1.77.0 -> 1.78.0 |

## Commits

| Hash | Message |
|------|---------|
| bc20c6b | test(24-04): add PHPUnit tests for todo CPT |
| 7cdd007 | refactor(24-04): update SearchDashboardTest to use CPT-based todos |
| 3570a8c | chore(24-04): bump version to 1.78.0 |

## Test Coverage

**New tests in TodoCptTest.php (16 tests):**
- test_stadion_todo_post_type_registered
- test_stadion_todo_has_rest_support
- test_user_can_only_see_own_todos
- test_admin_sees_own_todos
- test_get_todos_returns_user_todos
- test_get_person_todos_filters_by_person
- test_create_todo_creates_stadion_todo_post
- test_update_todo_changes_is_completed
- test_delete_todo_removes_post
- test_dashboard_counts_open_todos
- test_completed_todos_not_counted
- test_dashboard_counts_only_own_todos
- test_todos_blocked_for_unapproved_user
- test_todos_blocked_for_logged_out_user
- test_todos_excludes_completed_by_default
- test_todos_includes_completed_with_filter

**Full test suite:**
- 146 tests, 364 assertions, all passing

## Version

1.78.0
