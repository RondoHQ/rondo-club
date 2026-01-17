---
created: 2026-01-17T14:20
title: Fix dark mode contrast issues
area: ui
files:
  - src/pages/Dashboard.jsx
  - src/pages/PersonDetail.jsx
---

## Problem

Two dark mode contrast issues:

1. **Today button hover state unreadable** - On the Events widget date navigation, the "Today" button's hover state has poor contrast in dark mode, making it hard to read.

2. **Meetings count on Person tab unreadable** - On the Person detail page, the count badge next to "Meetings" tab label has insufficient contrast in dark mode.

## Solution

1. For Today button: Update hover state to use proper dark mode colors (e.g., `dark:hover:bg-gray-700 dark:hover:text-gray-100` or similar high-contrast combo)

2. For Meetings count: Update the badge background/text colors to ensure readable contrast in dark mode (follow pattern established in Phase 71 - use gray-300/gray-400 for text, solid backgrounds)
