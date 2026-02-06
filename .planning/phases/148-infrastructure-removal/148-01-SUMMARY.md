---
phase: 148-infrastructure-removal
plan: 01
subsystem: database, api
tags: [wordpress, cpt, acf, wp-cli, rest-api]

# Dependency graph
requires:
  - phase: 147-birthdate-field-widget
    provides: Birthdate stored directly on person records
provides:
  - Backend completely free of important_date CPT infrastructure
  - Clean codebase with no date_type taxonomy references
  - Simplified reminders and iCal systems using birthdate field
affects: [148-02-frontend-removal]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Birthdates stored directly on person post_meta"
    - "iCal feeds generated from person birthdate field"
    - "Reminders query persons with birthdate meta"

key-files:
  created: []
  modified:
    - includes/class-post-types.php
    - includes/class-taxonomies.php
    - includes/class-access-control.php
    - includes/class-rest-api.php
    - includes/class-reminders.php
    - includes/class-ical-feed.php
    - includes/class-auto-title.php
    - includes/class-rest-base.php
    - includes/class-rest-people.php
    - includes/class-rest-import-export.php
    - includes/class-user-roles.php
    - includes/class-wp-cli.php
    - includes/class-vcard-import.php
    - includes/class-vcard-export.php
    - includes/class-google-contacts-import.php
    - includes/class-google-contacts-api-import.php
    - tests/Wpunit/CptCrudTest.php
    - tests/Wpunit/SmokeTest.php
    - tests/Wpunit/UserIsolationTest.php
    - tests/Wpunit/SearchDashboardTest.php
    - tests/Wpunit/RelationshipsSharesTest.php
    - tests/Support/StadionTestCase.php

key-decisions:
  - "Delete production data first before removing code to prevent orphaned records"
  - "Keep deprecated WP-CLI commands as error stubs for safety"
  - "Update import systems to store birthdate directly on person records"

patterns-established:
  - "Birthdate stored as ACF field on person, not separate important_date post"
  - "is_deceased computed field always returns false (death date feature removed)"
  - "birth_year computed from person birthdate field"

# Metrics
duration: 45min
completed: 2026-02-06
---

# Phase 148 Plan 01: Backend Code Removal Summary

**Removed Important Dates CPT and date_type taxonomy from PHP codebase, deleted 1069 production records, and updated all dependent systems to use birthdate directly on person records**

## Performance

- **Duration:** 45 min
- **Started:** 2026-02-06T10:00:00Z
- **Completed:** 2026-02-06T10:45:00Z
- **Tasks:** 3
- **Files modified:** 24

## Accomplishments

- Deleted all 1069 important_date posts and 40 date_type terms from production database
- Removed CPT registration, taxonomy registration, and all related hooks
- Updated 16 PHP classes to remove important_date references
- Updated reminders and iCal systems to generate from person birthdate field
- Cleaned all test files to remove important_date fixtures and assertions

## Task Commits

Each task was committed atomically:

1. **Task 1: Delete production data via WP-CLI** - No commit (database operations only)
2. **Task 2: Remove backend PHP code** - `01a7bc9e` (feat)
3. **Task 3: Remove ACF, utility scripts, and test fixtures** - `29e331ad` (chore)
4. **Task 3 continued: Rename iCal methods** - `65a7aa4c` (refactor)

## Files Created/Modified

**Deleted:**
- `acf-json/group_important_date_fields.json` - ACF field group definition
- `bin/cleanup-duplicate-dates.php` - Utility script

**Modified (PHP classes):**
- `includes/class-post-types.php` - Removed CPT registration
- `includes/class-taxonomies.php` - Removed taxonomy registration
- `includes/class-access-control.php` - Removed from controlled post types
- `includes/class-rest-api.php` - Removed dashboard stats and filters
- `includes/class-reminders.php` - Rewrote to query person birthdate
- `includes/class-ical-feed.php` - Rewrote to generate from person birthdate
- `includes/class-auto-title.php` - Removed all important_date hooks
- `includes/class-rest-base.php` - Removed format_date method
- `includes/class-rest-people.php` - Removed get_dates_by_person, updated birth_year
- `includes/class-rest-import-export.php` - Updated birthday export
- `includes/class-user-roles.php` - Removed from post types array
- `includes/class-wp-cli.php` - Deprecated commands with error stubs
- `includes/class-vcard-import.php` - Store birthdate on person
- `includes/class-vcard-export.php` - Read birthdate from person
- `includes/class-google-contacts-import.php` - Store birthdate on person
- `includes/class-google-contacts-api-import.php` - Store birthdate on person

**Modified (Tests):**
- `tests/Wpunit/CptCrudTest.php` - Removed all important_date tests
- `tests/Wpunit/SmokeTest.php` - Removed CPT registration test
- `tests/Wpunit/UserIsolationTest.php` - Removed important_date access tests
- `tests/Wpunit/SearchDashboardTest.php` - Updated to use birthdate
- `tests/Wpunit/RelationshipsSharesTest.php` - Updated computed field tests
- `tests/Support/StadionTestCase.php` - Removed createImportantDate helper

## Decisions Made

- **Delete production data before code removal** - Data must be deleted while CPT is still registered, otherwise WP-CLI commands fail
- **Keep WP-CLI deprecated commands as error stubs** - Prevents confusing "command not found" errors if old scripts call them
- **is_deceased always returns false** - Death date feature removed with Important Dates, simplified computation
- **Rename get_workspace_important_dates to get_workspace_birthdays** - Clearer naming after infrastructure removal

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- **Date_type terms recreated after deletion** - The taxonomy registration code was still active after deleting terms, causing them to be recreated by `add_default_date_types()`. This was expected behavior and resolved when code was removed in Task 2.
- **Tests cannot run locally** - Database configuration not available in local environment; tests verified via grep checks on removed code.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Backend completely clean of important_date infrastructure
- Ready for Plan 02: Frontend removal (React components, routes, types)
- DO NOT deploy until Plan 02 complete for single coordinated release

---
*Phase: 148-infrastructure-removal*
*Completed: 2026-02-06*
