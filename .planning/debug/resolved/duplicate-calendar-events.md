---
status: resolved
trigger: "Same calendar event showing 4 times on dashboard after previous fix"
created: 2026-01-17T12:00:00Z
updated: 2026-01-17T12:15:00Z
---

## Current Focus

hypothesis: CONFIRMED - upsert_event() query missing post_status => 'any'
test: Verified with production query test
expecting: Fix the query to find future-status posts
next_action: Apply fix to class-google-calendar-provider.php and clean up duplicates

## Symptoms

expected: Each calendar event should appear once on the dashboard
actual: Same event is displayed 4 times
errors: None reported
reproduction: Visit dashboard on cael.is, calendar events appear multiple times
started: After previous fix that added 'future' post_status to queries

## Eliminated

- hypothesis: Frontend rendering duplicates
  evidence: Database contains duplicate posts - 3 copies of same event
  timestamp: 2026-01-17T12:05:00Z

## Evidence

- timestamp: 2026-01-17T12:02:00Z
  checked: Database calendar_event posts
  found: "Joost Vito" event exists 3 times (IDs 2352, 2680, 3001) with same _event_uid
  implication: Sync is creating duplicates instead of updating

- timestamp: 2026-01-17T12:03:00Z
  checked: Post meta for all 3 duplicate events
  found: All have identical _event_uid and _connection_id, all have post_status='future'
  implication: Duplicate detection is not working

- timestamp: 2026-01-17T12:04:00Z
  checked: upsert_event() query without post_status
  found: Returns 0 results because get_posts defaults to 'publish' status
  implication: Future-status posts are not found by duplicate detection

- timestamp: 2026-01-17T12:05:00Z
  checked: Same query with post_status => 'any'
  found: Returns the existing post correctly
  implication: Missing post_status in query is root cause

## Resolution

root_cause: In class-google-calendar-provider.php and class-caldav-provider.php upsert_event(), the get_posts() query for finding existing events does not specify post_status. Since WordPress defaults to 'publish', it cannot find events with 'future' status. When sync sets post_date to future event start time, WordPress auto-assigns 'future' status. Next sync cannot find the existing post, creates a duplicate.
fix: Added post_status => 'any' to the upsert_event() query in both Google and CalDAV providers. Cleaned up 797 duplicate calendar events from the database.
verification: Verified upsert query now finds future-status posts. Verified today-meetings endpoint returns correct count without duplicates. Deployed to production.
files_changed:
  - includes/class-google-calendar-provider.php
  - includes/class-caldav-provider.php
