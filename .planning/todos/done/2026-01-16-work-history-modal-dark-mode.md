---
created: 2026-01-16T19:16
title: Fix work history modal dark mode
area: ui
files:
  - src/components/WorkHistoryModal.jsx
---

## Problem

The Add work history modal doesn't work well in dark mode. Likely has contrast issues with text, backgrounds, or input fields.

## Solution

Review WorkHistoryModal.jsx and add appropriate dark: variants for text colors, backgrounds, and input fields to ensure good contrast in dark mode.
