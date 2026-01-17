---
created: 2026-01-17T15:26
title: Move calendar subscription box to Connections Calendars
area: ui
files:
  - src/pages/Settings/SyncSettings.jsx
  - src/pages/Settings/CalendarSettings.jsx
---

## Problem

The "Calendar subscription" box is currently located in Settings > Sync, but this placement is inconsistent with the information architecture. Calendar-related settings should be grouped together under Connections > Calendars.

Current location: Settings > Sync
Target location: Connections > Calendars

Additionally, the heading text needs to be updated from the current text to "Subscribe to important dates in your calendar" to better describe its purpose.

## Solution

1. Move the calendar subscription component from SyncSettings.jsx to CalendarSettings.jsx
2. Update the heading to "Subscribe to important dates in your calendar"
3. Remove the component from SyncSettings.jsx
