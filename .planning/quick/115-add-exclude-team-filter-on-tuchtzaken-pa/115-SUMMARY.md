---
phase: quick-115
plan: 01
subsystem: tuchtzaken-filters
tags: [filters, discipline-cases, DataTable, quick-task]
dependency_graph:
  requires: []
  provides: [exclude-team-filter-tuchtzaken]
  affects: [DisciplineCasesList]
tech_stack:
  added: []
  patterns: [DataTableToolbar filter pattern, createColumn with getFilterLabel]
key_files:
  modified:
    - src/pages/DisciplineCases/DisciplineCasesList.jsx
decisions:
  - Reuses existing teamFilterOptions for the exclude filter dropdown — same team list, inverse predicate
  - Added getFilterLabel to both team (include) and excludeTeam filters for consistent chip display
metrics:
  duration: 5 minutes
  completed: 2026-02-22
  tasks_completed: 1
  files_changed: 1
---

# Quick Task 115: Add Exclude Team Filter on Tuchtzaken Summary

**One-liner:** Exclude-team SELECT filter added to tuchtzaken page that hides all cases for a selected team using inverse predicate alongside the existing include-team filter.

## What Was Done

Added an "Uitsluiten team" (Exclude team) filter to the discipline cases list page. Users can now select a team to hide all its cases from the overview — useful for excluding youth teams with many minor infractions to focus on other teams.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add exclude team filter to tuchtzaken page | 87eea749 | src/pages/DisciplineCases/DisciplineCasesList.jsx |

## Implementation Details

The filter follows the exact same pattern as the existing team include filter but with an inverse predicate:

- `excludeTeamFilter` state variable added alongside `teamFilter`
- `excludeTeam` column definition in `filterColumns` useMemo — reuses `teamFilterOptions` (same team list)
- Filter logic: `if ((acf.team_name || '') === excludeTeamFilter) return false;` (removes matching cases)
- Include filter uses `!==` (keep matching); exclude filter uses `===` (remove matching) — filters compose naturally
- `getFilterLabel` added to both `team` and `excludeTeam` columns for proper chip display
- Wired into: `filters` prop, `setFilter` callback, `clearFilters`, `hasActiveFilters`, `activeFilterCount`, `filteredCases` deps, empty state condition

## Deviations from Plan

**Auto-added improvement:** Added `getFilterLabel` to the existing `team` (include) filter column as instructed in the plan (step 10), since it previously lacked one and would show raw values in chips. This was explicitly part of the plan spec.

None beyond what was planned — executed exactly as specified.

## Verification

- `npm run lint` — passed with 0 warnings
- `npm run build` — compiled successfully
- Deployed to production: https://rondo.svawc.nl/

## Self-Check: PASSED

- File exists: src/pages/DisciplineCases/DisciplineCasesList.jsx - FOUND
- Commit 87eea749 - FOUND
