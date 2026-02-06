# Access Control

This document describes the access control system in Rondo Club.

## Overview

Rondo Club uses a simple **shared access model**: all approved users can see and edit all data. This makes it ideal for teams that collaborate on the same contact database.

**Key principles:**

1. **Unapproved users see nothing** - New users must be approved by an administrator before they can access any data
2. **Approved users see everything** - Once approved, users can view and edit all people, teams, dates, and todos
3. **Trashed posts are hidden** - Posts in the trash are not accessible via the frontend

## User Approval

New users who register or are created in WordPress start as unapproved. An administrator must approve them before they can access Rondo Club data.

**Checking approval status:**

```php
// Check if a user is approved
$is_approved = STADION_User_Roles::is_user_approved( $user_id );

// Approve a user
STADION_User_Roles::approve_user( $user_id );
```

Administrators (users with `manage_options` capability) are always considered approved.

## Implementation

The access control system is implemented in `includes/class-access-control.php` via the `AccessControl` class.

### Controlled Post Types

Access control applies to these post types:

- `person` - Contact records
- `team` - Team/company records
- `stadion_todo` - Todo items

Standard WordPress posts and pages are not affected.

### Hook Points

The class intercepts data access at multiple levels:

| Hook | Purpose |
|------|---------|
| `pre_get_posts` | Blocks unapproved users from seeing any posts |
| `rest_{post_type}_query` | Blocks unapproved users from REST API list queries |
| `rest_prepare_{post_type}` | Verifies approval for single item REST access |

### Access Check Methods

**Check if user is approved:**

```php
$access_control = new Rondo\Core\AccessControl();
$can_access = $access_control->is_user_approved( $user_id );
```

**Check if user can access a specific post:**

```php
$can_access = $access_control->user_can_access_post( $post_id, $user_id );
// Returns false if: user not approved, post trashed, or post doesn't exist
```

**Get permission level:**

```php
$permission = $access_control->get_user_permission( $post_id, $user_id );
// Returns: 'owner' (if user created the post), 'editor' (if approved but not author), or false
```

## Bypassing Access Control

Internal system code can bypass access control using `suppress_filters`:

```php
$query = new WP_Query([
    'post_type' => 'person',
    'suppress_filters' => true, // Bypasses pre_get_posts
]);
```

## User Roles

Rondo Club creates a custom user role called **"Rondo User"** (`stadion_user`) on theme activation.

**Capabilities:**

- `read` - Required for WordPress access
- `edit_posts` - Create and edit posts
- `publish_posts` - Publish posts
- `delete_posts` - Delete posts
- `edit_published_posts` - Edit published posts
- `delete_published_posts` - Delete published posts
- `upload_files` - Upload files (photos, logos)

**What Rondo Users cannot do:**

- Manage other users
- Access WordPress admin settings
- Install plugins or themes

The role is removed on theme deactivation (users are reassigned to Subscriber).

## Security Considerations

1. **All access control is enforced server-side** - Never trust client-side checks
2. **REST API is protected** - Unapproved users receive 403 errors
3. **User approval is required** - New users cannot access any data until approved

## Related Documentation

- [Multi-User System](./multi-user.md) - User management and approval
- [Data Model](./data-model.md) - Post types and field definitions
- [REST API](./rest-api.md) - API endpoints
