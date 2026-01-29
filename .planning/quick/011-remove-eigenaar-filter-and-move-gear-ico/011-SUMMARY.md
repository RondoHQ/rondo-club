---
phase: quick
plan: 011
subsystem: ui
tags: [react, filters, people-list, ux]

# Dependency graph
requires:
  - phase: 115
    provides: PeopleList with column preferences and filter system
provides:
  - Cleaner filter dropdown without ownership filter
  - Improved header layout with gear icon at far right
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx

key-decisions:
  - "Keep ownership param as 'all' in API call for backend compatibility"
  - "Group add button and gear icon on right side with flex layout"

patterns-established: []

# Metrics
duration: 5min
completed: 2026-01-29
---

# Quick Task 011: Remove Eigenaar Filter and Move Gear Icon

**Removed ownership filter from PeopleList and repositioned gear icon to far right of header row**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-29
- **Completed:** 2026-01-29
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Removed "Eigenaar" filter section from filter dropdown
- Removed ownershipFilter state and all related references
- Moved gear icon (column settings) to far right of header row
- Updated header layout with proper justify-between grouping

## Task Commits

1. **Task 1: Remove Eigenaar filter and move gear icon to far right** - `87c8f3f` (feat)

## Files Modified
- `src/pages/People/PeopleList.jsx` - Removed ownership filter state, dropdown section, filter chip, and repositioned gear icon

## Decisions Made
- Keep `ownership: 'all'` in useFilteredPeople call for backend API compatibility
- Restructure header with two flex groups: left (sort, filter, chips) and right (add button, gear icon)

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None.

## Next Phase Readiness
- Filter dropdown now shows only: Labels, Geboortejaar, Laatst gewijzigd
- Gear icon properly positioned at far right for intuitive column settings access

---
*Phase: quick/011*
*Completed: 2026-01-29*
