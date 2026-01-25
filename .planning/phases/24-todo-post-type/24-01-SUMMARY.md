---
phase: 24-todo-post-type
plan: 01
subsystem: api
tags: [cpt, acf, rest-api, access-control, wordpress]

# Dependency graph
requires:
  - phase: 23
    provides: Testing infrastructure for validation
provides:
  - stadion_todo custom post type registered
  - ACF field group for todo metadata
  - Access control integration for todos
affects: [24-02, 24-03, 24-04, 25, 26, 27, 28]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - stadion_todo CPT follows existing CPT registration patterns
    - ACF field group mirrors visibility_settings for consistency

key-files:
  created:
    - acf-json/group_todo_fields.json
  modified:
    - includes/class-post-types.php
    - includes/class-access-control.php

key-decisions:
  - "No shared visibility option for todos (only private/workspace)"
  - "Single related_person field (not multi-select like important_date)"

patterns-established:
  - "stadion_todo follows same access control pattern as other CPTs"

issues-created: []

# Metrics
duration: 8min
completed: 2026-01-14
---

# Phase 24 Plan 01: Register stadion_todo CPT Summary

**stadion_todo custom post type registered with ACF fields and full access control integration**

## Performance

- **Duration:** 8 min
- **Started:** 2026-01-14T10:00:00Z
- **Completed:** 2026-01-14T10:08:00Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Registered `stadion_todo` custom post type with REST API support (`/wp/v2/todos`)
- Created ACF field group with related_person, is_completed, due_date, visibility, workspaces
- Integrated access control: query filtering, REST API filtering, workspace support

## Task Commits

Each task was committed atomically:

1. **Task 1: Register stadion_todo custom post type** - `683eb31` (feat)
2. **Task 2: Create ACF field group for todo metadata** - `766311e` (feat)
3. **Task 3: Add access control for stadion_todo** - `16b52e7` (feat)

**Plan metadata:** To be committed with this summary

## Files Created/Modified

- `acf-json/group_todo_fields.json` - ACF field group defining todo metadata fields
- `includes/class-post-types.php` - Added register_todo_post_type() method
- `includes/class-access-control.php` - Added stadion_todo to controlled post types and REST hooks

## Decisions Made

1. **Visibility options**: Only private and workspace visibility (no "shared with specific users" option for todos, simpler model)
2. **Single person relation**: related_person is single post_object, not multi-select (unlike important_date which can have multiple related people)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## Next Phase Readiness

- stadion_todo CPT registered and functional
- Ready for Plan 24-02: REST API endpoints for CRUD operations
- Foundation established for migrating existing comment-based todos

---
*Phase: 24-todo-post-type*
*Completed: 2026-01-14*
