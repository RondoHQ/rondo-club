# Access Control

This document describes the row-level security system that controls who can see and edit contacts, teams, and dates in Stadion.

## Overview

Stadion implements **row-level access control** with multiple access paths:

1. **Author access** - Users can always see posts they created
2. **Workspace access** - Members of a workspace can see workspace-visible posts
3. **Share access** - Users can see posts explicitly shared with them

**Administrators are restricted on the frontend** - they can only see posts they have access to, just like regular users. However, in the WordPress admin area, administrators have full access to manage all posts.

## Permission Resolution Chain

When determining if a user can access a post, the system checks in order:

```
1. Is user the author? ─────────────────────────── → Full access
   │
   │ no
   ▼
2. Is _visibility = 'private'? ─────────────────── → Deny
   │
   │ no
   ▼
3. Is _visibility = 'workspace'? ───────────────── → Check membership
   │                                                  └─ Member? → Allow with role
   │ no                                               └─ Not member? → Continue
   ▼
4. Is user in _shared_with? ────────────────────── → Allow with permission
   │
   │ no
   ▼
5. Deny
```

This chain ensures backward compatibility: existing posts without visibility settings default to `private`, making them visible only to their author.

## Permission Levels

The `get_user_permission()` method returns the user's permission level for a post:

| Return Value | Meaning | Can Edit |
|--------------|---------|----------|
| `'owner'` | User is the post author | Yes |
| `'admin'` | User is workspace admin | Yes |
| `'member'` | User is workspace member | Yes |
| `'viewer'` | User is workspace viewer | No |
| `'edit'` | User has edit share | Yes |
| `'view'` | User has view share | No |
| `false` | No access | - |

## Implementation

The access control system is implemented in `includes/class-access-control.php` via the `STADION_Access_Control` class.

### Controlled Post Types

Access control applies to these post types:

- `person` - Contact records
- `team` - Team records
- `important_date` - Important date records

Standard WordPress posts and pages are not affected.

### Hook Points

The class intercepts data access at multiple levels:

| Hook | Purpose |
|------|---------|
| `pre_get_posts` | Filters WP_Query to only return accessible posts |
| `rest_{post_type}_query` | Filters REST API list queries |
| `the_posts` | Double-checks single post access after query |
| `rest_prepare_{post_type}` | Verifies single item REST access |

## How It Works

### Query Filtering (`pre_get_posts`)

When a WP_Query runs for a controlled post type:

1. Check if user is an administrator AND in admin area → allow all (admin area only)
2. On frontend, even administrators are restricted
3. Get list of accessible post IDs via `get_accessible_post_ids()`
4. Set `post__in` query argument to limit results

```php
// Posts accessible to a user
$accessible_ids = $this->get_accessible_post_ids($post_type, $user_id);
$query->set('post__in', $accessible_ids);
```

### Accessible Post Discovery

The `get_accessible_post_ids()` method finds all posts a user can access through three queries:

**1. Posts authored by user:**
```sql
SELECT ID FROM wp_posts
WHERE post_type = %s
AND post_status = 'publish'
AND post_author = %d
```

**2. Workspace-visible posts where user is member:**
```sql
SELECT DISTINCT p.ID
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id
INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
INNER JOIN wp_terms t ON tt.term_id = t.term_id
WHERE p.post_type = %s
AND p.post_status = 'publish'
AND pm.meta_key = '_visibility'
AND pm.meta_value = 'workspace'
AND tt.taxonomy = 'workspace_access'
AND t.slug IN (workspace-1, workspace-2, ...)
```

**3. Posts shared directly with user:**
```sql
SELECT DISTINCT p.ID
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = %s
AND p.post_status = 'publish'
AND pm.meta_key = '_shared_with'
AND pm.meta_value LIKE '%"user_id":5%'
```

The results are merged and deduplicated.

### Single Post Access Check

The `user_can_access_post()` method provides a simple boolean check:

```php
// Usage
$can_access = $access_control->user_can_access_post($post_id, $user_id);

// Checks in order:
// 1. Is user approved? → if not, false
// 2. Is post a controlled type? → if not, true
// 3. Is post trashed? → false
// 4. Is user the author? → true
// 5. Is user admin in admin area? → true
// 6. Is _visibility = 'private'? → false
// 7. Is _visibility = 'workspace'? → check membership
// 8. Is user in _shared_with? → true
// 9. Default: false
```

### REST API Filtering

REST API requests are filtered at two levels:

**List Queries:** The `filter_rest_query()` method modifies the query args before WordPress executes them.

**Single Item Access:** The `filter_rest_single_access()` method checks access after the post is retrieved, returning a 403 error if unauthorized:

```php
return new WP_Error(
    'rest_forbidden',
    'You do not have permission to access this item.',
    ['status' => 403]
);
```

