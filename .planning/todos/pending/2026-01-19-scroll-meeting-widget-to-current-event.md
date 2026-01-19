---
created: 2026-01-19T14:30
title: Scroll meeting widget to current event
area: ui
files:
  - src/pages/Dashboard.jsx
---

## Problem

The meeting widget on the dashboard shows today's meetings but doesn't automatically scroll to the currently active meeting. Users have to manually scroll through the list to find the "now" highlighted event, especially if they have many meetings that day.

All-day events should be ignored when determining scroll position since they span the entire day and aren't time-specific.

## Solution

Add auto-scroll behavior to the meeting widget:
1. After meetings load, find the first meeting with `isNow` status
2. If found, scroll that element into view (using `scrollIntoView` or similar)
3. Skip all-day events when determining which meeting to scroll to
4. Only scroll on initial load, not on subsequent re-renders
