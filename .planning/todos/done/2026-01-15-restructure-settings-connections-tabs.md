---
created: 2026-01-15T14:19
title: Restructure Settings with Connections tab and subtabs
area: ui
files:
  - src/pages/Settings.jsx
  - src/App.jsx
  - includes/class-rest-api.php
---

## Problem

The current Settings/Admin page structure needs reteam. Currently calendars and contacts sync are separate top-level concerns, but they should be grouped under a unified "Connections" concept.

Current structure:
- Settings with various tabs including Calendars, Contacts/Sync

Desired structure:
- Settings
  - Connections (tab)
    - Calendars (subtab)
    - Sync (subtab, renamed from "Contacts")
  - Other settings tabs...

Changes needed:
1. Create a "Connections" parent tab in Settings
2. Add "Calendars" as a subtab under Connections
3. Rename "Contacts" to "Sync" and add as subtab under Connections
4. Register proper React Router routes for the new structure (e.g., `/settings/connections/calendars`)
5. Update all redirect URLs in PHP backend (OAuth callbacks, etc.)
6. Update any links/navigation that point to the old routes

## Solution

TBD - This is a significant UI restructuring that touches:
- React Router configuration in App.jsx
- Settings.jsx tab/subtab structure
- PHP REST API redirect URLs
- OAuth callback handlers
- Any other places that link to settings tabs
