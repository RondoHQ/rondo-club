---
status: verifying
trigger: "The events/meetings widget shows `&amp;` instead of `&` in some event titles"
created: 2026-01-17T10:00:00Z
updated: 2026-01-17T10:00:00Z
---

## Current Focus

hypothesis: CONFIRMED - sanitize_text_field() caused HTML entity encoding of & to &amp;
test: Fix deployed to production, user needs to verify on dashboard
expecting: Meetings with & in titles should display correctly
next_action: User verification - check Today's Meetings widget on dashboard

## Symptoms

expected: Event titles should display `&` character correctly (e.g., "Tom & Jerry")
actual: Event titles display `&amp;` literally (e.g., "Tom &amp; Jerry")
errors: None - just visual display issue
reproduction: View Today's Meetings widget on dashboard, look for events with ampersands in titles
started: Always been there since the feature was built

## Eliminated

## Evidence

- timestamp: 2026-01-17T10:05:00Z
  checked: format_today_meeting() in class-rest-calendar.php line 1394
  found: 'title' => sanitize_text_field( $event->post_title )
  implication: sanitize_text_field() HTML-encodes special characters like &

- timestamp: 2026-01-17T10:05:00Z
  checked: format_meeting_event() in class-rest-calendar.php line 1122
  found: 'title' => sanitize_text_field( $event->post_title )
  implication: Same issue exists in person meetings endpoint

- timestamp: 2026-01-17T10:06:00Z
  checked: WordPress sanitize_text_field() behavior
  found: Converts special characters to HTML entities for safe HTML output
  implication: Unnecessary for REST API - React handles escaping during render

## Resolution

root_cause: sanitize_text_field() was applied to post_title in REST API responses, which HTML-encodes special characters like & to &amp;. Since React handles escaping when rendering, this double-encoding caused &amp; to display literally.

fix: Removed sanitize_text_field() wrapper from $event->post_title in two locations:
- format_meeting_event() line 1122
- format_today_meeting() line 1394

verification: Deployed to production, awaiting user verification. Commit cef3d90.

files_changed:
- includes/class-rest-calendar.php
