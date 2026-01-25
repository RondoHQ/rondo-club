# Plan 08-01 Summary: REST Workspaces Endpoints

## Overview

Created REST API endpoints for workspace management, enabling the frontend to list workspaces, view details, manage members, and perform CRUD operations on workspaces.

## Changes Made

### Task 1 & 2: STADION_REST_Workspaces Class

**File:** `includes/class-rest-workspaces.php`

Created a new REST API class with 8 endpoints:

**Workspace List/Create:**
- `GET /stadion/v1/workspaces` - List user's workspaces with role and member count
- `POST /stadion/v1/workspaces` - Create new workspace (auto-adds creator as admin)

**Workspace Details/Management:**
- `GET /stadion/v1/workspaces/{id}` - Get workspace details including full member list
- `PUT /stadion/v1/workspaces/{id}` - Update workspace title/description
- `DELETE /stadion/v1/workspaces/{id}` - Delete workspace (owner only)

**Member Management:**
- `POST /stadion/v1/workspaces/{id}/members` - Add member with role
- `DELETE /stadion/v1/workspaces/{id}/members/{user_id}` - Remove member
- `PUT /stadion/v1/workspaces/{id}/members/{user_id}` - Update member role

**Permission Callbacks:**
- `check_workspace_access()` - User must be workspace member
- `check_workspace_admin()` - User must have admin role
- `check_workspace_owner()` - User must be post author (for delete)

### Class Registration

**File:** `functions.php`

- Added `STADION_REST_Workspaces` to autoloader class map
- Instantiated in `stadion_init()` alongside other REST classes for REST requests

## Verification

- [x] `npm run build` succeeds
- [x] All 8 REST endpoints registered (OPTIONS shows correct methods)
- [x] GET /stadion/v1/workspaces shows GET, POST methods
- [x] GET /stadion/v1/workspaces/{id} shows GET, PUT, DELETE methods
- [x] POST /stadion/v1/workspaces/{id}/members shows POST method
- [x] /stadion/v1/workspaces/{id}/members/{user_id} shows DELETE, PUT methods
- [x] Permission callbacks validate workspace membership/admin status
- [x] Deployed to production

## Files Modified

- `includes/class-rest-workspaces.php` - New class (created)
- `functions.php` - Added class to autoloader and instantiation
- `CHANGELOG.md` - Added changelog entries

## Commits

1. `feat(08-01): create REST workspaces class with member endpoints` (569b2eb)

## Deviations from Plan

None - Tasks 1 and 2 were combined into a single commit since they both create endpoints in the same class file.

## Notes

- Uses existing `STADION_Workspace_Members` class for all membership operations
- WordPress admins have full access to all workspaces
- Owner cannot be removed from workspace (protected by STADION_Workspace_Members)
- Only workspace owner can delete workspace (stricter than admin)
- Response includes user-friendly data: display_name, email, avatar_url
- Follows existing REST class patterns from STADION_REST_People

## API Response Examples

**GET /stadion/v1/workspaces:**
```json
[
  {
    "id": 123,
    "title": "Team Workspace",
    "description": "Shared contacts",
    "role": "admin",
    "member_count": 3,
    "joined_at": "2026-01-13T10:00:00+00:00",
    "is_owner": true
  }
]
```

**GET /stadion/v1/workspaces/{id}:**
```json
{
  "id": 123,
  "title": "Team Workspace",
  "description": "Shared contacts",
  "owner_id": 1,
  "member_count": 3,
  "members": [
    {
      "user_id": 1,
      "display_name": "John Doe",
      "email": "john@example.com",
      "avatar_url": "...",
      "role": "admin",
      "joined_at": "2026-01-13T10:00:00+00:00",
      "is_owner": true
    }
  ],
  "current_user": {
    "role": "admin",
    "is_owner": true
  }
}
```
