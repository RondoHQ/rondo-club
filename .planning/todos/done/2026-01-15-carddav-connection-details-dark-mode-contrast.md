---
created: 2026-01-15T12:09
title: Fix CardDAV connection details dark mode contrast
area: ui
files:
  - src/pages/Settings.jsx
---

## Problem

The Connection details block on the CardDAV sync card in the Settings page doesn't have the right contrast and colors in dark mode. The text or background colors likely don't adapt properly to the dark theme, making it hard to read or visually inconsistent with the rest of the dark mode UI.

## Solution

TBD - Likely needs Tailwind dark mode classes (`dark:`) added to the connection details block to ensure proper text and background contrast in dark mode.
