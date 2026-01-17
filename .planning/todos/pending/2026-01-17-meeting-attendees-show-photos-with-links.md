---
created: 2026-01-17T16:54
title: Meeting attendees show photos with profile links
area: ui
files:
  - src/pages/PersonDetail.jsx
  - src/components/MeetingDetailModal.jsx
---

## Problem

On the Meetings tab of the Personal Profile page, the "Also attending" section currently displays email addresses for other attendees. This is not user-friendly - it should show attendee photos (avatars) instead, and those photos should be clickable links to the attendees' profile pages.

## Solution

Update the "Also attending" display in the Meetings tab:
1. Replace email address display with person photo/avatar
2. Make each photo clickable, linking to `/people/{person_id}`
3. Consider showing name on hover for clarity
4. Handle case where attendee is not yet linked to a person record (fallback to email)
