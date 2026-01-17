---
created: 2026-01-15T14:43
title: Fix Today's meetings layout and timezone display
area: ui
files:
  - src/pages/Dashboard.jsx
  - src/components/MeetingsBlock.jsx
---

## Problem

Two issues with Today's meetings on the dashboard:

1. **Layout:** Currently displayed in a three-column wide block, but should be a single column for better readability and visual hierarchy.

2. **Timezone:** Meeting times are displayed in UTC instead of the user's local timezone, making it confusing to see when meetings actually occur.

## Solution

TBD:
1. Update the dashboard grid to make Today's meetings block span only one column
2. Convert meeting times from UTC to user's local timezone before display (use browser's Intl API or WordPress timezone setting)
