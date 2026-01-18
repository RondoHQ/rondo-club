---
status: diagnosed
phase: 86-meeting-card-polish
source: 86-01-SUMMARY.md
started: 2026-01-18T01:10:00Z
updated: 2026-01-18T01:10:00Z
---

## Current Test

[testing complete]

## Tests

### 1. 24h Time Format
expected: Meeting times on the dashboard display in 24-hour format (e.g., "14:30" instead of "2:30 PM")
result: pass

### 2. Past Meetings Dimmed
expected: Meetings that have already ended appear dimmed (50% opacity) compared to upcoming meetings
result: pass

### 3. Current Meeting Highlighted
expected: A meeting that is currently happening (now is between start and end time) has a distinct highlight with an accent-colored ring
result: skipped
reason: No active meeting at time of testing

### 4. Event Title Ampersands
expected: Event titles that previously had "&amp;" now correctly display as "&" (e.g., "Proctor & Gamble" not "Proctor &amp; Gamble")
result: issue
reported: "I see 'MW) Forward PT Emilia - Joost&amp;Marieke'"
severity: major

## Summary

total: 4
passed: 2
issues: 1
pending: 0
skipped: 1

## Gaps

- truth: "Event titles with & character display correctly (not &amp;)"
  status: fixed
  reason: "User reported: I see 'MW) Forward PT Emilia - Joost&amp;Marieke'"
  severity: major
  test: 4
  root_cause: "WP-CLI cleanup_titles only queried post_status='publish', missing 'future' events"
  artifacts:
    - path: "includes/class-wp-cli.php"
      issue: "SQL WHERE clause only included publish status"
  fix: "Changed query to include IN ('publish', 'future')"
  commit: "5f40ff5"
