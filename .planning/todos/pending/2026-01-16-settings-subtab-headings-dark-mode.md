---
created: 2026-01-16T19:20
title: Fix Settings subtab headings dark mode contrast
area: ui
files:
  - src/pages/Settings/Settings.jsx
---

## Problem

The "Connections", "CardDAV" and "Slack" headings underneath the Connections tab on Settings have too low contrast in dark mode, making them hard to read.

## Solution

Add appropriate dark: text color variants to the subtab heading elements in Settings.jsx to ensure good readability in dark mode.
