# Phase 88: Settings UI - Context

**Gathered:** 2026-01-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Admin interface for managing custom field definitions through a Settings subtab. Users can view existing fields, add new fields, edit field properties, and delete/archive fields. Separate field lists for People and Teams.

</domain>

<decisions>
## Implementation Decisions

### Field list presentation
- Simple table layout with rows for each field
- Columns: Label and Type only (minimal)
- Type displayed as text label, not icon
- Edit/Delete actions appear on row hover (hidden until hover)

### Add/Edit flow
- Slide-out panel for add/edit form (consistent with other Stadion detail views)
- Simple dropdown for field type selection (not grouped or visual picker)
- Field type changeable after creation, but with warning about data implications
- Form fields: Label (required), Type (required), Description (optional)

### Entity toggle
- Tab bar to switch between "People Fields" and "Team Fields"
- Tab bar positioned below the subtab header, above the field list
- Generic "Add Field" button (adds to currently selected entity)
- Remember last selected tab when returning to the page

### Delete behavior
- Offer both options: Archive (soft delete) or Permanently Delete (hard delete)
- Archive hides field definition but preserves data in database
- Permanent delete removes field definition AND all stored values
- Type-to-confirm required for permanent delete (type field name)
- Show count of affected records in confirmation ("This field has values on 47 people")
- Archived fields are hidden from UI — no archive view, recoverable only via code/support

### Claude's Discretion
- Exact styling and spacing of table/panel
- Loading states and error handling
- Animation/transition details for slide-out panel
- Empty state design when no fields exist

</decisions>

<specifics>
## Specific Ideas

- Slide-out panel should feel like other Stadion detail views (consistent pattern)
- Tab bar pattern similar to existing Stadion navigation patterns

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 88-settings-ui*
*Context gathered: 2026-01-18*
