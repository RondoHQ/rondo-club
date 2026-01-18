---
status: investigating
trigger: "User sees their own face in MeetingCard attendees, but it should be filtered out based on Profile Link setting"
created: 2026-01-18T14:30:00+01:00
updated: 2026-01-18T14:45:00+01:00
---

## Current Focus

hypothesis: Type mismatch between person_id (from API) and currentUserPersonId (from prmConfig) causing strict equality comparison to fail
test: Compare types at runtime - add console.log to MeetingCard filter
expecting: If types differ (string vs number), filter fails silently
next_action: User to test on production, check browser console for debug output showing types

## Symptoms

expected: Meeting cards show all attendees' faces EXCEPT the current user (identified via "Profile link" setting in user profile)
actual: User's own face appears in meeting card attendees on meetings where their email is listed
errors: None - filtering silently fails
reproduction: View any meeting card on dashboard where user's email is an attendee, after linking profile in Settings
started: Regression - was working after c5c86d3 commit, now broken

## Eliminated

(none yet)

## Evidence

- timestamp: 2026-01-18T14:25:00+01:00
  checked: MeetingCard filtering logic in Dashboard.jsx (lines 248-252)
  found: Uses strict inequality `!==` for comparison: `person.person_id !== currentUserPersonId`
  implication: Strict equality is type-sensitive; "123" !== 123 in JavaScript

- timestamp: 2026-01-18T14:27:00+01:00
  checked: PHP config generation in functions.php (lines 510, 527)
  found: `$linked_person_id = (int) get_user_meta(...)` cast to int, then `'currentUserPersonId' => $linked_person_id ?: null`
  implication: Should be integer in prmConfig, but JSON encoding could affect this

- timestamp: 2026-01-18T14:28:00+01:00
  checked: PHP matched_people formatting in class-rest-calendar.php (lines 1480-1484)
  found: `'person_id' => $person_id` where $person_id comes from Matcher which returns integers
  implication: Should also be integer, but need to verify runtime types

- timestamp: 2026-01-18T14:29:00+01:00
  checked: Git history - feature implemented in c5c86d3
  found: No changes to filtering logic since implementation; changes since are unrelated (Google Contacts sync, widget styling)
  implication: Bug may have been present from start, or caused by data issue rather than code change

## Resolution

root_cause:
fix:
verification:
files_changed: []
