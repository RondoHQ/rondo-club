---
created: 2026-01-16T19:17
title: Fix Add date modal dark mode contrast
area: ui
files:
  - src/components/ImportantDateModal.jsx
---

## Problem

In the "Add date" modal (ImportantDateModal), the Related people's names are hard to read in dark mode due to low contrast.

## Solution

Add appropriate dark: text color variants to the related people display in ImportantDateModal.jsx to ensure good readability in dark mode.
