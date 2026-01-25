---
created: 2026-01-16T10:20
title: Allow selecting Google Calendars to sync
area: ui
files:
  - src/pages/Settings/*
  - includes/class-calendar-integration.php
---

## Problem

Currently, when Google Calendar is connected, all calendars from the user's account are synced. Users may have multiple calendars (personal, work, shared, subscribed) and may only want to sync specific ones to Stadion.

Without calendar selection:
- Events from unwanted calendars clutter the dashboard
- Personal events may appear in a work context (or vice versa)
- No control over which calendar data is pulled into the CRM

## Solution

Add calendar selection UI in Settings > Connections > Google Calendar:
1. After OAuth connection, fetch list of available calendars via Google Calendar API
2. Display checkboxes/toggles for each calendar
3. Store selected calendar IDs in user meta
4. Filter calendar sync to only pull events from selected calendars
5. Allow re-selection at any time from settings
