---
phase: 25-todo-ui-migration
plan: 01
subsystem: api
tags: [rest-api, timeline, todos, cpt, react-query, cache-invalidation]

# Dependency graph
requires:
  - phase: 24-todo-post-type
    provides: prm_todo CPT with REST endpoints
provides:
  - Timeline endpoint returns todos alongside notes and activities
  - usePersonTodos hook for direct person todo fetching
  - Proper cache invalidation across all todo-related views
affects: [PersonDetail, TodosList, Dashboard]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Timeline endpoint merges data from comments + CPT
    - Broad query invalidation for dashboard hooks (no personId available)

key-files:
  created: []
  modified:
    - includes/class-comment-types.php
    - src/hooks/usePeople.js
    - src/hooks/useDashboard.js

key-decisions:
  - "Merge todos into timeline with sorting: Combined CPT query with comments and sorted by created date"
  - "Broad timeline invalidation in dashboard: Dashboard hooks invalidate ['people', 'timeline'] since personId not available"

patterns-established:
  - "Timeline endpoint pattern: Can merge data from multiple sources (comments + CPT) with unified format"

issues-created: []

# Metrics
duration: 2min
completed: 2026-01-14
---

# Phase 25 Plan 01: Timeline Integration Summary

**Timeline endpoint now returns todos from CPT merged with notes and activities, with proper cache invalidation across all todo-related views.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-14T13:37:02Z
- **Completed:** 2026-01-14T13:39:02Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- Timeline endpoint fetches todos from prm_todo CPT and merges with notes/activities
- Added usePersonTodos hook for direct person todo fetching
- Updated cache invalidation in usePeople.js mutations (timeline + todos)
- Updated cache invalidation in useDashboard.js mutations (timeline)

## Task Commits

Each task was committed atomically:

1. **Task 1: Update timeline endpoint to include CPT todos** - `c328c6b` (feat)
2. **Task 2: Add usePersonTodos hook for direct todo fetching** - `7eefe76` (feat)
3. **Task 3: Update useDashboard hooks to invalidate timeline** - `1c7bf4f` (feat)

## Files Created/Modified
- `includes/class-comment-types.php` - Updated get_timeline() to fetch and merge todos from prm_todo CPT
- `src/hooks/usePeople.js` - Added todos key, usePersonTodos hook, and updated todo mutations
- `src/hooks/useDashboard.js` - Added timeline invalidation to useUpdateTodo and useDeleteTodo

## Decisions Made
- **Merge todos into timeline with sorting:** Combined CPT query with comments query and applied unified date-based sorting
- **Broad timeline invalidation in dashboard:** Dashboard hooks invalidate all timeline queries since personId is not available

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## Next Phase Readiness
- Timeline endpoint returns todos for a person
- PersonDetail page should now show todos in the Todos section
- Cache invalidation ensures all todo-related views refresh on changes
- Ready for next plan in phase 25

---
*Phase: 25-todo-ui-migration*
*Completed: 2026-01-14*
