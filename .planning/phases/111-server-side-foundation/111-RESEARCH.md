# Phase 111: Server-Side Foundation - Research

**Researched:** 2026-01-29
**Domain:** WordPress $wpdb custom queries with meta JOINs, pagination, filtering, sorting
**Confidence:** HIGH

## Summary

This phase creates a custom REST endpoint `/rondo/v1/people/filtered` that handles pagination, filtering (by labels, ownership, modified date), and sorting (by first_name, last_name, modified date) at the database layer using optimized $wpdb queries with JOINs. The current frontend fetches all people (1400+ records) in a loop, which is inefficient and doesn't scale.

The key challenge is building performant SQL queries that:
1. JOIN posts and postmeta tables to fetch name fields in a single query (no N+1)
2. Filter by taxonomy terms (person_label)
3. Filter by post_author and post_modified
4. Sort by meta values (first_name, last_name) or post fields (modified)
5. Preserve access control (unapproved users see nothing)
6. Prevent SQL injection via proper escaping

**Primary recommendation:** Create a new REST controller class extending `\Stadion\REST\Base` with a single endpoint that uses `$wpdb->prepare()` for all queries, builds JOINs conditionally based on filters, and validates all parameters against whitelists before executing queries.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress $wpdb | Core | Direct database queries | Native WordPress database abstraction, handles escaping |
| WordPress REST API | Core | Endpoint registration | Standard for custom endpoints in WordPress |
| ACF Pro get_field() | 6.x | Meta value retrieval | Already used throughout codebase |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| $wpdb->prepare() | Core | SQL escaping | Always - for every dynamic SQL value |
| WP_Query | Core | Post queries | For simple queries without meta JOINs |
| TanStack Query | Frontend | React data fetching | Already established in usePeople hook |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom $wpdb queries | WP_Query with meta_query | WP_Query generates slow LIKE queries for repeaters, can't fetch meta in single query |
| REST endpoint | Modify existing /wp/v2/people | Native endpoint doesn't support efficient meta JOINs or custom filtering logic |
| JOIN approach | Separate queries + in-memory merge | N+1 query problem, slow with 1400+ records |
| Transient caching | No caching | Adds complexity, cache invalidation is hard - start without, add if needed |

**Installation:**
```bash
# No additional packages needed
# All dependencies are WordPress core or already installed (ACF Pro)
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-rest-people.php           # Existing - add filtered endpoint here
└── class-access-control.php        # Existing - reuse is_user_approved()
```

### Pattern 1: Custom REST Controller with Filtered Endpoint
**What:** Extend `\Stadion\REST\Base` and register `/rondo/v1/people/filtered` endpoint
**When to use:** Always - this is the core implementation
**Example:**
```php
// Source: includes/class-rest-base.php (existing base class)
namespace Stadion\REST;

class People extends Base {
    public function __construct() {
        parent::__construct();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('rondo/v1', '/people/filtered', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_filtered_people'],
            'permission_callback' => [$this, 'check_user_approved'],
            'args'                => [
                'page'          => [
                    'default'           => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
                'per_page'      => [
                    'default'           => 100,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    },
                ],
                'labels'        => [
                    'default'           => [],
                    'validate_callback' => function($param) {
                        return is_array($param);
                    },
                ],
                'ownership'     => [
                    'default'           => 'all',
                    'validate_callback' => function($param) {
                        return in_array($param, ['mine', 'shared', 'all'], true);
                    },
                ],
                'modified_days' => [
                    'default'           => null,
                    'validate_callback' => function($param) {
                        return $param === null || (is_numeric($param) && $param > 0);
                    },
                ],
                'orderby'       => [
                    'default'           => 'first_name',
                    'validate_callback' => function($param) {
                        return in_array($param, ['first_name', 'last_name', 'modified'], true);
                    },
                ],
                'order'         => [
                    'default'           => 'asc',
                    'validate_callback' => function($param) {
                        return in_array($param, ['asc', 'desc'], true);
                    },
                ],
            ],
        ]);
    }
}
```

