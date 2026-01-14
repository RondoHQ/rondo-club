---
phase: 30-todos-sidebar
plan: 01
subsystem: ui
tags: [react, persondetail, sidebar, todos, layout]

# Dependency graph
requires:
  - phase: 29-header-enhancement
    provides: PersonDetail header enhancements
provides:
  - Persistent todos sidebar in PersonDetail
  - Two-column flex layout for tab content
  - Open todos count badge
affects: [person-profile-enhancements]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Flex layout with persistent sidebar
    - Sticky sidebar for scroll persistence
    - useMemo for derived todo count

key-files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx

key-decisions:
  - "Use flex layout with gap-6 for content and sidebar spacing"
  - "Sidebar hidden on screens below lg breakpoint"
  - "Badge counts open + awaiting todos (excludes completed)"

patterns-established:
  - "Sidebar pattern: w-80 flex-shrink-0 hidden lg:block with sticky content"

issues-created: []

# Metrics
duration: 3 min
completed: 2026-01-14
---

# Phase 30 Plan 01: Persistent Todos Sidebar Summary

**Todos now display in a persistent sidebar visible across all PersonDetail tabs with open count badge**

## Performance

- **Duration:** 3 min
- **Started:** 2026-01-14T16:53:28Z
- **Completed:** 2026-01-14T16:55:58Z
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments

- Created two-column flex layout for tab content area
- Moved todos from Timeline tab to persistent sidebar
- Added sticky positioning for sidebar scroll behavior
- Added open todos count badge next to header

## Task Commits

1. **Task 1: Create sidebar layout structure** - `fb28da1` (feat)
2. **Task 2: Move todos to persistent sidebar** - `c335b28` (feat)
3. **Task 3: Add open todos count badge** - `a819dd3` (feat)

## Files Modified

- `src/pages/People/PersonDetail.jsx`
  - Added flex container wrapping tab content
  - Added main content wrapper with flex-1 min-w-0
  - Added aside element for sidebar (w-80, hidden lg:block, sticky top-6)
  - Moved todos card from Timeline tab to sidebar
  - Added openTodosCount useMemo
  - Added badge component showing open todo count

## Decisions Made

1. **Flex layout approach** - Used flex container with gap-6 rather than CSS Grid for simpler responsive handling. Main content uses flex-1 min-w-0 to prevent overflow.

2. **Sidebar visibility** - Hidden on screens below lg breakpoint (hidden lg:block). On smaller screens, users still see todos in the TodosList page.

3. **Count badge logic** - Badge shows count of todos where status is not 'completed' (includes both 'open' and 'awaiting' todos).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - straightforward layout changes.

## Next Phase Readiness

- Phase 30 has only one plan, phase is complete
- Ready for Phase 31: Person Image Polish

---
*Phase: 30-todos-sidebar*
*Completed: 2026-01-14*
