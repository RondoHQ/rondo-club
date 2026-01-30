---
phase: 120
plan: 01
subsystem: vog-management
tags: [react, list-view, navigation, filtering]
dependency-graph:
  requires: ["119-backend-foundation"]
  provides: ["vog-list-page", "vog-navigation", "vog-count-badge"]
  affects: ["121-vog-bulk-actions"]
tech-stack:
  added: []
  patterns: ["sub-navigation with indent", "status badges", "celebratory empty state"]
key-files:
  created:
    - src/hooks/useVOGCount.js
    - src/pages/VOG/VOGList.jsx
  modified:
    - src/App.jsx
    - src/components/layout/Layout.jsx
decisions:
  - "VOG appears as indented sub-item under Leden in navigation"
  - "FileCheck icon used for VOG (consistent with Phase 119 decision)"
  - "Nieuw badge (blue) for no VOG, Vernieuwing badge (purple) for expired"
  - "Celebratory green checkmark empty state for all VOGs valid"
metrics:
  duration: "~15 minutes"
  completed: "2026-01-30"
---

# Phase 120 Plan 01: VOG List Page Summary

VOG list page with server-side filtering showing volunteers needing new or renewed VOG, with navigation badge and celebratory empty state.

## What Was Done

### Task 1: Create useVOGCount hook and VOGList page component
- Created `src/hooks/useVOGCount.js` - Hook that uses `useFilteredPeople` with VOG filters to get count for navigation badge
- Created `src/pages/VOG/VOGList.jsx` - Full list page component (354 lines)
- Implemented server-side filtering: `huidigeVrijwilliger: '1'`, `vogMissing: '1'`, `vogOlderThanYears: 3`
- Table columns: Name, KNVB ID, Email, Phone, Datum VOG
- VOGBadge component: Nieuw (blue) for no VOG, Vernieuwing (purple) for expired
- VOGEmailIndicator: Shows Mail icon with tooltip when VOG email was sent
- Sortable headers: Name (first_name) and Datum VOG (custom_datum-vog)
- Loading state with centered spinner
- Error state with retry button
- Empty state with green CheckCircle icon and celebratory message
- Dark mode support throughout
- Commit: a3cda7c8

### Task 2: Add VOG route and navigation item
- Added lazy import for VOGList in `src/App.jsx`
- Added `/vog` route after People routes
- Added FileCheck icon import in `src/components/layout/Layout.jsx`
- Added `useVOGCount` import for navigation badge
- Added VOG to navigation array with `indent: true` property
- Updated Sidebar component to call useVOGCount and render VOG count
- Updated navigation rendering to apply `pl-8` padding for indented items
- Updated Header getPageTitle to return 'VOG' for /vog path
- Commit: f3c641df

### Task 3: Build, deploy, and verify
- Production build successful with `VOGList-COguuRSz.js` (6.18 kB)
- Deployed to production via `bin/deploy.sh`

## Artifacts Created

| File | Purpose | Lines |
|------|---------|-------|
| `src/hooks/useVOGCount.js` | Navigation badge count hook | 26 |
| `src/pages/VOG/VOGList.jsx` | VOG list page component | 328 |

## Artifacts Modified

| File | Changes |
|------|---------|
| `src/App.jsx` | Added VOGList lazy import and /vog route |
| `src/components/layout/Layout.jsx` | Added FileCheck icon, useVOGCount hook, VOG navigation item, indent styling |

## Implementation Details

### Navigation Pattern
The VOG item uses a new `indent: true` property in the navigation array. The rendering logic applies `pl-8 pr-3` padding instead of `px-3` for indented items, creating a visual hierarchy under the parent "Leden" item.

### Filtering Logic
Server-side filtering via `useFilteredPeople` combines:
- `huidigeVrijwilliger: '1'` - Only current volunteers
- `vogMissing: '1'` - No VOG date
- `vogOlderThanYears: 3` - OR VOG date 3+ years old

The backend handles the OR logic (from Phase 119), returning volunteers who match either condition.

### Badge Determination
Simple logic based on presence of `datum-vog` field:
- No value: "Nieuw" (blue) - volunteer never had a VOG
- Has value: "Vernieuwing" (purple) - must be expired since they're in this filtered list

## Deviations from Plan

None - plan executed exactly as written.

## Verification Checklist

- [x] VOG navigation item appears indented under Leden
- [x] VOG navigation shows FileCheck icon
- [x] VOG navigation shows count badge when > 0
- [x] Clicking VOG navigates to /vog
- [x] Header shows "VOG" as page title on /vog route
- [x] List shows columns: Name, KNVB ID, Email, Phone, Datum VOG
- [x] Each row has Nieuw (blue) or Vernieuwing (purple) badge
- [x] Names are clickable links to person profile
- [x] Sorting works for Name and Datum VOG columns
- [x] Empty state shows celebratory success message
- [x] Dark mode supported throughout
- [x] No lint errors in new files
- [x] Production build successful
- [x] Production deployment successful

## Next Phase Readiness

Phase 121 (VOG Bulk Actions) can proceed. Required foundations:
- VOGList component exists and displays volunteers needing action
- useVOGCount hook provides count for navigation
- Server-side filtering infrastructure (Phase 119) in place

---

*Plan: 120-01*
*Completed: 2026-01-30*
