# Quick Task 021: Add Justis Date to VOG Overview

## Summary

Added a new "Justis" column to the VOG overview that tracks when VOG requests were submitted to the Justis system. This is separate from and in addition to the "Verzonden" column that shows when the VOG email was sent.

## Changes Made

### Backend (`includes/class-rest-api.php`)
- Added new `/vog/bulk-mark-justis` endpoint for recording Justis submission dates
- Uses `vog_justis_submitted_date` post meta for storage

### Backend (`includes/class-rest-people.php`)
- Updated `get_filtered_people()` to include `vog_email_sent_date` and `vog_justis_submitted_date` post meta fields in the ACF array for frontend consistency

### Frontend (`src/api/client.js`)
- Added `bulkMarkVOGJustis(ids)` API client method

### Frontend (`src/pages/VOG/VOGList.jsx`)
- Added `showMarkJustisModal` state
- Added `markJustisMutation` for API calls
- Added `handleMarkJustis` handler function
- Added "Markeren bij Justis aangevraagd..." option to bulk actions dropdown
- Added "Justis" column header with sorting support
- Added Justis date column to VOGRow component
- Added confirmation modal for Justis marking action

## VOG Overview Now Shows

| Column | Description |
|--------|-------------|
| Naam | Person name with badge (Nieuw/Vernieuwing) |
| KNVB ID | KNVB identifier |
| Email | Email address |
| Telefoon | Phone number |
| Datum VOG | Original/previous VOG date |
| Verzonden | When VOG email was sent to member |
| Justis | When VOG request was submitted to Justis system |

## Commit

`244d45a` - feat(021): add Justis submission date to VOG overview

## Verification

- [x] Build successful
- [x] Deployed to production
- [x] Justis column visible in VOG overview
- [x] Bulk action available in dropdown
