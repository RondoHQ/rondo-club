---
created: 2026-01-16T15:05
title: Fix calendar sync duplicates and missing events
area: api
files:
  - includes/class-google-calendar-provider.php
  - includes/class-rest-calendar.php
---

## Problem

User reports two calendar sync issues after Phase 68 changes:

1. **Duplicate events** - Some events are showing up twice in the system
2. **Missing events** - Some events that exist on the calendar are not syncing/showing

This appeared after the calendar selection feature was added (ability to select which calendar to sync). Possible causes:
- Events synced from old calendar_id still exist when switching calendars
- Upsert logic not properly matching existing events after calendar change
- Date range or sync parameters causing gaps

## Solution

TBD - needs investigation:
1. Check if events from previous calendar_id are being retained
2. Verify upsert matching logic (Google event ID uniqueness)
3. Check if calendar switch should trigger cleanup of old events
4. Investigate missing events - date range issue? Filtering issue?
