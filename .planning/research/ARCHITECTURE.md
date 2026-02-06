# Architecture Patterns: People List Performance & Customization

**Project:** Stadion - Personal CRM
**Researched:** 2026-01-29
**Confidence:** HIGH

## Executive Summary

This research analyzes how to integrate infinite scroll with server-side filtering and per-user column preferences into Stadion's existing WordPress/React architecture. The system currently loads all people client-side (via paginated loop in `usePeople()`) and performs filtering/sorting in-memory. The milestone requires:

1. **Infinite scroll** with server-side filtering to handle large datasets (100+ people)
2. **Per-user column preferences** for customizable list views
3. **Server-side sorting** including ACF custom fields

Key findings: Stadion's architecture is well-suited for these enhancements. The existing REST endpoint pattern (`/wp/v2/people` + `/rondo/v1/*` for custom endpoints) provides clear integration points. TanStack Query's `useInfiniteQuery` replaces the current `usePeople()` implementation. User preferences fit naturally into existing user_meta storage patterns.

## Current Architecture Analysis

### Existing Data Flow

```
Frontend (React SPA)
├── PeopleList.jsx
│   ├── Uses usePeople() hook
│   ├── Client-side filtering (labels, birth year, last modified, ownership)
│   ├── Client-side sorting (first_name, last_name, organization, labels, custom fields)
│   └── Renders PersonListRow components
│
└── usePeople() hook
    ├── TanStack Query useQuery
    ├── Pagination loop (per_page: 100, fetches all pages)
    ├── Transforms person data (thumbnail, labels, computed fields)
    └── Returns flattened array of all people

Backend (WordPress)
├── /wp/v2/people (standard REST)
│   ├── WordPress core pagination (page, per_page)
│   ├── Standard WP_Query parameters
│   └── ACF fields in response via _embed
│
├── /rondo/v1/people/* (custom endpoints)
│   ├── /people/bulk-update - bulk operations
│   ├── /people/{id}/dates - person dates
│   └── /people/{id}/timeline - person timeline
│
└── Access Control (RONDO_Access_Control)
    ├── Hooks into WP_Query (pre_get_posts)
    ├── Approved users see all data
    └── Unapproved users see nothing
```

### Current Components

**Frontend:**
- `src/hooks/usePeople.js` - Data fetching with TanStack Query
- `src/pages/People/PeopleList.jsx` - List view with filtering/sorting
- `src/api/client.js` - Axios wrapper with nonce injection

**Backend:**
- `includes/class-rest-people.php` - Custom people endpoints
- `includes/class-rest-api.php` - General custom endpoints
- `includes/class-access-control.php` - Row-level filtering
- `includes/class-rest-custom-fields.php` - Custom field management

### Current Limitations

1. **All-or-nothing loading:** Current `usePeople()` fetches ALL people in a loop, poor performance with 100+ records
2. **Client-side operations:** All filtering and sorting happens in-memory in `PeopleList.jsx`
3. **No server-side ACF sorting:** Cannot sort by custom fields on server (requires meta_query)
4. **No user preferences:** Column visibility/order not persisted per-user
5. **No cursor-based pagination:** Uses page numbers, can miss new records

## Recommended Architecture

