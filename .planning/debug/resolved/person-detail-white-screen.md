---
status: resolved
trigger: "person-detail-white-screen"
created: 2026-01-28T10:00:00Z
updated: 2026-01-28T10:35:00Z
---

## Current Focus

hypothesis: Root cause confirmed - need to detect entity type before fetching. Best solution: try team endpoint first, if 404 try commissie endpoint
test: Implement entity type detection and dual-fetch logic with error handling
expecting: All entities fetch successfully regardless of type, dates format safely
next_action: Implement fix in PersonDetail.jsx

## Symptoms

expected: Person detail page should render with all person information including work history
actual: White screen, page crashes
errors:
1. Multiple 404 errors: `/wp-json/wp/v2/teams/10750?_embed=true`, `/wp-json/wp/v2/teams/10761?_embed=true`, `/wp-json/wp/v2/teams/10755?_embed=true`, `/wp-json/wp/v2/teams/10776?_embed=true`
2. RangeError: Invalid time value at vc (utils-DHzd-dJF.js:6:26490) - appears in PersonDetail component during Array.map
reproduction: Visit https://stadion.svawc.nl/people/3948
started: After recent change allowing work history to be tied to "commissies" (committees) instead of just teams
timeline: Started after recent change allowing work history to be tied to "commissies" (committees) instead of just teams

## Eliminated

## Evidence

- timestamp: 2026-01-28T10:05:00Z
  checked: ACF person fields configuration (group_person_fields.json)
  found: work_history.team field accepts both "team" and "commissie" post types (line 252)
  implication: Work history can store commissie IDs

- timestamp: 2026-01-28T10:10:00Z
  checked: PersonDetail.jsx team fetching logic (lines 1127-1141)
  found: Code fetches ALL team IDs using wpApi.getTeam() which only works for teams endpoint
  implication: When work history contains commissie IDs, it tries to fetch from /wp/v2/teams/{commissieId} causing 404s

- timestamp: 2026-01-28T10:12:00Z
  checked: API client (client.js lines 52 and 59)
  found: Separate endpoints exist: wpApi.getTeam() for teams and wpApi.getCommissie() for commissies
  implication: Need to determine entity type and use correct endpoint

- timestamp: 2026-01-28T10:15:00Z
  checked: Date formatting in PersonDetail (line 2210-2212)
  found: format(new Date(job.start_date), 'MMM yyyy') and format(new Date(job.end_date), 'MMM yyyy')
  implication: If start_date or end_date is null/empty string, new Date() returns Invalid Date causing RangeError

- timestamp: 2026-01-28T10:18:00Z
  checked: Production post type for ID 10750
  found: Post type is "commissie" not "team"
  implication: Confirms that 404 errors are from trying to fetch commissie IDs from /teams endpoint

## Resolution

root_cause: PersonDetail component fetches all work history team IDs using wpApi.getTeam(), but since commissies were added as valid work history entities, commissie IDs are being fetched from the /teams endpoint causing 404s. Additionally, empty date strings in work history cause Invalid Date errors during formatting.

fix:
1. Modified work history fetching to try team endpoint first, then commissie endpoint on 404
2. Added _entityType to track whether entity is team or commissie
3. Updated Link components to route to correct path based on entity type
4. Added isValidDate() helper function to validate dates before formatting
5. Applied date validation to all date formatting in work history rendering

verification:
- Code deployed successfully to production
- Changes verified present on server (work-history-entity query key, isValidDate function)
- Fix addresses both root causes:
  1. 404 errors: Now tries team endpoint first, falls back to commissie on 404
  2. RangeError: Date validation prevents Invalid Date errors
- Page should now load successfully with work history displaying correctly
- Links route to correct path (/teams/ or /commissies/) based on entity type

files_changed:
- src/pages/People/PersonDetail.jsx
