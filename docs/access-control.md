# Access Control

This document describes the row-level security system that ensures users can only see their own data.

## Overview

Caelis implements **row-level access control** - each user can only see posts (contacts, companies, dates) they created themselves (are the `post_author`).

**Administrators are restricted on the frontend** - they can only see their own posts, just like regular users. However, in the WordPress admin area, administrators have full access to manage all posts.

## Implementation

The access control system is implemented in `includes/class-access-control.php` via the `PRM_Access_Control` class.

### Controlled Post Types

Access control applies to these post types:

- `person` - Contact records
- `company` - Company records
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

The `get_accessible_post_ids()` method uses direct database queries for performance:

```php
// Get posts authored by user
SELECT ID FROM wp_posts 
WHERE post_type = %s 
AND post_status = 'publish' 
AND post_author = %d
```

### Single Post Access Check

The `user_can_access_post()` method provides a simple boolean check:

```php
// Usage
$can_access = PRM_Access_Control::user_can_access_post($post_id, $user_id);

// Checks in order:
// 1. Is user an admin AND in admin area? → true (admin area only)
// 2. Is post a controlled type? → if not, true
// 3. Is post trashed? → false
// 4. Is user the author? → true/false
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

## Administrator Access Control

Administrators (users with `manage_options` capability) have different access levels depending on context:

**In WordPress Admin Area (`is_admin() === true`):**
- Full access to all posts
- Can manage any post regardless of author or sharing

**On Frontend (React SPA):**
- Restricted like regular users
- Can only see posts they created themselves
- REST API calls from the frontend are filtered

This ensures that:
- Administrators can manage the system via WordPress admin
- Frontend users (including admins) only see their own data
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
    if (PRM_Access_Control::user_can_access_post($post_id, $user_id)) {
        // User can access this post
    }
}
```

## Security Considerations

1. **Never trust client-side access checks** - All access control is enforced server-side
2. **REST API is protected** - Both list and single-item endpoints verify access
3. **Direct database queries** bypass WordPress filters - Use `user_can_access_post()` when accessing posts outside normal query flow
4. **Posts are private by default** - Users can only see posts they created themselves

## Performance Notes

- `get_accessible_post_ids()` uses direct SQL for speed
- Results are not cached; consider caching for high-traffic scenarios

## User Roles

Caelis automatically creates a custom user role called **"Caelis User"** (`caelis_user`) when the theme is activated. This role has minimal permissions:

**Capabilities:**
- `read` - Required for WordPress access
- `edit_posts` - Create and edit their own posts
- `publish_posts` - Publish their own posts
- `delete_posts` - Delete their own posts
- `edit_published_posts` - Edit their own published posts
- `delete_published_posts` - Delete their own published posts
- `upload_files` - Upload files (photos, logos)

**What Caelis Users cannot do:**
- Edit other users' posts
- Manage other users
- Access WordPress admin settings
- Install plugins or themes
- Edit themes or plugins
- Access any WordPress admin areas outside of People, Companies, and Dates

The role is automatically registered on theme activation and removed on theme deactivation (users are reassigned to Subscriber role).

## Related Documentation

- [Data Model](./data-model.md) - Post types and field definitions
- [REST API](./rest-api.md) - API endpoints with access control