### Overview Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ Frontend: React SPA with TanStack Query                     │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  PeopleList.jsx (Modified)                                   │
│  ├── useInfiniteQuery for infinite scroll                   │
│  ├── Server-side filtering via query params                 │
│  ├── Server-side sorting via query params                   │
│  └── Column preferences from useUserPreferences()            │
│                                                               │
│  usePeopleInfinite() (New Hook)                              │
│  ├── Replaces usePeople() for list view                     │
│  ├── useInfiniteQuery with page-based pagination            │
│  ├── queryFn: GET /rondo/v1/people/filtered               │
│  ├── getNextPageParam: page+1 if hasMore                    │
│  └── Flattens pages for rendering                           │
│                                                               │
│  useUserPreferences() (New Hook)                             │
│  ├── GET /rondo/v1/user/list-preferences                  │
│  ├── PATCH /rondo/v1/user/list-preferences                │
│  └── Manages column visibility, order, and default sort     │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ Backend: WordPress REST API + Custom Endpoints              │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  /rondo/v1/people/filtered (New Endpoint)                  │
│  ├── Server-side filtering (labels, birth_year, modified)   │
│  ├── Server-side sorting (including ACF fields via JOIN)    │
│  ├── Efficient pagination (LIMIT/OFFSET)                    │
│  ├── Response: { people: [], hasMore: bool, total: int }    │
│  └── Respects AccessControl (approved users only)           │
│                                                               │
│  /rondo/v1/user/list-preferences (New Endpoint)            │
│  ├── GET: Returns visible_columns, column_order, sort       │
│  ├── PATCH: Updates preferences in user_meta                │
│  └── Stored: wp_usermeta with key 'stadion_people_list_prefs'│
│                                                               │
│  Custom SQL Query Builder (New Class)                        │
│  ├── Uses $wpdb for efficient JOINs                          │
│  ├── JOINs wp_postmeta for ACF fields when sorting          │
│  ├── Builds dynamic WHERE clauses for filters               │
│  ├── Applies AccessControl at SQL level                     │
│  └── Returns post IDs + total count                         │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Component Integration Points

### 1. New REST Endpoint: `/rondo/v1/people/filtered`

**Purpose:** Server-side filtering, sorting, and pagination for People list.

**Location:** Add to `includes/class-rest-people.php`

**Request Parameters:**
```php
[
    'page' => 1,              // Current page (default: 1)
    'per_page' => 20,         // Items per page (default: 20, max: 100)
    'orderby' => 'first_name',// Sort field (default: 'first_name')
    'order' => 'asc',         // Sort direction (default: 'asc')
    'labels' => [1, 2, 3],    // person_label term IDs (OR filter)
    'birth_year' => 1985,     // Birth year filter
    'modified_after' => '2025-12-01', // Modified after date
    'search' => 'John',       // Search query (first_name, last_name)
]
```

**Response Format:**
```php
[
    'people' => [
        // Array of person objects (same format as /wp/v2/people)
        // Includes ACF fields, thumbnail, labels (via transformPerson)
    ],
    'pagination' => [
        'page' => 1,
        'per_page' => 20,
        'total_items' => 143,
        'total_pages' => 8,
        'has_more' => true,
    ],
]
```

**Implementation Strategy:**

1. **Custom SQL Builder** - Build efficient queries with $wpdb
2. **ACF JOIN for sorting** - Join wp_postmeta when orderby is custom field
3. **Access Control Integration** - Apply approved-user filter at SQL level
4. **Transform results** - Use existing `transformPerson()` from usePeople.js

**Query Construction Pattern:**

```php
// Pseudo-code for server-side query
SELECT DISTINCT p.ID
FROM wp_posts p
-- Join for label filtering
LEFT JOIN wp_term_relationships tr ON p.ID = tr.object_id
LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
-- Join for custom field sorting
LEFT JOIN wp_postmeta pm_sort ON p.ID = pm_sort.post_id
    AND pm_sort.meta_key = '{custom_field_name}'
-- Join for birth_year filtering (requires subquery for important_date)
LEFT JOIN (
    SELECT pm.meta_value as person_id, d.birth_year
    FROM wp_posts d
    JOIN wp_postmeta pm ON d.ID = pm.post_id AND pm.meta_key = 'related_people'
    WHERE d.post_type = 'important_date'
) dates ON p.ID = dates.person_id
WHERE p.post_type = 'person'
  AND p.post_status = 'publish'
  -- Label filter (if provided)
  AND (tt.taxonomy = 'person_label' AND tt.term_id IN ({label_ids}))
  -- Birth year filter (if provided)
  AND dates.birth_year = {birth_year}
  -- Modified after filter (if provided)
  AND p.post_modified >= '{modified_after}'
  -- Search filter (if provided)
  AND (pm_first.meta_value LIKE '%{search}%' OR pm_last.meta_value LIKE '%{search}%')
ORDER BY {orderby_clause}
LIMIT {per_page} OFFSET {offset}
```

**Performance Considerations:**

