# Multi-User System

This document describes Caelis's multi-user collaboration features, enabling teams to share contacts and work together while maintaining privacy controls.

## Overview

Caelis supports two collaboration modes:

1. **Single-user mode** - The default. Contacts are private to the user who created them.
2. **Multi-user mode** - Workspaces and sharing enable team collaboration.

The transition is seamless: existing single-user data remains private until explicitly shared.

## Workspaces

Workspaces are shared spaces where team members can collaborate on contacts. Think of them as "teams" or "groups" within your organization.

### Creating a Workspace

1. Go to **Settings > Workspaces**
2. Click **Create Workspace**
3. Enter a name and optional description
4. You become the workspace owner with admin rights

### Workspace Roles

| Role | Can View | Can Edit | Can Invite | Can Delete Workspace |
|------|----------|----------|------------|----------------------|
| Owner | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | No |
| Member | Yes | Yes | No | No |
| Viewer | Yes | No | No | No |

**Notes:**
- Only one owner per workspace (the person who created it)
- Admins can manage members but cannot delete the workspace
- Members can add and edit contacts shared with the workspace
- Viewers have read-only access

### Inviting Members

1. Open the workspace from **Settings > Workspaces**
2. Click **Invite Member**
3. Enter the person's email address
4. Select their role (Admin, Member, or Viewer)
5. Click **Send Invitation**

The invited person receives an email with a link to join. Invitations expire after 7 days.

### Managing Members

Workspace admins can:
- Change member roles
- Remove members (except the owner)
- Revoke pending invitations

## Visibility System

Every contact has a visibility setting that determines who can see it.

### Visibility Options

| Visibility | Who Can See | When to Use |
|------------|-------------|-------------|
| **Private** | Only you | Personal contacts, sensitive information |
| **Workspace** | All workspace members | Team contacts, shared clients |
| **Shared** | Specific users you choose | One-off sharing, outside collaborators |

### Setting Visibility

When creating or editing a contact:

1. Look for the **Visibility** dropdown
2. Choose Private, Workspace, or Shared
3. For Workspace visibility: select which workspace(s)
4. For Shared visibility: specify users and their permission level

### Default Visibility

All new contacts are **private by default**. This preserves single-user behavior for existing installations and ensures data is never accidentally exposed.

## Sharing Contacts

You can share individual contacts with specific users, even if they're not in any of your workspaces.

### Direct Sharing

1. Open the contact you want to share
2. Click the **Share** button
3. Search for users by name or email
4. Select a permission level:
   - **View** - Can see the contact but not edit
   - **Edit** - Can modify the contact
5. Click **Share**

### Viewing Shared Contacts

Contacts shared with you appear in your contact list. Use the filter dropdown to see:
- **All Contacts** - Everything you have access to
- **My Contacts** - Contacts you created
- **Shared with Me** - Contacts others shared with you
- **[Workspace Name]** - Contacts in a specific workspace

## Collaborative Features

### Note Visibility

Notes on contacts can be private or shared:

- **Shared notes** - Visible to everyone who can see the contact
- **Private notes** - Only visible to you, even on shared contacts

Toggle note visibility when creating or editing a note.

### @Mentions

Mention workspace members in notes to notify them:

1. Type `@` followed by a name
2. Select from the autocomplete list
3. The mentioned user receives a notification

Notification delivery depends on user preferences:
- **Daily digest** - Included in the daily email (default)
- **Immediate** - Sent right away
- **Never** - No notification

### Workspace Calendar

Each workspace has an iCal feed containing important dates for contacts shared with the workspace. Find the feed URL in workspace settings.

### Activity Digest

The daily digest includes workspace activity:
- @mentions from other users
- Notes added to shared contacts by team members (last 24 hours)

This keeps you informed about team activity without constant notifications.

## Migration

Existing single-user installations can migrate to multi-user mode while preserving all data.

### Running the Migration

Use WP-CLI to migrate:

```bash
wp prm multiuser migrate
```

This command:
1. Sets `_visibility = private` on all existing contacts
2. Ensures backward compatibility with single-user behavior
3. Prepares the installation for workspace and sharing features

### What Changes

| Before Migration | After Migration |
|------------------|-----------------|
| Access based on `post_author` only | Access includes visibility, workspaces, and shares |
| No workspaces | Workspace CPT available |
| No sharing | Contacts can be shared |

### What Stays the Same

- All existing contacts remain accessible to their creators
- No data is lost or modified (only `_visibility` meta added)
- Single-user workflow continues to work unchanged

## Permission Resolution

When determining if a user can access a contact, the system checks in order:

1. **Author check** - Is the user the contact's creator? Full access.
2. **Private visibility** - Is `_visibility = private`? Deny (unless author).
3. **Workspace visibility** - Is `_visibility = workspace`? Check workspace membership.
4. **Shared access** - Is user in `_shared_with`? Allow with specified permission.
5. **Deny** - No access.

This chain ensures backward compatibility while enabling collaboration.

## REST API Endpoints

### Workspace Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/prm/v1/workspaces` | List your workspaces |
| POST | `/prm/v1/workspaces` | Create workspace |
| GET | `/prm/v1/workspaces/{id}` | Get workspace details |
| PUT | `/prm/v1/workspaces/{id}` | Update workspace |
| DELETE | `/prm/v1/workspaces/{id}` | Delete workspace (owner only) |

### Member Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/prm/v1/workspaces/{id}/members` | Add member |
| PUT | `/prm/v1/workspaces/{id}/members/{user_id}` | Update member role |
| DELETE | `/prm/v1/workspaces/{id}/members/{user_id}` | Remove member |

### Invitation Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/prm/v1/workspaces/{id}/invites` | Create invitation |
| GET | `/prm/v1/workspaces/{id}/invites` | List pending invitations |
| DELETE | `/prm/v1/workspaces/{id}/invites/{invite_id}` | Revoke invitation |
| GET | `/prm/v1/invites/{token}` | Validate invitation (public) |
| POST | `/prm/v1/invites/{token}/accept` | Accept invitation |

## Related Documentation

- [Access Control](./access-control.md) - Permission system details
- [Data Model](./data-model.md) - Workspace CPT and visibility fields
- [REST API](./rest-api.md) - Complete API documentation
- [iCal Feed](./ical-feed.md) - Calendar integration including workspace feeds
