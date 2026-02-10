---
phase: quick-46
plan: 1
subsystem: people-list-filtering
tags:
  - filtering
  - membership
  - rest-api
  - react-ui
dependency_graph:
  requires: []
  provides:
    - lid-tot-future-filter
  affects:
    - people-list-page
    - rest-api-people-filtered
    - google-sheets-export
tech_stack:
  added: []
  patterns:
    - server-side-filtering
    - url-state-management
key_files:
  created: []
  modified:
    - includes/class-rest-people.php
    - src/hooks/usePeople.js
    - src/pages/People/PeopleList.jsx
decisions: []
metrics:
  duration_seconds: 277
  tasks_completed: 1
  files_modified: 3
  completed_date: 2026-02-10
---

# Quick Task 46: Add Filter for People with Lid-tot Date in Future

**One-liner:** Added filter to show only people with membership end date (lid-tot) set in the future, enabling admins to identify members whose membership will expire soon.

## Objective

Add a filter to the People list that shows only people with a `lid-tot` date set in the future, allowing club administrators to easily identify members whose membership has a known upcoming end date.

## Implementation

### Backend (REST API)

**File:** `includes/class-rest-people.php`

1. Added `lid_tot_future` parameter to `/wp-json/rondo/v1/people/filtered` route registration
   - Type: string ('1' or empty)
   - Validates against whitelist of allowed values
   - Default: empty (show all people)

2. Extracted parameter in `get_filtered_people()` method

3. Added filter logic after VOG Justis status filter:
   - LEFT JOIN on `wp_postmeta` for `lid-tot` meta key
   - WHERE clause: `meta_value >= current_date` when filter active
   - Uses `gmdate('Y-m-d')` for timezone-safe comparison

### Frontend Hook

**File:** `src/hooks/usePeople.js`

1. Added `lidTotFuture` to JSDoc parameter documentation
2. Added `lid_tot_future` parameter passthrough in `useFilteredPeople` params object
3. Filter maps from camelCase (`lidTotFuture`) to snake_case (`lid_tot_future`) for REST API

### Frontend UI

**File:** `src/pages/People/PeopleList.jsx`

1. **URL State Management:**
   - Extracted `lidTot` URL parameter
   - Created `setLidTotFuture` setter using `updateSearchParams`

2. **Filter Dropdown UI:**
   - Added toggle after "Toon oud-leden" toggle
   - Label: "Lid-tot in de toekomst"
   - Uses same electric-cyan styling as other toggles

3. **Filter Integration:**
   - Passed `lidTotFuture` to `useFilteredPeople` hook
   - Added to `hasActiveFilters` check
   - Included in filter count badge calculation

4. **Filter Chip:**
   - Added chip "Lid-tot in de toekomst" after VOG older than chip
   - Shows when filter is active
   - Clickable X button to clear filter

5. **Selection Clearing:**
   - Added `lidTotFuture` to useEffect dependency array
   - Selection clears when filter changes

6. **Google Sheets Export:**
   - Added `lid_tot_future` to export filters object
   - Filter applied when exporting filtered results

## Technical Details

### Filter Logic

The backend filter uses a LEFT JOIN to avoid excluding people without a lid-tot value when the filter is inactive. When active (`lid_tot_future=1`), the WHERE clause filters to:
- `meta_value IS NOT NULL` - has a value
- `meta_value != ''` - not empty string
- `meta_value >= today` - date is today or future

This follows the established pattern used by other date-based filters in the same endpoint.

### URL State Pattern

Filter uses URL parameter `lidTot=1` (shortened for cleaner URLs), following the existing pattern:
- `oudLeden` for includeFormer
- `vogOuder` for vogOlderThanYears
- `fotoMissing` for photo filter

### UI Placement

Filter toggle placed immediately after "Toon oud-leden" toggle and before the Labels filter section, grouping membership-related filters together in the dropdown.

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- [x] `npm run build` succeeded without errors
- [x] `npm run lint` shows only pre-existing issues (140 lint problems documented in STATE.md)
- [x] `php -l includes/class-rest-people.php` shows no syntax errors
- [x] `grep lid_tot_future` confirms backend filter present
- [x] `grep lidTotFuture` confirms frontend filter wired
- [x] Deployed to production via `bin/deploy.sh`

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 31904658 | feat(quick-46): add lid-tot future filter to people list |

## Self-Check: PASSED

**Files modified (verified):**
- FOUND: includes/class-rest-people.php
- FOUND: src/hooks/usePeople.js
- FOUND: src/pages/People/PeopleList.jsx

**Commit (verified):**
- FOUND: 31904658

**Deployment:**
- SUCCESS: Deployed to production at https://stadion.svawc.nl/

## Impact

**User benefit:** Club administrators can now easily filter the people list to show only members with a known upcoming membership end date, enabling proactive outreach for membership renewals or farewell communications.

**Integration points:**
- REST API: `/wp-json/rondo/v1/people/filtered?lid_tot_future=1`
- URL state: `?lidTot=1`
- Export: Filter applies to Google Sheets exports

**Next steps:** None - feature is complete and ready for use.
