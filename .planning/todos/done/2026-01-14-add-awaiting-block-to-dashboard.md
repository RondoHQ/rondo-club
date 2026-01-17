---
created: 2026-01-14T20:14
title: Add Awaiting block to dashboard
area: ui
files:
  - src/pages/Dashboard.jsx
  - includes/class-rest-api.php
---

## Problem

The dashboard shows an "Open todos" count block at the top, but there's no equivalent block showing the count of items marked as "Awaiting" (pending response). Users need quick visibility into how many items are waiting for responses.

## Solution

Add an "Awaiting" block to the right of the "Open todos" block at the top of the dashboard. It should:
- Show a count of todos with awaiting/pending response status
- Match the visual style of the "Open todos" block
- Link to a filtered view of awaiting items when clicked

TBD: May need to extend the dashboard API endpoint to include awaiting count.