- **Index wp_postmeta.meta_key** - Essential for ACF field JOINs
- **Cache results** - Use WordPress transients for expensive queries (5 min TTL)
- **Avoid LIKE on large tables** - Use full-text search for large datasets (100k+ records)
- **Limit JOIN depth** - Maximum 3 JOINs per query to avoid exponential complexity

### 2. New REST Endpoint: `/rondo/v1/user/list-preferences`

**Purpose:** Store and retrieve per-user column preferences for People list.

**Location:** Add to `includes/class-rest-api.php` (or new `class-rest-user-preferences.php`)

**Storage:**
- **Table:** `wp_usermeta`
- **Meta Key:** `stadion_people_list_preferences`
- **Value Format:** JSON-encoded array

**Data Structure:**
```php
[
    'visible_columns' => [
        'first_name',     // Core fields
        'last_name',
        'organization',
        'labels',
        'custom_field_1', // Custom fields
        'custom_field_2',
    ],
    'column_order' => [
        'first_name',
        'organization',
        'custom_field_1',
        'last_name',
        'labels',
        'custom_field_2',
    ],
    'default_sort' => [
        'field' => 'first_name',
        'order' => 'asc',
    ],
]
```

**Endpoints:**

**GET** `/rondo/v1/user/list-preferences`
- Returns current user's preferences
- Defaults if not set:
  - `visible_columns`: All core columns + active custom fields with `show_in_list_view: true`
  - `column_order`: Core columns first, then custom fields by `list_view_order`
  - `default_sort`: `{ field: 'first_name', order: 'asc' }`

**PATCH** `/rondo/v1/user/list-preferences`
- Updates preferences (partial updates supported)
- Validates:
  - `visible_columns` must be subset of available columns
  - `column_order` must match `visible_columns` length
  - `default_sort.field` must be in `visible_columns`
- Returns updated preferences

**Integration Pattern:**
```php
// Get preferences
$preferences = get_user_meta(
    get_current_user_id(),
    'stadion_people_list_preferences',
    true
);

// Update preferences (merge with defaults)
update_user_meta(
    get_current_user_id(),
    'stadion_people_list_preferences',
    wp_parse_args($new_prefs, $default_prefs)
);
```

### 3. Modified Hook: `usePeopleInfinite()`

**Purpose:** Replace `usePeople()` for list view with infinite scroll support.

**Location:** `src/hooks/usePeople.js` (add new export)

**Implementation:**
```javascript
export function usePeopleInfinite(filters = {}, sort = { field: 'first_name', order: 'asc' }) {
  return useInfiniteQuery({
    queryKey: ['people', 'infinite', filters, sort],
    queryFn: async ({ pageParam = 1 }) => {
      const response = await prmApi.getPeopleFiltered({
        page: pageParam,
        per_page: 20,
        orderby: sort.field,
        order: sort.order,
        ...filters,
      });
      return response.data;
    },
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      return lastPage.pagination.has_more
        ? lastPage.pagination.page + 1
        : undefined;
    },
  });
}
```

**Usage in PeopleList.jsx:**
```javascript
const {
  data,
  fetchNextPage,
  hasNextPage,
  isFetchingNextPage,
} = usePeopleInfinite(filters, sort);

// Flatten pages for rendering
const people = useMemo(() => {
  return data?.pages.flatMap(page => page.people) || [];
}, [data]);
```

**Intersection Observer Pattern:**
```javascript
import { useInView } from 'react-intersection-observer';

// In component
const { ref, inView } = useInView();

useEffect(() => {
  if (inView && hasNextPage && !isFetchingNextPage) {
    fetchNextPage();
  }
}, [inView, hasNextPage, isFetchingNextPage, fetchNextPage]);

// Render sentinel element
<div ref={ref} className="h-10 w-full" />
```

### 4. New Hook: `useUserPreferences()`

**Purpose:** Manage per-user column preferences with optimistic updates.

**Location:** `src/hooks/useUserPreferences.js` (new file)

