# Quick Task 50: Remove "Markeren als aangevraagd" action from VOG page

**Completed:** 2026-02-10
**Duration:** 3 minutes
**Commit:** 49ae76b7

## Summary

Removed the "Markeren als aangevraagd" bulk action from the VOG page because it was redundant. The "VOG email verzenden" action already records the `vog-email-verzonden` date when sending emails, making a separate "mark as requested without sending" action unnecessary.

## Changes Made

### Frontend (React)
**File:** `src/pages/VOG/VOGList.jsx`

Removed:
- `showMarkRequestedModal` state variable
- `markRequestedMutation` useMutation hook
- `handleMarkRequested` async handler function
- "Markeren als aangevraagd..." button from bulk actions dropdown
- Complete "Mark Requested Modal" component (54 lines)
- `setShowMarkRequestedModal(false)` from `handleCloseModal`

### API Client
**File:** `src/api/client.js`

Removed:
- `bulkMarkVOGRequested: (ids) => api.post('/rondo/v1/vog/bulk-mark-requested', { ids })`

### Backend (PHP)
**File:** `includes/class-rest-api.php`

Removed:
- Route registration for `/vog/bulk-mark-requested` endpoint (18 lines)
- `bulk_mark_vog_requested()` method and docblock (50 lines)

## Impact

**Lines changed:** 239 insertions(+), 393 deletions(-) (net -154 lines)

**Remaining VOG bulk actions:**
1. VOG email verzenden - Sends email and records `vog-email-verzonden` date
2. Markeren bij Justis aangevraagd - Records `vog_justis_submitted_date`

Both remaining actions provide clear value and distinct functionality.

## Verification

- [x] `npm run build` succeeds
- [x] No references to `bulkMarkVOGRequested` in source code
- [x] No references to `showMarkRequestedModal` in source code
- [x] No references to `markRequestedMutation` in source code
- [x] Deployed to production

## Self-Check

All files and commits verified:

**Files modified:**
- FOUND: includes/class-rest-api.php
- FOUND: src/api/client.js
- FOUND: src/pages/VOG/VOGList.jsx

**Commit:**
- FOUND: 49ae76b7

## Self-Check: PASSED
