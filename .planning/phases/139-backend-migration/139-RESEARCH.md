# Phase 139: Backend & Migration - Research

**Researched:** 2026-02-04
**Domain:** WordPress Access Control & Data Migration
**Confidence:** HIGH

## Summary

This phase implements user isolation for tasks (stadion_todo custom post type) by filtering queries based on the `post_author` field. The system already has a robust access control infrastructure (RONDO_Access_Control class) that was recently refactored from shared access to user-isolated access. This phase extends that pattern to tasks, ensuring each user only sees tasks they created.

The standard approach in WordPress is to use the native `post_author` field combined with WP_Query filtering. The codebase already filters person, team, important_date, and stadion_todo post types in the AccessControl class, but currently only blocks unapproved users - it doesn't filter by author. This phase adds author-based filtering to existing infrastructure.

For data migration, WordPress provides `wp_update_post()` for bulk updates and WP-CLI for command-line operations. The codebase has established patterns for WP-CLI migration commands with dry-run support, progress reporting, and error handling.

**Primary recommendation:** Extend the existing `filter_queries()` and `filter_rest_query()` methods in AccessControl to add `author` parameter filtering for stadion_todo queries, then create a WP-CLI command following the established migration pattern (dry-run, verification, bulk update).

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress WP_Query | Core API | Query posts with author filtering | Native WordPress query system, supports `author` parameter |
| WordPress REST API Filters | Core API | Filter REST queries via hooks | Standard WordPress extension point (`rest_{post_type}_query`) |
| WP-CLI | 2.12.0+ | Command-line data migrations | Industry standard for WordPress CLI operations |
| count_user_posts() | Core Function | Count posts by author efficiently | Native WordPress function, optimized SQL COUNT query |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp_count_posts() | Core Function | Count posts by status | Dashboard stats (needs custom query for author filtering) |
| wp_update_post() | Core Function | Bulk post updates | Migration script to fix post_author |
| get_posts() | Core Function | Retrieve filtered posts | Simpler queries where WP_Query is overkill |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| WP_Query author param | Custom meta_query | Meta queries are slower, post_author is indexed |
| count_user_posts() | Custom SQL COUNT | Custom SQL bypasses WP caching and filters |
| WP-CLI command | REST API endpoint | CLI better for bulk operations, prevents timeouts |

**Installation:**
No external dependencies - all WordPress core functionality.

## Architecture Patterns

### Recommended Code Structure
```
includes/
├── class-access-control.php    # Modify: Add author filtering to filter_queries()
├── class-rest-api.php          # Modify: Replace wp_count_posts() with count_user_posts()
├── class-rest-todos.php        # Already uses access control filters (no changes)
└── class-wp-cli.php            # Add: WP-CLI migration command
```

### Pattern 1: WP_Query Author Filtering
**What:** Add `author` parameter to WP_Query args to filter by post_author
**When to use:** For all stadion_todo queries (WP_Query and REST API)
**Example:**
```php
// Source: WordPress WP_Query Class Reference
// https://developer.wordpress.org/reference/classes/wp_query/

// In AccessControl::filter_queries() for stadion_todo
if ( $post_type === 'stadion_todo' ) {
    $query->set( 'author', get_current_user_id() );
}

// Alternative array syntax for get_posts()
$args = [
    'post_type' => 'stadion_todo',
    'author'    => get_current_user_id(),
];
```

### Pattern 2: REST API Query Filtering
**What:** Use `rest_{post_type}_query` filter to modify WP_Query args before execution
**When to use:** For REST API endpoints that use standard WordPress controllers
**Example:**
```php
// Source: WordPress REST API Handbook
// https://developer.wordpress.org/reference/hooks/rest_this-post_type_query/

// In AccessControl::filter_rest_query()
public function filter_rest_query( $args, $request ) {
    if ( ! $this->is_user_approved() ) {
        $args['post__in'] = [ 0 ];
        return $args;
    }

    // NEW: Filter stadion_todo by author
    // Extract post_type from current filter hook
    $post_type = $request->get_route();
    if ( strpos( $post_type, 'todos' ) !== false ) {
        $args['author'] = get_current_user_id();
    }

    return $args;
}
```

