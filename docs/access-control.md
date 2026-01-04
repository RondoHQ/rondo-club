# Access Control

This document describes the row-level security system that ensures users can only see their own data or data explicitly shared with them.

## Overview

Caelis implements **row-level access control** - each user can only see posts (contacts, companies, dates) they either:
1. Created themselves (are the `post_author`)
2. Have been explicitly shared via the `shared_with` ACF field

**Administrators bypass all restrictions** and can see everything.

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

1. Check if user is an administrator → allow all
2. Get list of accessible post IDs via `get_accessible_post_ids()`
3. Set `post__in` query argument to limit results

```php
// Posts accessible to a user
$accessible_ids = $this->get_accessible_post_ids($post_type, $user_id);
$query->set('post__in', $accessible_ids);
```

### Accessible Post Discovery

The `get_accessible_post_ids()` method uses direct database queries for performance:

```php
// 1. Get posts authored by user
SELECT ID FROM wp_posts 
WHERE post_type = %s 
AND post_status = 'publish' 
AND post_author = %d

// 2. Get posts shared with user (ACF serialized format)
SELECT post_id FROM wp_postmeta 
WHERE meta_key = 'shared_with' 
AND (meta_value LIKE '%"123"%' OR meta_value LIKE '%i:123;%')
```

The `shared_with` field stores user IDs in PHP serialized format, requiring `LIKE` queries to match both array formats:
- `%"123"%` - matches string format
- `%i:123;%` - matches integer format

### Single Post Access Check

The `user_can_access_post()` method provides a simple boolean check:

```php
// Usage
$can_access = PRM_Access_Control::user_can_access_post($post_id, $user_id);

// Checks in order:
// 1. Is user an admin? → true
// 2. Is post a controlled type? → if not, true
// 3. Is user the author? → true  
// 4. Is user in shared_with array? → true/false
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

## The `shared_with` Field

Each controlled post type has a `shared_with` ACF field that allows sharing with other users:

| Post Type | Field Key |
|-----------|-----------|
| person | `field_shared_with` |
| company | `field_company_shared_with` |
| important_date | `field_date_shared_with` |

**Field Configuration:**
- Type: `user`
- Multiple: Yes (allows selecting multiple users)
- Return Format: `id` (stores user IDs)

## Administrator Bypass

Administrators (users with `manage_options` capability) bypass all access control:

```php
if (user_can($user_id, 'manage_options')) {
    return true; // Full access
}
```

This applies to:
- WP_Query filtering
- REST API queries
- Single post access checks

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
4. **Shared access is explicit** - Posts are private by default; sharing must be intentional

## Performance Notes

- `get_accessible_post_ids()` uses direct SQL for speed
- Results are not cached; consider caching for high-traffic scenarios
- The `LIKE` queries on `postmeta` may be slow with very large datasets

## Related Documentation

- [Data Model](./data-model.md) - Post types and field definitions
- [REST API](./rest-api.md) - API endpoints with access control