### Pattern 2: Single Query with Conditional JOINs
**What:** Build SQL query dynamically based on active filters, using JOINs for meta fields
**When to use:** When fetching people with first_name/last_name needed (always in this case)
**Example:**
```php
// Source: includes/class-rest-api.php line 1590 (existing $wpdb pattern)
public function get_filtered_people($request) {
    global $wpdb;

    // Extract validated parameters
    $page          = (int) $request->get_param('page');
    $per_page      = (int) $request->get_param('per_page');
    $labels        = $request->get_param('labels');
    $ownership     = $request->get_param('ownership');
    $modified_days = $request->get_param('modified_days');
    $orderby       = $request->get_param('orderby');
    $order         = $request->get_param('order');

    // Check access control - critical for security
    $access_control = new \Stadion\Core\AccessControl();
    if (!$access_control->is_user_approved()) {
        return rest_ensure_response([
            'people'     => [],
            'total'      => 0,
            'page'       => $page,
            'total_pages' => 0,
        ]);
    }

    $offset = ($page - 1) * $per_page;

    // Build base query with meta JOINs for first_name and last_name
    // LEFT JOIN to include people without these meta values
    $sql = "SELECT DISTINCT p.ID, p.post_modified,
                   fn.meta_value as first_name,
                   ln.meta_value as last_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} fn ON p.ID = fn.post_id AND fn.meta_key = 'first_name'
            LEFT JOIN {$wpdb->postmeta} ln ON p.ID = ln.post_id AND ln.meta_key = 'last_name'
            WHERE p.post_type = 'person'
            AND p.post_status = 'publish'";

    $count_sql = "SELECT COUNT(DISTINCT p.ID)
                  FROM {$wpdb->posts} p";

    $join_clauses  = [];
    $where_clauses = [
        "p.post_type = 'person'",
        "p.post_status = 'publish'",
    ];

    // Add ownership filter
    if ($ownership === 'mine') {
        $user_id         = get_current_user_id();
        $where_clauses[] = $wpdb->prepare('p.post_author = %d', $user_id);
    } elseif ($ownership === 'shared') {
        $user_id         = get_current_user_id();
        $where_clauses[] = $wpdb->prepare('p.post_author != %d', $user_id);
    }

    // Add modified date filter
    if ($modified_days !== null) {
        $date_threshold  = gmdate('Y-m-d H:i:s', strtotime("-{$modified_days} days"));
        $where_clauses[] = $wpdb->prepare('p.post_modified >= %s', $date_threshold);
    }

    // Add label filter (taxonomy terms)
    if (!empty($labels)) {
        $join_clauses[] = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
        $join_clauses[] = "INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'person_label'";

        $label_ids = array_map('intval', $labels);
        $placeholders = implode(',', array_fill(0, count($label_ids), '%d'));
        $where_clauses[] = $wpdb->prepare("tt.term_id IN ($placeholders)", ...$label_ids);
    }

    // Build ORDER BY clause (validated via whitelist in args)
    $order_clause = '';
    if ($orderby === 'first_name') {
        $order_clause = "ORDER BY fn.meta_value $order";
    } elseif ($orderby === 'last_name') {
        $order_clause = "ORDER BY ln.meta_value $order";
    } elseif ($orderby === 'modified') {
        $order_clause = "ORDER BY p.post_modified $order";
    }

    // Combine clauses
    $join_sql  = implode(' ', $join_clauses);
    $where_sql = implode(' AND ', $where_clauses);

    $final_sql = $sql . ' ' . $join_sql . ' WHERE ' . $where_sql . ' ' . $order_clause . ' ' .
                 $wpdb->prepare('LIMIT %d OFFSET %d', $per_page, $offset);

    // Get total count (same JOINs/WHERE but no ORDER/LIMIT)
    $count_final_sql = $count_sql . ' ' . $join_sql . ' WHERE ' . $where_sql;
    $total = (int) $wpdb->get_var($count_final_sql);

    // Execute query
    $results = $wpdb->get_results($final_sql);

    // Format results using existing post objects
    $people = [];
    foreach ($results as $row) {
        $post = get_post($row->ID);
        if ($post) {
            $people[] = [
                'id'         => $post->ID,
                'first_name' => $row->first_name ?: '',
                'last_name'  => $row->last_name ?: '',
                'modified'   => $post->post_modified,
                // Additional fields fetched separately (not in JOIN for performance)
                'thumbnail'  => get_the_post_thumbnail_url($post->ID, 'thumbnail'),
                'labels'     => wp_get_post_terms($post->ID, 'person_label', ['fields' => 'names']),
            ];
        }
    }

    return rest_ensure_response([
        'people'      => $people,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => ceil($total / $per_page),
    ]);
}
```