**Implementation:**
```javascript
export function useUserPreferences() {
  const queryClient = useQueryClient();

  const { data: preferences, isLoading } = useQuery({
    queryKey: ['user-preferences', 'people-list'],
    queryFn: async () => {
      const response = await prmApi.getListPreferences();
      return response.data;
    },
  });

  const updatePreferences = useMutation({
    mutationFn: (updates) => prmApi.updateListPreferences(updates),
    onMutate: async (updates) => {
      // Optimistic update
      await queryClient.cancelQueries({ queryKey: ['user-preferences', 'people-list'] });
      const previous = queryClient.getQueryData(['user-preferences', 'people-list']);
      queryClient.setQueryData(['user-preferences', 'people-list'], (old) => ({
        ...old,
        ...updates,
      }));
      return { previous };
    },
    onError: (err, updates, context) => {
      // Rollback on error
      queryClient.setQueryData(['user-preferences', 'people-list'], context.previous);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['user-preferences', 'people-list'] });
    },
  });

  return {
    preferences,
    isLoading,
    updatePreferences: updatePreferences.mutate,
  };
}
```

**Usage in PeopleList.jsx:**
```javascript
const { preferences, updatePreferences } = useUserPreferences();

// Toggle column visibility
const toggleColumn = (columnKey) => {
  const visible = preferences.visible_columns.includes(columnKey)
    ? preferences.visible_columns.filter(c => c !== columnKey)
    : [...preferences.visible_columns, columnKey];

  updatePreferences({ visible_columns: visible });
};
```

## Data Flow Changes

### Before (Current)

```
User opens People list
└── PeopleList.jsx renders
    └── usePeople() hook
        ├── Fetches page 1 (100 items)
        ├── Fetches page 2 (100 items)
        ├── ... continues until all pages fetched
        └── Returns flattened array [all 500+ people]
    └── Client-side filtering (useMemo)
        └── Filters by labels, birth year, modified date
    └── Client-side sorting (useMemo)
        └── Sorts by first_name, last_name, etc.
    └── Render ALL filtered/sorted people at once
```

**Issues:**
- Initial load fetches ALL records (slow with 500+ people)
- Memory overhead storing all records client-side
- Network overhead transferring all records
- Sorting custom fields requires loading all ACF data

### After (Proposed)

```
User opens People list
└── PeopleList.jsx renders
    ├── useUserPreferences() - Fetches column settings
    │   └── GET /rondo/v1/user/list-preferences
    │       └── Returns: visible_columns, column_order, default_sort
    │
    └── usePeopleInfinite(filters, sort) - Fetches first page
        ├── GET /rondo/v1/people/filtered?page=1&per_page=20&orderby=first_name&order=asc
        │   └── Server applies filters, sorts, returns 20 people
        ├── Renders 20 people
        │
        └── User scrolls to bottom
            ├── Intersection Observer triggers
            ├── fetchNextPage() called
            ├── GET /rondo/v1/people/filtered?page=2&per_page=20
            └── Appends next 20 people to list

User changes filter (e.g., adds label filter)
└── usePeopleInfinite(newFilters, sort) - Query key changes
    ├── TanStack Query cache miss
    ├── GET /rondo/v1/people/filtered?page=1&labels=3&orderby=first_name
    └── Renders filtered results

User changes sort (e.g., sort by custom field)
└── usePeopleInfinite(filters, newSort) - Query key changes
    ├── GET /rondo/v1/people/filtered?page=1&orderby=custom_field_name&order=desc
    └── Server JOINs wp_postmeta, sorts by ACF field
```

**Benefits:**
- Initial load only fetches 20 records (10-20x faster)
- Memory usage proportional to displayed records
- Server-side filtering/sorting reduces client computation
- Custom field sorting uses database indexes

## Build Order Recommendation

### Phase 1: Server-Side Foundation (Backend First)

**Goal:** Build new REST endpoint with server-side filtering/sorting

**Components:**
1. **Custom SQL Query Builder** (`includes/class-people-query-builder.php`)
   - Build efficient queries with $wpdb
   - Support filtering: labels, birth_year, modified_after, search
   - Support sorting: core fields + ACF fields
   - Handle AccessControl at SQL level
   - Return post IDs + pagination metadata

