---
phase: 115-column-preferences-ui
verified: 2026-01-29T20:00:00Z
status: gaps_found
score: 7/8 must-haves verified
gaps:
  - truth: "Column preferences sync between browser tabs"
    status: failed
    reason: "No cross-tab synchronization mechanism implemented"
    artifacts:
      - path: "src/hooks/useListPreferences.js"
        issue: "No BroadcastChannel, storage event listener, or window focus refetch"
    missing:
      - "BroadcastChannel API or localStorage event listener for cross-tab sync"
      - "refetchOnWindowFocus: true in TanStack Query config"
      - "Or: storage event listener that invalidates query cache"
human_verification:
  - test: "Open column settings modal and toggle column visibility"
    expected: "Columns appear/disappear immediately in the table"
    why_human: "Requires visual confirmation of instant UI feedback"
  - test: "Drag column dividers to resize columns"
    expected: "Column width changes smoothly during drag, persists after release"
    why_human: "Requires interaction testing and visual confirmation"
  - test: "Reorder columns via drag-and-drop in settings modal"
    expected: "Column order updates in modal, reflected in table after closing"
    why_human: "Requires drag-drop interaction and visual verification"
---

# Phase 115: Column Preferences UI Verification Report

**Phase Goal:** Users can customize which columns appear and in what order.
**Verified:** 2026-01-29T20:00:00Z
**Status:** gaps_found
**Re-verification:** No - initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | User can open column settings modal from People list header | VERIFIED | PeopleList.jsx lines 1060-1067: Settings button with onClick={() => setShowColumnSettings(true)}, ColumnSettingsModal rendered at line 1280 |
| 2 | User can toggle column visibility (show/hide) for name, team, labels, modified, and all active custom fields | VERIFIED | ColumnSettingsModal.jsx lines 63-78: SortableColumnItem has checkbox toggle with onToggleVisibility callback; lines 157-164: handleToggleVisibility updates visible_columns via updatePreferences |
| 3 | User can reorder columns via drag-and-drop in settings modal | VERIFIED | ColumnSettingsModal.jsx uses dnd-kit (lines 1-19 imports), DndContext/SortableContext at lines 256-277, handleDragEnd at lines 143-155 calls updatePreferences with new column_order |
| 4 | User can adjust column widths by dragging column dividers | VERIFIED | PeopleList.jsx lines 184-249: ResizableHeader component wraps useColumnResize hook; hook at src/hooks/useColumnResize.js lines 43-86: pointer event handlers for drag resize |
| 5 | Column preferences (visibility, order, width) persist across sessions | VERIFIED | Backend: class-rest-api.php lines 1094-1127 (GET) and 1136-1278 (PATCH) store in wp_usermeta; Frontend: useListPreferences.js lines 54-73 (query), 83-154 (mutation) with localStorage cache |
| 6 | Column preferences sync between browser tabs | FAILED | No BroadcastChannel, storage event listener, or refetchOnWindowFocus in useListPreferences.js; main.jsx QueryClient has no focus refetch config |
| 7 | "Tonen als kolom in lijstweergave" checkbox removed from Settings > Custom Fields form | VERIFIED | Grep for show_in_list_view in src/components/FieldFormPanel.jsx returns 0 results; SUMMARY confirms removal in 115-05 |
| 8 | People list renders only visible columns in user's preferred order with preferred widths | VERIFIED | PeopleList.jsx lines 703-714: visibleColumns computed from preferences.visible_columns and column_order; lines 99-178 and 311-328: columns rendered with columnWidths |

