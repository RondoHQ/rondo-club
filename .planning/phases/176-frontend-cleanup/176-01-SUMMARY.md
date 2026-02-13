---
phase: 176-frontend-cleanup
plan: 01
subsystem: frontend-infrastructure
tags: [cleanup, dead-code-removal, labels, taxonomies, api]
dependency_graph:
  requires: [175-02]
  provides: [label-infrastructure-removed]
  affects: [frontend-build, backend-api, database]
tech_stack:
  added: []
  patterns: [deviation-rule-3-blocking-fix]
key_files:
  created: []
  modified:
    - includes/class-taxonomies.php
    - includes/class-rest-commissies.php
    - src/api/client.js
    - src/hooks/usePeople.js
    - src/hooks/useListPreferences.js
    - src/router.jsx
    - src/pages/Settings/Settings.jsx
    - src/pages/Teams/TeamsList.jsx
    - src/pages/Commissies/CommissiesList.jsx
  deleted:
    - src/pages/Settings/Labels.jsx
    - src/components/BulkLabelsModal.jsx
decisions:
  - Applied Rule 3 deviation to remove BulkLabelsModal imports from TeamsList/CommissiesList (originally planned for 02)
  - Bumped cleanup option key to rondo_labels_cleaned_v2 to re-run for commissie_label
  - Removed default 'labels' column from useListPreferences reset
metrics:
  duration_seconds: 347
  tasks_completed: 2
  commits: 2
  files_modified: 11
  files_deleted: 2
  lines_removed: ~1100
completed_date: 2026-02-13
---

# Phase 176 Plan 01: Label Infrastructure Removal Summary

**Eliminated all label taxonomy infrastructure from backend and frontend, removing ~1100 lines of dead code.**

## Overview

Removed person_label, team_label, and commissie_label taxonomies completely:
- Deleted commissie_label PHP registration
- Removed all label API methods from client.js
- Deleted Settings/Labels page and BulkLabelsModal component
- Removed label UI from list pages and navigation
- Expanded database cleanup to include commissie_label

## Tasks Completed

### Task 1: Remove commissie_label backend and all label API methods
**Commit:** 1e15604c

**Backend changes:**
- Removed `register_commissie_label_taxonomy()` method from class-taxonomies.php
- Expanded `cleanup_removed_taxonomies()` to include commissie_label in SQL WHERE clauses
- Bumped cleanup option key to `rondo_labels_cleaned_v2` (re-runs cleanup on existing installs)
- Removed entire `bulk_update_commissies` endpoint from class-rest-commissies.php (only supported label operations)
- Removed `check_bulk_update_permission()` and `bulk_update_commissies()` methods

**Frontend changes:**
- Removed all 12 label taxonomy API methods from client.js:
  - `getPersonLabels`, `createPersonLabel`, `updatePersonLabel`, `deletePersonLabel`
  - `getTeamLabels`, `createTeamLabel`, `updateTeamLabel`, `deleteTeamLabel`
  - `getCommissieLabels`, `createCommissieLabel`, `updateCommissieLabel`, `deleteCommissieLabel`
- Removed labels extraction from `transformPerson()` in usePeople.js (lines 28-30, 46)
- Removed `labels` JSDoc param from useFilteredPeople
- Removed `labels: filters.labels` from params object
- Updated default visible columns in useListPreferences from `['team', 'labels', 'modified']` to `['team', 'modified']`

**Verification:**
- `npm run build` succeeded
- Grep confirmed no label taxonomy references (except cleanup code comments)
- Grep confirmed no label API method references

### Task 2: Delete Settings/Labels page, BulkLabelsModal, and remove navigation
**Commit:** ac5b4913

**Files deleted:**
1. `src/pages/Settings/Labels.jsx` - entire Settings/Labels management page
2. `src/components/BulkLabelsModal.jsx` - bulk label operations modal component

**Router changes:**
- Removed lazy import: `const Labels = lazy(() => import('@/pages/Settings/Labels'));`
- Removed route definition: `{ path: 'settings/labels', element: <Labels /> }`

**Settings page changes:**
- Removed Labels link from AdminTab component (lines 2936-2942)
- Link text was "Labels" with description "Beheer labels voor personen en organisaties"