2. **New REST Endpoint** (`/rondo/v1/people/filtered`)
   - Add to `includes/class-rest-people.php`
   - Use Query Builder for data fetching
   - Transform results with existing `transformPerson()` pattern
   - Return paginated response with hasMore flag

3. **Testing:**
   - Test with Query Monitor for SQL performance
   - Verify AccessControl (approved users only)
   - Test edge cases: empty results, last page, invalid filters

**Why backend first:** Frontend can't use infinite scroll until endpoint exists. Server-side filtering is the foundation for all subsequent work.

### Phase 2: Frontend Infinite Scroll (No UI Changes)

**Goal:** Replace client-side loading with server-side pagination

**Components:**
1. **New Hook** (`src/hooks/usePeopleInfinite.js`)
   - Implement useInfiniteQuery with new endpoint
   - Flatten pages for backward compatibility
   - Keep existing transformPerson() on client

2. **Update API Client** (`src/api/client.js`)
   - Add `getPeopleFiltered()` method
   - Map parameters to query string

3. **Modify PeopleList.jsx**
   - Replace `usePeople()` with `usePeopleInfinite()`
   - Keep existing filtering/sorting state management
   - Add Intersection Observer for infinite scroll
   - Add "Load More" button as fallback

4. **Testing:**
   - Verify infinite scroll works (scroll to load more)
   - Verify filters still work (query key changes trigger refetch)
   - Verify sorting still works (query key changes trigger refetch)

**Why this order:** Proves server-side pagination works before adding preferences complexity. Keeps existing UI flow.

### Phase 3: User Preferences Backend

**Goal:** Store and retrieve per-user column preferences

**Components:**
1. **New REST Endpoints** (`/rondo/v1/user/list-preferences`)
   - Add to `includes/class-rest-api.php`
   - GET: Return user preferences with defaults
   - PATCH: Update user preferences with validation
   - Use wp_usermeta for storage

2. **Default Preferences Logic**
   - Pull from custom fields metadata (show_in_list_view)
   - Merge user overrides with defaults

3. **Testing:**
   - Test preferences persist across sessions
   - Test validation (invalid columns rejected)
   - Test defaults for new users

**Why after infinite scroll:** Infinite scroll must work before adding preference-driven column visibility.

### Phase 4: Frontend Column Preferences UI

**Goal:** Allow users to customize visible columns

**Components:**
1. **New Hook** (`src/hooks/useUserPreferences.js`)
   - Fetch preferences with useQuery
   - Update preferences with useMutation
   - Optimistic updates for instant feedback

2. **Column Settings Modal** (new component)
   - Drag-and-drop column reordering
   - Checkbox list for visibility
   - Default sort selection

3. **Update PeopleList.jsx**
   - Render columns based on preferences
   - Show column settings button
   - Apply default sort from preferences

4. **Testing:**
   - Test column visibility changes persist
   - Test column reordering works
   - Test default sort applies on load

**Why last:** Preferences are enhancement, not blocker. Can ship Phase 1-3 without UI for preferences.

## Performance Considerations

### Database Optimization

**1. Required Indexes:**
```sql
-- Index for person_label filtering
CREATE INDEX idx_term_taxonomy_type
ON wp_term_taxonomy (taxonomy, term_id);

-- Index for ACF field sorting
CREATE INDEX idx_postmeta_key_value
ON wp_postmeta (meta_key, meta_value(100));

-- Index for modified date filtering
CREATE INDEX idx_posts_type_status_modified
ON wp_posts (post_type, post_status, post_modified);
```

**2. Query Caching Strategy:**
- **Transient cache for expensive queries** (5 min TTL)
- **Cache key:** `stadion_people_filtered_{hash_of_params}`
- **Invalidation:** On person create/update/delete
- **Benefits:** 10-50x speedup for repeated queries

**3. Pagination Best Practices:**
- **Use LIMIT/OFFSET** for page-based pagination
- **Maximum per_page: 100** to prevent memory issues
- **Default per_page: 20** for optimal UX (fast load + minimal scrolling)
- **Avoid COUNT(\*) on every request** - cache total count

### Frontend Optimization

