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

This requires linking a WordPress user to their "person" record in the CRM. Options to explore:

1. **User meta approach**: Store `person_id` in WordPress user meta when a person record is linked to a user account
2. **Person meta approach**: Store `user_id` in person post meta to link back to the WP user
3. **Email matching**: Match the current user's email against person email addresses (simpler but less reliable if emails differ)

Once the link exists:
- API endpoints return the current user's person_id in context
- Frontend filters out that person_id from attendee displays
- Apply consistently to: PersonDetail MeetingCard, EventsWidget, MeetingDetailModal

Need to discuss: Which linking approach is preferred? Email matching is simplest but may not work if user's WP email differs from their person record email.