### Pattern 3: WP-CLI Migration Command
**What:** Command-line migration with dry-run, verification, and progress reporting
**When to use:** For bulk data updates, especially post_author fixes
**Example:**
```php
// Source: Existing migration pattern in class-wp-cli.php (line 1000-1142)

/**
 * Fix task ownership
 *
 * @synopsis [--dry-run] [--verify]
 */
public function fix_task_ownership( $args, $assoc_args ) {
    $dry_run = isset( $assoc_args['dry-run'] );
    $verify  = isset( $assoc_args['verify'] );

    if ( $dry_run ) {
        WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
    }

    // Query all stadion_todo posts
    $todos = get_posts( [
        'post_type'      => 'stadion_todo',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ] );

    $fixed = 0;
    $issues = 0;

    foreach ( $todos as $todo ) {
        // Verification or repair logic
        if ( $verify ) {
            // Check if post_author is valid
        } else {
            // Fix post_author if needed
            wp_update_post( [
                'ID'          => $todo->ID,
                'post_author' => $corrected_author_id,
            ] );
            $fixed++;
        }
    }

    WP_CLI::success( sprintf( 'Fixed %d tasks', $fixed ) );
}
```

### Pattern 4: Efficient Post Counting by Author
**What:** Use `count_user_posts()` instead of `wp_count_posts()` for author-filtered counts
**When to use:** Dashboard stats, user-specific counts
**Example:**
```php
// Source: WordPress count_user_posts() Function Reference
// https://developer.wordpress.org/reference/functions/count_user_posts/

// OLD (counts ALL posts regardless of author):
$open_todos_count = wp_count_posts( 'stadion_todo' )->stadion_open ?? 0;

// NEW (counts only current user's posts):
// Note: count_user_posts() only counts 'publish' status by default
// For custom statuses, need custom query:
global $wpdb;
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts}
     WHERE post_type = %s
     AND post_status = %s
     AND post_author = %d",
    'stadion_todo',
    'stadion_open',
    get_current_user_id()
) );
```

### Anti-Patterns to Avoid
- **Don't use meta_query for author filtering:** `post_author` is an indexed column, meta queries are slower
- **Don't bypass WP_Query filters:** Custom SQL queries skip access control hooks
- **Don't mix access control logic:** Keep all filtering in AccessControl class, not scattered across endpoints
- **Don't forget REST API filtering:** Both WP_Query (pre_get_posts) and REST (rest_*_query) need filtering

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Counting posts by author | Custom SQL COUNT query | count_user_posts() for publish status, or WP_Query with 'fields' => 'ids' | WordPress handles caching, permission checks, and database abstraction |
| Filtering queries by author | Manual post__in with get_posts() | WP_Query 'author' parameter | WordPress optimizes author queries with indexed lookups |
| Bulk post updates | Loop with REST API calls | WP-CLI command with wp_update_post() | CLI avoids timeouts, provides progress reporting, dry-run support |
| REST API filtering | Modify controller classes | Use rest_{post_type}_query filter hook | Follows WordPress plugin architecture, survives core updates |

**Key insight:** WordPress query filtering is designed for performance at scale. The `post_author` field is indexed, and WP_Query automatically optimizes author queries. Custom solutions often bypass caching and optimization.

## Common Pitfalls

### Pitfall 1: Forgetting REST API Has Separate Filters
**What goes wrong:** Adding author filtering to `pre_get_posts` but forgetting `rest_{post_type}_query` results in frontend (WP_Query) being filtered but REST API still showing all posts.
**Why it happens:** REST API uses different filter hooks than standard WP_Query
**How to avoid:**
- Always filter both `pre_get_posts` (via filter_queries) AND `rest_{post_type}_query` (via filter_rest_query)
- Test both WP_Query and REST API endpoints
- Check existing AccessControl implementation which already handles both
**Warning signs:** Frontend queries work but REST API returns unfiltered data

