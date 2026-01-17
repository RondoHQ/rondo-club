---
created: 2026-01-14T18:32
title: Add persistent todos sidebar on person profile
area: ui
files:
  - src/pages/PersonDetail.jsx
  - src/components/Timeline/TodosList.jsx
---

## Problem

Currently, todos for a person are shown in the timeline tab or require navigating to a specific section. Users want to see and manage todos for a person at all times, regardless of which tab they're viewing on the person profile.

The todos list should be persistently visible as a sidebar that spans the entire page height and remains visible across all tabs (Timeline, Notes, Activities, etc.).

## Solution

Create a persistent sidebar component on the PersonDetail page that:
- Displays all todos for the current person
- Remains visible regardless of which tab is active
- Spans the full page height
- Allows quick todo management (complete, mark awaiting, add new)

TBD: Sidebar width and collapse behavior
TBD: Mobile/responsive handling
