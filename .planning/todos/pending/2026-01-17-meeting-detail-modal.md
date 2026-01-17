---
created: 2026-01-17T10:45
title: Meeting detail modal with add person
area: ui
files:
  - src/components/TodayMeetings.jsx
  - src/components/MeetingCard.jsx
  - includes/class-rest-calendar.php
---

## Problem

Currently, meetings on the dashboard show limited information. Users want to:
1. Click on a meeting to see full details (title, time, location, description, attendees)
2. See which attendees are already in Caelis (linked to their profiles)
3. Add attendees who aren't in Caelis yet as new people

This would improve the workflow of using calendar events to discover and add new contacts.

## Solution

TBD - Likely involves:
- New MeetingDetailModal component
- Fetch full meeting data including attendees from API
- Match attendees by email against existing people
- Quick add form for unknown attendees
