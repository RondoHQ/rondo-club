---
created: 2026-01-17T14:52
title: Meeting modal missing attendees when opened from Person page
area: ui
files:
  - src/pages/People/PersonDetail.jsx
  - src/components/MeetingDetailModal.jsx
---

## Problem

When opening the MeetingDetailModal from the Person detail page's Meetings tab, the attendees list is not displayed. The same modal works correctly on the Dashboard where attendees are shown.

This is likely because the meeting data passed from PersonDetail doesn't include the full attendee information that the Dashboard version includes, or the data structure differs between the two contexts.

## Solution

Compare the meeting object structure between:
1. Dashboard (where it works) - check what data is passed to MeetingDetailModal
2. PersonDetail (where it fails) - check what usePersonMeetings returns

Likely need to ensure the usePersonMeetings hook returns attendee data in the same format as the Dashboard meetings endpoint, or the MeetingDetailModal needs to handle both formats.