### Pitfall 2: wp_count_posts() Doesn't Support Author Filtering
**What goes wrong:** Using `wp_count_posts()` returns ALL posts regardless of current user, breaking dashboard counts
**Why it happens:** `wp_count_posts()` is designed for global stats, not user-specific counts
**How to avoid:**
- For standard 'publish' status: Use `count_user_posts( $user_id, $post_type )`
- For custom post statuses: Use WP_Query with `'fields' => 'ids'` and count the result, or direct $wpdb query
- Never use `wp_count_posts()` for user-isolated data
**Warning signs:** Dashboard shows same todo count for all users

### Pitfall 3: Migration Without Verification
**What goes wrong:** Running bulk updates without verifying post_author correctness can corrupt data (e.g., assigning all tasks to wrong user)
**Why it happens:** Assumptions about existing data structure may be incorrect
**How to avoid:**
- Always provide `--verify` flag that reports issues without fixing
- Always provide `--dry-run` flag that shows what WOULD change
- Log each change with before/after values
- Test on small dataset first
**Warning signs:** Migration completes but user reports seeing wrong tasks

### Pitfall 4: Not Handling get_all_todos Custom Endpoint
**What goes wrong:** The custom `/rondo/v1/todos` endpoint in class-rest-todos.php uses get_posts() which respects pre_get_posts filters, but developers might assume it needs separate filtering
**Why it happens:** Confusion about which filters apply to which query methods
**How to avoid:**
- Understand that `get_posts()` internally uses WP_Query, so `pre_get_posts` filters apply
- Access control filters in `filter_queries()` automatically apply to get_posts()
- Test the endpoint after adding filters to verify it works
**Warning signs:** Custom REST endpoints return all posts after adding filters

### Pitfall 5: Missing post_author on New Posts
**What goes wrong:** New todos created via REST API don't have post_author set, resulting in tasks "disappearing" after filtering is enabled
**Why it happens:** Forgetting to set post_author in wp_insert_post()
**How to avoid:**
- Check class-rest-todos.php line 237: Already sets `'post_author' => get_current_user_id()`
- Verify all todo creation endpoints set post_author
- Migration should NOT need to fix new posts, only existing ones
**Warning signs:** Users report newly created tasks disappearing

## Code Examples

Verified patterns from codebase analysis:

### Extending AccessControl::filter_queries()
```php
// Source: includes/class-access-control.php (line 169-199)
// Current implementation only blocks unapproved users

public function filter_queries( $query ) {
    // Respect suppress_filters flag
    if ( $query->get( 'suppress_filters' ) ) {
        return;
    }

    // Only filter our controlled post types
    $post_type = $query->get( 'post_type' );
    if ( ! $post_type || ! in_array( $post_type, $this->controlled_post_types ) ) {
        return;
    }

    // Check if user is approved
    if ( ! $this->is_user_approved() ) {
        // Unapproved or not logged in - show nothing
        $query->set( 'post__in', [ 0 ] );
        return;
    }

    // NEW: Filter stadion_todo by author (user isolation)
    if ( $post_type === 'stadion_todo' ) {
        $query->set( 'author', get_current_user_id() );
    }

    // VOG-only users see only volunteers for person post type
    if ( $post_type === 'person' && $this->should_filter_volunteers_only() ) {
        $meta_query   = $query->get( 'meta_query' ) ?: [];
        $meta_query[] = [
            'key'     => 'huidig-vrijwilliger',
            'value'   => '1',
            'compare' => '=',
        ];
        $query->set( 'meta_query', $meta_query );
    }
}
```

### Updating Dashboard Todo Counts
```php
// Source: includes/class-rest-api.php (line 2045-2046, 2128-2129)
// Current implementation counts ALL todos

// OLD (line 2045):
private function count_open_todos() {
    return wp_count_posts( 'stadion_todo' )->stadion_open ?? 0;
}

// NEW (use direct query for custom post status):
private function count_open_todos() {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = %s
         AND post_status = %s
         AND post_author = %d",
        'stadion_todo',
        'stadion_open',
        get_current_user_id()
    ) );
}

// Same pattern for count_awaiting_todos() (line 2128):
private function count_awaiting_todos() {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts}
         WHERE post_type = %s
         AND post_status = %s
         AND post_author = %d",
        'stadion_todo',
        'stadion_awaiting',
        get_current_user_id()
    ) );
}
```