**1. TanStack Query Caching:**
- **staleTime: 60000** (1 minute) - Reduce refetches
- **cacheTime: 300000** (5 minutes) - Keep in memory
- **keepPreviousData: true** - Smooth filter transitions

**2. Infinite Scroll Settings:**
- **Threshold: 500px** from bottom (start loading before user reaches end)
- **Debounce: 300ms** for filter changes (avoid query spam)
- **Optimistic updates:** for preference changes (instant feedback)

**3. Render Optimization:**
- **React.memo** for PersonListRow (avoid re-renders)
- **Virtual scrolling** if list exceeds 500 items (optional enhancement)
- **Skeleton loading** for first page (better perceived performance)

## Migration Strategy

### Backward Compatibility

**Keep existing usePeople() for:**
- Person detail pages (still need full person object)
- Dashboard recent people (small list)
- Bulk operations (need all IDs)

**Deprecate usePeople() for:**
- PeopleList.jsx (replace with usePeopleInfinite)

**No breaking changes:**
- `/wp/v2/people` endpoint unchanged
- Existing API clients continue working
- `/rondo/v1/people/filtered` is additive

### Rollout Plan

**Step 1:** Deploy Phase 1-2 (backend + infinite scroll)
- Feature flag: `RONDO_ENABLE_INFINITE_SCROLL` (default: true)
- Monitor performance with Query Monitor
- Rollback plan: Revert to usePeople() if issues

**Step 2:** Deploy Phase 3-4 (preferences)
- Feature flag: `RONDO_ENABLE_COLUMN_PREFS` (default: true)
- Graceful degradation: Show all columns if preferences endpoint fails

**Step 3:** Remove feature flags after 1 week stable

## Risks and Mitigations

### Risk 1: ACF JOIN Performance

**Issue:** Joining wp_postmeta for custom field sorting may be slow with large datasets.

**Mitigation:**
- Add index on wp_postmeta.meta_key
- Cache expensive queries with transients (5 min TTL)
- Limit custom field JOINs to 3 per query
- Fallback to client-side sorting if query exceeds 2 seconds

**Detection:** Query Monitor shows query execution time

### Risk 2: Pagination Race Conditions

**Issue:** New records inserted while user scrolling may cause duplicate/skipped records.

**Mitigation:**
- Use cursor-based pagination (future enhancement)
- Document limitation: "Sorting/filtering may miss very recent changes"
- Add "Refresh" button for manual updates

**Detection:** User reports seeing duplicates in list

### Risk 3: User Preference Conflicts

**Issue:** User preferences reference deleted custom fields.

**Mitigation:**
- Validate preferences against current custom field definitions
- Remove deleted fields from visible_columns on GET
- Show warning: "Some columns were removed because they no longer exist"

**Detection:** GET /rondo/v1/user/list-preferences returns fewer columns than saved

### Risk 4: AccessControl Bypass

**Issue:** Custom SQL query might bypass AccessControl filters.

**Mitigation:**
- Apply AccessControl at SQL level (WHERE post_author IN / user approval check)
- Test with unapproved user (should see 0 results)
- Code review by security-focused developer

**Detection:** Unapproved user can see people records (critical bug)

## Alternative Approaches Considered

### Alternative 1: Cursor-Based Pagination

**Description:** Use cursor (last record ID) instead of page numbers for pagination.

**Pros:**
- No duplicate/skipped records if data changes during scroll
- Better performance (no OFFSET calculation)

**Cons:**
- More complex implementation (need to track cursor in multiple sort scenarios)
- Cannot jump to specific page
- WordPress REST API uses page-based by default

**Decision:** Deferred to future enhancement. Page-based is simpler and works for current use case.

### Alternative 2: Virtual Scrolling

**Description:** Only render visible rows in viewport (react-window, react-virtualized).

**Pros:**
- Render 1000+ items without performance issues
- Memory efficient (only ~20 DOM nodes at a time)

**Cons:**
- Complex integration with TanStack Query
- Breaks accessibility (screen readers need full list)
- Height calculations tricky with variable row heights

**Decision:** Not needed for current dataset size (100-500 people). Revisit if dataset exceeds 1000 records.

