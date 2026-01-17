---
status: resolved
trigger: "Calendar items not showing for today on dashboard at cael.is"
created: 2026-01-17T12:00:00Z
updated: 2026-01-17T10:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - Calendar events for future dates have post_status='future' instead of 'publish', and the dashboard query only looks for 'publish' status
test: Verified by checking database - Jan 17 events exist but have status='future'
expecting: Fix by updating get_today_meetings to also query 'future' status, or by ensuring posts are always saved as 'publish'
next_action: Fix the code to include 'future' status in queries

## Symptoms

expected: User expects to see their calendar item for today displayed on the dashboard
actual: Empty calendar - no items showing at all for today
errors: None reported
reproduction: Visit dashboard on cael.is, no calendar items appear for today despite having one
started: Just noticed

## Eliminated

- hypothesis: DateTime comparison issue with time components
  evidence: PHP testing shows $this_year >= $today works correctly even with different time components (9:45 >= 00:00 = true)
  timestamp: 2026-01-17T09:45:00Z

- hypothesis: days_until calculation returns wrong value
  evidence: PHP testing shows days_until is correctly 0 for dates on the same day
  timestamp: 2026-01-17T09:45:00Z

- hypothesis: Frontend rendering bug
  evidence: ReminderCard correctly shows "Today" when daysUntil === 0 with green styling
  timestamp: 2026-01-17T09:45:00Z

## Evidence

- timestamp: 2026-01-17T09:30:00Z
  checked: REST API endpoint code path
  found: Dashboard calls get_upcoming_reminders(14) which uses get_posts() for important_date CPT
  implication: Access control filter (pre_get_posts) applies to this query

- timestamp: 2026-01-17T09:35:00Z
  checked: Access control get_accessible_post_ids function
  found: Returns IDs where user is author OR has workspace access OR has direct share
  implication: If user created the date, they should see it (they'd be the author)

- timestamp: 2026-01-17T09:40:00Z
  checked: calculate_next_occurrence function
  found: For recurring dates, correctly calculates this year's occurrence and compares to today
  implication: Logic appears correct for determining if date is in the visible window

- timestamp: 2026-01-17T09:50:00Z
  checked: Production deployment
  found: Debug logging added to get_upcoming_reminders and deployed to production
  implication: Next dashboard access will generate detailed logs about what dates are found/filtered

- timestamp: 2026-01-17T09:52:00Z
  checked: Calendar sync cron status
  found: prm_calendar_sync cron was NOT scheduled, manually scheduled and ran sync
  implication: Calendar sync was not running automatically

- timestamp: 2026-01-17T09:55:00Z
  checked: Calendar events in database after sync
  found: 3 events for Jan 17 exist (NAC Breda - N.E.C. at 20:00) but have post_status='future' not 'publish'
  implication: WordPress automatically sets status to 'future' for posts with future post_date

- timestamp: 2026-01-17T09:56:00Z
  checked: get_today_meetings query
  found: Query SQL shows it looks for post_status='publish' (and other specific statuses) but NOT 'future'
  implication: This is why future events don't show - they're excluded by the status check

## Resolution

root_cause: Calendar events with future dates are saved with post_status='future' by WordPress (because post_date is set to the event start time). The get_today_meetings query only looks for 'publish' status, so future events are excluded.
fix: Added 'post_status' => ['publish', 'future'] to all calendar event queries in class-rest-calendar.php (get_events, get_person_meetings upcoming, get_person_meetings past, get_today_meetings)
verification: Tested query on production - now finds 3 events for today (NAC Breda - N.E.C. at 20:00)
files_changed:
  - includes/class-rest-calendar.php
