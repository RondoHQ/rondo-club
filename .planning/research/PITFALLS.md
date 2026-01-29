# Domain Pitfalls: Adding Infinite Scroll & Server-Side Filtering to WordPress/React

**Domain:** WordPress + React CRM with existing access control and ACF fields
**Researched:** 2026-01-29
**Context:** Subsequent milestone adding infinite scroll, $wpdb JOINs, and column preferences to existing People list

## Critical Pitfalls

Mistakes that cause rewrites, security vulnerabilities, or major performance issues.

### Pitfall 1: Access Control Bypass with Custom $wpdb Queries

**What goes wrong:** Custom $wpdb queries bypass WordPress's `pre_get_posts` filter, which is how `STADION_Access_Control` enforces user approval checks. Direct SQL queries return ALL records, exposing unapproved users' data or bypassing row-level security.

**Why it happens:** Developers focus on performance optimization with $wpdb JOINs and forget that access control filters only work with `WP_Query`. The existing `filter_queries()` method in `class-access-control.php` explicitly checks for `suppress_filters` but $wpdb queries don't trigger these hooks at all.

**Consequences:**
- Unapproved users see all CRM data (security breach)
- Access control becomes inconsistent across endpoints
- Audit/compliance violations

**Prevention:**
1. **Always add access control checks BEFORE running custom queries:**
   ```php
   $access_control = new \STADION_Access_Control();
   if (!$access_control->is_user_approved()) {
       return []; // or WP_Error
   }
   ```

2. **Add post_author or post__in filters to WHERE clause:**
   ```php
   // In $wpdb query building
   if (!current_user_can('manage_options')) {
       // Add WHERE p.post_author IN (...approved_user_ids)
       // OR use post__in array if needed
   }
   ```

3. **Create reusable method in AccessControl class:**
   ```php
   public function get_sql_where_clause($table_alias = 'p') {
       if (!$this->is_user_approved()) {
           return "{$table_alias}.ID = 0"; // Returns nothing
       }
       // All approved users see everything
       return "1=1";
   }
   ```

**Detection:**
- Search for `global $wpdb` in new code
- Check if `$wpdb->prepare()` queries include access control logic
- Test with unapproved user account
- Review security test coverage for custom endpoints

**Phase impact:** Phase 2 (Server-side filtering endpoint) must address this before implementation.

