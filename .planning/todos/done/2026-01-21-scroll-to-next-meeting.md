---
created: 2026-01-21T12:00
title: Scroll to next meeting when no current meeting
area: ui
files:
  - src/pages/Dashboard/Dashboard.jsx
---

## Problem

When there is no meeting currently happening, the "Today's Meetings" section on the dashboard doesn't auto-scroll to show the next upcoming meeting. Users have to manually scroll to find their next meeting.

Related: There's already a pending todo about ignoring all-day events when scrolling to the current event. This todo focuses on scrolling to the *next* meeting when there's no *current* meeting.

## Solution

In the Dashboard component's meeting scroll logic:
1. First check if there's a current meeting (existing behavior)
2. If no current meeting, find the next upcoming meeting and scroll to that instead
3. This gives users immediate visibility into their next scheduled meeting
