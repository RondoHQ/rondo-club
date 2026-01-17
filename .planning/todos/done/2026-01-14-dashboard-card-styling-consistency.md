---
created: 2026-01-14T20:22
title: Dashboard card styling consistency
area: ui
files:
  - src/pages/Dashboard.jsx
---

## Problem

Two styling inconsistencies on the dashboard:
1. Awaiting response todos need slightly more left padding to align with content in other cards
2. The icons for Awaiting response and Favorites cards use a different color than the other 4 card icons

## Solution

1. Add left padding to the Awaiting response todos list to match the alignment of content in other dashboard cards
2. Update the icon color for Awaiting response and Favorites cards to use the same grey as the other 4 card icons
