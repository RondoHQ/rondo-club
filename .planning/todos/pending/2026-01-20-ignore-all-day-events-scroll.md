---
created: 2026-01-20T13:45
title: Ignore all-day events when scrolling to current event
area: ui
files:
  - src/components/MeetingWidget.jsx
---

## Problem

When the meeting widget scrolls to the current event, it should skip all-day events. Currently, all-day events may be selected as the "current" event to scroll to, which isn't the intended behavior since all-day events span the entire day and don't have a specific time to scroll to.

## Solution

Update the scroll-to-current logic in MeetingWidget to filter out all-day events when determining which event to scroll to. Only time-specific events should be considered for the auto-scroll behavior.
