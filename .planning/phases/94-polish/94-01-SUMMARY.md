---
phase: 94-polish
plan: 01
subsystem: api
tags: [acf, rest-api, validation, custom-fields, menu-order]

# Dependency graph
requires:
  - phase: 87-acf-foundation
    provides: CustomFields Manager class with ACF-native storage
  - phase: 88-settings-ui
    provides: REST API for custom field management
provides:
  - Field reordering via menu_order property
  - PUT /prm/v1/custom-fields/{post_type}/order endpoint
  - Unique constraint validation for custom field values
  - reorderCustomFields frontend API method
affects: [94-02, ui-drag-drop, field-editor]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - ACF validate_value filter for custom validation
    - menu_order for field ordering

key-files:
  created:
    - includes/customfields/class-validation.php
  modified:
    - includes/customfields/class-manager.php
    - includes/class-rest-custom-fields.php
    - src/api/client.js
    - functions.php

key-decisions:
  - "menu_order starts at 1, not 0 (ACF convention)"
  - "Unique validation scoped to current user's posts (per-user uniqueness)"
  - "Validation class placed in customfields directory for PSR-4 autoloading"

patterns-established:
  - "ACF validate_value hook pattern for custom field validation"

# Metrics
duration: 4min
completed: 2026-01-20
---

# Phase 94 Plan 01: Backend Reorder and Validation Summary

**Field reordering via menu_order property with bulk reorder REST endpoint, plus unique constraint validation via ACF hook**

## Performance

- **Duration:** 4 min
- **Started:** 2026-01-20T22:13:25Z
- **Completed:** 2026-01-20T22:17:30Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments

- Added menu_order and unique to Manager's UPDATABLE_PROPERTIES
- Created reorder_fields() method for bulk field ordering
- Added PUT /prm/v1/custom-fields/{post_type}/order endpoint
- Created Validation class with ACF validate_value hook for unique constraint
- Added reorderCustomFields method to frontend API client

## Task Commits

Each task was committed atomically:

1. **Task 1: Add menu_order and reorder_fields to Manager** - `f972e51` (feat)
2. **Task 2: Add reorder endpoint and unique to REST API** - `2435136` (feat)
3. **Task 3: Add unique validation class** - `add124c` (feat)

## Files Created/Modified

- `includes/customfields/class-manager.php` - Added menu_order, unique properties and reorder_fields method
- `includes/class-rest-custom-fields.php` - Added PUT /order endpoint and unique param
- `includes/customfields/class-validation.php` - New validation class with unique constraint
- `src/api/client.js` - Added reorderCustomFields API method
- `functions.php` - Load Validation class

## Decisions Made

- **menu_order starts at 1:** Following ACF convention where menu_order is 1-indexed rather than 0-indexed
- **Per-user uniqueness:** Unique validation only checks posts owned by the current user, allowing different users to have the same values
- **Validation class location:** Placed in customfields/ directory to match PSR-4 autoloading expectations

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Moved validation class to correct directory**
- **Found during:** Task 3 (Validation class creation)
- **Issue:** Initially created class-validation.php in includes/ but Composer PSR-4 autoloading required it in includes/customfields/
- **Fix:** Moved file to includes/customfields/class-validation.php and regenerated autoloader
- **Files modified:** includes/customfields/class-validation.php
- **Verification:** Deploy succeeded without class not found warnings
- **Committed in:** add124c (Task 3 commit amended)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Minor file location adjustment for proper autoloading. No scope creep.

## Issues Encountered

None - plan executed as specified after autoload path correction.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Backend reorder and validation infrastructure complete
- Ready for Plan 02: Frontend UI for drag-and-drop reordering
- Unique validation will be enforced automatically when fields are marked unique=true

---
*Phase: 94-polish*
*Completed: 2026-01-20*
