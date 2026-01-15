---
created: 2026-01-15T14:21
title: Fix search modal active result dark mode contrast
area: ui
files:
  - src/components/SearchModal.jsx
---

## Problem

In dark mode, the active/selected search result in the search modal is unreadable due to very low contrast. The highlighted result's text color likely doesn't adapt properly for dark mode, making it difficult or impossible to read the selected item.

## Solution

TBD - Add proper `dark:` Tailwind classes to the active result state in the search modal to ensure sufficient contrast between the highlight background and text color.
