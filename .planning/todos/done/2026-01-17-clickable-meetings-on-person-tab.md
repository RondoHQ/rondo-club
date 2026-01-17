---
created: 2026-01-17T14:35
title: Make meetings clickable on Person Meetings tab
area: ui
files:
  - src/pages/People/PersonDetail.jsx
  - src/components/MeetingDetailModal.jsx
---

## Problem

On the Person detail page, the Meetings tab shows upcoming and past meetings for that person. Currently these meetings are not clickable. Users expect to click on a meeting to see the same Meeting detail modal that appears when clicking meetings on the Dashboard.

This inconsistency makes the UI feel incomplete - the modal exists and works on the Dashboard, but not on the Person page.

## Solution

Reuse the existing MeetingDetailModal component from Phase 73-74:
1. Import MeetingDetailModal into PersonDetail.jsx
2. Add onClick handler to meeting items in the Meetings tab
3. Add state for selected meeting ID
4. Render MeetingDetailModal with the selected meeting