### WP-CLI Migration Command Pattern
```php
// Source: includes/class-wp-cli.php (existing migration pattern)

/**
 * Verify or fix task ownership
 *
 * Ensures all stadion_todo posts have correct post_author.
 *
 * ## OPTIONS
 *
 * [--verify]
 * : Only verify ownership, don't fix
 *
 * [--dry-run]
 * : Show what would be fixed without making changes
 *
 * ## EXAMPLES
 *
 *     wp stadion tasks fix-ownership --verify
 *     wp stadion tasks fix-ownership --dry-run
 *     wp stadion tasks fix-ownership
 *
 * @when after_wp_load
 */
public function fix_ownership( $args, $assoc_args ) {
    $verify  = isset( $assoc_args['verify'] );
    $dry_run = isset( $assoc_args['dry-run'] );

    if ( $dry_run ) {
        WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
    }

    WP_CLI::log( '' );
    WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
    WP_CLI::log( '║         Task Ownership Verification/Fix                    ║' );
    WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
    WP_CLI::log( '' );

    // Query all stadion_todo posts
    $todos = get_posts( [
        'post_type'      => 'stadion_todo',
        'posts_per_page' => -1,
        'post_status'    => [ 'stadion_open', 'stadion_awaiting', 'stadion_completed' ],
        'suppress_filters' => true, // Bypass access control for admin command
    ] );

    if ( empty( $todos ) ) {
        WP_CLI::success( 'No tasks found.' );
        return;
    }

    WP_CLI::log( sprintf( 'Found %d task(s) to check.', count( $todos ) ) );
    WP_CLI::log( '' );

    $valid   = 0;
    $invalid = 0;
    $fixed   = 0;
    $failed  = 0;

    foreach ( $todos as $todo ) {
        $author_id = (int) $todo->post_author;
        $user      = get_userdata( $author_id );

        if ( $user ) {
            WP_CLI::log( sprintf( '✓ Task #%d has valid author: %s (ID: %d)', $todo->ID, $user->user_login, $author_id ) );
            $valid++;
        } else {
            WP_CLI::warning( sprintf( '✗ Task #%d has invalid author ID: %d', $todo->ID, $author_id ) );
            $invalid++;

            if ( ! $verify && ! $dry_run ) {
                // Attempt to determine correct author from related_persons
                $person_ids = get_field( 'related_persons', $todo->ID );
                if ( ! is_array( $person_ids ) || empty( $person_ids ) ) {
                    WP_CLI::warning( sprintf( '  Cannot fix: No related persons found for task #%d', $todo->ID ) );
                    $failed++;
                    continue;
                }

                // Use the first related person's author
                $person_id = $person_ids[0];
                $person    = get_post( $person_id );
                if ( ! $person ) {
                    WP_CLI::warning( sprintf( '  Cannot fix: Related person #%d not found', $person_id ) );
                    $failed++;
                    continue;
                }

                $new_author_id = (int) $person->post_author;
                $new_user      = get_userdata( $new_author_id );
                if ( ! $new_user ) {
                    WP_CLI::warning( sprintf( '  Cannot fix: Person #%d has invalid author', $person_id ) );
                    $failed++;
                    continue;
                }

                // Fix the task's post_author
                wp_update_post( [
                    'ID'          => $todo->ID,
                    'post_author' => $new_author_id,
                ] );

                WP_CLI::log( sprintf( '  Fixed: Set task #%d author to %s (ID: %d)', $todo->ID, $new_user->user_login, $new_author_id ) );
                $fixed++;
            } elseif ( $dry_run ) {
                WP_CLI::log( sprintf( '  Would fix task #%d', $todo->ID ) );
            }
        }
    }

    WP_CLI::log( '' );
    WP_CLI::log( '────────────────────────────────────────────────────────────────' );
    WP_CLI::log( 'Summary:' );
    WP_CLI::log( '────────────────────────────────────────────────────────────────' );
    WP_CLI::log( sprintf( '  Valid: %d', $valid ) );
    WP_CLI::log( sprintf( '  Invalid: %d', $invalid ) );

    if ( ! $verify && ! $dry_run ) {
        WP_CLI::log( sprintf( '  Fixed: %d', $fixed ) );
        WP_CLI::log( sprintf( '  Failed: %d', $failed ) );

        if ( $failed > 0 ) {
            WP_CLI::warning( sprintf( 'Completed with %d failure(s).', $failed ) );
        } else {
            WP_CLI::success( 'All tasks verified/fixed!' );
        }
    } else {
        WP_CLI::success( $verify ? 'Verification complete.' : 'Dry run complete.' );
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Shared access (all approved users see all data) | User isolation (users only see their own data) | Phase 111 (v14.0) | Access control refactored, now filtering by post_author |
| Comment-based todos | Custom post type todos | Phase 24-25 (v13.0) | Todos are now posts, support post_author field naturally |
| wp_count_posts() for stats | count_user_posts() or custom queries | Ongoing | User-specific counts require different approach |
| Manual post__in filtering | WP_Query author parameter | WordPress core since 2.1 | Native indexed queries are faster |

**Deprecated/outdated:**
- Shared access model: Replaced by user isolation in Phase 111
- Comment-based todos: Migrated to CPT in Phase 24
- Using wp_count_posts() for user-specific counts: Doesn't support author filtering

## Open Questions

Things that couldn't be fully resolved:

1. **Should admins see all tasks or only their own?**
   - What we know: Current AccessControl has special handling for admins (user_can 'manage_options')
   - What's unclear: Whether task isolation applies to admins or if they should see all tasks
   - Recommendation: Default to user isolation for all users (including admins), add separate admin view if needed later

2. **What happens to tasks with invalid post_author (deleted users)?**
   - What we know: Migration command can detect invalid authors
   - What's unclear: Whether to reassign to admin, delete, or leave orphaned
   - Recommendation: Migration should attempt to fix by looking at related_persons author, report unfixable cases for manual review

3. **Should PersonDetail sidebar show tasks from all users or just current user?**
   - What we know: Requirement TASK-02 says "user only sees their own tasks in PersonDetail sidebar"
   - What's unclear: Use case for seeing other users' tasks related to same person
   - Recommendation: Follow requirement strictly - filter by current user's tasks only

## Sources

### Primary (HIGH confidence)
- WordPress WP_Query Class Reference - https://developer.wordpress.org/reference/classes/wp_query/
- WordPress count_user_posts() Function - https://developer.wordpress.org/reference/functions/count_user_posts/
- WordPress wp_count_posts() Function - https://developer.wordpress.org/reference/functions/wp_count_posts/
- Codebase: includes/class-access-control.php (existing access control patterns)
- Codebase: includes/class-rest-todos.php (todo REST API implementation)
- Codebase: includes/class-wp-cli.php (existing migration patterns)
- Codebase: tests/Wpunit/UserIsolationTest.php (user isolation test patterns)

### Secondary (MEDIUM confidence)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/reference/posts/) - REST API filtering patterns
- [WP-CLI Commands](https://smartwp.com/wp-cli-commands/) - WP-CLI best practices 2026
- [rest_{post_type}_query Hook](https://developer.wordpress.org/reference/hooks/rest_this-post_type_query/) - REST query filtering

### Tertiary (LOW confidence)
- WebSearch results on WordPress author filtering - General patterns confirmed but not version-specific

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All WordPress core functions, verified in official documentation
- Architecture: HIGH - Patterns already exist in codebase (AccessControl, WP-CLI migrations)
- Pitfalls: HIGH - Based on common WordPress query filtering issues and codebase analysis
- Code examples: HIGH - Extracted from actual codebase and verified against WordPress documentation

**Research date:** 2026-02-04
**Valid until:** 2026-03-04 (30 days - stable WordPress core features, unlikely to change)
