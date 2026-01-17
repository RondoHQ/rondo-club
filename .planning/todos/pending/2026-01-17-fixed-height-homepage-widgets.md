---
created: 2026-01-17T12:41
title: Fixed height for homepage widgets
area: ui
files:
  - src/pages/Dashboard.jsx
  - src/components/dashboard/
---

## Problem

Homepage widgets (meetings, todos, etc.) currently resize dynamically based on content. When more events load or the data changes, this causes the whole page to re-render and shift layout, creating a jarring user experience.

## Solution

Give dashboard widgets a fixed height with internal scrolling when content overflows. This will:
- Prevent layout shifts when data loads/changes
- Provide consistent visual structure
- Improve perceived performance

TBD: Determine optimal fixed heights for each widget type.
