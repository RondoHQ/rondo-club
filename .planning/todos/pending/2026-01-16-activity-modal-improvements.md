---
created: 2026-01-16T19:15
title: Activity modal improvements
area: ui
files:
  - src/components/QuickActivityModal.jsx
---

## Problem

The Add Activity modal (QuickActivityModal) has several issues:

1. Activity type button has bad contrast in dark mode - hard to read
2. Missing useful activity types: "Dinner" and "Zoom"
3. "Phone call" label is too long - should be "Phone"

## Solution

1. Fix dark mode contrast on activity type button (likely needs explicit dark: text color)
2. Add "Dinner" and "Zoom" to activity types array
3. Rename "Phone call" to "Phone" in activity types
