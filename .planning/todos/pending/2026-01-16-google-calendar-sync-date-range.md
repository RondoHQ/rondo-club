---
created: 2026-01-16T10:25
title: Check and configure Google Calendar sync date range
area: api
files:
  - includes/class-calendar-integration.php
---

## Problem

The Google Calendar sync fetches events for a certain number of days into the future, but:
1. It's unclear what the current setting is
2. Users may want to see events further ahead (or limit to fewer days)
3. The setting may not be configurable

Need to investigate current implementation and potentially add user control.

## Solution

1. Check current implementation in calendar integration class
2. Identify how far ahead events are fetched
3. Consider adding a user setting for sync range (e.g., 7, 14, 30, 90 days)
4. Document current behavior if no change needed
