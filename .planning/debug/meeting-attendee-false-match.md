---
status: verifying
trigger: "Meeting attendees are being matched to people in the CRM who are NOT those people. Matching should only happen by email, but it appears to match by other criteria (possibly name)."
created: 2026-01-22T10:00:00Z
updated: 2026-01-22T10:15:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED
test: Removed fuzzy name matching fallback from match_single() method
expecting: After re-match, only exact email matches will show
next_action: Run WP-CLI rematch command and verify false matches are cleared

## Symptoms

expected: Meeting attendees should ONLY be matched to CRM people by exact email address match
actual: The meeting "Atarim - Yearly Kickoff 2026" shows Richard Theuws and Adam Buckeridge as attendees, but neither should be there. The email addresses shown don't match their actual emails.
errors: None - it's a logic/matching issue
reproduction: View a meeting in the meeting interface - some attendees are incorrectly matched to CRM people
started: Unknown - just discovered

## Eliminated

## Evidence

- timestamp: 2026-01-22T10:05:00Z
  checked: includes/class-calendar-matcher.php - match_single() method (lines 254-278)
  found: Code explicitly falls back to fuzzy_name_match() when email lookup fails. Lines 268-276 show the fallback logic.
  implication: This confirms the hypothesis - name-based matching is intentionally implemented as a fallback

- timestamp: 2026-01-22T10:06:00Z
  checked: includes/class-calendar-matcher.php - fuzzy_name_match() method (lines 287-375)
  found: Three levels of name matching implemented:
    1. Exact full name match (80% confidence) - lines 312-329
    2. First name only match if unique (60% confidence) - lines 331-349
    3. Levenshtein distance <= 2 on full name (50% confidence) - lines 351-372
  implication: False positives likely caused by these name-based matches, especially first-name-only matching

## Resolution

root_cause: The `match_single()` method in class-calendar-matcher.php had a fuzzy name matching fallback that ran when email matching failed. This fallback included:
  1. Exact full name match (80% confidence)
  2. First name only match if unique (60% confidence) - LIKELY CULPRIT
  3. Levenshtein distance <= 2 on full name (50% confidence)

  The first-name-only matching was particularly problematic - if a CRM had only one "Richard", any meeting attendee named "Richard" would match, regardless of last name or email.

fix: Removed the fuzzy name matching fallback entirely. The `match_single()` method now only performs exact email matching (100% confidence). Also removed the unused `fuzzy_name_match()` and `parse_name()` helper methods.

verification: PENDING - Need to run WP-CLI command to re-match existing events:
  ```
  wp prm calendar rematch --user-id=1
  ```
  Then verify the "Atarim - Yearly Kickoff 2026" meeting no longer shows Richard Theuws and Adam Buckeridge as attendees.

files_changed:
  - includes/class-calendar-matcher.php
