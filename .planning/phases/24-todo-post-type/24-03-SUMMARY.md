---
phase: 24-todo-post-type
plan: 03
subsystem: api
tags: [wp-cli, migration, cleanup, refactor]

# Dependency graph
requires:
  - phase: 24-01
    provides: stadion_todo CPT registration
  - phase: 24-02
    provides: RONDO_REST_Todos class for CRUD operations
provides:
  - WP-CLI migration command for todos
  - Clean comment-types.php without todo code
  - Clean rest-api.php without legacy todo endpoints
  - CPT-based dashboard todo count
affects: [24-04, frontend]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WP-CLI command class pattern with --dry-run support
    - CPT-based counting replacing comment-based counting

key-files:
  created: []
  modified:
    - includes/class-wp-cli.php
    - includes/class-comment-types.php
    - includes/class-rest-api.php

key-decisions:
  - "Migration deletes original comments after successful CPT creation"
  - "Dashboard count_open_todos() uses WP_Query with access control filtering"

patterns-established:
  - "RONDO_Todos_CLI_Command follows existing CLI command patterns"

issues-created: []

# Metrics
duration: 15min
completed: 2026-01-14
---

# Phase 24 Plan 03: Migration Command and Legacy Cleanup Summary

**WP-CLI migration command created and 13 todos migrated from comments to CPT, legacy code removed**

## Performance

- **Duration:** 15 min
- **Started:** 2026-01-14T14:30:00Z
- **Completed:** 2026-01-14T14:45:00Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Created `RONDO_Todos_CLI_Command` class with `migrate` subcommand for todo migration
- Successfully migrated 13 comment-based todos to stadion_todo CPT posts on production
- Removed all todo-related code from `RONDO_Comment_Types` (152 lines)
- Removed legacy `get_all_todos()` and `/rondo/v1/todos` route from `RONDO_REST_API`
- Updated `count_open_todos()` to query CPT instead of comments

## Task Commits

Each task was committed atomically:

1. **Task 1: Create WP-CLI migration command for todos** - `305f374` (feat)
2. **Task 2: Remove todo endpoints from class-comment-types.php** - `1bd6ca6` (refactor)
3. **Task 3: Update class-rest-api.php to remove old todo endpoints** - `852edd6` (refactor)

## Files Created/Modified

- `includes/class-wp-cli.php` - Added `RONDO_Todos_CLI_Command` class with migrate subcommand
- `includes/class-comment-types.php` - Removed TYPE_TODO constant, todo routes, todo methods, todo meta
- `includes/class-rest-api.php` - Removed get_all_todos(), updated count_open_todos() to use CPT

## Decisions Made

1. **Migration behavior**: Original comments are deleted after successful CPT post creation (not preserved)
2. **Dashboard count**: Uses `WP_Query` with automatic access control filtering via `RONDO_Access_Control` hooks

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## Verification Results

- `wp prm todos migrate --dry-run` works and shows count of todos
- Migration ran successfully: 13 todos migrated, 0 skipped, 0 failed
- After migration: `wp prm todos migrate --dry-run` reports "No comment-based todos found"
- `grep -c "todo" includes/class-comment-types.php` returns 0
- `grep -c "get_all_todos" includes/class-rest-api.php` returns 0
- Notes (5) and activities (25) remain in comments table unaffected
- Todos accessible via `/rondo/v1/todos` from new `RONDO_REST_Todos` class

## Next Phase Readiness

- Migration complete: all existing todos now in CPT
- Legacy code removed: no duplicate endpoints
- Ready for Plan 24-04: Frontend migration to use CPT-based endpoints

---
*Phase: 24-todo-post-type*
*Completed: 2026-01-14*
