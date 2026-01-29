# Phase 115: Column Preferences UI - Context

**Gathered:** 2026-01-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can customize which columns appear in the People list and in what order. This includes showing/hiding columns, reordering via drag-and-drop, persisting column widths, and removing the old "show in list view" setting from custom field settings. Backend preferences API already exists from Phase 114.

</domain>

<decisions>
## Implementation Decisions

### Settings Modal Design
- Access via gear icon in the People list table header
- Opens as full centered modal with backdrop
- Changes apply instantly (no save button required)
- Subtle "Reset to defaults" link at bottom of modal

### Column Visibility Controls
- Standard checkboxes next to each column name
- Name column is always visible (checkbox disabled/hidden)
- All columns in one list — custom fields mixed with core columns (not separated)
- Default visible columns for new users: Name, Team, Labels, Modified (custom fields hidden by default)

### Drag-and-Drop Reordering
- Reordering happens only in the modal (not on table headers)
- Blue line/highlight shows drop zone during drag
- Hidden columns preserve their order position — when shown again, they appear in their saved spot
- Name column is always first and cannot be reordered (locked position)

### Width Adjustment Behavior
- Drag column dividers (borders between headers) to resize
- Minimum width constraint (~50px) to prevent columns from disappearing
- No maximum width constraint
- When columns don't fit viewport: horizontal scroll (columns keep set widths)

### Claude's Discretion
- Auto-fit on double-click (whether to implement)
- Exact drag-drop library choice
- Minimum width value (around 50px)
- Loading/saving indicator behavior
- Modal close behavior (click outside, escape key)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches for drag-drop and column resize implementations.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 115-column-preferences-ui*
*Context gathered: 2026-01-29*
