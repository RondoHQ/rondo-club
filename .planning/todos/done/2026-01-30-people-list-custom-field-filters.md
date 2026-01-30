---
created: 2026-01-30T11:15
title: Add custom field filters to People list
area: ui
files:
  - src/pages/PeopleList.jsx
  - src/hooks/usePeople.js
  - includes/class-rest-api.php
---

## Problem

The People list currently lacks filters for important custom fields that users need to filter on:

- `huidig-vrijwilliger` - Current volunteer status (boolean)
- `financiele-blokkade` - Financial block status
- `datum-foto` - Photo date (for tracking outdated photos)
- `datum-vog` - VOG certificate date (for compliance tracking)
- `type-lid` - Member type classification

These fields contain important data for managing members but cannot be used to filter the list.

## Solution

Add filter controls to the People list for these custom fields:

1. Boolean filters (huidig-vrijwilliger, financiele-blokkade): Yes/No/All toggle
2. Date filters (datum-foto, datum-vog): Could filter by "older than X months" or date range
3. Select filter (type-lid): Dropdown with available options

Backend may need REST API support for filtering by custom field values.
