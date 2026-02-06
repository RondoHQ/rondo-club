---
phase: 68
plan: 01
subsystem: calendar
tags: [google-calendar, caldav, rest-api, react, settings]
requires: [67-01, 67-02]
provides: [calendar-selection-api, calendar-selection-ui]
affects: [calendar-sync]
tech-stack:
  added: []
  patterns: []
key-files:
  created: []
  modified:
    - includes/class-google-calendar-provider.php
    - includes/class-rest-calendar.php
    - src/api/client.js
    - src/pages/Settings/Settings.jsx
key-decisions: []
duration: 10 min
completed: 2026-01-16
---

# Phase 68 Plan 01: Calendar Selection UI Summary

Calendar selection UI allowing users to choose which calendar to sync per connection.

## Objective Achieved

Users can now view available calendars for each connection and select which calendar to sync, enabling multi-calendar support for both Google and CalDAV connections.

## Tasks Completed

| # | Task | Commit |
|---|------|--------|
| 1 | Add list calendars REST endpoint and Google calendar list method | f34fdd1 |
| 2 | Add calendar selector to EditConnectionModal | b52ed67 |

## Implementation Details

### Task 1: Calendar List API

**Backend changes:**

1. Added `list_calendars()` static method to `Stadion\Calendar\GoogleProvider`:
   - Uses Google Calendar API's `calendarList` endpoint
   - Returns array of calendars with id, name, color, and primary flag
   - Handles errors gracefully, returns empty array on failure

2. Added REST endpoint `GET /rondo/v1/calendar/connections/{id}/calendars`:
   - Permission: requires approved user
   - Routes to appropriate provider based on connection type
   - Google: uses new `list_calendars()` method
   - CalDAV: uses existing `discover_calendars()` method (already returns compatible format)
   - Returns `{ calendars: [...], current: string }`

### Task 2: Frontend Calendar Selection

**API client:**
- Added `getConnectionCalendars(id)` method to prmApi

**EditConnectionModal enhancements:**
- Added state for `calendars`, `loadingCalendars`, and `selectedCalendarId`
- Added useEffect to fetch available calendars when modal opens
- Added calendar dropdown selector after connection name field
- Shows "(Primary)" suffix for primary calendars
- Includes `calendar_id` in save data when changed
- Loading state shown while fetching calendars

**Connection card display:**
- Shows calendar_id as subtitle when set and not "primary"
- Helps users identify which specific calendar is being synced

## Files Modified

| File | Changes |
|------|---------|
| `includes/class-google-calendar-provider.php` | Added `list_calendars()` method |
| `includes/class-rest-calendar.php` | Added `/calendar/connections/{id}/calendars` endpoint |
| `src/api/client.js` | Added `getConnectionCalendars` API method |
| `src/pages/Settings/Settings.jsx` | Enhanced EditConnectionModal with calendar selection |

## Verification

- [x] `npm run build` succeeds without errors
- [x] PHP syntax validation passes for all modified files
- [x] REST endpoint returns calendar list for Google connections
- [x] REST endpoint returns calendar list for CalDAV connections
- [x] EditConnectionModal displays calendar picker
- [x] Changing calendar in EditConnectionModal saves correctly

## Deviations from Plan

None - plan executed exactly as written.

## Next Step

Phase 68 complete (single plan). Ready for milestone completion.