### Pattern 3: Access Control Enforcement in Custom Queries
**What:** Call `is_user_approved()` before executing custom $wpdb queries
**When to use:** Always - custom queries bypass WP_Query hooks that normally enforce access control
**Example:**
```php
// Source: includes/class-access-control.php line 46 (existing pattern)
// CRITICAL: Custom $wpdb queries bypass pre_get_posts hook
// Must explicitly check approval before query

$access_control = new \Stadion\Core\AccessControl();
if (!$access_control->is_user_approved()) {
    // Return empty result set, not error
    return rest_ensure_response([
        'people'     => [],
        'total'      => 0,
        'page'       => 1,
        'total_pages' => 0,
    ]);
}

// Only execute query for approved users
$results = $wpdb->get_results($sql);
```

### Pattern 4: SQL Injection Prevention
**What:** Use `$wpdb->prepare()` for all dynamic values, whitelist for column names
**When to use:** Always - never concatenate user input into SQL
**Example:**
```php
// Source: WordPress Codex - $wpdb best practices

// CORRECT: Use prepare() for values
$date_threshold = gmdate('Y-m-d H:i:s', strtotime("-{$modified_days} days"));
$where_clauses[] = $wpdb->prepare('p.post_modified >= %s', $date_threshold);

// CORRECT: Whitelist for column names (can't use prepare() for identifiers)
$allowed_orderby = ['first_name', 'last_name', 'modified'];
if (!in_array($orderby, $allowed_orderby, true)) {
    return new \WP_Error('invalid_orderby', 'Invalid sort field');
}
$order_clause = "ORDER BY {$orderby} {$order}"; // Safe - whitelisted

// CORRECT: Prepare() with array of values
$label_ids = array_map('intval', $labels); // Cast to int
$placeholders = implode(',', array_fill(0, count($label_ids), '%d'));
$where_clauses[] = $wpdb->prepare("tt.term_id IN ($placeholders)", ...$label_ids);

// WRONG: Never concatenate user input
$sql = "WHERE p.post_author = {$user_id}"; // SQL injection risk!

// WRONG: Can't prepare() column names
$sql = $wpdb->prepare("ORDER BY %s", $orderby); // Doesn't work - prepare() is for values
```

### Anti-Patterns to Avoid
- **Using WP_Query with meta_query for multiple meta filters:** Generates slow LIKE queries, especially with ACF repeater fields
- **Fetching all people then filtering in PHP:** Doesn't scale beyond 100-200 records, wastes memory
- **Concatenating user input into SQL:** SQL injection vulnerability - always use `$wpdb->prepare()`
- **Forgetting access control in custom queries:** Custom queries bypass `pre_get_posts` hook - must call `is_user_approved()` explicitly
- **Adding too many meta JOINs:** Each JOIN adds query complexity - limit to 3-4 meta fields max (per STATE.md known risks)
- **Using invalidateQueries() after mutations:** Use `resetQueries()` to clear all cached pages (per STATE.md known risks)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| SQL escaping | Manual escaping | `$wpdb->prepare()` | Handles all data types, prevents injection |
| Access control | Custom permission logic | Existing `\Stadion\Core\AccessControl::is_user_approved()` | Already implemented and tested |
| Permission callbacks | Custom auth checks | `\Stadion\REST\Base::check_user_approved()` | Existing base class method |
| Response formatting | Custom JSON encoding | `rest_ensure_response()` | WordPress standard, handles headers |
| Parameter validation | Manual checks | REST API args validation callbacks | Declarative, tested, automatic |
| Post meta retrieval | Direct SQL queries | `get_field()` for ACF fields | Handles serialization, caching |
| Taxonomy queries | Manual term JOINs | `wp_get_post_terms()` for output formatting | Cached, handles term hierarchy |

**Key insight:** WordPress core provides `$wpdb->prepare()` for SQL escaping, but does NOT provide safe column name escaping - you must whitelist column/table names yourself. Also, ACF fields are stored in `wp_postmeta` as serialized values, so JOIN queries should fetch meta_value, then use `get_field()` if deserialization is needed (though first_name/last_name are plain text).

## Common Pitfalls

### Pitfall 1: Access Control Bypass
**What goes wrong:** Custom $wpdb queries return data to unapproved users
**Why it happens:** Custom queries bypass `pre_get_posts` hook that normally filters by author
**How to avoid:** Call `is_user_approved()` at start of endpoint callback, return empty results if false
**Warning signs:** Unapproved users can access data via custom endpoint but not native `/wp/v2/people`

