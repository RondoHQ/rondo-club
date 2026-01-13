# Plan 19-01 Summary: Frontend Bug Fixes

## Status: COMPLETED

## Tasks Completed: 3/3

### Task 1: Default date to today when creating new important date
- **File Modified:** `src/components/ImportantDateModal.jsx`
- **Change:** Added `const today = new Date().toISOString().split('T')[0];` and set `date_value: today` in the reset call for new dates
- **Commit:** `fix(19-01): default date to today when creating new important date`

### Task 2: Fix visibility required error when editing important date
- **File Modified:** `src/pages/People/PersonDetail.jsx`
- **Change:** Added `_visibility` and `_assigned_workspaces` fields to the ACF payload when updating an important date, preserving existing values or defaulting appropriately
- **Commit:** `fix(19-01): preserve visibility fields when editing important date`

### Task 3: Fix cache invalidation query key mismatch
- **File Modified:** `src/pages/People/PersonDetail.jsx`
- **Change:**
  - Imported `peopleKeys` from `@/hooks/usePeople`
  - Changed cache invalidation from `queryClient.invalidateQueries({ queryKey: ['person-dates', id] })` to `queryClient.invalidateQueries({ queryKey: peopleKeys.dates(id) })`
  - This ensures the query key matches `['people', 'detail', id, 'dates']` used by `usePersonDates` hook
- **Commit:** `fix(19-01): fix cache invalidation query key for dates`

## Files Modified
- `src/components/ImportantDateModal.jsx`
- `src/pages/People/PersonDetail.jsx`
- `style.css` (version bump)
- `package.json` (version bump)
- `CHANGELOG.md`

## Verification
- [x] New important dates default to today's date
- [x] Editing dates saves without 400 error (visibility fields preserved)
- [x] Cache invalidation uses correct query key matching `usePersonDates` hook
- [x] `npm run build` succeeds without errors (chunk size warning is expected)
- [ ] `npm run lint` - ESLint config missing from project (pre-existing issue)

## Version
- Updated from 1.70.0 to 1.70.1

## Notes
- The ESLint configuration file is missing from the project root, so `npm run lint` fails. This is a pre-existing condition and not related to this plan's changes.
- The cache invalidation fix discovered a mismatch between the query key used in `handleSaveDate` (`['person-dates', id]`) and the key used by `usePersonDates` hook (`peopleKeys.dates(id)` which expands to `['people', 'detail', id, 'dates']`).
