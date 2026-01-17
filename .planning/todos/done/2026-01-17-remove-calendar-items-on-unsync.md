---
created: 2026-01-17T15:22
title: Remove calendar items when calendar unsynced
area: api
files:
  - includes/class-calendar-sync.php
  - src/pages/Settings/EditConnectionModal.jsx
---

## Problem

When a user removes a calendar from their sync settings (deselects it in EditConnectionModal), the calendar events that were previously synced from that calendar remain in the system. This leaves orphaned meeting records that the user no longer wants to track.

Expected behavior: When a calendar is deselected from sync, all meetings associated with that calendar should be removed from the system.

## Solution

TBD - Options to consider:
1. Delete meetings on calendar deselection (immediate cleanup)
2. Mark meetings as "orphaned" and clean up on next sync
3. Add a confirmation dialog warning about meeting deletion