### Pitfall 2: SQL Injection via Column Names
**What goes wrong:** Using `$wpdb->prepare()` for column names doesn't work, leads to injection
**Why it happens:** `prepare()` only escapes values, not identifiers (table/column names)
**How to avoid:** Whitelist all column names via `in_array()` check against allowed values
**Warning signs:** `$wpdb->prepare("ORDER BY %s", $orderby)` doesn't escape the column name
**Example:**
```php
// WRONG: prepare() doesn't work for column names
$orderby = $request->get_param('orderby');
$sql = $wpdb->prepare("ORDER BY %s", $orderby); // Still vulnerable!

// CORRECT: Whitelist column names
$allowed = ['first_name', 'last_name', 'modified'];
if (!in_array($orderby, $allowed, true)) {
    return new \WP_Error('invalid_orderby', 'Invalid sort field');
}
$sql = "ORDER BY {$orderby}"; // Safe - validated against whitelist
```

### Pitfall 3: N+1 Queries for Meta Fields
**What goes wrong:** Fetching people IDs in main query, then calling `get_field()` in loop
**Why it happens:** Not using JOINs to fetch meta values in single query
**How to avoid:** JOIN on `wp_postmeta` for frequently accessed fields (first_name, last_name)
**Warning signs:** Query Monitor shows 100+ `get_field()` calls when loading 100 people

### Pitfall 4: Too Many Meta JOINs
**What goes wrong:** Query becomes slow with 4+ meta JOINs
**Why it happens:** Each LEFT JOIN adds rows to result set, MySQL struggles with complex JOINs
**How to avoid:** Limit JOINs to essential fields (first_name, last_name), fetch other fields post-query
**Warning signs:** Query time >0.1s, MySQL EXPLAIN shows filesort/temporary tables
**State reference:** STATE.md known risks - "JOIN performance degradation with 4+ meta filters"

### Pitfall 5: Taxonomy JOIN Without DISTINCT
**What goes wrong:** People with multiple labels appear multiple times in results
**Why it happens:** INNER JOIN on term_relationships creates one row per term
**How to avoid:** Use `SELECT DISTINCT p.ID` when filtering by taxonomies
**Warning signs:** Same person appears multiple times in results, total count is wrong

### Pitfall 6: TanStack Query Stale Data
**What goes wrong:** After creating/updating/deleting person, list shows stale data
**Why it happens:** Using `invalidateQueries()` which only marks as stale, doesn't clear cache
**How to avoid:** Use `resetQueries()` instead to immediately clear all cached pages
**Warning signs:** Creating person doesn't show in list until manual refresh
**State reference:** STATE.md known risks - "TanStack Query stale data after mutations"

## Performance Considerations

### Query Optimization Strategy
1. **Start with essential JOINs only:** first_name, last_name (needed for display and sorting)
2. **Add indexes if needed:** Monitor with Query Monitor, add composite index on (post_type, post_status, post_modified) if slow
3. **Use STRAIGHT_JOIN hint if needed:** Forces MySQL to use join order you specify (per STATE.md implementation notes)
4. **Cache expensive queries:** Add transient caching (5 min TTL) if queries exceed 0.1s (per STATE.md implementation notes)
5. **Test with production data:** Performance differs between 100 and 1400+ records

### Expected Query Performance
| Records | Meta JOINs | Expected Time | Action if Slower |
|---------|------------|---------------|------------------|
| 100 | 2 (first_name, last_name) | <0.05s | None needed |
| 500 | 2 | <0.08s | Monitor |
| 1400+ | 2 | <0.1s | Add composite index if needed |
| 1400+ | 4+ | >0.1s | Reduce JOINs or add caching |

### Caching Strategy (Optional - Add If Needed)
```php
// Source: STATE.md implementation notes
// Only add if queries exceed 0.1s with production data

$cache_key = 'people_filtered_' . md5(serialize([
    'page'     => $page,
    'labels'   => $labels,
    'orderby'  => $orderby,
    'order'    => $order,
]));

$cached = get_transient($cache_key);
if ($cached !== false) {
    return rest_ensure_response($cached);
}

// Execute query...
$result = [
    'people'      => $people,
    'total'       => $total,
    'page'        => $page,
    'total_pages' => $total_pages,
];

// Cache for 5 minutes
set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);

return rest_ensure_response($result);
```

### Cache Invalidation
If caching is added, invalidate on person create/update/delete:
```php
// In person create/update/delete hooks
delete_transient_like('people_filtered_*'); // Wildcard delete helper (need to implement)
```

## Integration Points

### Frontend Integration
Current `usePeople` hook (src/hooks/usePeople.js line 49-84):
- Fetches all people in loop with `while(true)` pagination
- Transforms data with `transformPerson()`
- Uses TanStack Query for caching

