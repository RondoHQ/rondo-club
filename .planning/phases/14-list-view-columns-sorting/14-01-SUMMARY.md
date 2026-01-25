---
phase: 14-list-view-columns-sorting
plan: 01
subsystem: ui
tags: [react, table, sorting, columns, list-view]

# Dependency graph
requires:
  - phase: 12-list-view-selection
    provides: List view table structure, PersonListRow component
  - phase: 13-bulk-actions
    provides: Selection infrastructure, personTeamMap lookup
provides:
  - Split First Name/Last Name columns in list view
  - Labels column with pill styling
  - Extended sorting (Team, Workspace, Labels)
  - Zebra striping for table rows
affects: [15-extended-bulk-actions]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Two-stage filtering/sorting with sortedPeople useMemo
    - Empty values sort last pattern for optional fields

key-files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx

key-decisions:
  - "Split sorting into separate useMemo after personTeamMap is available for team sorting"
  - "Empty values (no org/workspace/labels) sort last to keep populated records prominent"
  - "Limited labels display to 3 with '+N more' indicator to prevent row height explosion"

patterns-established:
  - "Two-stage filtering/sorting: filteredAndSortedPeople (filters) then sortedPeople (sorts)"
  - "Zebra striping via isOdd prop and conditional bg-gray-50"

issues-created: []

# Metrics
duration: 18min
completed: 2026-01-13
---

# Phase 14 Plan 01: List View Columns & Sorting Summary

**Split name columns, added labels column with pill display, and extended sorting to Team/Workspace/Labels**

## Performance

- **Duration:** 18 min
- **Started:** 2026-01-13T18:00:00Z
- **Completed:** 2026-01-13T18:18:00Z
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments

- Split single Name column into First Name and Last Name columns
- Added Labels column displaying up to 3 labels as styled pills with overflow indicator
- Added zebra striping (odd rows bg-gray-50) for easier visual scanning
- Extended sort dropdown with Team, Workspace, and Labels options
- Implemented sorting that handles empty values (sort last)

## Task Commits

Each task was committed atomically:

1. **Task 1: Split Name column into First Name and Last Name columns** - `fcd38e0` (feat)
2. **Task 2: Add Labels column to table** - `c2923ed` (feat)
3. **Task 3: Add Team and Workspace sorting options** - `60393d8` (feat)

**Version bump:** `bdca0b0` (chore: bump version to 1.65.0)

## Files Created/Modified

- `src/pages/People/PeopleList.jsx` - Split name columns, added labels column, refactored sorting logic

## Decisions Made

- **Two-stage sorting architecture:** Separated filtering (filteredAndSortedPeople) from sorting (sortedPeople) to allow team sorting to use personTeamMap which is computed after team data is fetched
- **Empty values sort last:** When sorting by Team, Workspace, or Labels, empty values are pushed to the end regardless of sort direction - keeps populated records prominent
- **Labels display limit:** Limited to 3 labels with "+N more" indicator to prevent row height explosion in list view

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- **ESLint configuration missing:** `npm run lint` fails with configuration error. The project has eslint in devDependencies but no .eslintrc file in the project root. Build succeeds, so this is a pre-existing configuration issue, not a code problem.

## Next Phase Readiness

Ready for Phase 15: Extended Bulk Actions
- List view now has all 6 columns (checkbox, first name, last name, team, workspace, labels)
- All columns are sortable
- Selection infrastructure from Phase 12/13 remains intact

---
*Phase: 14-list-view-columns-sorting*
*Completed: 2026-01-13*
