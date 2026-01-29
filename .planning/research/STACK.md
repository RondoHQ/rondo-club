# Technology Stack - People List Performance Features

**Project:** Stadion People List Infinite Scroll
**Researched:** 2026-01-29
**Confidence:** HIGH

## Executive Summary

No new dependencies required. All necessary technologies are already present in Stadion's stack. Implementation requires only pattern changes using existing TanStack Query v5.17.0, native Intersection Observer API, and WordPress core APIs (user_meta, wpdb, REST).

## Core Stack (Already Present)

| Technology | Version | Purpose | Notes |
|------------|---------|---------|-------|
| TanStack Query | ^5.17.0 | Server state management | Already includes `useInfiniteQuery` for pagination |
| React | ^18.2.0 | UI framework | Already supports Intersection Observer via refs |
| WordPress REST API | Core | Backend pagination | Built-in `per_page`, `page` params + headers |
| WordPress User Meta API | Core | Per-user preferences | Native `get_user_meta`, `update_user_meta` |
| WordPress $wpdb | Core | Custom JOIN queries | Global `$wpdb` object for performant queries |

## No New Dependencies Required

**CRITICAL FINDING:** The project already has everything needed:
- TanStack Query v5.17.0 includes `useInfiniteQuery` (introduced in v4, stable in v5)
- Intersection Observer API is native to all modern browsers (no polyfill needed)
- WordPress core provides user_meta and wpdb without plugins

## Implementation Patterns

### 1. TanStack Query useInfiniteQuery Pattern

**Current implementation (usePeople.js lines 49-84):** Loads ALL people in a while loop, client-side filtering.

**New pattern:**
```javascript
export function usePeopleInfinite(filters = {}) {
  return useInfiniteQuery({
    queryKey: peopleKeys.list(filters),
    queryFn: async ({ pageParam = 1 }) => {
      const response = await wpApi.getPeople({
        per_page: 50, // Server-side pagination
        page: pageParam,
        _embed: true,
        ...filters, // Pass filters to server
      });

      return {
        people: response.data.map(transformPerson),
        nextPage: pageParam + 1,
        totalPages: parseInt(response.headers['x-wp-totalpages'] || '1', 10),
      };
    },
    initialPageParam: 1, // REQUIRED in v5
    getNextPageParam: (lastPage) => {
      // Return undefined when no more pages (stops fetching)
      return lastPage.nextPage <= lastPage.totalPages
        ? lastPage.nextPage
        : undefined;
    },
    getPreviousPageParam: (firstPage) => {
      return firstPage.page > 1 ? firstPage.page - 1 : undefined;
    },
    maxPages: 10, // NEW in v5: Limit memory usage
  });
}
```

**Why this pattern:**
- `initialPageParam` is REQUIRED in v5 (breaking change from v4)
- `maxPages` prevents infinite memory growth (limits stored pages to 10)
- `getNextPageParam` returning `undefined` signals end of data
- Data structure: `data.pages` array, each page contains `people` array
- Access via: `data.pages.flatMap(page => page.people)`

