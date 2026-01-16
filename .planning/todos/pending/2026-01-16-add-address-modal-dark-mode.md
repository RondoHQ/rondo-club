---
created: 2026-01-16T19:18
title: Fix Add address modal dark mode
area: ui
files:
  - src/components/AddressModal.jsx
---

## Problem

The Add address modal doesn't work well in dark mode. Likely has contrast issues with text, backgrounds, or input fields.

## Solution

Review AddressModal.jsx and add appropriate dark: variants for text colors, backgrounds, and input fields to ensure good contrast in dark mode.
