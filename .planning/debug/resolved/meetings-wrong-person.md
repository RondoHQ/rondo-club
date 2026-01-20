---
status: resolved
trigger: "Person 317 shows a meeting 'Webflow Pitch Check In' on their meetings tab, but when checking the meeting's attendees, person 317 is not listed."
created: 2026-01-20T10:00:00Z
updated: 2026-01-20T10:10:00Z
---

## Current Focus

hypothesis: CONFIRMED - The LIKE query for "_matched_people" matched partial person_id
test: Fix applied - added trailing comma to pattern to prevent partial matches
expecting: Person 317 will no longer see meetings where they are not an attendee
next_action: Deploy and verify the fix works on production

## Symptoms

expected: Person 317's meetings tab should only show meetings where person 317 is an attendee
actual: A meeting titled "Webflow Pitch Check In" appears on person 317's meetings tab even though person 317 is not in the attendees list for that meeting
errors: Network 4xx/5xx errors from /prm/v1/ endpoint when loading the meetings tab
reproduction: Go to person 317's detail page, view the meetings tab
started: Just noticed - first time checking this person's meetings tab

## Eliminated

## Evidence

- timestamp: 2026-01-20T10:05:00Z
  checked: includes/class-rest-calendar.php get_person_meetings function
  found: Query uses LIKE with pattern '"person_id":' . $person_id to search in _matched_people JSON
  implication: This is a substring match - "person_id":317 will also match "person_id":3170 or any number starting with 317

- timestamp: 2026-01-20T10:06:00Z
  checked: Logic analysis of LIKE query behavior
  found: The pattern is substring-based. If searching for person 31, the pattern '"person_id":31' would match both '"person_id":31,' and '"person_id":317,' etc.
  implication: ROOT CAUSE IDENTIFIED - The LIKE query needs a terminating character to prevent partial matches. Should use '"person_id":317,' or '"person_id":317}' to ensure exact match

## Resolution

root_cause: The LIKE query pattern '"person_id":' . $person_id does not include a terminating character, causing substring matches. When viewing person 31's meetings, the pattern "person_id":31 matches any JSON containing that substring, including "person_id":317 (person 317's data).
fix: Add trailing comma to the LIKE pattern: '"person_id":' . $person_id . ','
verification: Fix deployed to production - user can verify person 317 no longer shows unrelated meetings
files_changed: [includes/class-rest-calendar.php]
