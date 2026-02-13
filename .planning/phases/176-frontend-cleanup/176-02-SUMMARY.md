---
phase: 176-frontend-cleanup
plan: 02
subsystem: frontend-ui
tags: [cleanup, dead-code-removal, labels, ui-components]
dependency_graph:
  requires: [176-01]
  provides: [label-ui-removed]
  affects: [frontend-components, user-interface]
tech_stack:
  added: []
  patterns: []
key_files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx
    - src/pages/Teams/TeamsList.jsx
    - src/pages/Commissies/CommissiesList.jsx
    - src/pages/People/PersonDetail.jsx
  deleted: []
decisions: []
metrics:
  duration_seconds: 478
  tasks_completed: 2
  commits: 2
  files_modified: 4
  lines_removed: ~467
completed_date: 2026-02-13
---

# Phase 176 Plan 02: Component Label UI Cleanup Summary

**Removed all label-related UI from list views and detail pages, eliminating ~467 lines of dead code.**

## Overview

Completed the frontend label removal by stripping out all label UI components:
- Removed labels column, filter dropdown section, filter chips, and bulk actions from PeopleList
- Deleted inline BulkLabelsModal component from PeopleList (118 lines)
- Removed empty bulk actions dropdowns from TeamsList and CommissiesList
- Removed label badges and add/remove controls from PersonDetail

## Tasks Completed

### Task 1: Remove labels from PeopleList
**Commit:** 39872f3c

**Changes:**
- Removed `Tag` icon from lucide-react imports
- Removed `labels: 'labels'` from COLUMN_SORT_FIELDS mapping
- Deleted labels column rendering block (lines 150-176) with label badges display
- Deleted entire inline BulkLabelsModal component (lines 518-635, 118 lines)
- Removed `showBulkLabelsModal` state variable
- Removed person labels query (`queryKey: ['person-labels']`)
- Removed `availableLabelsWithIds` derived state
- Removed `selectedLabelIds` URL param parsing and useMemo
- Removed `setSelectedLabelIds` URL param setter callback
- Removed `labels: selectedLabelIds` from useFilteredPeople hook params
- Removed `|| sortField === 'labels'` from orderby ternary
- Removed `selectedLabelIds.length > 0` from hasActiveFilters check
- Removed `selectedLabelIds.length` from filter count badge
- Removed entire Labels Filter section from filter dropdown (lines 1208-1240)
- Removed label filter chips rendering (lines 1456-1472)
- Removed "Labels beheren..." button from bulk actions dropdown
- Removed `<BulkLabelsModal>` component usage (lines 1788-1811)
- Updated fallback visible columns from `['team', 'labels']` to `['team']`
- Removed `handleLabelToggle` function
- Removed `selectedLabelIds` from useEffect dependencies and export filters

**Verification:**
- Build succeeded
- Only benign "label" references remain (HTML label elements, column labels)

### Task 2: Remove labels from TeamsList, CommissiesList, and PersonDetail
**Commit:** 4ddaec05

**TeamsList.jsx changes:**
- Removed `Tag` and `ChevronDown` from lucide-react imports
- Removed `useBulkUpdateTeams` import
- Removed `showBulkDropdown`, `bulkActionLoading` states
- Removed `bulkDropdownRef` ref
- Removed `bulkUpdateMutation` variable
- Removed bulk dropdown click-outside handling from useEffect
- Removed entire bulk actions dropdown JSX (lines 725-739)
- Kept selection toolbar skeleton (checkboxes, count, clear button) for future use

**CommissiesList.jsx changes:**
- Removed `Tag` and `ChevronDown` from lucide-react imports
- Removed `useBulkUpdateCommissies` import
- Removed `showBulkDropdown`, `bulkActionLoading` states
- Removed `bulkDropdownRef` ref
- Removed `bulkUpdateMutation` variable
- Removed bulk dropdown click-outside handling from useEffect
- Removed entire bulk actions dropdown JSX (lines 625-639)
- Kept selection toolbar skeleton for future use

**PersonDetail.jsx changes:**
- Removed `isAddingLabel`, `selectedLabelToAdd` state variables
- Removed person labels query (`queryKey: ['person-labels']`)
- Removed all label-derived state:
  - `availableLabels`
  - `currentLabelNames`
  - `currentLabelTermIds`
  - `availableLabelsToAdd`
- Removed `handleRemoveLabel` function (18 lines)
- Removed `handleAddLabel` function (18 lines)
- Removed entire label badges and add/remove UI section (lines 1036-1099, 64 lines):
  - Label badges with remove buttons
  - "Label toevoegen" button
  - Label select dropdown with add/cancel buttons
- Preserved social links section structure

**Verification:**
- Build succeeded (15.52s)
- Lint shows only pre-existing errors unrelated to labels
- Grep verified zero references to:
  - `BulkLabelsModal`
  - `person_label`, `team_label`, `commissie_label`
  - `getPersonLabels`, `getTeamLabels`, `getCommissieLabels`
  - `isAddingLabel`, `selectedLabelToAdd`, `handleRemoveLabel`, `handleAddLabel`

## Deviations from Plan

None - plan executed exactly as written. The note about plan 01 removing BulkLabelsModal imports was confirmed and handled correctly.

## Technical Decisions

1. **Preserved selection infrastructure:** Kept checkboxes and selection state in TeamsList/CommissiesList even though bulk actions are gone, to support future bulk operations without labels
2. **Removed empty dropdowns:** Since labels were the only bulk action for teams/commissies, removed the entire dropdown UI rather than leaving an empty placeholder
3. **Careful JSX surgery:** In PersonDetail, precisely removed only the labels div while preserving the surrounding social links section structure

## State After Completion

**Frontend label UI: Zero**
- No label columns in any list view
- No label filters or chips
- No label bulk actions
- No label badges on detail pages
- No label add/remove controls

**Clean build:**
- Production build succeeds in 15.52s
- Zero label-related code in any src/ file
- All verification greps return zero hits

**Next steps (plan 03):**
- Remove remaining label references from API client if not already done
- Final cleanup of any stray label-related utilities or constants

## Self-Check: PASSED

**Modified files verified:**
```
FOUND: src/pages/People/PeopleList.jsx
FOUND: src/pages/Teams/TeamsList.jsx
FOUND: src/pages/Commissies/CommissiesList.jsx
FOUND: src/pages/People/PersonDetail.jsx
```

**Commits verified:**
```
FOUND: 39872f3c (Task 1: refactor(176-02): remove label UI from PeopleList)
FOUND: 4ddaec05 (Task 2: refactor(176-02): remove label UI from TeamsList, CommissiesList, PersonDetail)
```

All verification checks passed. Plan executed successfully.
