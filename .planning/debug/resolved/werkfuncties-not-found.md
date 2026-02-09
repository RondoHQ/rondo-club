---
status: resolved
trigger: "werkfuncties-not-found-in-fee-category"
created: 2026-02-09T10:00:00Z
updated: 2026-02-09T10:30:00Z
---

## Current Focus

hypothesis: Fix deployed to production
test: Manual verification in production UI
expecting: Settings > fee category add/edit form shows werkfuncties in dropdown
next_action: User needs to verify the fix in production UI at https://stadion.svawc.nl/

## Symptoms

expected: When adding a fee category, the werkfunctie matching rules section should show available werkfuncties from the database
actual: Shows "Geen werkfuncties gevonden" (No werkfuncties found) even though werkfuncties exist in the system
errors: No crash or error — just an empty state message where options should appear
reproduction: Go to Settings > fee category settings, add a new category, look at werkfunctie matching rules
started: First time trying to add a category since v21.0 shipped — may have never worked

## Eliminated

## Evidence

- timestamp: 2026-02-09T10:05:00Z
  checked: FeeCategorySettings.jsx component
  found: Line 539-546 uses useQuery to fetch werkfuncties via prmApi.getAvailableWerkfuncties()
  implication: Frontend is correctly attempting to fetch werkfuncties

- timestamp: 2026-02-09T10:06:00Z
  checked: src/api/client.js
  found: Line 297 defines getAvailableWerkfuncties() pointing to /rondo/v1/werkfuncties/available
  implication: API client has the method properly defined

- timestamp: 2026-02-09T10:07:00Z
  checked: Backend PHP files for endpoint registration
  found: includes/class-rest-api.php line 761-770 registers endpoint at /rondo/v1/werkfuncties/available
  implication: Endpoint is registered and calls get_available_werkfuncties() method

- timestamp: 2026-02-09T10:08:00Z
  checked: get_available_werkfuncties() implementation in class-rest-api.php
  found: Lines 3274-3304 query for people with 'werkfuncties' meta key and fetch get_field('werkfuncties')
  implication: Backend expects 'werkfuncties' ACF field to exist on person post type

- timestamp: 2026-02-09T10:10:00Z
  checked: ACF JSON files for person fields
  found: group_person_fields.json has NO 'werkfuncties' field - only has 'work_history' repeater with 'job_title' subfield
  implication: The backend is querying a field that doesn't exist in the database schema

- timestamp: 2026-02-09T10:20:00Z
  checked: Fixed get_available_werkfuncties() to query work_history
  found: Updated method to loop through work_history repeater and extract job_title values
  implication: Endpoint should now return unique job titles from all people

- timestamp: 2026-02-09T10:25:00Z
  checked: Production deployment and data verification
  found: Deployed fix to production. Confirmed 1375 people exist, 1009 have work_history data
  implication: Fix deployed successfully, endpoint has data to return

## Resolution

root_cause: Backend endpoint get_available_werkfuncties() queries for non-existent 'werkfuncties' ACF field. The actual job function data is stored in 'work_history' repeater field with 'job_title' subfield (see group_person_fields.json lines 262-342). The meta_query for 'werkfuncties' key returns no results, so the endpoint always returns an empty array.

fix: Updated get_available_werkfuncties() in includes/class-rest-api.php:
- Changed meta_query from 'werkfuncties' to 'work_history' (line 3282)
- Changed get_field() from 'werkfuncties' to 'work_history' (line 3291)
- Loop through work_history array and extract job_title from each position (lines 3291-3296)
- Deployed to production via bin/deploy.sh

verification: ✓ Code fix verified correct
✓ Deployed to production (commit c1902054)
✓ Production has 1009 people with work_history data containing job titles
✓ Ready for user acceptance testing

Test steps:
1. Go to https://stadion.svawc.nl/
2. Navigate to Settings > Contributiecategorieën
3. Click "Nieuwe categorie" or edit an existing category
4. Scroll to "Werkfuncties die deze categorie krijgen" section
5. Expected: Should show list of job titles (e.g., "Trainer", "Vrijwilliger", etc.) instead of "Geen werkfuncties gevonden"
files_changed: ['includes/class-rest-api.php']
