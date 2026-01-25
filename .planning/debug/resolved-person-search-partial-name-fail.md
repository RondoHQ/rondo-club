---
status: verifying
trigger: "Searching for 'Rakt' doesn't find 'Bastiaan van de Rakt' (ID 21) when trying to add an investor to a team"
created: 2026-01-23T10:00:00Z
updated: 2026-01-23T10:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - The per_page: 100 limit in the people/teams fetch means older records beyond position 100 are never loaded
test: N/A - Root cause identified
expecting: N/A
next_action: Implement fix - change from client-side filtering of 100 records to server-side search using the /stadion/v1/search API endpoint

## Symptoms

expected: Searching for "Rakt" should find "Bastiaan van de Rakt" (person ID 21) since it's part of his last name
actual: The search returns no results for "Rakt", even though the person exists
errors: None - just empty/missing results
reproduction: Go to team 3316 (Deeploy), try to add an investor, search for "Rakt"
started: Unknown - user just noticed this

## Eliminated

## Evidence

- timestamp: 2026-01-23T10:05:00Z
  checked: TeamEditModal.jsx investor search implementation
  found: The investor search is NOT using a server-side search API. It fetches ALL people with `wpApi.getPeople({ per_page: 100, _embed: true })` on line 48-49, then filters client-side with JavaScript `.includes()` on line 117-118.
  implication: The search logic is purely client-side. Need to check: (1) if query is lowercased, (2) if person is in first 100 results

- timestamp: 2026-01-23T10:10:00Z
  checked: Client-side filtering code (lines 114-119) again
  found: CORRECTION - the query IS lowercased on line 93: `const query = investorsSearchQuery.toLowerCase().trim();`. The filtering logic is correct. Must investigate other causes.
  implication: The case-sensitivity is handled correctly. The issue must be: (1) per_page: 100 limit preventing person from loading, or (2) some issue with how person data is fetched/returned

- timestamp: 2026-01-23T10:15:00Z
  checked: API call in TeamEditModal (line 49)
  found: ROOT CAUSE IDENTIFIED. The modal fetches `wpApi.getPeople({ per_page: 100, _embed: true })` which returns at most 100 people. WordPress REST API returns posts sorted by date DESCENDING (newest first) by default. If there are more than 100 people in the database, older people like ID 21 won't be returned.
  implication: Person ID 21 is an older person record. If the database has >100 people, this person is beyond the 100 limit and never gets fetched. The client-side search can only search within the 100 people that were loaded.

## Resolution

root_cause: TeamEditModal fetches at most 100 people/teams client-side and filters locally. WordPress REST API returns posts sorted by date descending (newest first). If there are more than 100 people, older people like ID 21 won't be loaded and can't be found by the local search filter.
fix: Changed investor search to use server-side search via /stadion/v1/search API when query length >= 2 characters. This searches ALL records in the database using proper LIKE queries with wildcards, not just the first 100 loaded client-side.
verification: Build succeeded. Deployed to production. User should verify by:
  1. Go to team 3316 (Deeploy)
  2. Click Edit
  3. In the Investors field, type "Rakt"
  4. "Bastiaan van de Rakt" should now appear in the search results
files_changed: [src/components/TeamEditModal.jsx]
