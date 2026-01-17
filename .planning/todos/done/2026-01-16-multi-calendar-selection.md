---
created: 2026-01-16T19:21
title: Support multiple calendar selection per connection
area: ui
files:
  - src/pages/Settings/CalendarSettings.jsx
  - src/components/EditConnectionModal.jsx
  - includes/class-calendar-sync.php
---

## Problem

Currently, each Google Calendar connection can only sync one calendar at a time. Users with multiple calendars (personal, work, shared) need to create separate connections for each calendar they want to sync.

It would be more convenient to select multiple calendars from a single connection.

## Solution

1. Change calendar selector from single-select dropdown to multi-select checkboxes
2. Store selected_calendars as array instead of single calendar_id
3. Update sync logic to iterate through all selected calendars
4. Update UI to show count of selected calendars on connection card
