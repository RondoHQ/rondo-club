---
phase: 148
plan: 02
subsystem: frontend
tags: [react, documentation, infrastructure-removal, important-dates]

dependency-graph:
  requires: [148-01]
  provides: [clean-frontend, updated-docs, v19-release]
  affects: []

tech-stack:
  added: []
  removed: []
  patterns: []

key-files:
  deleted:
    - src/pages/Dates/DatesList.jsx
    - src/components/ImportantDateModal.jsx
    - src/hooks/useDates.js
  modified:
    - src/hooks/usePeople.js
    - src/api/client.js
    - src/router.jsx
    - src/components/layout/Layout.jsx
    - src/pages/People/PersonDetail.jsx
    - src/pages/People/FamilyTree.jsx
    - src/hooks/useDocumentTitle.js
    - src/pages/Dashboard.jsx
    - docs/data-model.md
    - docs/rest-api.md
    - docs/ical-feed.md
    - docs/reminders.md
    - docs/access-control.md
    - docs/import.md
    - style.css
    - package.json
    - CHANGELOG.md

decisions:
  - id: deceased-from-allpeople
    choice: "Use is_deceased from allPeople instead of querying dates"
    reason: "getPersonDates endpoint removed, but allPeople already has is_deceased flag"
  - id: age-from-allpeople
    choice: "Use birth_year from allPeople for personAgeMap"
    reason: "Person records now have birthdate field, use that instead of querying dates"
  - id: version-19-major
    choice: "Version bump to 19.0.0 (major)"
    reason: "Removing a feature (Important Dates) is a breaking change"

metrics:
  duration: 35min
  completed: 2026-02-06
  tasks: 3
  commits: 3
---

# Phase 148 Plan 02: Frontend Code Removal Summary

Frontend components removed, documentation updated, version 19.0.0 released.

## What Was Done

### Task 1: Delete standalone date files and clean hooks

**Deleted files:**
- `src/pages/Dates/DatesList.jsx` - Dates list page
- `src/pages/Dates/` directory
- `src/components/ImportantDateModal.jsx` - Modal for creating/editing dates
- `src/hooks/useDates.js` - Date-related hooks

**Cleaned shared modules:**

`src/hooks/usePeople.js`:
- Removed `dates` key from `peopleKeys`
- Removed `usePersonDates()` function
- Removed `useDeleteDate()` function
- Removed birthday creation via Important Dates CPT from `useCreatePerson`

`src/api/client.js`:
- Removed `getDates`, `getDate`, `createDate`, `updateDate`, `deleteDate` from wpApi
- Removed `getDateTypes` from wpApi
- Removed `getPersonDates` from prmApi

**Commit:** 4d02d586

### Task 2: Remove dates from router, navigation, and PersonDetail

**src/router.jsx:**
- Removed lazy import for DatesList
- Removed entire '/dates' route block

**src/components/layout/Layout.jsx:**
- Removed Calendar icon from lucide-react imports
- Removed Datums navigation item from navigation array
- Removed `case 'Datums'` from `getCounts()` function
- Removed `/dates` handling from `getPageTitle()` function

**src/pages/People/PersonDetail.jsx:**
- Removed imports: Gift, Heart, usePersonDates, useDeleteDate, ImportantDateModal, peopleKeys
- Removed state variables: showDateModal, isSavingDate, editingDate
- Removed deathDate calculation from personDates
- Simplified isDeceased to use `person.is_deceased || false`
- Removed allDates calculation and sorting
- Removed handleSaveDate and handleDeleteDate functions
- Removed personDates from vCard export options
- Refactored personAgeMap to use birth_year from allPeople
- Refactored personDeceasedMap to use is_deceased from allPeople
- Removed entire Important Dates card from JSX
- Removed ImportantDateModal component from JSX
- Removed deceased display block with deathDateValue

**src/pages/People/FamilyTree.jsx:**
- Removed useQueries and prmApi imports
- Refactored personDeceasedMap to use is_deceased from allPeople instead of querying dates