**Score:** 7/8 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `src/pages/People/ColumnSettingsModal.jsx` | Modal with drag-drop and visibility toggles | VERIFIED | 308 lines, dnd-kit integration, checkbox toggles, reset button |
| `src/hooks/useListPreferences.js` | Hook for fetching/updating preferences | VERIFIED | 232 lines, TanStack Query with optimistic updates, localStorage cache, debounced width updates |
| `src/hooks/useColumnResize.js` | Hook for pointer-based column resize | VERIFIED | 114 lines, pointer capture for smooth resize, min-width constraint |
| `src/pages/People/PeopleList.jsx` | Integrated modal, resize, preferences rendering | VERIFIED | 1334 lines, imports all hooks, Settings button, ResizableHeader, dynamic column rendering |
| `includes/class-rest-api.php` | GET/PATCH endpoints for list-preferences | VERIFIED | Lines 212-258 (routes), 1088-1278 (handlers), stores visible_columns, column_order, column_widths |
| `src/components/FieldFormPanel.jsx` | show_in_list_view removed | VERIFIED | No references to show_in_list_view in file |

### Key Link Verification

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| PeopleList.jsx | useListPreferences | import + hook call | WIRED | Line 12 import, line 609-613 hook invocation, preferences used throughout |
| PeopleList.jsx | useColumnResize | import + ResizableHeader | WIRED | Line 13 import, lines 184-249 ResizableHeader uses hook at line 196 |
| PeopleList.jsx | ColumnSettingsModal | import + render | WIRED | Line 14 import, line 1280 render with isOpen and onClose |
| useListPreferences | /rondo/v1/user/list-preferences | API calls | WIRED | Lines 57 (GET) and 85 (PATCH) via api client |
| ColumnSettingsModal | useListPreferences | import + hook call | WIRED | Line 20 import, line 98 hook invocation, updatePreferences at lines 153 and 164 |
| ResizableHeader | handleColumnWidthChange | onWidthChange callback | WIRED | Line 306 passes callback, line 202-203 calls it on resize end |
| handleColumnWidthChange | updateColumnWidths | callback chain | WIRED | Line 720-722 calls updateColumnWidths from hook |

### Requirements Coverage

| Requirement | Status | Blocking Issue |
|-------------|--------|----------------|
| COL-01: User can show/hide columns on PeopleList | SATISFIED | - |
| COL-02: User can reorder columns via drag-and-drop | SATISFIED | - |
| COL-04: Available columns include: name, team, labels, modified, all active custom fields | SATISFIED | Backend get_available_columns_metadata() at line 1303-1323 includes core + custom fields |
| COL-05: Column width preferences persist per user | SATISFIED | - |
| COL-06: Settings modal provides column customization UI | SATISFIED | - |
| COL-07: "Tonen als kolom in lijstweergave" removed from custom field settings | SATISFIED | - |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| - | - | - | - | No blocking anti-patterns found |

### Human Verification Required

#### 1. Column Visibility Toggle
**Test:** Open column settings modal from gear icon, toggle a column visibility checkbox
**Expected:** Column immediately appears/disappears in the People list table
**Why human:** Requires visual confirmation of instant UI feedback and table re-render

#### 2. Column Resize
**Test:** Hover over column divider, drag to resize column width
**Expected:** Column width changes smoothly during drag, width persists after page refresh
**Why human:** Requires pointer interaction and visual confirmation of resize behavior

#### 3. Column Reorder
**Test:** In settings modal, drag a column to new position using grip handle
**Expected:** Column order updates in modal preview, reflected in table when modal closes
**Why human:** Requires drag-drop interaction and visual verification

### Gaps Summary

**One gap identified:** Cross-tab synchronization is not implemented. The useListPreferences hook uses TanStack Query with optimistic updates and localStorage caching, but there is no mechanism to sync preferences between multiple open browser tabs. When a user changes column preferences in one tab, other tabs will not reflect the change until manually refreshed.

**Missing implementation options:**
1. Add `refetchOnWindowFocus: true` to the query config
2. Add BroadcastChannel API to notify other tabs of preference changes
3. Add localStorage storage event listener to invalidate query cache on external changes

The localStorage caching in useListPreferences.js only syncs column_widths for instant page load, but does not listen for changes from other tabs.

---

_Verified: 2026-01-29T20:00:00Z_
_Verifier: Claude (gsd-verifier)_
