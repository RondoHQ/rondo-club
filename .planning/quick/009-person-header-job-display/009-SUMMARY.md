---
phase: quick
plan: 009
subsystem: ui
tags: [react, person-detail, header, job-display]

# Dependency graph
requires: []
provides:
  - Improved person header job/function display with grouping logic
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "groupedPositions useMemo pattern for complex display logic"

key-files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx

key-decisions:
  - "Verenigingsbreed functions show title only without team link"
  - "Functions grouped by team - multiple titles at same team shown together"
  - "Kaderlid Algemeen filtered when multiple functions exist"

patterns-established:
  - "Position grouping: null key for Verenigingsbreed/no-team, sorted first"

# Metrics
duration: 4min
completed: 2026-01-28
---

# Quick Task 009: Person Header Job Display Summary

**Improved person header job display: hides "bij Verenigingsbreed" link, groups functions by commissie, and filters redundant "Kaderlid Algemeen" when multiple functions exist**

## Performance

- **Duration:** 4 min
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Hide "bij Verenigingsbreed" link for org-wide functions (show title only)
- Group functions by team (multiple titles at same commissie shown together, e.g., "Voorzitter, Penningmeester bij Financiele Commissie")
- Filter out "Kaderlid Algemeen" when person has multiple functions
- Keep "Kaderlid Algemeen" visible when it's the only function

## Task Commits

1. **Task 1: Update person header job display logic** - `cdcf587` (feat)

## Files Modified
- `src/pages/People/PersonDetail.jsx` - Added `groupedPositions` useMemo that processes currentPositions, updated header JSX to render grouped structure

## Decisions Made
- **Grouping approach:** Used Map with null key for Verenigingsbreed/no-team positions, placed first in sort order
- **Team detection:** Check `teamMap[job.team]?.name === 'Verenigingsbreed'` to identify org-wide functions
- **Kaderlid handling:** Filter applied only when `currentPositions.length > 1`, with fallback to show all if filtering removes everything

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Person header display improved for various function configurations
- No blockers or concerns

---
*Quick Task: 009*
*Completed: 2026-01-28*