**src/hooks/useDocumentTitle.js:**
- Removed `/dates` from extractRouteId regex
- Removed entire `/dates` route handling block

**src/pages/Dashboard.jsx:**
- Removed Herinneringen stat card that linked to /dates
- Removed linkTo="/dates" from reminders widget

**Commit:** 6d2531ca

### Task 3: Update documentation and deploy

**Documentation updates:**
- `docs/data-model.md` - Changed from 3 CPTs to 2, removed Important Date section and Date Type taxonomy
- `docs/rest-api.md` - Removed Important Dates endpoints, updated reminders to birthdays-only
- `docs/ical-feed.md` - Updated for birthday-only feeds from person.birthdate
- `docs/reminders.md` - Updated for birthday-only digest system
- `docs/access-control.md` - Removed important_date from controlled post types
- `docs/import.md` - Updated BDAY field mapping to person.birthdate

**Version update:**
- Bumped version to 19.0.0 (major - feature removed)
- Added comprehensive CHANGELOG entry

**Deployment:**
- Built production assets with `npm run build`
- Deployed to production with `bin/deploy.sh`
- Flushed rewrite rules on production

**Commit:** 6691c68f

## Decisions Made

| Decision | Choice | Reason |
|----------|--------|--------|
| Deceased status source | Use is_deceased from allPeople | getPersonDates endpoint removed, allPeople already has flag |
| Age calculation source | Use birth_year from allPeople | Person records have birthdate field now |
| Version number | 19.0.0 (major) | Removing Important Dates is a breaking change |

## Deviations from Plan

### Additional Refactoring Required

**1. [Rule 3 - Blocking] FamilyTree.jsx needed refactoring**
- **Found during:** Task 2
- **Issue:** FamilyTree.jsx was querying dates via prmApi.getPersonDates to get deceased status
- **Fix:** Refactored personDeceasedMap to use is_deceased from allPeople data
- **Files modified:** src/pages/People/FamilyTree.jsx
- **Commit:** 6d2531ca

**2. [Rule 3 - Blocking] PersonDetail.jsx age/deceased maps**
- **Found during:** Task 2
- **Issue:** personAgeMap and personDeceasedMap were using date queries
- **Fix:** Refactored to use birth_year and is_deceased from allPeople
- **Files modified:** src/pages/People/PersonDetail.jsx
- **Commit:** 6d2531ca

**3. [Rule 2 - Missing Critical] Dashboard stats card removal**
- **Found during:** Task 2
- **Issue:** Dashboard had "Herinneringen" stat card linking to /dates
- **Fix:** Removed the stat card entirely
- **Files modified:** src/pages/Dashboard.jsx
- **Commit:** 6d2531ca

## Verification Results

All success criteria met:
- [x] `src/pages/Dates/` directory does not exist
- [x] `src/components/ImportantDateModal.jsx` does not exist
- [x] `src/hooks/useDates.js` does not exist
- [x] No date-related code remains in src/
- [x] `npm run build` completes without errors
- [x] Production deployed and working
- [x] Documentation updated with no Important Dates references
- [x] Version bumped to 19.0.0
- [x] CHANGELOG.md updated

## Commits

| Hash | Type | Description |
|------|------|-------------|
| 4d02d586 | feat | Delete standalone date files and clean hooks |
| 6d2531ca | feat | Remove dates from router, navigation, and PersonDetail |
| 6691c68f | docs | Update documentation for Important Dates removal |

## Phase 148 Complete

Phase 148 (Infrastructure Removal) is now complete:
- Plan 01: Backend removal (PHP classes, ACF fields, 1069 production records)
- Plan 02: Frontend removal (React components, documentation)

The Important Dates subsystem has been fully removed from Stadion. Birthdays are now stored directly on person records via the `birthdate` ACF field, and all date-related features (reminders, iCal feeds) now source from this simpler data model.
