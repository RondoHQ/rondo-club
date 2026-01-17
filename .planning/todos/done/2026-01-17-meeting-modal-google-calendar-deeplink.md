---
created: 2026-01-17T15:30
title: Add Google Calendar deeplink to meeting modals
area: ui
files:
  - src/components/MeetingDetailModal.jsx
---

## Problem

When viewing a meeting in the MeetingDetailModal, there's no way to quickly navigate to the event in Google Calendar. Users may want to edit the event, add notes, or see additional details that are only available in the calendar app.

## Solution

Add a link/button in the meeting modal that opens the event directly in Google Calendar. The deeplink format for Google Calendar is typically `https://calendar.google.com/calendar/event?eid=[encoded_event_id]`. Need to verify the event ID format stored in our system matches what Google Calendar expects for deep linking.
