---
phase: 27-pending-response-ui
plan: 01
subsystem: ui
tags: [react, todo, timeline, dashboard, tailwind]

# Dependency graph
requires:
  - phase: 26
    provides: awaiting_response and awaiting_response_since fields on todos
provides:
  - Awaiting response toggle in TodoModal
  - Awaiting response indicator in TodosList with day count
  - Awaiting response section in Dashboard
  - Helper functions for awaiting days calculation and urgency styling
affects: [28-todo-filtering]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "getAwaitingDays() returns days since awaiting, null if not awaiting"
    - "getAwaitingUrgencyClass() returns yellow/orange/red based on days"

key-files:
  created: []
  modified:
    - src/components/Timeline/TodoModal.jsx
    - src/pages/Todos/TodosList.jsx
    - src/pages/Dashboard.jsx
    - src/utils/timeline.js

key-decisions:
  - "Awaiting response checkbox available for both new and existing todos"
  - "Day count shows 0 as 'Awaiting today' or 'Today' for clarity"
  - "Dashboard awaiting section only shows when awaitingTodos.length > 0"

patterns-established:
  - "Urgency color scheme: yellow (0-2 days), orange (3-6 days), red (7+ days)"

issues-created: []

# Metrics
duration: 8min
completed: 2026-01-14
---

# Phase 27 Plan 01: Pending Response UI Summary

**Awaiting response UI with toggle in TodoModal, visual badges in TodosList, and dedicated dashboard section with aging indicators**

## Performance

- **Duration:** 8 min
- **Started:** 2026-01-14T15:10:00Z
- **Completed:** 2026-01-14T15:18:00Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments

- Added awaiting response checkbox to TodoModal for both create and edit modes
- Created helper functions getAwaitingDays() and getAwaitingUrgencyClass() in timeline.js
- Added visual badges to TodosList showing day count with urgency colors
- Added "Awaiting response" dashboard section showing up to 5 awaiting todos

## Task Commits

Each task was committed atomically:

1. **Task 1: Add awaiting response toggle to TodoModal** - `79e0c71` (feat)
2. **Task 2: Add awaiting response indicator to TodosList** - `dd3c86c` (feat)
3. **Task 3: Add awaiting response section to Dashboard** - `224a55f` (feat)

## Files Created/Modified

- `src/components/Timeline/TodoModal.jsx` - Added awaitingResponse state and checkbox UI
- `src/utils/timeline.js` - Added getAwaitingDays() and getAwaitingUrgencyClass() helpers
- `src/pages/Todos/TodosList.jsx` - Added awaiting response badge to TodoItem
- `src/pages/Dashboard.jsx` - Added AwaitingTodoCard component and awaiting section

## Decisions Made

1. **Checkbox placement:** Added after due date input and before completed checkbox, visible for both new and existing todos
2. **Day count display:** Shows "Awaiting today" for 0 days in TodosList, "Today" for 0 days in Dashboard for brevity
3. **Conditional rendering:** Dashboard awaiting section only renders when there are awaiting todos

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## Next Phase Readiness

- Awaiting response UI complete across TodoModal, TodosList, and Dashboard
- Visual indicators working with urgency-based color coding
- Ready for Phase 28: Todo filtering (if planned) or milestone completion

---
*Phase: 27-pending-response-ui*
*Completed: 2026-01-14*
