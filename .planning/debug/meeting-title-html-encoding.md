---
status: verifying
trigger: "Meeting titles showing `&amp;` instead of `&` on dashboard meetings widget"
created: 2026-01-18T12:00:00Z
updated: 2026-01-18T12:15:00Z
---

## Current Focus

hypothesis: CONFIRMED - WordPress post_title contains HTML entities, REST API returns raw database value
test: Traced data flow from Google Calendar sync -> database -> REST API -> React
expecting: Need to decode HTML entities when returning title from REST API
next_action: Apply fix by decoding post_title in format_today_meeting

## Symptoms

expected: Meeting titles should display `&` character correctly (e.g., "Bob & Jane meeting")
actual: Meeting titles display `&amp;` instead of `&` (e.g., "Bob &amp; Jane meeting")
errors: No error messages - visual display issue only
reproduction: View dashboard meetings widget where a meeting title contains an ampersand
started: Recently broke - used to display correctly

## Eliminated

## Evidence

- timestamp: 2026-01-18T12:05:00Z
  checked: class-google-calendar-provider.php line 303
  found: Title is saved with html_entity_decode(sanitize_text_field($event->getSummary()))
  implication: The sync code attempts to decode entities but WordPress may re-encode on insert

- timestamp: 2026-01-18T12:06:00Z
  checked: class-rest-calendar.php format_today_meeting (line 1541)
  found: Returns $event->post_title directly without decoding
  implication: Raw database value (with HTML entities) is returned to React

- timestamp: 2026-01-18T12:07:00Z
  checked: Dashboard.jsx MeetingCard component (line 263)
  found: Renders {meeting.title} directly (React auto-escapes, so &amp; displays literally)
  implication: Fix should be server-side - decode entities before returning from REST API

## Resolution

root_cause: WordPress stores post_title with HTML entities encoded in the database. The REST API returns $event->post_title raw, which contains &amp; instead of &. React renders this literally since it auto-escapes output.
fix: Added html_entity_decode($event->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8') in three places:
  - format_today_meeting (line 1541) - dashboard meetings widget
  - format_meeting_event (line 1295) - person meetings view
  - get_events callback (line 980) - calendar events list
verification: Deployed to production - verify meeting titles with & characters display correctly
files_changed:
  - includes/class-rest-calendar.php (3 places fixed)
