---
phase: 113-add-awc-team-column-and-filter-to-tuchtz
plan: "01"
subsystem: frontend/discipline-cases
tags: [discipline-cases, filter, column-visibility, DataTable]
dependency_graph:
  requires: []
  provides: [team-column-tuchtzaken, team-filter-tuchtzaken]
  affects: [DisciplineCaseTable, DisciplineCasesList]
tech_stack:
  added: []
  patterns: [dynamic-filter-options-from-data, column-visibility-gating]
key_files:
  created: []
  modified:
    - src/components/DisciplineCaseTable.jsx
    - src/pages/DisciplineCases/DisciplineCasesList.jsx
decisions:
  - "Dynamic filter options derived from loaded cases via useMemo — no hardcoded team names"
  - "FILTER_COLUMNS converted from static module-level const to filterColumns useMemo inside component — required for reactivity on teamFilterOptions"
  - "Team column placed between Wedstrijd and Sanctie in table; first in colVisColumns list"
metrics:
  duration_seconds: 175
  completed_date: "2026-02-22"
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 113: Add AWC Team Column and Filter to Tuchtzaken

**One-liner:** Team column (acf.team_name) surfaced between Wedstrijd and Sanctie in tuchtzaken table with sortable header, column visibility toggle, and dynamic SELECT filter populated from loaded case data.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add team column to DisciplineCaseTable | eba2e54b | src/components/DisciplineCaseTable.jsx |
| 2 | Add team filter and column visibility to DisciplineCasesList | f5c53c68 | src/pages/DisciplineCases/DisciplineCasesList.jsx |

## What Was Built

### Task 1: DisciplineCaseTable

- Added `SortableHeader` for "Team" (columnId: `team_name`) gated by `isColVisible('team')`, positioned between Wedstrijd and Sanctie headers
- Added corresponding `<td>` rendering `acf.team_name || '-'` with `text-sm text-gray-900 dark:text-gray-100` styling
- Added `team_name` case to sort switch using `localeCompare` (same pattern as `sanction`)
- Updated expanded row `colSpan` calculation to include `(isColVisible('team') ? 1 : 0)`
- The existing "Team" section in the expanded row detail remains for when the column is hidden

### Task 2: DisciplineCasesList

- Added `teamFilter` state, wired into `filteredCases`, `hasActiveFilters`, `activeFilterCount`, `clearFilters`, `setFilter`, `DataTableToolbar filters` prop, and empty state condition
- Added `team` entry to `colVisColumns` as the first entry (before `wedstrijd`)
- Added `teamFilterOptions` useMemo: derives unique sorted team names from loaded cases
- Converted `FILTER_COLUMNS` static module-level constant to `filterColumns` useMemo inside the component (depends on `teamFilterOptions`) — enables dynamic options to react to data changes
- Team column definition uses `FILTER_TYPES.SELECT` with dynamically derived options, placed after `persoon` filter

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- src/components/DisciplineCaseTable.jsx: modified with team column — FOUND
- src/pages/DisciplineCases/DisciplineCasesList.jsx: modified with team filter — FOUND
- Commit eba2e54b: FOUND
- Commit f5c53c68: FOUND
- `npm run build`: PASSED
- `npm run lint`: PASSED (0 warnings)
- Deployed to production: https://rondo.svawc.nl/
