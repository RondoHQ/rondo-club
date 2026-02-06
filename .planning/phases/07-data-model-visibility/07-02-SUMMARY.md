# Plan 07-02 Summary: Visibility Fields

## Overview

Added visibility and sharing post meta fields to Person, Team, and Important Date post types to enable contacts to be marked as private, workspace-visible, or shared with specific users.

## Changes Made

### Task 1: Visibility ACF Field Group

**File:** `acf-json/group_visibility_settings.json`

Created ACF field group with:
- Group key: `group_visibility_settings`
- Title: "Visibility Settings"
- Location: Person OR Team OR Important Date post types
- Position: side (sidebar)
- Menu order: 100 (after main content)

Field:
- `_visibility` (select)
  - Required: yes
  - Choices: private, workspace, shared
  - Default: private (preserves current single-user behavior)
  - Instructions: "Control who can see this contact"
  - REST API enabled (`show_in_rest: true`)

### Task 2: RONDO_Visibility Helper Class

**Files:** `includes/class-visibility.php`, `functions.php`

Created static helper class with:

**Constants:**
- `VISIBILITY_PRIVATE` = 'private'
- `VISIBILITY_WORKSPACE` = 'workspace'
- `VISIBILITY_SHARED` = 'shared'
- `SHARED_WITH_META_KEY` = '_shared_with'

**Methods:**
- `get_visibility($post_id)` - Returns visibility value (defaults to 'private')
- `set_visibility($post_id, $visibility)` - Sets visibility (validates input)
- `get_shares($post_id)` - Returns array of share objects
- `add_share($post_id, $user_id, $permission, $shared_by)` - Adds or updates a share
- `remove_share($post_id, $user_id)` - Removes a user's share
- `user_has_share($post_id, $user_id)` - Checks if user has share access
- `get_share_permission($post_id, $user_id)` - Returns 'view', 'edit', or false
- `get_valid_visibilities()` - Returns array of valid visibility values
- `is_valid_visibility($visibility)` - Validates a visibility value

**_shared_with structure:**
```json
[
  {
    "user_id": 5,
    "permission": "edit",
    "shared_by": 1,
    "shared_at": "2026-01-15T10:30:00Z"
  }
]
```

Added `RONDO_Visibility` to autoloader in `functions.php`.

## Verification

- [x] ACF field group JSON file created with correct structure
- [x] Field group targets all three post types (person, team, important_date)
- [x] Visibility field in sidebar position
- [x] RONDO_Visibility class has no PHP syntax errors
- [x] Class properly added to autoloader
- [x] `npm run build` succeeds
- [x] Deployed to production

## Files Modified

- `acf-json/group_visibility_settings.json` - New ACF field group
- `includes/class-visibility.php` - New visibility helper class
- `functions.php` - Added RONDO_Visibility to autoloader
- `docs/data-model.md` - Updated with visibility documentation
- `CHANGELOG.md` - Added changelog entries

## Commits

1. `feat(07-02): add visibility ACF field group for Person/Team/Important Date` (a63fdcb)
2. `feat(07-02): add RONDO_Visibility helper class for visibility and sharing` (7f21620)

## Next Steps

Phase 7 continues with:
- 07-03: Add workspace membership to user meta (completed in parallel)
- 07-04: Update RONDO_Access_Control to check visibility, workspace membership, and shares

## Notes

- Default visibility is 'private' which preserves current single-user behavior
- `_shared_with` is managed programmatically, not via ACF (complex nested structure)
- ACF's `show_in_rest` enables REST API access for the visibility field
- The class uses `get_field()` for visibility (ACF-managed) and `get_post_meta()` for shares (manually managed)
