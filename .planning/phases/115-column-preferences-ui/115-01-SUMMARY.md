---
phase: 115
plan: 01
subsystem: user-preferences
tags: [rest-api, tanstack-query, optimistic-updates, localstorage]
dependency-graph:
  requires: [114-user-preferences-backend]
  provides: [column-order-storage, column-widths-storage, list-preferences-hook]
  affects: [115-02-column-settings-modal, 115-03-resizable-headers]
tech-stack:
  added: []
  patterns: [optimistic-updates, localstorage-cache, debounced-updates]
key-files:
  created:
    - src/hooks/useListPreferences.js
  modified:
    - includes/class-rest-api.php
    - src/api/client.js
decisions:
  115-01-001: "Store column_order separately from visible_columns in user_meta"
  115-01-002: "Store column_widths as object in separate user_meta key"
  115-01-003: "Use localStorage as instant cache for column widths to prevent flicker"
  115-01-004: "Debounce width updates by 300ms to prevent excessive API calls"
metrics:
  duration: "5 minutes"
  completed: "2026-01-29"
---

# Phase 115 Plan 01: Preferences API and Hook Summary

Extended backend list-preferences API with column_order and column_widths storage, created useListPreferences TanStack Query hook with optimistic updates and localStorage caching.

## What Was Built

### Backend API Extension (class-rest-api.php)

Extended the `/rondo/v1/user/list-preferences` endpoint created in Phase 114:

**GET Response now includes:**
- `visible_columns` - array of visible column IDs (existing)
- `column_order` - array of ALL column IDs in user's preferred order
- `column_widths` - object mapping column IDs to pixel widths
- `available_columns` - array of column metadata (existing)

**PATCH accepts:**
- `visible_columns` - update visible columns (existing)
- `column_order` - update column order (filters invalid IDs silently)
- `column_widths` - update column widths (validates positive integers)
- `reset` - clear all preferences and return defaults

**Storage:**
- `stadion_people_list_preferences` - visible_columns (existing)
- `stadion_people_list_column_order` - column_order (new)
- `stadion_people_list_column_widths` - column_widths (new)

### Frontend Hook (useListPreferences.js)

New TanStack Query hook at `src/hooks/useListPreferences.js`:

**Returns:**
- `preferences` - object with visible_columns, column_order, column_widths, available_columns
- `isLoading` - boolean loading state
- `updatePreferences` - function for immediate optimistic updates
- `updateColumnWidths` - function with 300ms debouncing for width changes
- `isUpdating` - boolean mutation state

**Features:**
- Optimistic updates for instant UI feedback
- localStorage sync for column_widths to prevent flicker on page load
- Debounced width updates batch rapid changes into single API call
- Automatic cache invalidation on mutation settled

### API Client Addition (client.js)

Added to `prmApi`:
- `getListPreferences()` - GET /rondo/v1/user/list-preferences
- `updateListPreferences(prefs)` - PATCH /rondo/v1/user/list-preferences

## Decisions Made

| ID | Decision | Rationale |
|----|----------|-----------|
| 115-01-001 | Store column_order separately from visible_columns | Hidden columns maintain position when re-shown |
| 115-01-002 | Store column_widths as object in separate user_meta | Clean separation, easier to update individual widths |
| 115-01-003 | Use localStorage as instant cache for column widths | Prevents visual flicker on page load before API responds |
| 115-01-004 | Debounce width updates by 300ms | Prevents excessive API calls during column resize drag |

## Deviations from Plan

None - plan executed exactly as written.

## Verification

**Backend verification (browser console):**
```javascript
// Test column_order and column_widths storage
await fetch('/wp-json/rondo/v1/user/list-preferences', {
  method: 'PATCH',
  headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.wpApiSettings.nonce },
  body: JSON.stringify({
    column_order: ['team', 'labels', 'modified'],
    column_widths: { team: 200, labels: 150 }
  })
}).then(r => r.json());
// Returns: column_order and column_widths in response

// Test GET returns stored values
await fetch('/wp-json/rondo/v1/user/list-preferences', {
  headers: { 'X-WP-Nonce': window.wpApiSettings.nonce }
}).then(r => r.json());
// Returns: column_order: ['team', 'labels', 'modified'], column_widths: { team: 200, labels: 150 }

// Test reset clears all
await fetch('/wp-json/rondo/v1/user/list-preferences', {
  method: 'PATCH',
  headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.wpApiSettings.nonce },
  body: JSON.stringify({ reset: true })
}).then(r => r.json());
// Returns: defaults for all fields
```

**Build verification:**
- `npm run lint -- src/hooks/useListPreferences.js` - No errors
- `npm run build` - Successful build

## Commits

| Hash | Message | Files |
|------|---------|-------|
| 697e4cc | feat(115-01): extend list-preferences API with column_order and column_widths | includes/class-rest-api.php |
| 5fa1719 | feat(115-01): create useListPreferences hook with optimistic updates | src/hooks/useListPreferences.js, src/api/client.js |

## Next Phase Readiness

**Ready for 115-02:** ColumnSettingsModal component

The useListPreferences hook provides all the data and mutation functions needed:
- `preferences.available_columns` for rendering column list in modal
- `preferences.visible_columns` for checkbox state
- `preferences.column_order` for drag-drop ordering
- `updatePreferences({ visible_columns, column_order })` for saving changes

**Ready for 115-03:** ResizableTableHeader component

The hook provides width management:
- `preferences.column_widths` for initial header widths
- `updateColumnWidths({ columnId: width })` for persisting resize changes
- localStorage caching prevents flicker on page load