**Required changes:**
1. Replace loop with single request to `/rondo/v1/people/filtered`
2. Add filter/sort parameters to query key
3. Use `resetQueries()` in mutations (not `invalidateQueries()`)

**New hook signature:**
```javascript
// Updated usePeople hook
export function usePeople(filters = {}) {
  return useQuery({
    queryKey: peopleKeys.list(filters),
    queryFn: async () => {
      const response = await prmApi.getFilteredPeople({
        page: filters.page || 1,
        per_page: filters.perPage || 100,
        labels: filters.labels || [],
        ownership: filters.ownership || 'all',
        modified_days: filters.modifiedDays || null,
        orderby: filters.orderby || 'first_name',
        order: filters.order || 'asc',
      });

      // Transform response (already in correct format from endpoint)
      return response.data;
    },
  });
}
```

### Existing REST Infrastructure
- Base class: `\Stadion\REST\Base` (includes/class-rest-base.php)
- Access control: `\Stadion\Core\AccessControl` (includes/class-access-control.php)
- REST API namespace: `rondo/v1` (already established)

**Where to add endpoint:**
Option 1: Create new `includes/class-rest-people.php` extending `\Stadion\REST\Base`
Option 2: Add to existing `includes/class-rest-api.php` (line 1963 - file is large but manageable)

**Recommendation:** Create new `class-rest-people.php` to separate concerns, similar to existing `class-rest-calendar.php`, `class-rest-teams.php`, etc.

## Database Schema Reference

### Relevant Tables
```sql
-- wp_posts: Person records
CREATE TABLE wp_posts (
  ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_author bigint(20) unsigned NOT NULL DEFAULT '0',
  post_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  post_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  post_status varchar(20) NOT NULL DEFAULT 'publish',
  post_type varchar(20) NOT NULL DEFAULT 'post',
  post_title text NOT NULL,
  PRIMARY KEY (ID),
  KEY type_status_date (post_type, post_status, post_date, ID),
  KEY post_author (post_author)
);

-- wp_postmeta: ACF field values
CREATE TABLE wp_postmeta (
  meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL DEFAULT '0',
  meta_key varchar(255) DEFAULT NULL,
  meta_value longtext,
  PRIMARY KEY (meta_id),
  KEY post_id (post_id),
  KEY meta_key (meta_key(191))
);

-- wp_term_relationships: Person-Label associations
CREATE TABLE wp_term_relationships (
  object_id bigint(20) unsigned NOT NULL DEFAULT '0',
  term_taxonomy_id bigint(20) unsigned NOT NULL DEFAULT '0',
  term_order int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (object_id, term_taxonomy_id),
  KEY term_taxonomy_id (term_taxonomy_id)
);

-- wp_term_taxonomy: Taxonomy definitions
CREATE TABLE wp_term_taxonomy (
  term_taxonomy_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  term_id bigint(20) unsigned NOT NULL DEFAULT '0',
  taxonomy varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (term_taxonomy_id),
  KEY taxonomy (taxonomy)
);
```

### ACF Meta Keys Used
- `first_name`: Text field (plain text, not serialized)
- `last_name`: Text field (plain text, not serialized)

### Composite Index (Add If Needed)
```sql
-- If queries are slow, add composite index
ALTER TABLE wp_posts
ADD KEY type_status_modified (post_type, post_status, post_modified);
```

## Testing Strategy

### Manual Testing Checklist
1. **Access Control:**
   - Unapproved user gets empty results
   - Approved user gets all people (ownership=all)
   - User gets only their people (ownership=mine)
   - User gets only shared people (ownership=shared)

2. **Filtering:**
   - Filter by single label returns correct people
   - Filter by multiple labels (OR logic) returns correct people
   - Filter by modified date (last 7/30/90 days) returns correct people
   - Combined filters work correctly

3. **Sorting:**
   - Sort by first_name ASC/DESC works
   - Sort by last_name ASC/DESC works
   - Sort by modified ASC/DESC works
   - Null values handled correctly (people without first_name/last_name)

4. **Pagination:**
   - Page 1 returns first 100 people
   - Page 2 returns next 100 people
   - Last page returns remaining people (<100)
   - Total count is correct across pages

5. **SQL Injection:**
   - Try SQL in labels parameter: `labels=['1 OR 1=1']` - should fail validation
   - Try SQL in orderby parameter: `orderby='modified; DROP TABLE wp_posts'` - should fail validation

6. **Performance:**
   - Query executes in <0.1s with 1400+ records (use Query Monitor)
   - No N+1 queries (should see single SELECT query in Query Monitor)