### Alternative 3: GraphQL Instead of REST

**Description:** Use WPGraphQL plugin for flexible querying.

**Pros:**
- Single request for nested data (no _embed needed)
- Client specifies exact fields needed
- Built-in pagination (first/after, last/before)

**Cons:**
- Major architectural change (entire API surface)
- Learning curve for team
- Overkill for this use case (REST works fine)

**Decision:** Rejected. REST API is sufficient and already integrated.

## Security Considerations

### Access Control

**Critical:** Custom SQL queries MUST respect AccessControl.

**Implementation:**
```php
// In query builder
if (!$access_control->is_user_approved()) {
    // Unapproved users see nothing
    $sql .= " AND p.ID IN (0)";
} else {
    // Approved users see all posts
    // No additional filtering needed
}
```

**Testing:** Create test user, deny approval, verify they see empty list.

### User Preference Validation

**Risk:** User could inject malicious column names in preferences.

**Mitigation:**
- Whitelist allowed columns (core + active custom fields)
- Validate field names against whitelist on PATCH
- Sanitize all input with `sanitize_text_field()`

**Example:**
```php
$allowed_columns = array_merge(
    ['first_name', 'last_name', 'organization', 'labels'],
    $this->get_active_custom_field_keys('person')
);

$visible = array_intersect($request['visible_columns'], $allowed_columns);
```

### SQL Injection Prevention

**Risk:** User-provided filters could inject SQL.

**Mitigation:**
- Use `$wpdb->prepare()` for all queries
- Validate filter types (labels = array of ints, birth_year = int, etc.)
- Escape LIKE wildcards with `$wpdb->esc_like()`

**Example:**
```php
if ($labels) {
    $labels = array_map('intval', $labels);
    $placeholders = implode(',', array_fill(0, count($labels), '%d'));
    $sql .= $wpdb->prepare(" AND tt.term_id IN ($placeholders)", $labels);
}
```

## Testing Strategy

### Unit Tests

**Backend (PHPUnit):**
1. Query Builder
   - Test filter combinations (labels + birth_year + modified)
   - Test sorting (core fields + custom fields)
   - Test pagination (LIMIT/OFFSET calculation)
   - Test AccessControl integration

2. REST Endpoints
   - Test request validation (invalid params return 400)
   - Test permission checks (unapproved users return 403)
   - Test response format (matches schema)

**Frontend (Vitest):**
1. usePeopleInfinite
   - Test page fetching (mock API responses)
   - Test getNextPageParam (hasMore = true/false)
   - Test query key changes (filters/sort trigger refetch)

2. useUserPreferences
   - Test optimistic updates (immediate UI change)
   - Test rollback on error (reverts to previous state)

### Integration Tests

**E2E (Playwright/Cypress):**
1. Infinite scroll flow
   - Load list → scroll to bottom → verify next page loads
   - Filter list → verify filtered results → scroll → verify pagination continues

2. Column preferences flow
   - Open settings → hide column → verify column disappears
   - Reorder columns → verify order persists on refresh

3. Performance tests
   - Load list with 100 people → verify initial load < 2 seconds
   - Scroll through 500 people → verify no lag

### Manual Testing Checklist

- [ ] Infinite scroll works on desktop
- [ ] Infinite scroll works on mobile (touch drag)
- [ ] Filters update results without breaking scroll
- [ ] Sorting updates results without breaking scroll
- [ ] Column preferences persist across sessions
- [ ] Default preferences work for new users
- [ ] AccessControl prevents unapproved users from seeing data
- [ ] Query Monitor shows no slow queries (< 0.1s)

## Documentation Requirements

### Developer Documentation

**API Reference:**
- Document `/rondo/v1/people/filtered` endpoint (request/response schemas)
- Document `/rondo/v1/user/list-preferences` endpoint
- Add examples to API client (`src/api/client.js` JSDoc comments)

**Architecture Decision Record (ADR):**
- Why server-side pagination (performance)
- Why page-based over cursor-based (simplicity)
- Why user_meta for preferences (WordPress native pattern)

### User Documentation

