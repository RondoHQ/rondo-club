---
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-people.php
autonomous: true
estimated_time: 45 minutes
---

# Plan 1: Backend Filtered Endpoint

## Goal

Create a new REST endpoint `/rondo/v1/people/filtered` that returns paginated, filtered, and sorted people using optimized `$wpdb` queries with JOINs. This endpoint is the foundation for scaling the People list beyond 1400+ contacts.

## Context

The current approach fetches all people via `/wp/v2/people` in a loop (100 per request), then filters and sorts in JavaScript. This doesn't scale. The new endpoint moves all data operations to the database layer.

**Key patterns from research:**
- Use `$wpdb->prepare()` for all dynamic values
- Whitelist column names (can't use prepare() for identifiers)
- Check `is_user_approved()` before executing any query
- LEFT JOIN for meta fields to include people without those fields
- Use DISTINCT when joining taxonomy tables

## Tasks

<task id="1">
<title>Register filtered people endpoint</title>
<file>includes/class-rest-people.php</file>
<action>edit</action>
<description>
Add a new route registration in the `register_routes()` method for `/rondo/v1/people/filtered` with comprehensive parameter validation.

In the `register_routes()` method of `class-rest-people.php`, add a new route registration AFTER the existing route registrations (around line 203). Add this new endpoint:

```php
// Filtered people with server-side pagination, filtering, and sorting
register_rest_route(
    'rondo/v1',
    '/people/filtered',
    [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_filtered_people' ],
        'permission_callback' => [ $this, 'check_user_approved' ],
        'args'                => [
            'page'          => [
                'default'           => 1,
                'validate_callback' => function ( $param ) {
                    return is_numeric( $param ) && (int) $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
            'per_page'      => [
                'default'           => 100,
                'validate_callback' => function ( $param ) {
                    return is_numeric( $param ) && (int) $param > 0 && (int) $param <= 100;
                },
                'sanitize_callback' => 'absint',
            ],
            'labels'        => [
                'default'           => [],
                'validate_callback' => function ( $param ) {
                    if ( ! is_array( $param ) ) {
                        return false;
                    }
                    foreach ( $param as $id ) {
                        if ( ! is_numeric( $id ) ) {
                            return false;
                        }
                    }
                    return true;
                },
                'sanitize_callback' => function ( $param ) {
                    return array_map( 'absint', $param );
                },
            ],
            'ownership'     => [
                'default'           => 'all',
                'validate_callback' => function ( $param ) {
                    return in_array( $param, [ 'mine', 'shared', 'all' ], true );
                },
            ],
            'modified_days' => [
                'default'           => null,
                'validate_callback' => function ( $param ) {
                    return $param === null || $param === '' || ( is_numeric( $param ) && (int) $param > 0 );
                },
                'sanitize_callback' => function ( $param ) {
                    return $param === null || $param === '' ? null : absint( $param );
                },
            ],
            'orderby'       => [
                'default'           => 'first_name',
                'validate_callback' => function ( $param ) {
                    return in_array( $param, [ 'first_name', 'last_name', 'modified' ], true );
                },
            ],
            'order'         => [
                'default'           => 'asc',
                'validate_callback' => function ( $param ) {
                    return in_array( strtolower( $param ), [ 'asc', 'desc' ], true );
                },
                'sanitize_callback' => function ( $param ) {
                    return strtolower( $param );
                },
            ],
        ],
    ]
);
```

Key validations:
- `page`: Must be positive integer
- `per_page`: Must be 1-100 (capped at 100 to prevent memory issues)
- `labels`: Array of integers (taxonomy term IDs)
- `ownership`: Whitelisted to 'mine', 'shared', 'all'
- `modified_days`: Positive integer or null
- `orderby`: Whitelisted to 'first_name', 'last_name', 'modified'
- `order`: Whitelisted to 'asc', 'desc'
</description>
</task>

<task id="2">
<title>Implement get_filtered_people callback</title>
<file>includes/class-rest-people.php</file>
<action>edit</action>
<description>
Add the `get_filtered_people()` method that builds and executes the optimized SQL query. Add this method AFTER the `bulk_update_people()` method (at the end of the class, before the closing brace).

```php
/**
 * Get filtered and paginated people
 *
 * Returns people with server-side filtering, sorting, and pagination.
 * Uses optimized $wpdb queries with JOINs to fetch data in minimal queries.
 *
 * @param \WP_REST_Request $request The REST request object.
 * @return \WP_REST_Response Response with people array and pagination info.
 */
public function get_filtered_people( $request ) {
    global $wpdb;

    // Extract validated parameters
    $page          = (int) $request->get_param( 'page' );
    $per_page      = (int) $request->get_param( 'per_page' );
    $labels        = $request->get_param( 'labels' ) ?: [];
    $ownership     = $request->get_param( 'ownership' );
    $modified_days = $request->get_param( 'modified_days' );
    $orderby       = $request->get_param( 'orderby' );
    $order         = strtoupper( $request->get_param( 'order' ) );

    // Double-check access control (permission_callback should have caught this,
    // but custom $wpdb queries bypass pre_get_posts hooks, so we verify explicitly)
    $access_control = new \Stadion\Core\AccessControl();
    if ( ! $access_control->is_user_approved() ) {
        return rest_ensure_response( [
            'people'      => [],
            'total'       => 0,
            'page'        => $page,
            'total_pages' => 0,
        ] );
    }

    $offset = ( $page - 1 ) * $per_page;

    // Build query components
    $select_fields  = "p.ID, p.post_modified, p.post_author";
    $join_clauses   = [];
    $where_clauses  = [
        "p.post_type = 'person'",
        "p.post_status = 'publish'",
    ];
    $prepare_values = [];

    // Always JOIN meta for first_name and last_name (needed for display and sorting)
    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fn ON p.ID = fn.post_id AND fn.meta_key = 'first_name'";
    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ln ON p.ID = ln.post_id AND ln.meta_key = 'last_name'";
    $select_fields .= ", fn.meta_value AS first_name, ln.meta_value AS last_name";

    // Ownership filter
    if ( $ownership === 'mine' ) {
        $where_clauses[]  = 'p.post_author = %d';
        $prepare_values[] = get_current_user_id();
    } elseif ( $ownership === 'shared' ) {
        $where_clauses[]  = 'p.post_author != %d';
        $prepare_values[] = get_current_user_id();
    }

    // Modified date filter
    if ( $modified_days !== null ) {
        $date_threshold   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$modified_days} days" ) );
        $where_clauses[]  = 'p.post_modified >= %s';
        $prepare_values[] = $date_threshold;
    }

    // Label filter (taxonomy terms with OR logic)
    if ( ! empty( $labels ) ) {
        $join_clauses[] = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
        $join_clauses[] = "INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'person_label'";

        $placeholders     = implode( ',', array_fill( 0, count( $labels ), '%d' ) );
        $where_clauses[]  = "tt.term_id IN ($placeholders)";
        $prepare_values   = array_merge( $prepare_values, $labels );
    }

    // Build ORDER BY clause (columns are whitelisted in args validation)
    // ORDER and orderby are safe - validated against whitelist
    switch ( $orderby ) {
        case 'first_name':
            $order_clause = "ORDER BY fn.meta_value $order, ln.meta_value $order";
            break;
        case 'last_name':
            $order_clause = "ORDER BY ln.meta_value $order, fn.meta_value $order";
            break;
        case 'modified':
            $order_clause = "ORDER BY p.post_modified $order";
            break;
        default:
            $order_clause = "ORDER BY fn.meta_value $order";
    }

    // Combine clauses
    $join_sql  = implode( ' ', $join_clauses );
    $where_sql = implode( ' AND ', $where_clauses );

    // Main query with DISTINCT (needed when filtering by taxonomy to avoid duplicates)
    $main_sql = "SELECT DISTINCT $select_fields
                 FROM {$wpdb->posts} p
                 $join_sql
                 WHERE $where_sql
                 $order_clause";

    // Add pagination
    $prepare_values[] = $per_page;
    $prepare_values[] = $offset;
    $paginated_sql    = $main_sql . ' LIMIT %d OFFSET %d';

    // Prepare and execute main query
    $prepared_sql = $wpdb->prepare( $paginated_sql, $prepare_values );
    $results      = $wpdb->get_results( $prepared_sql );

    // Count query (same joins/where, no order/limit)
    // Need to rebuild prepare_values without the pagination values
    $count_prepare_values = array_slice( $prepare_values, 0, -2 );
    $count_sql            = "SELECT COUNT(DISTINCT p.ID)
                             FROM {$wpdb->posts} p
                             $join_sql
                             WHERE $where_sql";

    if ( ! empty( $count_prepare_values ) ) {
        $prepared_count_sql = $wpdb->prepare( $count_sql, $count_prepare_values );
    } else {
        $prepared_count_sql = $count_sql;
    }
    $total = (int) $wpdb->get_var( $prepared_count_sql );

    // Format results
    $people = [];
    foreach ( $results as $row ) {
        $people[] = [
            'id'         => (int) $row->ID,
            'first_name' => $this->sanitize_text( $row->first_name ?: '' ),
            'last_name'  => $this->sanitize_text( $row->last_name ?: '' ),
            'modified'   => $row->post_modified,
            // These are fetched post-query to avoid complex JOINs
            'thumbnail'  => $this->sanitize_url( get_the_post_thumbnail_url( $row->ID, 'thumbnail' ) ),
            'labels'     => wp_get_post_terms( $row->ID, 'person_label', [ 'fields' => 'names' ] ),
        ];
    }

    return rest_ensure_response( [
        'people'      => $people,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => (int) ceil( $total / $per_page ),
    ] );
}
```

Key implementation details:
1. **Access control check** at the start returns empty results for unapproved users
2. **LEFT JOIN** for meta fields so people without first_name/last_name are still returned
3. **INNER JOIN** for taxonomy filter because we only want people WITH those labels
4. **DISTINCT** prevents duplicate rows when a person has multiple matching labels
5. **Whitelist validation** for orderby/order columns prevents SQL injection
6. **$wpdb->prepare()** for all dynamic values
7. **Post-query fetches** for thumbnail and labels avoid complex JOINs
</description>
</task>

<task id="3">
<title>Test endpoint with curl commands</title>
<file>none</file>
<action>test</action>
<description>
After deploying, test the endpoint using curl or browser:

1. Basic request:
   `GET /wp-json/rondo/v1/people/filtered`
   Expected: 200 OK, array of up to 100 people, total count, page info

2. Pagination:
   `GET /wp-json/rondo/v1/people/filtered?page=1&per_page=10`
   `GET /wp-json/rondo/v1/people/filtered?page=2&per_page=10`
   Expected: Different sets of people, correct page numbers

3. Label filter:
   `GET /wp-json/rondo/v1/people/filtered?labels[]=5&labels[]=7`
   Expected: Only people with label ID 5 OR label ID 7

4. Ownership filter:
   `GET /wp-json/rondo/v1/people/filtered?ownership=mine`
   Expected: Only people where post_author = current user

5. Modified date filter:
   `GET /wp-json/rondo/v1/people/filtered?modified_days=30`
   Expected: Only people modified in last 30 days

6. Sorting:
   `GET /wp-json/rondo/v1/people/filtered?orderby=last_name&order=desc`
   Expected: People sorted by last name descending

7. SQL injection attempt:
   `GET /wp-json/rondo/v1/people/filtered?orderby=first_name;DROP TABLE`
   Expected: 400 error (validation failure)

8. Unapproved user test:
   Log in as unapproved user, call endpoint
   Expected: Empty results (people: [], total: 0)
</description>
</task>

## Verification

<verification>
<checklist>
- [ ] Endpoint registered at `/rondo/v1/people/filtered`
- [ ] GET request returns JSON with `people`, `total`, `page`, `total_pages`
- [ ] `page` and `per_page` parameters work correctly
- [ ] `labels` filter returns people with ANY matching label
- [ ] `ownership=mine` returns only current user's people
- [ ] `ownership=shared` returns only other users' people
- [ ] `modified_days` filter returns recently modified people only
- [ ] `orderby=first_name` sorts by first name
- [ ] `orderby=last_name` sorts by last name
- [ ] `orderby=modified` sorts by modification date
- [ ] `order=asc` and `order=desc` work correctly
- [ ] Unapproved users receive empty results
- [ ] Invalid `orderby` values are rejected (400 error)
- [ ] Invalid `order` values are rejected
- [ ] Response includes `thumbnail` and `labels` for each person
- [ ] No PHP errors in error log
- [ ] Query Monitor shows single SQL query (not N+1)
</checklist>
</verification>

## Must-Haves (Goal-Backward Verification)

These criteria must be met for the phase goal "People data can be filtered and sorted at the database layer":

1. **Single query fetches posts + meta** - Verified by checking Query Monitor or `$wpdb->queries`
2. **Pagination returns correct subsets** - Verified by comparing page 1 vs page 2 results
3. **Label filter uses OR logic** - Verified by testing person with label A appears when filtering by A,B
4. **Access control blocks unapproved users** - Verified by testing as unapproved user
5. **SQL injection impossible** - Verified by testing malicious orderby values

## Rollback

If issues arise:
1. The new endpoint is additive - existing `/wp/v2/people` endpoint unchanged
2. Remove the route registration to disable
3. Frontend can fall back to original `usePeople` hook
