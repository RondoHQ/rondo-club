# Plan 08-02 Summary: Workspace Invitation System

## Overview

Implemented a complete workspace invitation system with a custom post type for tracking invites, REST API endpoints for invite management, and email notifications with HTML templates.

## Changes Made

### Task 1: Register workspace_invite CPT

**File:** `includes/class-post-types.php`

Added `register_workspace_invite_post_type()` method:
- Post type slug: `workspace_invite`
- Labels: Workspace Invites / Workspace Invite
- Settings:
  - public: false
  - publicly_queryable: false
  - show_ui: true (for admin debugging)
  - show_in_menu: `edit.php?post_type=workspace` (submenu under Workspaces)
  - show_in_rest: false (custom endpoints only)
  - supports: title (email address for easy identification)

**File:** `acf-json/group_workspace_invite_fields.json`

Created ACF field group with 7 fields:
- `_invite_workspace_id` (number) - The workspace being invited to
- `_invite_email` (email) - Invitee email address
- `_invite_role` (select: admin|member|viewer) - Role to assign
- `_invite_token` (text) - Secure random token for URL
- `_invite_status` (select: pending|accepted|expired|revoked) - Current status
- `_invite_expires_at` (text) - ISO 8601 expiration timestamp
- `_invite_accepted_by` (number) - User ID who accepted

### Task 2: Add Invite REST Endpoints

**File:** `includes/class-rest-workspaces.php`

Added 5 invite management endpoints:

1. **POST /rondo/v1/workspaces/{id}/invites** - Create & send invite
   - Permission: workspace admin
   - Validates email not already member
   - Prevents duplicate pending invites
   - Generates secure 32-char alphanumeric token
   - Sets 7-day expiration
   - Sends HTML invitation email

2. **GET /rondo/v1/workspaces/{id}/invites** - List pending invites
   - Permission: workspace admin
   - Returns array with id, email, role, status, expires_at, invited_by, invited_at

3. **DELETE /rondo/v1/workspaces/{id}/invites/{invite_id}** - Revoke invite
   - Permission: workspace admin
   - Sets status to 'revoked'
   - Only pending invites can be revoked

4. **GET /rondo/v1/invites/{token}** - Validate invite (PUBLIC)
   - Permission: none (public endpoint)
   - Validates token, status, and expiration
   - Auto-marks as expired if past due
   - Returns workspace name, role, inviter info

5. **POST /rondo/v1/invites/{token}/accept** - Accept invite
   - Permission: approved user
   - Validates token, status, expiration
   - Enforces email matching (admin override available)
   - Adds user to workspace via RONDO_Workspace_Members::add()
   - Updates invite status and accepted_by

**Helper Methods:**
- `get_invite_by_token($token)` - Find invite by token
- `send_invite_email($invite_id)` - Send HTML invitation email
- `build_invite_email_html(...)` - Generate styled HTML email template

## Verification

- [x] workspace_invite CPT registered and shows in admin under Workspaces
- [x] ACF fields visible on invite posts
- [x] `npm run build` succeeds
- [x] Deployed to production
- [x] Caches cleared

## Files Modified

- `includes/class-post-types.php` - Added workspace_invite CPT registration
- `acf-json/group_workspace_invite_fields.json` - New ACF field group
- `includes/class-rest-workspaces.php` - Added 5 invite endpoints + helpers
- `style.css` - Version bump to 1.48.0
- `package.json` - Version bump to 1.48.0
- `CHANGELOG.md` - Added entries for 1.47.0 and 1.48.0

## Commits

1. `feat(08-02): register workspace_invite CPT` (8e3d9c3)
2. `feat(08-02): add invite REST endpoints to RONDO_REST_Workspaces` (6f6c531)

## Deviations from Plan

None - All tasks completed as specified.

## Notes

- Invite tokens are 32-character alphanumeric strings (no special characters)
- Invitations expire after 7 days and are auto-marked as expired on validation
- Email matching is enforced but WordPress admins can accept any invite
- The accept URL follows React Router pattern: `/workspace-invite/{token}`
- HTML email template uses inline styles for email client compatibility
- Email uses gradient header consistent with Stadion branding

## API Response Examples

**POST /rondo/v1/workspaces/{id}/invites:**
```json
{
  "id": 456,
  "email": "user@example.com",
  "role": "member",
  "status": "pending",
  "expires_at": "2026-01-20T12:00:00+00:00",
  "email_sent": true
}
```

**GET /rondo/v1/invites/{token}:**
```json
{
  "email": "user@example.com",
  "role": "member",
  "workspace_id": 123,
  "workspace_name": "Team Workspace",
  "invited_by": "John Doe",
  "expires_at": "2026-01-20T12:00:00+00:00"
}
```

**POST /rondo/v1/invites/{token}/accept:**
```json
{
  "success": true,
  "workspace_id": 123,
  "workspace_name": "Team Workspace",
  "role": "member",
  "message": "You have joined the workspace."
}
```
