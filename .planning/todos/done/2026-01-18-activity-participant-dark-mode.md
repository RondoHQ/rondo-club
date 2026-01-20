---
created: 2026-01-18T02:15
title: Fix activity participant name readability in dark mode
area: ui
files:
  - src/components/QuickActivityModal.jsx
---

## Problem

When adding a participant to an activity in the add activity modal, the participant's name is hardly readable in dark mode. The text color likely lacks proper dark mode contrast styling.

## Solution

Add appropriate dark mode text color class (e.g., `dark:text-gray-200` or similar) to the participant name element in QuickActivityModal.jsx.
