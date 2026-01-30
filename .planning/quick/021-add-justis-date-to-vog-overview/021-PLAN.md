# Quick Task 021: Add Justis Date to VOG Overview

## Goal
Add a new "Aangevraagd bij Justis" date column to the VOG overview that tracks when the VOG request was submitted to the Justis system. This is separate from the email sent date.

## Current State
- VOG list shows: Name, KNVB ID, Email, Phone, Datum VOG, Verzonden (email sent date)
- No field exists for tracking when VOG was submitted to Justis
- "Markeren als aangevraagd" action only sets `vog-email-verzonden` field

## Tasks

### Task 1: Add VOG Justis date field to REST API
**Files:** `includes/class-rest-api.php`

Changes:
1. Add new bulk action endpoint `/vog/bulk-mark-justis` to set the Justis submission date
2. Use post meta `vog_justis_submitted_date` for storage

### Task 2: Add bulk action to frontend
**Files:** `src/pages/VOG/VOGList.jsx`, `src/api/client.js`

Changes:
1. Add API client method `bulkMarkVOGJustis(ids)`
2. Add "Markeren als bij Justis aangevraagd" option to bulk actions dropdown
3. Add modal for confirmation
4. Add "Justis" column to table after "Verzonden"

## Verification
- Build frontend
- Deploy to production
- Test bulk action and column display in VOG overview
