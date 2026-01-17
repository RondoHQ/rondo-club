---
status: verifying
trigger: "duplicate-attendees-multiple-emails"
created: 2026-01-17T10:00:00Z
updated: 2026-01-17T10:15:00Z
---

## Current Focus

hypothesis: Fix applied - now verifying on production
test: Check FAIR Sync meeting from October 10, 2025 - Carrie should appear only once
expecting: Single entry for Carrie with person_id 632, not two entries
next_action: User verification on production (https://cael.is)

## Symptoms

expected: When a person has multiple email addresses and those emails appear as meeting attendees, the person should show only once in the attendee list
actual: The person shows up multiple times (once per email address that matched). Also, adding a new email address to an existing person from the attendee list fails.
errors: Adding email fails (unknown error type)
reproduction: View meeting "FAIR Sync" from October 10, 2025 - shows Carrie (person ID 632) twice because she has two email addresses (carrie@carriedils.com and carriedils@gmail.com)
started: Always been this way since feature was built

## Eliminated

## Evidence

- timestamp: 2026-01-17T10:03:00Z
  checked: class-calendar-matcher.php match_attendees() function
  found: match_attendees() loops through each attendee email and returns one match per email. If a person has emails A and B and both are meeting attendees, this returns two matches with the same person_id but different attendee_email values.
  implication: The matching logic itself is correct per-email, but deduplication needs to happen when displaying attendees

- timestamp: 2026-01-17T10:04:00Z
  checked: class-rest-calendar.php format_meeting_event() and format_today_meeting()
  found: Both functions build matched_emails lookup from matched_people_raw and then iterate raw_attendees. Each attendee with matching email gets matched=true. If person has 2 emails in raw_attendees, they appear twice in the output with different email values but same person_id.
  implication: ROOT CAUSE CONFIRMED for Issue 1 - need to deduplicate attendees by person_id when matched

- timestamp: 2026-01-17T10:05:00Z
  checked: usePeople.js useAddEmailToPerson() hook
  found: Hook looks correct - fetches person, checks for duplicate, adds email, updates person. Uses wpApi.updatePerson() which should work.
  implication: Need to test the actual API call to see what error occurs when adding email fails - DEFER to separate investigation

- timestamp: 2026-01-17T10:07:00Z
  checked: Full code path for attendee deduplication
  found: The fix needs to happen in two places - format_meeting_event() and format_today_meeting(). Both have similar logic. Need to: (1) track seen person_ids, (2) for matched attendees with seen person_id, skip adding to array but collect their emails, (3) for matched attendees not yet seen, add to array and track person_id
  implication: Can extract helper method to avoid code duplication

- timestamp: 2026-01-17T10:12:00Z
  checked: Implemented fix
  found: Created build_deduplicated_attendees() helper method that tracks seen person_ids and only adds each matched person once. Additional emails are collected in an 'emails' array on the first entry. Updated both format_meeting_event() and format_today_meeting() to use this helper.
  implication: Deployed to production for verification

## Resolution

root_cause: In format_meeting_event() and format_today_meeting(), attendees were built by iterating raw_attendees and checking matched_emails. If same person_id matched multiple emails, they appeared multiple times because there was no deduplication by person_id.
fix: Added build_deduplicated_attendees() helper method that tracks seen_person_ids. When processing an attendee whose person_id was already seen, the duplicate is skipped and the additional email is added to an 'emails' array on the existing entry. Both format_meeting_event() and format_today_meeting() now use this helper.
verification: PENDING - deployed to production, awaiting user verification
files_changed:
  - includes/class-rest-calendar.php (added build_deduplicated_attendees helper, updated format_meeting_event and format_today_meeting)
