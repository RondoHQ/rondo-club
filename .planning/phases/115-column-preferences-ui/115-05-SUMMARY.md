---
phase: 115-column-preferences-ui
plan: 05
subsystem: custom-fields
tags: [cleanup, ui, form, column-preferences]
dependency-graph:
  requires: [115-01]
  provides: [clean-custom-field-form]
  affects: []
tech-stack:
  added: []
  patterns: [deprecation-cleanup]
key-files:
  created: []
  modified:
    - src/components/FieldFormPanel.jsx
    - includes/class-rest-custom-fields.php
    - includes/customfields/class-manager.php
decisions:
  - id: 115-05-001
    choice: Remove show_in_list_view without data migration
    reason: Existing values in ACF storage are harmless - frontend now ignores them
metrics:
  duration: 10min
  completed: 2026-01-29
---

# Phase 115 Plan 05: Remove Custom Field List View Settings Summary

**One-liner:** Removed obsolete show_in_list_view and list_view_order settings from custom field form - column visibility now user-controlled via preferences system (COL-07).

## What Was Built

### Task 1: Remove show_in_list_view from FieldFormPanel (d42b9f5)
Removed the list view settings from the custom field form UI:

1. **Removed from `getDefaultFormData()`:**
   - Deleted `show_in_list_view: false`
   - Deleted `list_view_order: 999`

2. **Removed from field loading `useEffect`:**
   - Deleted `show_in_list_view: field.show_in_list_view ?? false`
   - Deleted `list_view_order: field.list_view_order ?? 999`

3. **Removed from `handleSubmit`:**
   - Deleted `submitData.show_in_list_view = formData.show_in_list_view`
   - Deleted conditional `list_view_order` setting

4. **Removed "Weergave opties" UI section:**
   - Entire Display Options section with checkbox and order input removed
   - Validation Options section remains intact

### Task 2: Clean up backend validation (41e3212)
Removed list view settings from backend processing:

1. **REST API (`class-rest-custom-fields.php`):**
   - Removed from `$optional_params` array in `create_item()`
   - Removed from `$updatable_params` array in `update_item()`
   - Removed from `get_create_params()` schema documentation
   - Removed from `get_field_metadata()` response formatting

2. **Manager class (`class-manager.php`):**
   - Removed `show_in_list_view` from `UPDATABLE_PROPERTIES`
   - Removed `list_view_order` from `UPDATABLE_PROPERTIES`

## Technical Decisions

| ID | Decision | Rationale |
|----|----------|-----------|
| 115-05-001 | Remove without data migration | Existing ACF values are harmless - frontend ignores them, backend no longer processes them |

## Architecture Notes

**Backwards Compatibility:**
- Existing custom fields retain their `show_in_list_view` values in ACF storage
- These values are simply ignored - no breaking changes
- No data migration required

**New Column Visibility System:**
- Per-user column preferences (implemented in 115-01)
- Column settings modal (implemented in 115-02)
- Users control their own visible columns and order

## Verification Results

1. **FieldFormPanel.jsx:** 0 references to `show_in_list_view`
2. **FieldFormPanel.jsx:** 0 references to `list_view_order`
3. **FieldFormPanel.jsx:** 0 references to "Weergave opties"
4. **Backend PHP files:** 0 references to `show_in_list_view` or `list_view_order`
5. **npm run build:** Succeeds without errors

## Commits

| Commit | Type | Description |
|--------|------|-------------|
| d42b9f5 | feat | Remove list view settings from custom field form |
| 41e3212 | chore | Remove list view settings from backend validation |

## Deviations from Plan

None - plan executed exactly as written.

## Next Steps

Phase 115 is now complete. All plans (115-01 through 115-05) have been executed:
- 115-01: Column preferences backend and frontend hook
- 115-02: Column settings modal
- 115-03: Column resize hook
- 115-04: PeopleList integration
- 115-05: Remove obsolete list view settings (this plan)

**Note:** Deployment to production requires user to authorize SSH agent.
