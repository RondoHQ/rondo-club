# Phase 118: Custom Field Edit Control - Context

**Gathered:** 2026-01-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Custom fields can be marked as non-editable in UI while remaining API-accessible. This lets admins designate certain fields as "Sportlink-managed" — visible to users but not editable through the UI. The REST API continues to accept updates for automation/sync purposes.

</domain>

<decisions>
## Implementation Decisions

### Settings UI placement
- Add "Bewerkbaar in UI" toggle in the FieldFormPanel slide-over (existing field edit form)
- Place after the "Verplicht" (required) checkbox — natural grouping of field behavior settings
- Use True/False toggle style consistent with existing "Verplicht" checkbox

### Non-editable field display
- Field value displays normally in CustomFieldsSection (no visual change to value itself)
- Hide the edit button from CustomFieldsSection header when ALL fields are non-editable
- If at least one field is editable, show edit button — modal opens but non-editable fields are read-only within it
- In CustomFieldsEditModal, non-editable fields show their value as plain text with a small lock icon and "Wordt beheerd via API" note
- No graying out or disabled styling — clean read-only presentation

### Scope of control
- Setting applies globally per field (not per entity type)
- If a person custom field has editable_in_ui=false, no person can have that field edited via UI
- This matches how other field settings (required, unique) work

### Default behavior
- New fields default to editable_in_ui=true (backward compatible)
- Existing fields auto-migrate to editable_in_ui=true on first load (no admin action needed)
- Migration is implicit — field without the property is treated as editable

### Claude's Discretion
- Lock icon styling and exact placement
- "Wordt beheerd via API" text wording
- Whether to show field grouping (editable vs read-only) in the edit modal

</decisions>

<specifics>
## Specific Ideas

- The setting should feel like a simple admin toggle, not a complex permission system
- Clean, minimal UI — don't make non-editable fields look broken or disabled
- The goal is to communicate "this comes from Sportlink" without cluttering the interface

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 118-custom-field-edit-control*
*Context gathered: 2026-01-29*