## Visibility Settings

Visibility is stored in the `_visibility` post meta field:

| Value | Meaning |
|-------|---------|
| `private` | Only the author can see (default) |
| `workspace` | Visible to workspace members |
| `shared` | Visible to users in `_shared_with` |

The `STADION_Visibility` helper class provides methods for managing visibility:

```php
// Get visibility (returns 'private' if not set)
$visibility = STADION_Visibility::get_visibility($post_id);

// Set visibility
STADION_Visibility::set_visibility($post_id, 'workspace');
```

## Workspace Access

When `_visibility = 'workspace'`, the `workspace_access` taxonomy determines which workspaces can see the post.

**How workspace access is checked:**

1. Get user's workspace IDs from user meta (`_workspace_memberships`)
2. Get post's workspace terms from `workspace_access` taxonomy
3. If any overlap exists, user has access with their workspace role

**Workspace roles and permissions:**

| Role | Can View | Can Edit |
|------|----------|----------|
| admin | Yes | Yes |
| member | Yes | Yes |
| viewer | Yes | No |

## Direct Sharing

When visibility is set to `shared` or as an additional access path, the `_shared_with` post meta stores sharing details:

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

The `STADION_Visibility` class manages shares:

```php
// Add/update a share
STADION_Visibility::add_share($post_id, $user_id, 'edit');

// Remove a share
STADION_Visibility::remove_share($post_id, $user_id);

// Check if user has share
$has_share = STADION_Visibility::user_has_share($post_id, $user_id);

// Get permission level ('view', 'edit', or false)
$permission = STADION_Visibility::get_share_permission($post_id, $user_id);
```

## Administrator Access Control

Administrators (users with `manage_options` capability) have different access levels depending on context:

**In WordPress Admin Area (`is_admin() === true`):**
- Full access to all posts
- Can manage any post regardless of author or sharing

**On Frontend (React SPA):**
- Restricted like regular users
- Can only see posts they have access to via author/workspace/share
- REST API calls from the frontend are filtered

This ensures that:
- Administrators can manage the system via WordPress admin
- Frontend users (including admins) only see their permitted data
- Data privacy is maintained even for administrators on the frontend

```php
// Admin bypass only applies in admin area
if (user_can($user_id, 'manage_options') && !is_frontend()) {
    return true; // Full access in admin area
}
// On frontend, admins are restricted like regular users
```

## Bypassing Access Control

In some cases, internal system code needs to bypass access control (e.g., finding potential duplicates during import). Use `suppress_filters`:

```php
$query = new WP_Query([
    'post_type' => 'person',
    'suppress_filters' => true, // Bypasses pre_get_posts
    // ... other args
]);
```

**Important:** When bypassing filters, always verify access manually if needed:

```php
if ($query->have_posts()) {
    $post_id = $query->posts[0]->ID;
    // Manually check access
    if ($access_control->user_can_access_post($post_id, $user_id)) {
        // User can access this post
    }
}
```

## Security Considerations

1. **Never trust client-side access checks** - All access control is enforced server-side
2. **REST API is protected** - Both list and single-item endpoints verify access
3. **Direct database queries** bypass WordPress filters - Use `user_can_access_post()` when accessing posts outside normal query flow
4. **Posts are private by default** - The `_visibility` field defaults to `private` when not set
5. **Share data is validated** - User IDs in `_shared_with` are verified to exist

## Performance Notes

- `get_accessible_post_ids()` uses direct SQL for speed
- Results are not cached; consider caching for high-traffic scenarios
- Workspace membership is stored in user meta for efficient "my workspaces" queries
- The `workspace_access` taxonomy enables efficient WordPress term queries

## User Roles

Stadion automatically creates a custom user role called **"Stadion User"** (`stadion_user`) when the theme is activated. This role has minimal permissions:

**Capabilities:**
- `read` - Required for WordPress access
- `edit_posts` - Create and edit their own posts
- `publish_posts` - Publish their own posts
- `delete_posts` - Delete their own posts
- `edit_published_posts` - Edit their own published posts
- `delete_published_posts` - Delete their own published posts
- `upload_files` - Upload files (photos, logos)

**What Stadion Users cannot do:**
- Edit other users' posts
- Manage other users
- Access WordPress admin settings
- Install plugins or themes
- Edit themes or plugins
- Access any WordPress admin areas outside of People, Teams, and Dates

The role is automatically registered on theme activation and removed on theme deactivation (users are reassigned to Subscriber role).

## Related Documentation

- [Multi-User System](./multi-user.md) - Workspaces, visibility, and sharing
- [Data Model](./data-model.md) - Post types and field definitions
- [REST API](./rest-api.md) - API endpoints with access control
