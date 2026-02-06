---
phase: 32-todo-data-model
plan: 01
subsystem: api
tags: [acf, rest-api, wp-cli, migration, todo, multi-person]

# Dependency graph
requires:
  - phase: 24-28
    provides: stadion_todo CPT with custom post statuses
provides:
  - notes ACF field for todo descriptions
  - related_persons multi-person field
  - REST API endpoints returning persons array
  - wp prm todos migrate-persons CLI command
  - Timeline endpoint with multi-person support
affects: [phase-33-todo-modal, phase-34-cross-person-display]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - LIKE query for ACF serialized array lookup
    - Backward compatibility with deprecated fields

key-files:
  created: []
  modified:
    - acf-json/group_todo_fields.json
    - includes/class-rest-todos.php
    - includes/class-wp-cli.php
    - includes/class-comment-types.php

key-decisions:
  - "Keep deprecated person_id/person_name/person_thumbnail for backward compatibility"
  - "Use LIKE query with quoted ID format for serialized array lookups"
  - "Sanitize notes with wp_kses_post for XSS protection"

patterns-established:
  - "Multi-value ACF fields stored as serialized arrays, queried with LIKE"
  - "Backward compatibility fields during API transitions"

issues-created: []

# Metrics
duration: 3min
completed: 2026-01-14
---

# Phase 32 Plan 01: Todo Data Model Enhancement Summary

**Added notes field and multi-person support to todo data model with REST API updates and migration command**

## Performance

- **Duration:** 3 min
- **Started:** 2026-01-14T19:16:35Z
- **Completed:** 2026-01-14T19:19:59Z
- **Tasks:** 5
- **Files modified:** 4

## Accomplishments

- Added WYSIWYG notes field to todo ACF field group for detailed descriptions
- Changed related_person to related_persons with multi-select enabled
- Updated all REST API endpoints to return persons array with backward compatibility
- Created wp prm todos migrate-persons CLI command for data migration
- Updated timeline endpoint to query and return multi-person data

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Notes ACF Field** - `625f701` (feat)
2. **Task 2: Change related_person to Multi-Value** - `c98fe6b` (feat)
3. **Task 3: Update REST API for multi-person and notes** - `28facb1` (feat)
4. **Task 4: Create Migration CLI Command** - `9220533` (feat)
5. **Task 5: Update Timeline Endpoint** - `0893892` (feat)

## Files Created/Modified

- `acf-json/group_todo_fields.json` - Added notes WYSIWYG field, changed related_person to related_persons with multiple:1
- `includes/class-rest-todos.php` - Updated format_todo, get_person_todos, create_person_todo, update_todo for multi-person and notes
- `includes/class-wp-cli.php` - Added migrate_persons command to RONDO_Todos_CLI_Command class
- `includes/class-comment-types.php` - Updated get_timeline method for multi-person queries and response format

## Decisions Made

1. **Backward compatibility fields** - Keep deprecated person_id, person_name, person_thumbnail in API responses during v3.3 transition. Frontend will migrate to using persons array in Phase 33.

2. **LIKE query for serialized arrays** - ACF stores multi-value fields as serialized PHP arrays. Using LIKE with format `"%d"` (e.g., `"123"`) matches the ID within the serialized string safely.

3. **XSS sanitization** - Notes field uses wp_kses_post() for HTML sanitization, consistent with how notes/activities handle rich text content.

4. **Field key preservation** - Kept ACF field key as field_todo_related_person even though name changed to related_persons. This allows get_field() to work with either name during migration.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- Data model ready for frontend integration
- REST API returns all necessary data for TodoModal enhancement
- Migration command ready to run on production after deployment
- Timeline endpoint ready for cross-person todo display

---
*Phase: 32-todo-data-model*
*Completed: 2026-01-14*
