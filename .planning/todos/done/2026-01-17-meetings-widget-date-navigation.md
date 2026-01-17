---
created: 2026-01-17T10:50
title: Add date navigation to meetings widget
area: ui
files:
  - src/components/TodayMeetings.jsx
  - src/pages/Dashboard.jsx
  - includes/class-rest-calendar.php
---

## Problem

The "Today's meetings" widget on the dashboard only shows meetings for the current day. Users want to:
1. Navigate to previous days to see past meetings
2. Navigate to future days to see upcoming meetings
3. Easily return to "today" after navigating

This would make the dashboard more useful for planning and reviewing meetings across multiple days.

## Solution

TBD - Likely involves:
- Add prev/next arrow buttons to widget header
- Add "Today" button to return to current date
- Update widget title to show selected date (e.g., "Meetings - Jan 17" or "Yesterday")
- Modify API endpoint to accept date parameter
