# Plan 07-01 Summary: Workspace CPT and Taxonomy

## Overview

Registered the workspace Custom Post Type and workspace_access taxonomy as WordPress primitives for the multi-user collaboration system.

## Changes Made

### Task 1: Workspace Custom Post Type

**File:** `includes/class-post-types.php`

Added `register_workspace_post_type()` method with:
- Post type slug: `workspace`
- Labels: Workspaces/Workspace
- `public: false`, `publicly_queryable: false`
- `show_ui: true`, `show_in_menu: true`
- `show_in_rest: true` with `rest_base: 'workspaces'`
- `capability_type: 'post'` (standard capabilities for now)
- `supports: ['title', 'editor', 'author', 'thumbnail']`
- `menu_position: 4` (before People)
- `menu_icon: 'dashicons-networking'`
- `rewrite: false` (React Router handles routing)

### Task 2: workspace_access Taxonomy

**File:** `includes/class-taxonomies.php`

Added `register_workspace_access_taxonomy()` method with:
- Taxonomy slug: `workspace_access`
- Object types: `['person', 'team', 'important_date']`
- `hierarchical: false` (tags, not categories)
- `show_ui: true`, `show_admin_column: false`
- `show_in_rest: true` (needed for React API calls)
- `query_var: true`
- `rewrite: false` (no frontend permalinks needed)

## Verification

- [x] WordPress admin shows "Workspaces" menu item (position 4)
- [x] `/wp/v2/workspaces` REST endpoint returns `[]` (empty array, not 404)
- [x] `/wp/v2/workspace_access` REST endpoint returns `[]` (empty array, not 404)
- [x] No PHP errors in debug log
- [x] Existing functionality unchanged (person, team, important_date)

## Files Modified

- `includes/class-post-types.php` - Added workspace CPT registration
- `includes/class-taxonomies.php` - Added workspace_access taxonomy registration
- `style.css` - Version bump to 1.43.1
- `package.json` - Version bump to 1.43.1
- `CHANGELOG.md` - Added changelog entries

## Commits

1. `feat(07-01): register workspace Custom Post Type` (8d4d71a)
2. `feat(07-01): register workspace_access taxonomy` (6fdc8c6)

## Next Steps

Phase 7 continues with:
- 07-02: Add `_visibility` post meta field
- 07-03: Add workspace membership to user meta
- 07-04: Update STADION_Access_Control for visibility checks

## Notes

- Terms for workspace_access will be auto-created when workspaces are created (Phase 8)
- Standard post capabilities used for now; custom capabilities may be added later
- Both registrations follow existing patterns in the codebase
