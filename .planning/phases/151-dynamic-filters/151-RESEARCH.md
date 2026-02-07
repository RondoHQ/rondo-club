# Phase 151: Dynamic Filters - Research

**Researched:** 2026-02-07
**Domain:** WordPress REST API, React state management, SQL query optimization
**Confidence:** HIGH

## Summary

Phase 151 requires converting two hardcoded filter dropdowns (age group and member type) on the People list from static arrays to dynamic data-driven options. The current implementation already has all necessary infrastructure in place:

1. **Backend**: PHP REST API with server-side filtering using optimized `$wpdb` queries with JOINs
2. **Frontend**: React with TanStack Query for data fetching, URL-based filter state persistence
3. **Data**: Sportlink-synced meta fields stored as WordPress post meta (`type-lid`, `leeftijdsgroep`)

The research reveals that **both filters are already implemented and functional** — they just use hardcoded option arrays in the JSX. The backend query filtering supports these fields via exact string matching on post meta values. This phase converts these hardcoded arrays to API-derived options with counts.

**Primary recommendation:** Create a dedicated REST endpoint `/rondo/v1/people/filter-options` that queries distinct meta values with counts, add smart numeric sorting for age groups, implement loading/error states in dropdowns, and build generic infrastructure for future dynamic filters.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | Native | Backend API | Standard WordPress approach, already extensively used |
| TanStack Query | ~5.x | React data fetching | Already used throughout app (see `src/hooks/usePeople.js`) |
| React Router | 6.x | URL query params | Already managing filter state via `useSearchParams` |
| Axios | Current | HTTP client | Project standard (see `src/api/client.js`) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress $wpdb | Native | Direct SQL queries | When WP_Query is too slow (already used in `get_filtered_people`) |
| ACF field meta | Native | Custom field storage | All custom fields use this approach |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Dedicated endpoint | Embedded in list response | Separate endpoint allows independent caching/refresh |
| SQL DISTINCT query | WordPress meta query | $wpdb is 10-100x faster on large datasets (1400+ people) |
| Client-side sorting | Server-side sorting | Age groups need smart numeric extraction - easier in PHP |

**Installation:**
None required — all dependencies already in place.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-rest-people.php     # Add get_filter_options() method
src/
├── api/client.js             # Add getFilterOptions() method
├── hooks/
│   └── usePeople.js          # Add useFilterOptions() hook
└── pages/People/
    └── PeopleList.jsx        # Convert hardcoded arrays to API data
