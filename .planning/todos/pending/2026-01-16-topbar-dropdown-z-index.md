---
created: 2026-01-16T19:22
title: Fix topbar + dropdown z-index on People screen
area: ui
files:
  - src/components/TopBar.jsx
  - src/pages/People/PeopleList.jsx
---

## Problem

The dropdown menu from the "+" button on the topbar has a lower z-index than the sticky table header on the People screen. When the dropdown opens, it disappears behind the table header instead of appearing on top of it.

## Solution

Increase the z-index on the topbar dropdown menu to be higher than the sticky table header. Check current z-index values and ensure proper stacking order:
- Table header sticky: likely z-10 or z-20
- Topbar dropdown: needs to be higher (z-30 or z-40)
