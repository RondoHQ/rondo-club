---
phase: 78
plan: 01
subsystem: calendar
tags: [google-calendar, multi-select, checkbox-ui, sync]
dependency-graph:
  requires: [phase-68]
  provides: [multi-calendar-selection, calendar-ids-array]
  affects: []
tech-stack:
  added: []
  patterns: [array-normalization-helper]
key-files:
  created: []
  modified:
    - includes/class-google-calendar-provider.php
    - includes/class-rest-calendar.php
    - src/pages/Settings/Settings.jsx
decisions:
  - id: 78-01-01
    title: "Array normalization via static helper"
    choice: "get_calendar_ids() method returns array from any format"
    rationale: "Centralized backward compatibility - old connections work without migration"
metrics:
  duration: 4m 12s
  completed: 2026-01-17
---

# Phase 78 Plan 01: Multi-Calendar Selection Summary

**Multi-calendar sync for Google Calendar connections via checkbox UI**

## Key Changes

### Backend (PHP)

**includes/class-google-calendar-provider.php:**
- Added `get_calendar_ids()` static helper that normalizes connection data:
  - Returns `calendar_ids` array if present (new format)
  - Falls back to `[calendar_id]` for old single-value format
  - Defaults to `['primary']` if neither present
- Refactored `do_sync()` to loop through all selected calendars
- Extracted sync logic into `sync_single_calendar()` private method
- Updated `upsert_event()` to accept `$calendar_id` parameter for correct meta storage

**includes/class-rest-calendar.php:**
- Added `calendar_ids` array parameter to create_connection route args
- Added `calendar_ids` array parameter to update_connection route args
- Updated `update_connection()` to handle `calendar_ids` array and clear old single-value fields
- Updated `get_connection_calendars()` to return `current_ids` array alongside `current`

### Frontend (React)

**src/pages/Settings/Settings.jsx:**
- Changed state from `selectedCalendarId` to `selectedCalendarIds` array
- Initialize from `calendar_ids` (new) or `calendar_id` (old format)
- Replaced dropdown with checkbox list for multiple calendar selection
- Updated `handleSave()` to send `calendar_ids` array for Google connections
- Disabled Save button when no calendars selected for Google connections
- Updated connection card display to show "N calendars selected" for multi-calendar

## Verification

- [x] PHP syntax passes for both modified files
- [x] Build succeeds without errors
- [x] Backward compatibility: old single-calendar format normalized to array

## Success Criteria Met

- CAL-01: Checkbox UI allows selecting multiple calendars
- CAL-02: `calendar_ids` array stored in connection
- CAL-03: Sync iterates through all selected calendars
- CAL-04: Connection card shows "N calendars selected"
- CAL-05: Old `calendar_id` format normalized to array on read

## Deviations from Plan

None - plan executed exactly as written.

## Commits

| Hash | Description |
|------|-------------|
| 4965ed0 | feat(78-01): add multi-calendar backend support |
| ef76a1b | feat(78-01): add multi-calendar checkbox UI for Google connections |