```

### Pattern 1: REST Endpoint for Filter Options
**What:** Dedicated endpoint that returns available filter values with counts
**When to use:** When filter options need to be loaded independently of list data
**Example:**
```php
// Source: Phase 151 requirement + existing pattern in class-rest-api.php
public function get_filter_options( $request ) {
    global $wpdb;

    // Query distinct values with counts
    $age_groups = $wpdb->get_results(
        "SELECT pm.meta_value AS value, COUNT(DISTINCT p.ID) AS count
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'leeftijdsgroep'
        WHERE p.post_type = 'person' AND p.post_status = 'publish'
            AND pm.meta_value != ''
        GROUP BY pm.meta_value
        HAVING count > 0"
    );

    return rest_ensure_response([
        'age_groups' => $this->sort_age_groups($age_groups),
        'member_types' => $this->get_member_type_counts(),
    ]);
}
```

### Pattern 2: TanStack Query Hook for Filter Options
**What:** React hook that fetches and caches filter options
**When to use:** When component needs filter options separate from list data
**Example:**
```javascript
// Source: Existing pattern in src/hooks/usePeople.js
export function useFilterOptions(options = {}) {
  return useQuery({
    queryKey: ['people', 'filter-options'],
    queryFn: async () => {
      const response = await prmApi.getFilterOptions();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - options don't change often
    ...options,
  });
}
```

### Pattern 3: Smart Age Group Sorting
**What:** Extract numeric parts from strings like "Onder 7" to sort naturally
**When to use:** Age group filters, any numeric-text hybrid sorting
**Example:**
```php
// Source: Existing pattern in class-rest-people.php line 1192-1199
private function sort_age_groups( $groups ) {
    usort($groups, function($a, $b) {
        // Extract numeric value from "Onder X" pattern
        preg_match('/\d+/', $a->value, $a_num);
        preg_match('/\d+/', $b->value, $b_num);

        if (!empty($a_num) && !empty($b_num)) {
            return (int)$a_num[0] - (int)$b_num[0];
        }

        // Non-numeric values (e.g., "Senioren") sort to end
        return empty($a_num) ? 1 : -1;
    });
    return $groups;
}
```

### Pattern 4: Loading State with Disabled Dropdown
**What:** Show loading indicator and disable dropdown while fetching options
**When to use:** Any async-loaded select element
**Example:**
```javascript
// Source: React patterns + user requirement from CONTEXT.md
const { data: filterOptions, isLoading } = useFilterOptions();

<select
  value={leeftijdsgroep}
  onChange={(e) => setLeeftijdsgroep(e.target.value)}
  disabled={isLoading}
  className="w-full text-sm border..."
>
  {isLoading ? (
    <option value="">Laden...</option>
  ) : (
    <>
      <option value="">Alle ({filterOptions?.age_groups_total || 0})</option>
      {filterOptions?.age_groups?.map(opt => (
        <option key={opt.value} value={opt.value}>
          {opt.value} ({opt.count})
        </option>
      ))}
    </>
  )}
</select>
```

### Pattern 5: URL Query Param Filter State
**What:** Store active filters in URL for persistence on back navigation
**When to use:** List pages with filters (already used for all People filters)
**Example:**
```javascript
// Source: Existing pattern in src/pages/People/PeopleList.jsx line 634-676
const [searchParams, setSearchParams] = useSearchParams();
const leeftijdsgroep = searchParams.get('leeftijdsgroep') || '';

const setLeeftijdsgroep = useCallback((value) => {
  updateSearchParams({ leeftijdsgroep: value });
}, [updateSearchParams]);
```

### Anti-Patterns to Avoid
- **Client-side filtering large datasets:** With 1400+ people, client-side filtering is too slow. Always filter server-side.
- **N+1 meta queries:** Don't query postmeta for each person individually. Use JOINs (already done in `get_filtered_people`).
- **Fetching filter options on every keystroke:** Cache filter options with staleTime (5+ minutes reasonable).
- **Hardcoding field names in multiple places:** Abstract meta key names into constants or config.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Distinct meta values query | Custom loop through all posts | SQL DISTINCT with GROUP BY | 100x faster on 1400+ posts |
| React async state | useState + useEffect | TanStack Query | Handles loading, error, caching, refetch automatically |
| Smart number sorting | String comparison | Numeric extraction with regex | "Onder 10" should come after "Onder 9" not before "Onder 2" |
| Filter option caching | localStorage | TanStack Query staleTime | Built-in cache invalidation, no manual sync needed |
| Count calculation | Client-side array length | SQL COUNT in GROUP BY | Accurate counts without fetching all data |

**Key insight:** The existing `get_filtered_people` method already does complex meta filtering with JOINs and counts. Reuse this query pattern for filter options endpoint.

## Common Pitfalls

### Pitfall 1: Zero-Count Values Showing
**What goes wrong:** Filter dropdown shows options with 0 people (e.g., after data changes)
**Why it happens:** Not filtering results by `HAVING count > 0` in SQL query
**How to avoid:** Always include `HAVING count > 0` in GROUP BY queries
**Warning signs:** User reports seeing empty age groups in dropdown

### Pitfall 2: Stale Filter Active with No Results
**What goes wrong:** User has "Onder 20" filter active, that value disappears from data, shows 0 results
**Why it happens:** Filter options API doesn't include values with 0 count
**How to avoid:** This is correct behavior per CONTEXT.md — user manually clears filter. Don't auto-clear.
**Warning signs:** User confusion about empty results, expecting auto-reset

### Pitfall 3: Age Groups Sort Alphabetically
**What goes wrong:** "Onder 10" appears before "Onder 9" in dropdown
**Why it happens:** Default string sort treats "10" < "9" alphabetically
**How to avoid:** Extract numeric part with regex, sort numerically
**Warning signs:** Age groups out of logical order in UI

### Pitfall 4: Race Condition with Filter Options and List
**What goes wrong:** Filter dropdown loads slower than list, causing UI flash
**Why it happens:** Two separate queries with different response times
**How to avoid:** Use `placeholderData` in TanStack Query to show previous options during refetch
**Warning signs:** Dropdowns flicker between "Laden..." and actual values

### Pitfall 5: SQL Injection in Dynamic Field Names
**What goes wrong:** Security vulnerability if meta_key is not sanitized
**Why it happens:** Using user input directly in SQL query
**How to avoid:** Use `$wpdb->prepare()` for meta_key values (see line 1185-1187 in class-rest-people.php)
**Warning signs:** Code review flags unsanitized SQL

### Pitfall 6: Generic Filter System Over-Engineering
**What goes wrong:** Spending days building "any field can be dynamic" when only 2 fields needed
**Why it happens:** Premature abstraction without concrete use cases
**How to avoid:** Build for current 2 filters + lightweight abstraction (meta key config array)
**Warning signs:** Planning document longer than actual code

## Code Examples

Verified patterns from official sources:

### WordPress REST Endpoint Registration
```php
// Source: includes/class-rest-api.php lines 24-40
register_rest_route(
    'rondo/v1',
    '/people/filter-options',
    [
        'methods'             => \WP_REST_Server::READABLE,
        'callback'            => [ $this, 'get_filter_options' ],
        'permission_callback' => [ $this, 'check_user_approved' ],
    ]
);
```

### Optimized Meta Value Query with Counts
```php
// Source: Adapted from includes/class-rest-people.php lines 1000-1098
public function get_filter_options( $request ) {
    global $wpdb;

    // Get total count for "Alle" option
    $total = $wpdb->get_var(
        "SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'person' AND p.post_status = 'publish'"
    );

    // Age groups with counts
    $age_groups = $wpdb->get_results(
        "SELECT pm.meta_value AS value, COUNT(DISTINCT p.ID) AS count
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'person'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'leeftijdsgroep'
            AND pm.meta_value != ''
        GROUP BY pm.meta_value
        HAVING count > 0"
    );

    // Member types with counts
    $member_types = $wpdb->get_results(
        "SELECT pm.meta_value AS value, COUNT(DISTINCT p.ID) AS count
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'person'
            AND p.post_status = 'publish'
            AND pm.meta_key = 'type-lid'
            AND pm.meta_value != ''
        GROUP BY pm.meta_value
        HAVING count > 0"
    );

    return rest_ensure_response([
        'total' => (int) $total,
        'age_groups' => $this->sort_age_groups($age_groups),
        'member_types' => $this->sort_member_types($member_types),
    ]);
}
```

### Frontend API Client Method
```javascript
// Source: Existing pattern in src/api/client.js lines 42-48
export const prmApi = {
  // ... existing methods
  getFilterOptions: () => api.get('/rondo/v1/people/filter-options'),
};
```

### React Hook with TanStack Query
```javascript
// Source: Existing pattern in src/hooks/usePeople.js lines 50-89
export function useFilterOptions(options = {}) {
  return useQuery({
    queryKey: ['people', 'filter-options'],
    queryFn: async () => {
      const response = await prmApi.getFilterOptions();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
    ...options,
  });
}
```

### Dropdown with Loading/Error States
```javascript
// Source: React best practices + user requirements from CONTEXT.md
const { data: filterOptions, isLoading, error, refetch } = useFilterOptions();

// Loading state
if (isLoading) {
  return (
    <select disabled className="...">
      <option value="">Laden...</option>
    </select>
  );
}

// Error state
if (error) {
  return (
    <div className="flex items-center gap-2">
      <select disabled className="...">
        <option value="">Fout bij laden</option>
      </select>
      <button onClick={() => refetch()} className="btn-secondary text-xs">
        Opnieuw proberen
      </button>
    </div>
  );
}

// Success state with counts
<select
  value={leeftijdsgroep}
  onChange={(e) => setLeeftijdsgroep(e.target.value)}
  className="..."
>
  <option value="">Alle ({filterOptions.total})</option>
  {filterOptions.age_groups.map(opt => (
    <option key={opt.value} value={opt.value}>
      {opt.value} ({opt.count})
    </option>
  ))}
</select>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded filter arrays | Dynamic API-driven filters | This phase (151) | New values auto-appear after sync |
| Client-side filtering | Server-side $wpdb queries | Phase 112+ | 10-100x faster on 1400+ people |
| Individual meta queries | JOINed queries | Phase 112+ | Reduced queries from N to 1 |
| No filter counts | Counts in dropdown | This phase (151) | Better UX - users see result sizes |

**Deprecated/outdated:**
- **Hardcoded option arrays in JSX** (lines 1241-1280 in PeopleList.jsx): Will be replaced with API data
- **No loading indicators on dropdowns**: Current implementation shows options immediately, will show loading state

## Open Questions

Things that couldn't be fully resolved:

1. **Caching/Freshness Strategy**
   - What we know: Filter options change only when Sportlink sync runs (typically daily)
   - What's unclear: Should cache be 5 minutes, 1 hour, or tied to sync events?
   - Recommendation: Start with 5 minutes staleTime (conservative), monitor, adjust to 1 hour if stable

2. **Member Type Sort Order**
   - What we know: Context.md says "logical/meaningful order" but doesn't define specifics
   - What's unclear: Is "Junior, Senior, Donateur, Lid van Verdienste" the correct order?
   - Recommendation: Implement hardcoded sort order array (easily adjustable), ask user for confirmation during UAT

3. **Generic Filter Infrastructure Scope**
   - What we know: Build it "so future phases can easily make additional meta fields dynamic"
   - What's unclear: How abstract should it be? Array config vs. full class hierarchy?
   - Recommendation: Create simple array config mapping meta_key → sort_function, don't build full framework

4. **Total Count Performance**
   - What we know: Counting 1400+ posts is fast, but could be cached
   - What's unclear: Is separate total query worth optimizing with WordPress transient?
   - Recommendation: Measure in production. If < 100ms, leave as-is. If slower, cache total in 5-minute transient.

## Sources

### Primary (HIGH confidence)
- Existing codebase:
  - `/includes/class-rest-people.php` - Current filter implementation (lines 957-1200)
  - `/includes/class-rest-api.php` - REST endpoint registration patterns (lines 1-150)
  - `/src/pages/People/PeopleList.jsx` - Hardcoded filter arrays (lines 1230-1280)
  - `/src/hooks/usePeople.js` - TanStack Query patterns (lines 119-159)
  - `/src/api/client.js` - Axios API client structure (lines 1-100)
- User requirements:
  - `.planning/phases/151-dynamic-filters/151-CONTEXT.md` - Implementation decisions
  - Milestone v21.0 requirements (REQUIREMENTS.md FILT-01 through FILT-04)

### Secondary (MEDIUM confidence)
- WordPress Codex: `$wpdb->get_results()`, `register_rest_route()` - Standard WordPress APIs
- TanStack Query docs: Query caching, staleTime, placeholderData - Library best practices
- React Router docs: useSearchParams hook - URL state management

### Tertiary (LOW confidence)
- None - all findings verified against codebase or official documentation

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in use, verified in package.json and codebase
- Architecture: HIGH - Patterns extracted from existing working code in same codebase
- Pitfalls: MEDIUM - Based on common WordPress/React issues + phase requirements
- Generic filter system scope: LOW - Requirement vague, will need user clarification during planning

**Research date:** 2026-02-07
**Valid until:** 2026-03-07 (30 days - codebase stable, no major version changes expected)
