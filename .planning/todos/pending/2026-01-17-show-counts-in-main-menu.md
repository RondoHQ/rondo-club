---
created: 2026-01-17T12:35
title: Show counts in main menu navigation
area: ui
files:
  - src/components/Navigation.jsx
  - src/App.jsx
---

## Problem

The main navigation menu shows links to People, Organizations, Events, and Todos but doesn't indicate how many items exist in each category. Adding counts would give users a quick overview of their data at a glance.

Example desired format:
- People (42)
- Organizations (15)
- Events (8)
- Todos (3)

## Solution

TBD - Options to consider:
1. Fetch counts from existing endpoints (dashboard may already have some)
2. Add a dedicated counts endpoint for efficiency
3. Use TanStack Query to cache counts and update when data changes