**References:**
- [WordPress Modular DS Plugin CVE-2026-23550](https://thehackernews.com/2026/01/critical-wordpress-modular-ds-plugin.html) - Recent example of authentication bypass in custom routes

### Pitfall 2: SQL Injection via Unsanitized Filter Parameters

**What goes wrong:** Filter values (labels, teams, custom fields) are inserted directly into SQL queries without proper escaping. ACF field names are particularly dangerous as they come from user configuration and may contain special characters.

**Why it happens:**
- Developers trust filter values as "internal" data (taxonomies, post IDs)
- ACF field names look safe but can contain underscores, dashes that break SQL
- `$wpdb->prepare()` placeholders (%s, %d) don't work for dynamic table/column names
- Meta key comparisons require LIKE for serialized ACF data, increasing injection surface

**Consequences:**
- SQL injection vulnerability allowing data exfiltration
- Database corruption or deletion
- Server compromise

**Prevention:**

1. **Always use $wpdb->prepare() with correct placeholders:**
   ```php
   // WRONG - direct interpolation
   $query = "WHERE meta_key = '$field_name'";

   // CORRECT - prepared statement
   $query = $wpdb->prepare("WHERE meta_key = %s", $field_name);
   ```

2. **Whitelist ACF field names:**
   ```php
   // Get allowed field names from ACF configuration
   $allowed_fields = array_map(function($field) {
       return $field['name'];
   }, acf_get_fields('group_person_fields'));

   if (!in_array($field_name, $allowed_fields, true)) {
       return new WP_Error('invalid_field');
   }
   ```

3. **Sanitize filter arrays:**
   ```php
   // For label IDs (taxonomy terms)
   $label_ids = array_map('absint', $filter_params['labels']);

   // For custom field values
   $field_value = sanitize_text_field($filter_params['field_value']);
   ```

4. **Never build dynamic column/table names from user input:**
   ```php
   // WRONG - meta_key from user input
   $query = "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '{$_GET['field']}'";

   // CORRECT - whitelist validation first
   if (!in_array($field_name, $safe_fields)) {
       return new WP_Error('invalid_field');
   }
   $query = $wpdb->prepare(
       "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s",
       $field_name
   );
   ```

**Detection:**
- Code review: Search for `$wpdb->query()`, `$wpdb->get_results()` without `prepare()`
- Check for string concatenation in SQL: `"WHERE " . $var`
- Test with SQL injection payloads: `' OR '1'='1`, `'; DROP TABLE--`
- Use tools like Psalm/PHPStan with security rulesets

**Phase impact:** Phase 2 (Server-side filtering) - MUST validate all filter inputs before query building.

**References:**
- [WordPress SQL Injection Prevention Guide](https://patchstack.com/articles/sql-injection/)
- [Malcure 2026 Prevention Guide](https://malcure.com/blog/malware-removal-guides/how-to-prevent-wordpress-sql-injection-attacks/)

### Pitfall 3: Post_Meta JOIN Performance Degradation at Scale

**What goes wrong:** Each filter condition on ACF fields adds another LEFT JOIN to `wp_postmeta`. With 3-4 filters active, queries slow from 50ms to 2-5 seconds. MySQL query optimizer makes poor choices with multiple meta JOINs, especially without proper indexing.

**Why it happens:**
- `wp_postmeta` is a generic EAV table (post_id, meta_key, meta_value)
- Each ACF field requires a separate JOIN to match meta_key
- MySQL can't use indexes effectively with multiple LEFT JOINs
- ACF stores complex data serialized, requiring LIKE comparisons
- No composite indexes on (post_id, meta_key) by default

**Consequences:**
- People list becomes unusable with >5,000 records
- Server CPU spikes during peak usage
- Database locks causing timeout errors
- Poor user experience, users stop filtering

**Prevention:**

1. **Use STRAIGHT_JOIN hint for complex meta queries:**
   ```php
   // Force MySQL to use joins in order specified
   $query = "SELECT STRAIGHT_JOIN p.ID FROM {$wpdb->posts} p";
   ```

2. **Add composite index on postmeta:**
   ```php
   // In activation/migration
   $wpdb->query(
       "CREATE INDEX idx_meta_key_value ON {$wpdb->postmeta} (post_id, meta_key, meta_value(191))"
   );
   ```

3. **Limit JOINs to 3-4 maximum:**
   ```php
   // If more than 4 filters, fall back to two-step query:
   if (count($filters) > 4) {
       // Step 1: Get matching IDs with WP_Query (cached)
       // Step 2: Filter those IDs with fewer JOINs
   }
   ```

4. **Cache filter results aggressively:**
   ```php
   $cache_key = 'people_filtered_' . md5(json_encode($filters));
   $cached = get_transient($cache_key);
   if ($cached !== false) {
       return $cached;
   }
   // ... run query ...
   set_transient($cache_key, $results, 5 * MINUTE_IN_SECONDS);
   ```

5. **Consider materialized view for common filters:**
   ```php
   // Store current_team in wp_posts meta (duplicated but indexed)
   // Update on work_history changes
   update_post_meta($person_id, '_current_team_id', $team_id);

   // Query directly instead of JOIN
   $query .= $wpdb->prepare(" AND pm.meta_key = '_current_team_id' AND pm.meta_value = %d", $team_id);
   ```

**Detection:**
- Monitor query times with Query Monitor plugin
- Check `EXPLAIN` output for queries with >3 JOINs
- Load test with 10,000+ people records
- Watch for "Using temporary; Using filesort" in EXPLAIN

**Phase impact:** Phase 3 (Advanced filtering) - Must implement caching and JOIN limits.

**References:**
- [ACF Post Meta Query Performance Best Practices](https://www.advancedcustomfields.com/blog/wordpress-post-meta-query/)
- [WordPress Core Ticket #20134](https://core.trac.wordpress.org/ticket/20134) - Complex meta query performance issues

### Pitfall 4: TanStack Query Stale Data After Mutations

**What goes wrong:** User creates/edits a person, returns to list, sees old data. Filter changes don't trigger refetch. Cache becomes permanently stale after invalidation because `useInfiniteQuery` doesn't refetch all pages when inactive.

**Why it happens:**
- `useInfiniteQuery` with `staleTime: Infinity` never refetches
- `queryClient.invalidateQueries()` only marks as stale, doesn't trigger refetch for inactive queries
- Filter changes modify `queryKey` but old key remains in cache
- On page navigation back, stale data loads instantly (appears fast but wrong)

**Consequences:**
- Users see outdated information
- Confusion when edits "don't save"
- Loss of trust in application
- Support requests for "broken" features

**Prevention:**

1. **Invalidate AND refetch on mutations:**
   ```javascript
   // WRONG - only invalidates
   queryClient.invalidateQueries({ queryKey: ['people'] });

   // CORRECT - invalidate and refetch active queries
   await queryClient.invalidateQueries({
       queryKey: ['people'],
       refetchType: 'active'
   });
   ```

2. **Use shorter staleTime for infinite queries:**
   ```javascript
   const { data, fetchNextPage } = useInfiniteQuery({
       queryKey: ['people', filters],
       queryFn: fetchPeoplePage,
       staleTime: 30 * 1000, // 30 seconds, not Infinity
       gcTime: 5 * 60 * 1000,  // Keep in cache 5 minutes
   });
   ```

3. **Refetch on filter changes with reset:**
   ```javascript
   useEffect(() => {
       // When filters change, refetch from page 1
       queryClient.resetQueries({ queryKey: ['people', filters] });
   }, [filters, queryClient]);
   ```

4. **Implement optimistic updates for create/edit:**
   ```javascript
   const createMutation = useMutation({
       mutationFn: createPerson,
       onMutate: async (newPerson) => {
           // Cancel outgoing refetches
           await queryClient.cancelQueries({ queryKey: ['people'] });

           // Optimistically update cache
           queryClient.setQueryData(['people', filters], (old) => {
               return {
                   ...old,
                   pages: [
                       { data: [newPerson, ...old.pages[0].data], hasMore: true },
                       ...old.pages.slice(1)
                   ]
               };
           });
       },
       onSettled: () => {
           // Refetch to ensure consistency
           queryClient.invalidateQueries({ queryKey: ['people'] });
       }
   });
   ```

5. **Reset query on mount if data might be stale:**
   ```javascript
   useEffect(() => {
       // Refetch when returning to list page
       queryClient.refetchQueries({
           queryKey: ['people'],
           type: 'active'
       });
   }, []); // Only on mount
   ```

**Detection:**
- Manual testing: Create person → Navigate away → Return → Check if new person appears
- Check React DevTools TanStack Query tab for stale queries
- Add logging to mutation `onSuccess` handlers
- Monitor refetch behavior in network tab

**Phase impact:** Phase 1 (Infinite scroll) AND Phase 2 (Filters) - Cache strategy must be correct from start.

**References:**
- [TanStack Query Issue #5648](https://github.com/TanStack/query/issues/5648) - Programmatic invalidation issues
- [Query Invalidation Docs](https://tanstack.com/query/latest/docs/framework/react/guides/query-invalidation)

## Moderate Pitfalls

Mistakes that cause delays, technical debt, or user confusion.

### Pitfall 5: Race Conditions with Rapid Filter Changes

**What goes wrong:** User changes filter dropdown rapidly (Team A → Team B → Team C). Three API requests fire. Response C arrives first, then B, then A. UI shows results for Team A (wrong).

**Why it happens:**
- Network responses don't arrive in request order
- `useInfiniteQuery` doesn't cancel previous queries automatically
- React state updates from old requests overwrite new ones
- No request cancellation tokens

**Consequences:**
- Wrong data displayed when filtering quickly
- User sees "Team B" selected but "Team A" results
- Intermittent bugs that are hard to reproduce
- Users learn to "wait" after filtering (bad UX)

**Prevention:**

1. **Use TanStack Query's automatic request cancellation:**
   ```javascript
   const fetchPeople = async ({ queryKey, pageParam, signal }) => {
       const [_key, filters] = queryKey;
       const response = await axios.get('/wp-json/stadion/v1/people-filtered', {
           params: { ...filters, page: pageParam },
           signal // Pass AbortSignal to axios
       });
       return response.data;
   };
   ```

2. **Debounce filter changes:**
   ```javascript
   import { useDebouncedValue } from '@/hooks/useDebouncedValue';

   const [filterInput, setFilterInput] = useState({});
   const debouncedFilters = useDebouncedValue(filterInput, 300); // 300ms delay

   const { data } = useInfiniteQuery({
       queryKey: ['people', debouncedFilters],
       // ...
   });
   ```

3. **Show loading state during filter changes:**
   ```javascript
   const isRefetching = isFetching && !isFetchingNextPage;

   {isRefetching && (
       <div className="absolute inset-0 bg-white/50 flex items-center justify-center">
           <LoadingSpinner />
       </div>
   )}
   ```

**Detection:**
- Test by rapidly changing filters
- Check Network tab for overlapping requests
- Add request IDs to logging
- Monitor for "Request aborted" errors (expected/good)

**Phase impact:** Phase 2 (Filters) - Must implement debouncing and cancellation.

**References:**
- [DEV Community: Infinite Scroll Race Conditions](https://dev.to/pipipi-dev/infinite-scroll-with-zustand-and-react-19-async-pitfalls-57c) (2025)

### Pitfall 6: Memory Leaks with Large Result Sets

**What goes wrong:** Fetching 10,000 people records with `posts_per_page: -1` consumes 500MB+ memory. `$wpdb->get_results()` loads entire result set into memory. With multiple concurrent users, server runs out of memory.

**Why it happens:**
- `$wpdb->get_results()` stores full result array in `$wpdb->last_result`
- WordPress doesn't stream large result sets
- ACF field loading for each person multiplies memory usage
- No pagination or limit on initial data fetch

**Consequences:**
- PHP "Allowed memory size exhausted" errors
- 502 Bad Gateway responses
- Server crashes requiring restart
- Can't scale beyond 5-10 concurrent users

**Prevention:**

1. **Always use pagination, never fetch all:**
   ```php
   // WRONG
   $people = get_posts(['post_type' => 'person', 'posts_per_page' => -1]);

   // CORRECT
   $people = get_posts([
       'post_type' => 'person',
       'posts_per_page' => 50, // Match frontend page size
       'paged' => $page
   ]);
   ```

2. **Use $wpdb with LIMIT and OFFSET:**
   ```php
   $offset = ($page - 1) * $per_page;
   $query = $wpdb->prepare(
       "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'person' LIMIT %d OFFSET %d",
       $per_page,
       $offset
   );
   ```

3. **Fetch only needed fields:**
   ```php
   // Don't load full post objects
   $ids = get_posts([
       'post_type' => 'person',
       'fields' => 'ids', // Only IDs
       'posts_per_page' => 50
   ]);
   ```

4. **Lazy-load ACF fields:**
   ```php
   // Don't load all ACF fields for list view
   $people = array_map(function($person) {
       return [
           'id' => $person->ID,
           'first_name' => get_field('first_name', $person->ID),
           'last_name' => get_field('last_name', $person->ID),
           // Only fields needed for list display
       ];
   }, $people);
   ```

5. **Set memory limits in endpoint:**
   ```php
   // At top of expensive endpoint
   if (defined('WP_DEBUG') && WP_DEBUG) {
       ini_set('memory_limit', '512M');
   }
   ```

**Detection:**
- Monitor `memory_get_peak_usage()` in endpoints
- Check error logs for memory errors
- Load test with Apache Bench or k6
- Profile with Xdebug or Blackfire

**Phase impact:** Phase 2 (Server-side filtering) - Must enforce pagination limits.

**References:**
- [WordPress Core Ticket #12257](https://core.trac.wordpress.org/ticket/12257) - wpdb memory issues with large result sets
- [Tyche Software: wpdb get_results() limit](https://www.tychesoftwares.com/wordpress-wpdb-get_results-limit/)

### Pitfall 7: Nonce Validation Bypassed in Custom Endpoints

**What goes wrong:** Custom REST endpoint at `/stadion/v1/people-filtered` has `permission_callback => 'is_user_logged_in'` but doesn't verify nonce. Logged-in user can forge requests, potentially bypassing access control or triggering CSRF attacks.

**Why it happens:**
- Developers assume `is_user_logged_in()` is sufficient security
- Don't realize nonce validation is NOT automatic for custom endpoints
- Confusion about when `rest_cookie_check_errors()` runs
- REST API docs say "nonce is validated" but only for cookie auth

**Consequences:**
- CSRF vulnerabilities allowing actions on behalf of logged-in users
- Session hijacking via XSS + CSRF
- Security audit failures
- Compliance violations

**Prevention:**

1. **Use existing permission callback from STADION_REST_API:**
   ```php
   register_rest_route('stadion/v1', '/people-filtered', [
       'methods' => 'GET',
       'callback' => [$this, 'get_people_filtered'],
       'permission_callback' => [$this, 'check_user_approved'], // From Base class
   ]);
   ```

2. **Nonce is validated automatically for cookie auth:**
   ```php
   // WordPress handles this in rest_cookie_check_errors()
   // As long as X-WP-Nonce header is sent (client.js already does this)
   ```

3. **Don't manually verify nonce unless needed:**
   ```php
   // WRONG - unnecessary and can cause issues
   if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'wp_rest')) {
       return new WP_Error('invalid_nonce');
   }

   // CORRECT - WordPress handles it if using cookie auth
   // Just use proper permission_callback
   ```

4. **For AJAX (non-REST), verify nonce:**
   ```php
   // In admin-ajax.php handlers
   check_ajax_referer('stadion_action', 'nonce');
   ```

**Detection:**
- Security audit of all `register_rest_route()` calls
- Check `permission_callback` isn't `__return_true` for protected endpoints
- Verify client sends `X-WP-Nonce` header (check network tab)
- Test with nonce removed from requests

**Phase impact:** Phase 2 (Server-side filtering) - Verify permission callbacks correct.

**References:**
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [Purple Turtle Creative: Why wp_verify_nonce() Fails in REST](https://purpleturtlecreative.com/blog/2022/10/why-wp_verify_nonce-fails-in-wordpress-rest-api-endpoints/)

### Pitfall 8: ACF Repeater Query Complexity

**What goes wrong:** Filtering by work_history sub-fields (team, title, is_current) is extremely slow. ACF stores repeaters serialized, requiring LIKE queries. Each sub-field filter adds more complexity.

**Why it happens:**
- ACF repeater structure: `work_history_0_team`, `work_history_1_team`, etc.
- Can't query "is_current = true" without checking all indices
- Need to use `meta_key LIKE 'work_history_%_is_current'`
- Multiple sub-field filters require multiple LIKEs

**Consequences:**
- "Filter by current team" feature unusable
- Timeouts on queries with repeater filters
- Database CPU spikes
- Can't implement advanced work history search

**Prevention:**

1. **Denormalize current team to separate field:**
   ```php
   // On work_history save
   add_action('acf/save_post', function($post_id) {
       if (get_post_type($post_id) !== 'person') return;

       $work_history = get_field('work_history', $post_id);
       $current_team = null;

       foreach ($work_history as $job) {
           if ($job['is_current'] && $job['team']) {
               $current_team = $job['team'];
               break;
           }
       }

       update_post_meta($post_id, '_current_team_id', $current_team);
   });
   ```

2. **Query denormalized field instead:**
   ```php
   // Fast query
   $query = new WP_Query([
       'post_type' => 'person',
       'meta_query' => [
           [
               'key' => '_current_team_id',
               'value' => $team_id,
               'compare' => '='
           ]
       ]
   ]);
   ```

3. **If must query repeater, use two-step approach:**
   ```php
   // Step 1: Get all people with work_history
   $people = get_posts(['post_type' => 'person', 'fields' => 'ids']);

   // Step 2: Filter in PHP
   $filtered = array_filter($people, function($person_id) use ($team_id) {
       $work_history = get_field('work_history', $person_id);
       foreach ($work_history as $job) {
           if ($job['is_current'] && $job['team'] == $team_id) {
               return true;
           }
       }
       return false;
   });
   ```

4. **Cache results aggressively:**
   ```php
   $cache_key = "people_current_team_{$team_id}";
   $cached = wp_cache_get($cache_key, 'stadion');
   if ($cached !== false) return $cached;

   // ... expensive query ...

   wp_cache_set($cache_key, $results, 'stadion', 300); // 5 min
   ```

**Detection:**
- Profile queries with repeater LIKE conditions
- Check for queries taking >1 second
- Monitor `wp_postmeta` table scans in slow query log

**Phase impact:** Phase 3 (Advanced filtering) - Must denormalize before implementing team filters.

**References:**
- [ACF Support: Querying Repeater Subfields](https://support.advancedcustomfields.com/forums/topic/querying-the-database-for-serialized-repeater-sub-field-values/)

### Pitfall 9: Infinite Scroll Doesn't Refetch All Pages on Invalidation

**What goes wrong:** User scrolls to page 5, creates a person, returns to list. Only page 1 is refetched. Pages 2-5 show old data. New person doesn't appear until user scrolls to page 1 and refreshes.

**Why it happens:**
- `queryClient.invalidateQueries()` only refetches the first page of infinite queries
- Pages 2+ remain in cache but marked stale
- Refetching all pages would be slow (5+ API requests)
- TanStack Query's design choice for performance

**Consequences:**
- Confusing user experience (new data doesn't appear)
- Users must "pull to refresh" or clear cache manually
- Data consistency issues across pages
- Support requests

**Prevention:**

1. **Reset query instead of invalidate on mutations:**
   ```javascript
   // WRONG - only refetches page 1
   queryClient.invalidateQueries({ queryKey: ['people'] });

   // CORRECT - clears all pages, starts fresh
   queryClient.resetQueries({ queryKey: ['people'] });
   ```

2. **Optimistically insert new items into page 1:**
   ```javascript
   onSuccess: (newPerson) => {
       queryClient.setQueryData(['people', filters], (old) => {
           if (!old) return old;

           return {
               ...old,
               pages: [
                   {
                       data: [newPerson, ...old.pages[0].data],
                       hasMore: old.pages[0].hasMore
                   },
                   ...old.pages.slice(1)
               ]
           };
       });
   }
   ```

3. **Use maxPages to limit cached pages:**
   ```javascript
   const { data } = useInfiniteQuery({
       queryKey: ['people', filters],
       queryFn: fetchPeople,
       maxPages: 3, // Only keep 3 pages in cache
       getNextPageParam: (lastPage) => lastPage.nextCursor,
   });
   ```

4. **Show "data may be stale" warning on deep pages:**
   ```javascript
   {data.pages.length > 3 && (
       <div className="bg-yellow-50 p-2 text-sm">
           Data may be outdated. <button onClick={refetch}>Refresh</button>
       </div>
   )}
   ```

**Detection:**
- Test: Scroll to page 3 → Create person → Check if person appears
- Monitor cache size in React DevTools
- Check refetch behavior in network tab

**Phase impact:** Phase 1 (Infinite scroll) - Must use resetQueries pattern.

**References:**
- [TanStack Query Discussion #3576](https://github.com/TanStack/query/discussions/3576) - refetchOnMount with infinite queries
- [TanStack Query Discussion #7569](https://github.com/TanStack/query/discussions/7569) - Only last page refetches on invalidation

## Minor Pitfalls

Mistakes that cause annoyance but are fixable.

### Pitfall 10: Column Preferences Not Synced Between Devices

**What goes wrong:** User configures visible columns on desktop, switches to mobile, sees default columns. Configures differently on mobile, returns to desktop, loses original settings.

**Why it happens:**
- Column preferences stored in user_meta (server-side)
- React state initialized from user_meta on mount
- Two browser tabs/devices have separate React state
- No real-time sync between sessions

**Consequences:**
- User frustration reconfiguring repeatedly
- Inconsistent experience across devices
- Support questions

**Prevention:**

1. **Save to server immediately on change:**
   ```javascript
   const saveColumnPrefs = useMutation({
       mutationFn: (columns) =>
           prmApi.patch('/user/column-preferences', { columns }),
       onSuccess: () => {
           // Also update local cache
           queryClient.setQueryData(['user', 'preferences'], (old) => ({
               ...old,
               columns
           }));
       }
   });

   // Save on every change, not just on unmount
   useEffect(() => {
       if (visibleColumns !== initialColumns) {
           saveColumnPrefs.mutate(visibleColumns);
       }
   }, [visibleColumns]);
   ```

2. **Refetch preferences on window focus:**
   ```javascript
   useQuery({
       queryKey: ['user', 'preferences'],
       queryFn: fetchUserPreferences,
       refetchOnWindowFocus: true, // Sync when switching tabs
   });
   ```

3. **Show sync status indicator:**
   ```javascript
   {saveColumnPrefs.isLoading && <span>Syncing...</span>}
   {saveColumnPrefs.isError && <span>Failed to save</span>}
   ```

**Detection:**
- Test with two browser tabs open
- Change preferences in tab 1, reload tab 2
- Check if preferences persist

**Phase impact:** Phase 4 (Column preferences) - Must implement immediate save.

### Pitfall 11: Sort Direction Indicator Wrong After Server-Side Sort

**What goes wrong:** User clicks "First Name" column header to sort ascending. UI shows ↑ arrow but results are actually descending. Clicking again doesn't change order.

**Why it happens:**
- Client-side state (`sortDirection`) doesn't match server response
- Server returns data in wrong order but doesn't return sort metadata
- No validation that server respected sort parameters

**Consequences:**
- Confusing UI (arrow points wrong direction)
- Users think sorting is broken
- Multiple clicks trying to fix it

**Prevention:**

1. **Server returns sort metadata in response:**
   ```php
   return [
       'data' => $people,
       'meta' => [
           'sort_by' => $sort_by,
           'sort_direction' => $sort_direction,
           'page' => $page,
           'total' => $total
       ]
   ];
   ```

2. **Client uses server metadata as source of truth:**
   ```javascript
   const { data } = useInfiniteQuery({
       queryKey: ['people', filters, sortBy, sortDirection],
       // ...
   });

   // Use server's actual sort state
   const actualSortBy = data?.pages[0]?.meta?.sort_by || 'first_name';
   const actualDirection = data?.pages[0]?.meta?.sort_direction || 'asc';
   ```

3. **Validate sort parameters server-side:**
   ```php
   $allowed_sort_fields = ['first_name', 'last_name', 'date_modified'];
   if (!in_array($sort_by, $allowed_sort_fields)) {
       $sort_by = 'first_name'; // Fallback to default
   }
   ```

**Detection:**
- Click column headers and verify sort order
- Compare UI indicator with actual data order

**Phase impact:** Phase 2 (Server-side filtering) - Include sort metadata in response.

### Pitfall 12: Empty State Not Shown When All Filtered Out

**What goes wrong:** User filters by "Team: X" with no results. Screen shows infinite loading spinner, no "No results" message.

**Why it happens:**
- Empty response `{ data: [], hasMore: false }` treated as loading state
- No distinction between "loading first page" and "no results"
- `isLoading` stays true even after empty response

**Consequences:**
- User doesn't know if query failed or has no results
- Confusion whether to wait or change filters

**Prevention:**

1. **Check for empty data after loading:**
   ```javascript
   const isEmpty = !isLoading && data?.pages[0]?.data.length === 0;

   if (isEmpty) {
       return (
           <EmptyState
               title="No people found"
               description="Try adjusting your filters"
           />
       );
   }
   ```

2. **Show count in filter UI:**
   ```javascript
   <FilterPanel
       filters={filters}
       resultCount={data?.pages[0]?.meta?.total || 0}
   />
   ```

**Detection:**
- Test filters that return zero results
- Check if empty state appears

**Phase impact:** Phase 2 (Filters) - Add empty state component.

## Phase-Specific Warnings

| Phase | Likely Pitfall | Mitigation |
|-------|---------------|------------|
| **Phase 1: Infinite Scroll** | Pitfall 4 (Cache invalidation), Pitfall 9 (Refetching pages) | Use `resetQueries` on mutations, implement `maxPages`, test with scroll to page 3+ |
| **Phase 2: Server-Side Filtering** | Pitfall 1 (Access control bypass), Pitfall 2 (SQL injection), Pitfall 6 (Memory leaks), Pitfall 7 (Nonce validation) | Audit all custom queries, use `$wpdb->prepare()`, enforce pagination, verify permission callbacks |
| **Phase 3: Advanced Filtering** | Pitfall 3 (JOIN performance), Pitfall 8 (ACF repeater complexity) | Implement STRAIGHT_JOIN, denormalize current team, add caching, limit JOINs to 3-4 |
| **Phase 4: Column Preferences** | Pitfall 10 (Sync between devices), Pitfall 11 (Sort indicator) | Save immediately to server, refetch on focus, include sort metadata in response |

## Testing Checklist

Before deploying server-side filtering:

- [ ] Test with unapproved user account (should see nothing)
- [ ] Test with 10,000+ people records (performance acceptable?)
- [ ] Test SQL injection payloads in filter params
- [ ] Test rapid filter changes (race conditions?)
- [ ] Test infinite scroll to page 5+ then create person (appears in list?)
- [ ] Test with two browser tabs (preferences sync?)
- [ ] Test memory usage during peak load
- [ ] Review all `$wpdb` queries for `prepare()` usage
- [ ] Verify access control in custom queries
- [ ] Check EXPLAIN output for >3 JOINs

## Additional Resources

### SQL Injection & Security
- [Patchstack SQL Injection Guide](https://patchstack.com/articles/sql-injection/)
- [WordPress REST API Security](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [CVE-2026-23550 Case Study](https://thehackernews.com/2026/01/critical-wordpress-modular-ds-plugin.html)

### Performance
- [ACF Query Performance Best Practices](https://www.advancedcustomfields.com/blog/wordpress-post-meta-query/)
- [WordPress Core: Complex Meta Queries](https://core.trac.wordpress.org/ticket/20134)
- [wpdb Memory Issues](https://core.trac.wordpress.org/ticket/12257)

### TanStack Query
- [Query Invalidation Guide](https://tanstack.com/query/latest/docs/framework/react/guides/query-invalidation)
- [Infinite Queries Documentation](https://tanstack.com/query/v4/docs/framework/react/guides/infinite-queries)
- [Cache Invalidation Patterns](https://dev.to/ignasave/we-kept-breaking-cache-invalidation-in-tanstack-query-so-we-stopped-managing-it-manually-47k2)

---

**Next Steps:**

1. **Phase 2 Security Audit:** Review all planned custom queries for access control and SQL injection vulnerabilities BEFORE implementation
2. **Performance Baseline:** Measure current query times with 10K records to set performance targets
3. **Cache Strategy Document:** Define when to invalidate vs. reset queries for infinite scroll
4. **Denormalization Plan:** Identify which ACF fields need to be denormalized for performant filtering
