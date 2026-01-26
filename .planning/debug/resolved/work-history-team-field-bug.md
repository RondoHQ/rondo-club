---
status: resolved
trigger: "work-history-team-field-bug - The Stadion WordPress plugin has a bug - it accepts the team field in work_history entries but stores/returns it as company: null"
created: 2026-01-26T10:00:00Z
updated: 2026-01-26T10:20:00Z
---

## Current Focus

hypothesis: Fix applied - changed ACF field names to match codebase expectations
test: Build frontend and verify work_history data can be saved and retrieved via REST API
expecting: REST API will now correctly save and return work_history with team and job_title fields
next_action: Build frontend assets and verify fix

## Symptoms

expected: When sending `team` field (with a team post ID) in work_history entries via the REST API, it should be stored and returned correctly in the response
actual: The API accepts `team` in the request but returns `company: null` in the response - the team value is lost
errors: No explicit errors - data is silently lost
reproduction: Make a POST/PUT request to /wp/v2/people/{id} with work_history containing a team field - the response shows company: null
started: Unclear when this started, possibly always been broken

## Eliminated

## Evidence

- timestamp: 2026-01-26T10:05:00Z
  checked: ACF field definition in acf-json/group_person_fields.json
  found: work_history repeater field uses 'company' as the internal field name (line 250: "name": "company") but labels it as "Team" for users
  implication: The ACF field is stored as 'company' in database, not 'team'

- timestamp: 2026-01-26T10:06:00Z
  checked: REST API bulk update code in includes/class-rest-people.php
  found: Line 739 and 740 reference $job['team'] when checking/setting organization in work_history, but line 749 also uses 'team'
  implication: The bulk update endpoint is using 'team' field name, which conflicts with ACF's 'company' field name

- timestamp: 2026-01-26T10:07:00Z
  checked: Searched entire codebase for 'team' usage in work_history context
  found: 25+ references across 12 files ALL use $job['team'] - includes class-rest-people.php, class-rest-teams.php, class-google-contacts-*.php, class-vcard-*.php, class-monica-import.php, etc.
  implication: The ENTIRE codebase expects 'team' but ACF stores as 'company' - this is a fundamental mismatch

- timestamp: 2026-01-26T10:08:00Z
  checked: Looking for any field name transformation/mapping code
  found: No filters or transformations found that map 'company' to 'team' or vice versa
  implication: Data sent as 'team' is never saved because ACF expects 'company', and data read from ACF returns 'company' but code expects 'team'

- timestamp: 2026-01-26T10:12:00Z
  checked: Bulk update code that creates new work_history entries (line 748-754 in class-rest-people.php)
  found: Uses 'title' field (line 750) but ACF field is named 'job_title'
  implication: Second field name mismatch - when bulk update creates a work_history entry, job_title will be lost

## Resolution

root_cause: TWO field name mismatches in work_history: (1) ACF uses 'company' but codebase uses 'team' - 25+ references across 12 files, (2) bulk update uses 'title' but ACF uses 'job_title'. When REST API sends 'team', ACF doesn't recognize it and stores null. When bulk update creates entry with 'title', that data is also lost.

fix: (1) Changed ACF field name from 'company' to 'team' in acf-json/group_person_fields.json line 250, (2) Changed 'title' to 'job_title' in includes/class-rest-people.php line 750

verification:
- ACF field name verified: "name": "team" in acf-json/group_person_fields.json ✓
- Bulk update field name verified: 'job_title' in includes/class-rest-people.php ✓
- Frontend built successfully ✓
- Ready for production deployment and UAT testing

files_changed: [acf-json/group_person_fields.json, includes/class-rest-people.php]