**Additional blocking fixes (Deviation Rule 3):**

Originally planned for plan 02, but build was blocked by missing imports:

**TeamsList.jsx:**
- Removed `import BulkLabelsModal from '@/components/BulkLabelsModal'`
- Removed `showBulkLabelsModal` state
- Removed "Labels beheren..." button from bulk dropdown
- Removed entire BulkLabelsModal component usage
- Removed teamLabels query (dead code)

**CommissiesList.jsx:**
- Removed `import BulkLabelsModal from '@/components/BulkLabelsModal'`
- Removed `showBulkLabelsModal` state
- Removed "Labels beheren..." button from bulk dropdown
- Removed entire BulkLabelsModal component usage
- Removed commissieLabels query (dead code)

**Verification:**
- `npm run build` succeeded (16.27s)
- Files confirmed deleted
- Grep confirmed no BulkLabelsModal references (except inline definition in PeopleList.jsx - handled in plan 02)
- Grep confirmed no `settings/labels` route references

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Removed BulkLabelsModal imports from TeamsList/CommissiesList**
- **Found during:** Task 2 build verification
- **Issue:** Build failed with "Could not load /Users/.../BulkLabelsModal (imported by TeamsList.jsx)" after deleting BulkLabelsModal.jsx. TeamsList and CommissiesList both imported the deleted component.
- **Root cause:** Deleting BulkLabelsModal.jsx created broken imports that blocked build completion
- **Fix applied:** Removed BulkLabelsModal import, state, UI buttons, modal usage, and dead label queries from both TeamsList.jsx and CommissiesList.jsx
- **Files modified:** TeamsList.jsx, CommissiesList.jsx
- **Lines removed:** ~150 lines across both files
- **Commit:** ac5b4913 (same commit as Task 2)
- **Rationale:** Plan specified these would be cleaned in plan 02, but build breakage qualified as Rule 3 blocking issue requiring immediate fix

## Technical Decisions

1. **Cleanup migration versioning:** Bumped option key from `rondo_labels_cleaned` to `rondo_labels_cleaned_v2` to ensure commissie_label cleanup runs on existing installations that already ran v1
2. **Default columns update:** Removed 'labels' from default visible columns in list preferences reset action to prevent UI showing non-existent column
3. **Complete endpoint removal:** Deleted entire `bulk_update_commissies` endpoint rather than just label operations since no other update types were supported
4. **Deviation handling:** Applied Rule 3 automatically for blocking import errors - no user permission needed per GSD protocol

## State After Completion

**Backend:**
- Zero label taxonomy registrations (person_label, team_label, commissie_label all gone)
- Database cleanup scheduled via `rondo_labels_cleaned_v2` option
- No label-related REST endpoints

**Frontend:**
- Zero label API client methods
- Settings/Labels page unreachable and deleted
- BulkLabelsModal component deleted
- No label UI in TeamsList or CommissiesList bulk actions
- Build succeeds cleanly

**Remaining cleanup (for plan 02):**
- PeopleList.jsx has inline BulkLabelsModal definition (not imported)
- PersonDetail.jsx may have label references
- Any other components with label UI

## Self-Check: PASSED

**Created files verified:** N/A (no files created)

**Modified files verified:**
```
FOUND: includes/class-taxonomies.php
FOUND: includes/class-rest-commissies.php
FOUND: src/api/client.js
FOUND: src/hooks/usePeople.js
FOUND: src/hooks/useListPreferences.js
FOUND: src/router.jsx
FOUND: src/pages/Settings/Settings.jsx
FOUND: src/pages/Teams/TeamsList.jsx
FOUND: src/pages/Commissies/CommissiesList.jsx
```

**Deleted files verified:**
```
MISSING (deleted): src/pages/Settings/Labels.jsx
MISSING (deleted): src/components/BulkLabelsModal.jsx
```

**Commits verified:**
```
FOUND: 1e15604c (Task 1: refactor(176-01): remove label taxonomies and API methods)
FOUND: ac5b4913 (Task 2: refactor(176-01): delete Settings/Labels page and remove label UI)
```

All verification checks passed. Plan executed successfully.
