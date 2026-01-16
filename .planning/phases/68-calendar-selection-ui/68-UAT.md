---
status: testing
phase: 68-calendar-selection-ui
source: [68-01-SUMMARY.md]
started: 2026-01-16T12:00:00Z
updated: 2026-01-16T12:00:00Z
---

## Current Test

number: 1
name: Calendar List Loads in Edit Modal
expected: |
  Open Settings → Connections → Calendars tab.
  Click Edit (pencil icon) on an existing Google Calendar connection.
  Modal should show a "Calendar to sync" dropdown with available calendars listed.
  Primary calendar should have "(Primary)" suffix.
awaiting: user response

## Tests

### 1. Calendar List Loads in Edit Modal
expected: Open Edit modal for a Google connection. A "Calendar to sync" dropdown appears with available calendars. Primary calendar shows "(Primary)" suffix.
result: [pending]

### 2. Calendar Selection Changes
expected: Select a different calendar from the dropdown. The selection changes in the UI.
result: [pending]

### 3. Calendar Selection Saves
expected: After selecting a different calendar, click Save. Reopen the edit modal - the newly selected calendar should still be selected.
result: [pending]

### 4. Connection Card Shows Calendar
expected: For a connection with a non-primary calendar selected, the connection card should show the calendar ID or name as a subtitle below the connection name.
result: [pending]

### 5. CalDAV Calendar List
expected: Edit a CalDAV connection (if available). The calendar dropdown should also show available calendars for CalDAV connections.
result: [pending]

## Summary

total: 5
passed: 0
issues: 0
pending: 5
skipped: 0

## Issues for /gsd:plan-fix

[none yet]
