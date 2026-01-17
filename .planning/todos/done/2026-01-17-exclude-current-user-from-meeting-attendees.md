---
created: 2026-01-17T17:17
title: Exclude current user from meeting attendees display
area: ui
files:
  - src/pages/People/PersonDetail.jsx
  - src/components/Dashboard/EventsWidget.jsx
  - src/components/MeetingDetailModal.jsx
---

## Problem

When viewing meetings (on person detail screen, dashboard events widget, or meeting detail modal), the current logged-in user's photo/avatar appears in the attendee list. This is redundant since the user already knows they're attending - they should only see *other* attendees.

Currently we filter out the person being viewed on the person detail screen, but we don't filter out the current user in any of these views.

## Solution

**Decision: Use user meta approach**

1. Store `person_id` in WordPress user meta: `update_user_meta($user_id, 'linked_person_id', $person_id)`
2. Add UI in Settings to link current user to their person record (dropdown of people)
3. Return `current_user_person_id` in initial config/bootstrap data (wpApiSettings or similar)
4. Frontend filters out that person_id from attendee displays in:
   - PersonDetail MeetingCard
   - EventsWidget (dashboard)
   - MeetingDetailModal