**Source:** [TanStack Query v5 useInfiniteQuery Reference](https://tanstack.com/query/v5/docs/framework/react/reference/useInfiniteQuery)

### 2. Intersection Observer for Scroll Detection

**Pattern (no libraries needed):**
```javascript
import { useEffect, useRef } from 'react';

function useInfiniteScroll(callback, hasMore, isFetching) {
  const observerTarget = useRef(null);

  useEffect(() => {
    if (!hasMore || isFetching) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          callback();
        }
      },
      { threshold: 0.1 } // Trigger when 10% visible
    );

    if (observerTarget.current) {
      observer.observe(observerTarget.current);
    }

    return () => observer.disconnect();
  }, [callback, hasMore, isFetching]);

  return observerTarget;
}

// Usage in component:
const { data, fetchNextPage, hasNextPage, isFetchingNextPage } = usePeopleInfinite();
const loadMoreRef = useInfiniteScroll(fetchNextPage, hasNextPage, isFetchingNextPage);

// JSX:
<div ref={loadMoreRef} className="h-10" />
```

**Why native Intersection Observer:**
- No external dependencies (87% browser support, all modern browsers)
- More performant than scroll event listeners
- Automatic viewport detection
- Built-in threshold control

**Sources:**
- [MDN Intersection Observer API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API)
- [Infinite Scroll in React with Intersection Observer](https://dev.to/matan3sh/infinite-scroll-in-react-with-intersection-observer-3932)

### 3. WordPress wpdb JOIN Query Pattern

**Current problem:** Multiple meta_query joins on wp_postmeta cause exponential performance degradation.

**Research finding:** ACF stores relationships as serialized arrays in post_meta. Querying across work_history (repeater field) requires JOINing wp_postmeta multiple times, which is a known WordPress performance killer.

**Recommended pattern (server-side PHP):**
```php
// In custom REST endpoint: /stadion/v1/people
global $wpdb;

// Single JOIN to wp_postmeta for work_history
// Conditional aggregation instead of multiple JOINs
$query = "
  SELECT DISTINCT p.ID, p.post_title, p.post_modified,
    MAX(CASE WHEN pm.meta_key = 'first_name' THEN pm.meta_value END) as first_name,
    MAX(CASE WHEN pm.meta_key = 'last_name' THEN pm.meta_value END) as last_name,
    MAX(CASE WHEN pm.meta_key = 'work_history' THEN pm.meta_value END) as work_history_serialized
  FROM {$wpdb->posts} p
  LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
  WHERE p.post_type = 'person'
    AND p.post_status = 'publish'
  GROUP BY p.ID
  ORDER BY %s %s
  LIMIT %d OFFSET %d
";

$prepared = $wpdb->prepare($query, $orderby, $order, $per_page, $offset);
$results = $wpdb->get_results($prepared);

// Post-process: Unserialize work_history, extract current team
foreach ($results as &$row) {
  $work_history = maybe_unserialize($row->work_history_serialized);
  $row->current_team_id = extract_current_team($work_history);
}
```

**Why this pattern:**
- Single LEFT JOIN instead of multiple meta_query JOINs
- Conditional aggregation (CASE) pivots meta rows into columns
- Post-processing in PHP for complex ACF repeater logic
- Reduces query time from 4s to <1s (per research findings)

**Critical optimization:** Use `LIMIT` and `OFFSET` for pagination at SQL level, not PHP.

**Sources:**
- [ACF WordPress Post Meta Query Performance Best Practices](https://www.advancedcustomfields.com/blog/wordpress-post-meta-query/)
- [Delicious Brains SQL Query Optimization](https://deliciousbrains.com/sql-query-optimization/)
- [WooCommerce Issue #27746: Double-left join performance killer](https://github.com/woocommerce/woocommerce/issues/27746)

### 4. WordPress User Meta for Column Preferences

**Pattern (already used in Stadion - see class-rest-api.php theme preferences):**

**Frontend (React):**
```javascript
// In custom hook: useColumnPreferences.js
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useColumnPreferences() {
  const queryClient = useQueryClient();

  return useQuery({
    queryKey: ['user-preferences', 'people-columns'],
    queryFn: async () => {
      const response = await prmApi.getUserPreference('people_list_columns');
      // Returns array of column keys: ['first_name', 'last_name', 'organization', 'labels']
      return response.data.columns || DEFAULT_COLUMNS;
    },
  });
}

export function useUpdateColumnPreferences() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (columns) =>
      prmApi.updateUserPreference('people_list_columns', { columns }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-preferences'] });
    },
  });
}
```

**Backend (PHP REST endpoint):**
```php
// In includes/class-rest-api.php (pattern already exists for theme_preferences)
register_rest_route('stadion/v1', '/user/list-preferences', [
  'methods' => 'GET',
  'callback' => function() {
    $user_id = get_current_user_id();
    $columns = get_user_meta($user_id, 'people_list_columns', true);

    return rest_ensure_response([
      'columns' => $columns ?: ['first_name', 'last_name', 'organization', 'labels']
    ]);
  },
]);

register_rest_route('stadion/v1', '/user/list-preferences', [
  'methods' => 'PATCH',
  'callback' => function($request) {
    $user_id = get_current_user_id();
    $columns = $request->get_param('columns');

    update_user_meta($user_id, 'people_list_columns', $columns);

    return rest_ensure_response(['success' => true]);
  },
]);
```

**Why user_meta:**
- Per-user storage (not global)
- Survives cache clears
- Native WordPress API (no custom tables)
- Already used in Stadion (theme_preferences, dashboard_settings)

**Sources:**
- [WordPress Working with User Metadata](https://developer.wordpress.org/plugins/users/working-with-user-metadata/)
- [update_user_meta() Function Reference](https://developer.wordpress.org/reference/functions/update_user_meta/)

### 5. WordPress REST API Pagination Headers

**WordPress native headers (already available):**
```javascript
// Response headers from wpApi.getPeople():
const totalRecords = response.headers['x-wp-total'];      // Total count of all records
const totalPages = response.headers['x-wp-totalpages'];   // Total pages for current per_page

// Standard query params (already working):
wpApi.getPeople({
  per_page: 50,  // Max 100 per WordPress core
  page: 2,       // Current page
  orderby: 'title', // 'title', 'date', 'modified', 'id'
  order: 'asc'   // 'asc' or 'desc'
});
```

**No custom implementation needed.** WordPress REST API handles pagination headers automatically for custom post types.

**Source:** [WordPress REST API Pagination Handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/)

## Server-Side Filtering Requirements

**New REST endpoint pattern:**
```php
// Extend wp/v2/people endpoint with meta_query support
add_filter('rest_person_query', function($args, $request) {
  // Server-side search filter
  if ($search = $request->get_param('search')) {
    $args['s'] = $search;
  }

  // Server-side label filter
  if ($labels = $request->get_param('person_label')) {
    $args['tax_query'] = [
      [
        'taxonomy' => 'person_label',
        'field' => 'term_id',
        'terms' => $labels,
      ]
    ];
  }

  // Server-side team filter (via work_history meta)
  // NOTE: This will still be slow with meta_query
  // Prefer custom wpdb endpoint for performance
  if ($team_id = $request->get_param('team_id')) {
    $args['meta_query'] = [
      [
        'key' => 'work_history',
        'value' => serialize(strval($team_id)),
        'compare' => 'LIKE'
      ]
    ];
  }

  return $args;
}, 10, 2);
```

**For complex filters (team, custom fields), use custom wpdb endpoint instead of extending wp/v2/people.**

## Performance Optimizations

### TanStack Query maxPages (NEW in v5)

**Problem:** Infinite scroll can consume infinite memory.
**Solution:** `maxPages` option limits stored pages.

```javascript
useInfiniteQuery({
  // ... other options
  maxPages: 10, // Only keep 10 pages in memory
});
```

When user scrolls past 10 pages, oldest pages are evicted from cache. Scrolling back up triggers refetch.

**Source:** [TanStack Query v5 Migration Guide](https://tanstack.com/query/v5/docs/react/guides/migrating-to-v5)

### WordPress Query Performance

**Best practices for custom wpdb queries:**
1. **Avoid multiple JOINs on wp_postmeta** - Use conditional aggregation (CASE statements)
2. **Use LIMIT/OFFSET at SQL level** - Not array_slice in PHP
3. **Add indexes** - `ALTER TABLE wp_postmeta ADD INDEX meta_key_value (meta_key, meta_value(20))`
4. **Set no_found_rows** - For WP_Query: `'no_found_rows' => true` (skips SQL_CALC_FOUND_ROWS)
5. **Use fields parameter** - Return only needed fields: `'fields' => 'ids'`

**Source:** [WordPress VIP WP_Query Performance](https://wpvip.com/blog/wp-query-performance/)

## Integration with Existing Stack

### Existing Patterns to Preserve

1. **Access Control (class-access-control.php):** Apply to new endpoint
2. **Nonce Authentication (api/client.js):** Already configured
3. **TanStack Query Keys (peopleKeys):** Extend with filter params
4. **Error Handling (axios interceptors):** Already handles 401/403

### Files to Modify

**Backend:**
- `includes/class-rest-api.php` - Add `/stadion/v1/people` endpoint with wpdb query
- `includes/class-rest-api.php` - Add `/stadion/v1/user/list-preferences` endpoints

**Frontend:**
- `src/hooks/usePeople.js` - Add `usePeopleInfinite` hook
- `src/hooks/` - Add `useColumnPreferences.js`
- `src/pages/People/PeopleList.jsx` - Replace table rendering with infinite scroll
- `src/api/client.js` - Add `prmApi.getUserPreference`, `prmApi.updateUserPreference`

## Libraries NOT Needed

**Confirmed NOT required (research findings):**
- ❌ `react-intersection-observer` - Native API is sufficient
- ❌ `react-infinite-scroll-component` - TanStack Query handles state
- ❌ `react-virtualized` / `react-window` - Not needed for 1400 records with pagination
- ❌ ACF custom table addon - Standard ACF + wpdb optimization is sufficient

## Version Compatibility

| Component | Version | Compatibility |
|-----------|---------|---------------|
| TanStack Query | 5.17.0 | ✅ v5.0+ supports useInfiniteQuery with maxPages |
| React | 18.2.0 | ✅ useEffect, useRef support Intersection Observer |
| WordPress | 6.0+ | ✅ REST API pagination headers native |
| PHP | 8.0+ | ✅ $wpdb global available |
| Browsers | Modern | ✅ Intersection Observer 87% support (IE11 not supported) |

## Recommended Approach

### Phase 1: Backend Foundation
1. Create custom `/stadion/v1/people` endpoint with wpdb JOIN query
2. Implement server-side filtering (search, labels, team)
3. Add `/stadion/v1/user/list-preferences` endpoints for column storage
4. Add indexes to wp_postmeta if needed

### Phase 2: Frontend Integration
1. Create `usePeopleInfinite` hook with TanStack Query
2. Create `useColumnPreferences` hook for user settings
3. Build Intersection Observer custom hook
4. Migrate PeopleList.jsx to infinite scroll pattern

### Phase 3: Optimization
1. Add `maxPages` limit to prevent memory growth
2. Profile wpdb query performance with WP_Query debugging
3. Add loading states and error boundaries
4. Implement optimistic updates for column preferences

## Sources

**TanStack Query:**
- [Infinite Queries Guide](https://tanstack.com/query/v5/docs/react/guides/infinite-queries)
- [useInfiniteQuery Reference](https://tanstack.com/query/v5/docs/framework/react/reference/useInfiniteQuery)
- [TanStack Query v5 useInfiniteQuery Discussion #5921](https://github.com/TanStack/query/discussions/5921)

**WordPress Performance:**
- [ACF Post Meta Query Performance](https://www.advancedcustomfields.com/blog/wordpress-post-meta-query/)
- [Delicious Brains SQL Query Optimization](https://deliciousbrains.com/sql-query-optimization/)
- [WordPress VIP WP_Query Performance](https://wpvip.com/blog/wp-query-performance/)
- [WooCommerce Issue #27746: Double-left join performance](https://github.com/woocommerce/woocommerce/issues/27746)

**Intersection Observer:**
- [MDN Intersection Observer API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API)
- [FreeCodeCamp: Infinite Scrolling in React](https://www.freecodecamp.org/news/infinite-scrolling-in-react/)
- [DEV: Infinite Scroll with Intersection Observer](https://dev.to/matan3sh/infinite-scroll-in-react-with-intersection-observer-3932)

**WordPress APIs:**
- [WordPress REST API Pagination](https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/)
- [WordPress User Metadata](https://developer.wordpress.org/plugins/users/working-with-user-metadata/)
- [update_user_meta() Function](https://developer.wordpress.org/reference/functions/update_user_meta/)
- [get_user_meta() Function](https://developer.wordpress.org/reference/functions/get_user_meta/)
