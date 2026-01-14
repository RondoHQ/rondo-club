---
phase: 27-pending-response-ui
plan: 02
subsystem: ui
tags: [react, tailwind, todo, awaiting-response]

# Dependency graph
requires:
  - phase: 27-01
    provides: getAwaitingDays and getAwaitingUrgencyClass helper functions
provides:
  - Awaiting response badge in TimelineView
  - Awaiting response checkbox in GlobalTodoModal
  - Awaiting response indicator in PersonDetail sidebar
affects: [todo-filtering, statistics]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Consistent awaiting response badge styling across components

key-files:
  created: []
  modified:
    - src/components/Timeline/TimelineView.jsx
    - src/components/Timeline/GlobalTodoModal.jsx
    - src/pages/People/PersonDetail.jsx

key-decisions:
  - "Badge shows 'Awaiting response' for 0 days, 'Awaiting Xd' for 1+ days"
  - "Smaller badge in PersonDetail sidebar (px-1.5 vs px-2) to fit tight space"

patterns-established:
  - "Awaiting response badge pattern: Clock icon + day count + urgency class"

issues-created: []

# Metrics
duration: 8min
completed: 2026-01-14
---

# Phase 27 Plan 02: Complete Awaiting Response UI Summary

**Awaiting response badges added to TimelineView, GlobalTodoModal checkbox, and PersonDetail sidebar with consistent urgency coloring**

## Performance

- **Duration:** 8 min
- **Started:** 2026-01-14T14:20:00Z
- **Completed:** 2026-01-14T14:28:00Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- TimelineView now shows awaiting response badges on todos in the timeline
- GlobalTodoModal allows marking todos as awaiting response at creation time
- PersonDetail sidebar displays awaiting response indicator inline with todos
- All indicators use consistent styling: Clock icon, day count, urgency colors (yellow/orange/red)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add awaiting response indicator to TimelineView** - `2922b58` (feat)
2. **Task 2: Add awaiting response checkbox to GlobalTodoModal** - `db7c503` (feat)
3. **Task 3: Add awaiting response indicator to PersonDetail sidebar** - `bcf5f4d` (feat)

## Files Created/Modified

- `src/components/Timeline/TimelineView.jsx` - Added Clock icon import, awaiting helpers import, and badge after todo due date
- `src/components/Timeline/GlobalTodoModal.jsx` - Added awaitingResponse state, reset on open, checkbox UI, and data inclusion
- `src/pages/People/PersonDetail.jsx` - Added Clock icon and awaiting helpers imports, badge in todo sidebar

## Decisions Made

- Badge text: Shows "Awaiting response" for 0 days, "Awaiting Xd" for 1+ days (consistent with Plan 01)
- PersonDetail uses slightly smaller badge (px-1.5 py-0.5) to fit the tighter sidebar layout
- GlobalTodoModal checkbox placed after due date input, before submit buttons

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## Next Phase Readiness

- Awaiting response UI complete across all todo contexts
- Ready for Phase 28: Todo Filtering/Statistics
- Users can now see and set awaiting response status from any todo display

---
*Phase: 27-pending-response-ui*
*Completed: 2026-01-14*
