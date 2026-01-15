---
status: diagnosed
phase: 52-settings-ui
source: 52-01-SUMMARY.md
started: 2026-01-15T14:00:00Z
updated: 2026-01-15T14:15:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Calendars Tab Navigation
expected: In Settings page, a "Calendars" tab appears in the navigation. Clicking it shows the calendar connections interface.
result: pass

### 2. Connections List Display
expected: Any connected calendars show with provider icon (Google or CalDAV), name, last sync time, and action buttons (Sync Now, Edit, Delete). Error/paused badges appear when applicable.
result: issue
reported: "When I click 'Connect Google Calendar', I'm correctly taken to a Google oAuth screen with the correct permissions, when I click Allow on that, I'm redirected to the Dashboard, not to the settings page. When I then go to the Settings page -> Calendar tab, it doesn't list an active connection."
severity: major

### 3. Google Calendar OAuth Button
expected: "Connect Google Calendar" button is visible. Clicking it redirects to Google OAuth authorization.
result: pass

### 4. CalDAV Add Modal
expected: "Add CalDAV Calendar" button opens a modal with fields: Name, Server URL, Username, Password. A "Test Connection" button validates credentials.
result: pass

### 5. CalDAV Test and Save
expected: After entering CalDAV credentials and clicking Test Connection, a dropdown of available calendars appears. Selecting one and clicking Save creates the connection.
result: skipped

### 6. Edit Connection Modal
expected: Clicking Edit on a connection opens modal with: connection name, sync enabled toggle, auto-log meetings toggle, sync from days dropdown (30/60/90/180).
result: skipped
reason: Blocked by UAT-001 - no connections exist to edit

### 7. Sync Now Button
expected: Clicking "Sync Now" on a connection triggers a sync. Some feedback indicates sync started.
result: skipped
reason: Blocked by UAT-001 - no connections exist

### 8. Delete Connection
expected: Clicking Delete shows confirmation. Confirming removes the connection from the list.
result: skipped
reason: Blocked by UAT-001 - no connections exist

### 9. Dark Mode Styling
expected: All calendar settings UI elements display correctly in dark mode with proper contrast and colors.
result: pass

## Summary

total: 9
passed: 4
issues: 1
pending: 0
skipped: 4

## Issues for /gsd:plan-fix

- UAT-001: Google OAuth redirect goes to Dashboard instead of Settings, connection not created (major) - Test 2
  root_cause: wp_redirect() + exit pattern doesn't work in REST API callbacks (includes/class-rest-calendar.php lines 543, 550, 559, 566, 593, 597). REST endpoints expect response objects, not redirects.
