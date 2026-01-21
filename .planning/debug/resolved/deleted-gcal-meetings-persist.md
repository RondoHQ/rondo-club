---
status: resolved
trigger: "Meetings deleted from Google Calendar still appear in 'Today's Meetings' on dashboard"
created: 2026-01-21T12:00:00Z
updated: 2026-01-21T12:06:00Z
---

## Current Focus

hypothesis: CONFIRMED - sync process only creates/updates, never detects deletions
test: Fix implemented and deployed
expecting: After sync, events deleted from source calendar will be removed locally
next_action: N/A - resolved

## Symptoms

expected: Meeting disappears from dashboard on next calendar sync after being deleted in Google Calendar
actual: Meeting persists on dashboard even after deletion from Google Calendar
errors: None reported
reproduction: Delete a meeting directly in Google Calendar, then check the dashboard - the meeting still appears
started: Has always been this way - deleted meetings have never synced properly

## Eliminated

## Evidence

- timestamp: 2026-01-21T12:01:00Z
  checked: GoogleProvider::sync_single_calendar() method (lines 167-230)
  found: The sync loop iterates over events from Google API and calls upsert_event() for each. It only creates or updates. There is NO code that:
    1. Collects all event UIDs returned from API
    2. Queries existing local events for this connection/calendar
    3. Deletes local events whose UIDs are not in the API response
  implication: This confirms deleted events are never cleaned up during sync

- timestamp: 2026-01-21T12:01:00Z
  checked: GoogleProvider::upsert_event() method (lines 264-352)
  found: Method only handles insert/update via wp_insert_post/wp_update_post. No deletion logic exists here.
  implication: Supports hypothesis - no deletion mechanism exists in the sync process

- timestamp: 2026-01-21T12:02:00Z
  checked: CalDAVProvider::do_sync() method and process_calendar_report()
  found: Same issue - only upserts events, no deletion detection. CalDAV also needs the fix.
  implication: Both providers need deletion logic added

## Resolution

root_cause: The Google Calendar sync process (GoogleProvider::sync_single_calendar) and CalDAV sync (CalDAVProvider::do_sync) only perform upserts - they create new events and update existing ones, but have no deletion detection. When an event is deleted from the source calendar, the local calendar_event CPT post is never removed.

fix: Added deletion detection to both providers:
  - GoogleProvider: Added delete_removed_events() method called after sync_single_calendar()
  - CalDAVProvider: Added delete_removed_events() method called after process_calendar_report()
  - Both track seen event UIDs during sync
  - After sync, query local events for same connection/calendar within sync date range
  - Delete any local events whose UIDs are not in the seen list
  - Updated REST API and background sync logging to include deleted count

verification:
  - Deploy completed successfully
  - To verify: Delete an event from Google Calendar, trigger manual sync in Settings > Connections > Calendars
  - Sync response will now include "deleted" count
  - Server logs will show deletion messages when events are removed

files_changed:
  - includes/class-google-calendar-provider.php
  - includes/class-caldav-provider.php
  - includes/class-calendar-sync.php
  - includes/class-rest-calendar.php
