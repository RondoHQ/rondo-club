# Plan 07-04 Summary: Access Control Extension

## Overview

Extended RONDO_Access_Control to check visibility, workspace membership, and direct shares, enabling contacts to be accessed by workspace members and shared users, not just the author.

## Changes Made

### Task 1: Extended get_accessible_post_ids()

**File:** `includes/class-access-control.php`

Rewrote the method to include posts where:
1. **User is author** (existing behavior)
2. **Workspace-visible posts** where user is a member of any assigned workspace
3. **Shared posts** where user appears in `_shared_with` meta

Implementation details:
- Workspace query joins posts → postmeta → term_relationships → term_taxonomy → terms
- Checks for `_visibility = 'workspace'` AND matching `workspace_access` term slugs
- Shared query uses LIKE for serialized `_shared_with` meta array
- Results merged and deduplicated

### Task 2: Updated user_can_access_post() with Full Permission Chain

**File:** `includes/class-access-control.php`

Permission resolution order:
1. Is user the author? → Full access
2. Is `_visibility = 'private'`? → Deny (unless #1)
3. Is `_visibility = 'workspace'`? → Check workspace membership → Allow with role-based permission
4. Check `_shared_with` for user → Allow with specified permission
5. Deny

Also added `get_user_permission()` method returning:
- `'owner'` - Post author
- `'admin'`, `'member'`, `'viewer'` - Workspace roles
- `'edit'`, `'view'` - Direct share permissions
- `false` - No access

### Task 3: Human Verification (Checkpoint)

Verified that:
- Author still has full access to their own posts
- Private posts are only visible to author
- Workspace-visible posts accessible to workspace members
- Shared posts accessible to users in `_shared_with`
- Non-authorized users cannot access restricted posts

## Verification

- [x] Author still has full access to their own posts
- [x] Private posts are only visible to author
- [x] Workspace-visible posts are accessible to workspace members
- [x] Shared posts are accessible to users in `_shared_with`
- [x] Non-authorized users cannot access any restricted posts
- [x] REST API respects all access rules
- [x] `npm run build` succeeds
- [x] Deployed to production

## Files Modified

- `includes/class-access-control.php` - Extended access control logic
- `style.css` - Version bump to 1.45.0
- `package.json` - Version bump to 1.45.0
- `CHANGELOG.md` - Added changelog entries

## Commits

1. `feat(07-04): extend access control for visibility, workspace, and shares` (8689467)

## Deviations from Plan

None - plan executed exactly as written.

## Notes

- The LIKE query for `_shared_with` is simple but may need optimization for large datasets in the future
- Workspace term slugs follow format `workspace-{ID}` for easy parsing
- Existing single-user behavior preserved (private is default visibility)
- REST API filters use the same logic via `get_accessible_post_ids()`

## Next Steps

Phase 7: Data Model & Visibility System is now complete. All plans executed:
- 07-01: Workspace CPT & Taxonomy
- 07-02: Visibility Fields
- 07-03: Workspace Members
- 07-04: Access Control Extension

Ready for Phase 8: Workspace & Team Infrastructure.
