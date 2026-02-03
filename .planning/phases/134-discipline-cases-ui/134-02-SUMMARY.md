---
phase: 134-02
subsystem: ui
tags: [discipline-cases, list-page, table-component, season-filter, react]

dependency_graph:
  requires: [134-01]
  provides: [discipline-cases-list-ui, discipline-case-table]
  affects: [134-03]

tech_stack:
  added: []
  patterns: [expandable-table-rows, batch-person-fetch, season-filtering]

key_files:
  created:
    - src/components/DisciplineCaseTable.jsx
  modified:
    - src/pages/DisciplineCases/DisciplineCasesList.jsx

decisions:
  - id: client-side-sorting
    choice: Sort discipline cases by match_date client-side
    rationale: Data volume is manageable (<100 cases per season), avoids extra API params

metrics:
  duration: 3m 12s
  completed: 2026-02-03
---

# Phase 134 Plan 02: List Page UI for Discipline Cases Summary

**One-liner:** Reusable table component and list page with season filtering for discipline cases

## What Was Built

### 1. DisciplineCaseTable Component (src/components/DisciplineCaseTable.jsx)

A reusable table component for displaying discipline cases with:

- **Columns:** Person (optional), Match (with date), Sanction (truncated), Fee
- **Expandable rows:** Click to reveal inline details (charges, full sanction, team, dossier)
- **Date sorting:** Toggle ascending/descending by match_date
- **ACF date parsing:** Correctly handles Ymd format (e.g., "20241015")
- **Props:**
  - `cases` - Array of discipline case objects
  - `showPersonColumn` - Boolean to show/hide Person column (false on person detail tab)
  - `personMap` - Map of person ID to person data for avatar/name display
  - `isLoading` - Loading state
- **Dutch labels:** Persoon, Wedstrijd, Sanctie, Boete, Tenlastelegging, etc.
- **Dark mode support:** Full Tailwind dark mode classes

### 2. DisciplineCasesList Page (src/pages/DisciplineCases/DisciplineCasesList.jsx)

Complete list page replacing placeholder with:

- **Season filter dropdown:**
  - Defaults to current season (via useCurrentSeason hook)
  - "Alle seizoenen" option to show all cases
  - Filter icon indicator
- **Batch person fetching:** Efficiently fetches all persons referenced in cases
- **Person map creation:** Maps person IDs to display data for table
- **Pull-to-refresh:** Mobile-friendly refresh gesture
- **Error handling:** Displays error message if API fails
- **Empty state:** Shows when filtered season has no cases

## Technical Implementation

### Season Filter Flow

1. Page loads, fetches current season via `useCurrentSeason()`
2. Sets `selectedSeasonId` to current season ID
3. Fetches cases via `useDisciplineCases({ seizoen: selectedSeasonId })`
4. User can select different season or "Alle seizoenen" (null)

### Person Data Flow

1. Extract unique person IDs from cases
2. Batch fetch all persons via `wpApi.getPeople({ include: ids })`
3. Create Map of person ID to person data
4. Pass map to DisciplineCaseTable for avatar/name rendering

### Expandable Row Details

When expanded, shows:
- **Tenlastelegging:** Full charge description with charge codes
- **Sanctie (volledig):** Complete sanction text
- **Team:** Team name from discipline case
- **Details:** Dossier ID, processing date, charged status

## Verification Results

1. npm run lint passes for both new files (no errors)
2. npm run build succeeds
3. DisciplineCasesList.jsx: 171 lines (> 80 minimum)
4. DisciplineCaseTable.jsx: 251 lines (> 100 minimum)
5. Key links verified:
   - DisciplineCasesList imports useDisciplineCases, useSeasons from hooks
   - DisciplineCaseTable imports formatCurrency from formatters

## Commits

| Hash | Description |
|------|-------------|
| 7437d246 | feat(134-02): create reusable DisciplineCaseTable component |
| c91250d3 | feat(134-02): implement discipline cases list page with season filter |

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

Ready for Plan 03 (Person Detail Tab):
- DisciplineCaseTable component accepts `showPersonColumn={false}` prop
- usePersonDisciplineCases hook available from 134-01
- Table styling consistent with list page