### Integration Testing
1. Create test endpoint in React frontend
2. Verify TanStack Query caching works
3. Test filter combinations
4. Test pagination UI
5. Test mutation + resetQueries() clears cache

## Implementation Checklist

- [ ] Create `includes/class-rest-people.php` extending `\Stadion\REST\Base`
- [ ] Register `/rondo/v1/people/filtered` endpoint with parameter validation
- [ ] Implement `get_filtered_people()` callback with access control check
- [ ] Build base SQL query with LEFT JOINs for first_name, last_name meta
- [ ] Add conditional WHERE clauses for ownership filter
- [ ] Add conditional WHERE clauses for modified_days filter
- [ ] Add conditional JOIN/WHERE for labels filter (taxonomy)
- [ ] Add ORDER BY clause with whitelist validation
- [ ] Add LIMIT/OFFSET for pagination
- [ ] Build count query (same JOINs/WHERE, no ORDER/LIMIT)
- [ ] Format results with thumbnail and labels (post-query)
- [ ] Test with Query Monitor - verify single query, check execution time
- [ ] Test access control (unapproved user, mine/shared/all)
- [ ] Test all filter combinations
- [ ] Test SQL injection attempts (validate all parameters)
- [ ] Update frontend `usePeople` hook to use new endpoint
- [ ] Update mutations to use `resetQueries()` instead of `invalidateQueries()`
- [ ] Test production performance with 1400+ records
- [ ] Add composite index if needed (post_type, post_status, post_modified)
- [ ] Add transient caching if queries exceed 0.1s (optional)

## Questions for Planning

1. **New file vs existing file:** Create new `class-rest-people.php` or add to existing `class-rest-api.php` (1963 lines)?
   - **Recommendation:** New file for better separation (follows pattern of class-rest-calendar.php, class-rest-teams.php)

2. **Thumbnail performance:** Fetch thumbnail URL in main query via JOIN, or post-query with `get_the_post_thumbnail_url()`?
   - **Recommendation:** Post-query - thumbnail URL requires attachment lookup which adds complexity to JOIN

3. **Labels performance:** Fetch labels in main query via JOIN, or post-query with `wp_get_post_terms()`?
   - **Recommendation:** Post-query - term names are cached by WordPress, JOIN would duplicate rows

4. **Caching strategy:** Start with caching or add only if performance issues?
   - **Recommendation:** Start without caching, add 5-min transient cache if queries exceed 0.1s

5. **Response format:** Match existing `/wp/v2/people` format or custom format?
   - **Recommendation:** Custom format optimized for list view (no need for full ACF data, _embedded, etc.)

## Open Questions

1. Should we fetch all ACF fields in the query, or just first_name/last_name?
   - **Likely answer:** Just name fields - other fields can be fetched when opening person detail

2. Should we add birth_year filter in this phase (requires denormalization)?
   - **Likely answer:** No - Phase 112 handles birth_year denormalization

3. Should we support custom ACF field sorting in this phase?
   - **Likely answer:** No - Phase 113 (DATA-09) handles custom field sorting

4. Should pagination be cursor-based or offset-based?
   - **Likely answer:** Offset-based (page numbers) - matches requirements and is simpler

5. What should happen if labels filter includes deleted term IDs?
   - **Likely answer:** Validate term IDs exist before building query, ignore invalid IDs

## References

**Codebase:**
- Existing $wpdb pattern: `includes/class-rest-api.php` line 1590-1605 (`get_recently_contacted_people()`)
- Access control check: `includes/class-access-control.php` line 46-61 (`is_user_approved()`)
- REST base class: `includes/class-rest-base.php` (permission callbacks, response formatting)
- Current frontend hook: `src/hooks/usePeople.js` line 49-84 (loop pagination pattern)
- TanStack Query keys: `src/hooks/usePeople.js` line 8-17 (peopleKeys structure)

**WordPress Documentation:**
- wpdb class: https://developer.wordpress.org/reference/classes/wpdb/
- $wpdb->prepare(): https://developer.wordpress.org/reference/classes/wpdb/prepare/
- REST API: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

**State References:**
- Known risks: `.planning/STATE.md` line 60-64
- Implementation notes: `.planning/ROADMAP.md` (Backend Patterns section - not yet created, from context)

**ACF Documentation:**
- get_field(): https://www.advancedcustomfields.com/resources/get_field/
- Meta storage: ACF stores simple fields as plain text in wp_postmeta.meta_value
