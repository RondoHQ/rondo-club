---
phase: 95-backend-foundation
plan: 01
subsystem: api
tags: [wordpress, cpt, acf, feedback, rest-api]

# Dependency graph
requires: []
provides:
  - caelis_feedback custom post type registered
  - ACF field group for feedback metadata (11 fields)
  - REST API endpoint /wp-json/wp/v2/feedback
  - Conditional logic for bug vs feature request fields
affects: [96-rest-api, 97-frontend-submission, 98-admin-management]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - CPT registration pattern for feedback
    - ACF conditional logic for type-specific fields

key-files:
  created:
    - acf-json/group_feedback_fields.json
  modified:
    - includes/class-post-types.php

key-decisions:
  - "Use ACF select field for status instead of custom post statuses (simpler than prm_todo pattern)"
  - "Feedback is global scope (not workspace-scoped)"

patterns-established:
  - "Feedback CPT with ACF metadata pattern"
  - "Conditional field visibility based on feedback type"

# Metrics
duration: 5min
completed: 2026-01-21
---

# Phase 95 Plan 01: CPT and ACF Field Group Summary

**caelis_feedback CPT with 11-field ACF group including type/status/priority and conditional bug/feature request fields**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-21T12:00:00Z
- **Completed:** 2026-01-21T12:05:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Registered caelis_feedback custom post type with REST API support
- Created ACF field group with 11 fields for feedback metadata
- Implemented conditional logic showing bug-specific or feature request fields based on type
- Enabled REST API endpoint at /wp-json/wp/v2/feedback

## Task Commits

Each task was committed atomically:

1. **Task 1: Register caelis_feedback custom post type** - `d2e4de0` (feat)
2. **Task 2: Create ACF field group for feedback metadata** - `df07bd7` (feat)

## Files Created/Modified
- `includes/class-post-types.php` - Added register_feedback_post_type() method
- `acf-json/group_feedback_fields.json` - ACF field group with 11 fields

## Decisions Made
- Used ACF select field for status (new/in_progress/resolved/declined) instead of custom post statuses - simpler approach than prm_todo pattern
- Menu position 26 places Feedback after Settings area in WordPress admin
- Used dashicons-megaphone for feedback menu icon

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- CPT and ACF fields ready for REST API extension in Phase 96
- Custom REST endpoints can query feedback by status, type, priority
- Frontend can create feedback via /wp-json/wp/v2/feedback with ACF fields

---
*Phase: 95-backend-foundation*
*Completed: 2026-01-21*
