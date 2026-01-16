---
status: complete
phase: 68-calendar-selection-ui
source: [68-01-SUMMARY.md]
started: 2026-01-16T12:00:00Z
updated: 2026-01-16T14:55:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Calendar List Loads in Edit Modal
expected: Open Edit modal for a Google connection. A "Calendar to sync" dropdown appears with available calendars. Primary calendar shows "(Primary)" suffix.
result: pass

### 2. Calendar Selection Changes
expected: Select a different calendar from the dropdown. The selection changes in the UI.
result: pass

### 3. Calendar Selection Saves
expected: After selecting a different calendar, click Save. Reopen the edit modal - the newly selected calendar should still be selected.
result: pass

### 4. Connection Card Shows Calendar
expected: For a connection with a non-primary calendar selected, the connection card should show the calendar ID or name as a subtitle below the connection name.
result: pass

### 5. CalDAV Calendar List
expected: Edit a CalDAV connection (if available). The calendar dropdown should also show available calendars for CalDAV connections.
result: skipped
reason: No CalDAV connection available to test

## Summary

total: 5
passed: 4
issues: 1
pending: 0
skipped: 1

## Issues for /gsd:plan-fix

- UAT-001: Connection card shows calendar_id instead of calendar name (cosmetic) - Test 2
