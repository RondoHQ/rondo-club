---
phase: 114-user-preferences-backend
plan: 01
subsystem: api
tags: [wordpress, rest-api, user-preferences, custom-fields]

# Dependency graph
requires:
  - phase: 113-frontend-pagination
    provides: Frontend list infrastructure ready for preference integration
  - phase: 106-custom-fields
    provides: CustomFields\Manager::get_fields() for validation
provides:
  - REST API endpoints for storing user-specific column preferences
  - Server-side validation against active custom fields
  - Default columns fallback for new users
affects: [115-column-customization-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - User preferences stored in wp_usermeta (follows theme_preferences pattern)
    - Custom field validation via Manager::get_fields()
    - Silent filtering of invalid columns (deleted fields)

key-files:
  created: []
  modified:
    - includes/class-rest-api.php

key-decisions:
  - "Store preferences in wp_usermeta with key 'stadion_people_list_preferences'"
  - "Empty array and reset:true both reset to defaults"
  - "Invalid column IDs filtered silently (not rejected with error)"
  - "Core columns (team, labels, modified) always available"

patterns-established:
  - "REST API preference endpoints follow dashboard-settings pattern"
  - "Default columns defined as class constant DEFAULT_LIST_COLUMNS"
  - "available_columns metadata includes type and custom flag for UI rendering"

# Metrics
duration: ~35min
completed: 2026-01-29
---

# Phase 114 Plan 01: User Preferences Backend Summary

**REST API endpoints for per-user People list column preferences with validation against active custom fields**

## Performance

- **Duration:** ~35 minutes
- **Started:** 2026-01-29T15:25:00Z (checkpoint initiated)
- **Completed:** 2026-01-29T16:00:00Z (checkpoint approved)
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments
- GET /rondo/v1/user/list-preferences endpoint returns visible_columns and available_columns metadata
- PATCH /rondo/v1/user/list-preferences endpoint saves preferences with validation
- Invalid columns (deleted custom fields) filtered silently to prevent UI breaks
- Default columns ['team', 'labels', 'modified'] for new users and reset cases

## Task Commits

Each task was committed atomically:

1. **Task 1: Register list-preferences endpoints** - `044b5ae` (feat)
2. **Task 2: Implement GET and PATCH handler methods** - `92a381c` (feat)
3. **Task 3: Verify endpoints in browser** - Checkpoint approved (all 7 tests passed)

## Files Created/Modified
- `includes/class-rest-api.php` - Added two REST route registrations, four handler methods, and two constants (DEFAULT_LIST_COLUMNS, CORE_LIST_COLUMNS)

## Decisions Made

**1. Store preferences in wp_usermeta with key 'stadion_people_list_preferences'**
- Rationale: Follows existing pattern from 'stadion_theme_preferences' and 'stadion_dashboard_settings'
- Enables per-user preferences that sync across devices
- Survives browser cache clears

**2. Empty array and reset:true both reset to defaults**
- Rationale: Per CONTEXT.md requirement - both should reset to default state
- Empty array = user wants "no custom columns" = reset to defaults
- reset:true flag = explicit reset action

**3. Invalid column IDs filtered silently (not rejected with error)**
- Rationale: Per must_haves - "Invalid column IDs are filtered silently (not rejected)"
- Prevents UI breaks when custom fields are deleted after user saved preferences
- User sees valid columns only, no error messages for stale preferences

**4. Core columns (team, labels, modified) always available**
- Rationale: These columns exist regardless of custom field configuration
- Defined in CORE_LIST_COLUMNS constant for consistency
- Combined with active custom fields via Manager::get_fields()

## Deviations from Plan

None - plan executed exactly as written. All endpoints, validation logic, and default behaviors implemented per specification.

## Issues Encountered

None - implementation followed existing dashboard-settings pattern closely, which provided clear guidance for authentication, validation, and response structure.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Phase 115 (Column Customization UI):**
- Backend API complete and verified with 7 browser tests
- available_columns metadata includes type and custom flag for UI rendering
- visible_columns array ready for state management
- Default columns established and tested

**Verification completed:**
- GET returns defaults for new users
- PATCH saves custom columns successfully
- Invalid columns filtered silently
- Reset flag and empty array both reset to defaults
- Unauthenticated requests properly rejected
- Each user has independent preferences (implicit in user_meta design)

**No blockers or concerns for next phase.**

---
*Phase: 114-user-preferences-backend*
*Completed: 2026-01-29*