**Help Text:**
- "Column Settings" tooltip explaining drag-to-reorder
- "Default Sort" explanation (sorts list on load)
- Warning if custom field deleted ("Column X no longer exists")

**Release Notes:**
- "Infinite scroll for faster loading with large contact lists"
- "Customize visible columns to show only what you need"
- "Sort by custom fields (e.g., 'Industry', 'Score')"

## Success Criteria

### Performance Metrics

- **Initial page load:** < 1 second (from click to first render)
- **Scroll to next page:** < 500ms (from trigger to append)
- **Filter change:** < 1 second (from change to updated results)
- **Preference update:** < 200ms perceived (optimistic update)

### Functional Requirements

- [ ] Users can scroll infinitely through people list
- [ ] Users can filter by labels, birth year, modified date
- [ ] Users can sort by core fields + custom fields
- [ ] Users can show/hide columns in list view
- [ ] Users can reorder columns in list view
- [ ] Preferences persist across browser sessions
- [ ] AccessControl prevents data leaks

### Code Quality

- [ ] Query Monitor shows all queries < 0.1s
- [ ] No N+1 queries detected
- [ ] Unit test coverage > 80%
- [ ] No console errors in browser
- [ ] Lighthouse Performance score > 90

## Sources

### TanStack Query & Infinite Scroll
- [TanStack Query Infinite Queries](https://tanstack.com/query/latest/docs/framework/react/guides/infinite-queries)
- [How to use Infinity Queries (TanStack Query) to do infinite scrolling](https://dev.to/davi_rezende/how-to-use-infinity-queries-tanstack-query-to-do-infinite-scrolling-2i2e)
- [React TanStack Query Load More Infinite Scroll Example](https://tanstack.com/query/latest/docs/framework/react/examples/load-more-infinite-scroll)
- [Caching, Pagination, and Infinite Scrolling with TanStack Query](https://medium.com/@lakshaykapoor08/%EF%B8%8F-caching-pagination-and-infinite-scrolling-with-tanstack-query-4212b24d3806)
- [How to Implement React Infinite Scrolling with React Query v5](https://pieces.app/blog/how-to-implement-react-infinite-scrolling-with-react-query-v5)

### WordPress REST API & Filtering
- [Sorting/Orderby for custom meta fields in WordPress REST API](https://iamshishir.com/sorting-orderby-for-custom-meta-fields-in-wordpress/)
- [WordPress REST API order by meta_value](https://www.timrosswebdevelopment.com/wordpress-rest-api-order-by-meta_value/)
- [Enabling Filters for Meta Fields in the WordPress Rest API](https://iamshishir.com/enabling-filters-for-meta-fields-in-the-wordpress-rest-api/)
- [Adding meta fields with orderby endpoint to WordPress rest API](https://gist.github.com/aaronsummers/8518b0d70c4bc34e0bde4049fabac08c)

### WordPress Performance & ACF Optimization
- [ACF WordPress Post Meta Query Performance Best Practices](https://www.advancedcustomfields.com/blog/wordpress-post-meta-query/)
- [Optimizing Advanced Custom Fields for Fast WordPress Sites](https://acfcopilotplugin.com/blog/optimizing-advanced-custom-fields-for-fast-wordpress-sites/)
- [Tips & Tricks for Optimizing ACF Performance in WordPress](https://wpfieldwork.com/optimizing-acf-performance/)
- [How to Optimize ACF Relationship and Repeater Queries](https://www.lexo.ch/blog/2025/04/optimize-acf-relationship-and-repeater-queries/)

### WordPress User Meta & Preferences
- [Working with User Metadata – Plugin Handbook](https://developer.wordpress.org/plugins/users/working-with-user-metadata/)
- [How WordPress user data is stored in the database](https://usersinsights.com/wordpress-user-database-tables/)
- [What Is the User Meta Function in WordPress? A Practical, Real-World Guide](https://thelinuxcode.com/what-is-the-user-meta-function-in-wordpress-a-practical-realworld-guide/)
- [Choosing Between WordPress User Meta System and Custom User-Related Tables](https://www.voxfor.com/choosing-between-wordpress-user-meta-system-and-custom-user-related-tables/)
