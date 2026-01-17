---
created: 2026-01-17T14:40
title: Fix meeting event icon dark mode on Person tab
area: ui
files:
  - src/pages/People/PersonDetail.jsx
---

## Problem

On the Person detail page's Meetings tab, the event/calendar icon for meetings doesn't look right in dark mode. The contrast or colors are off, making it hard to see or visually inconsistent with the rest of the dark mode UI.

## Solution

Find the icon styling in the Meetings tab section of PersonDetail.jsx and add appropriate dark mode classes (likely `dark:text-gray-400` or similar pattern used elsewhere in the codebase).
