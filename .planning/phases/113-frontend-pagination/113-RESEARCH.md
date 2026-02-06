# Phase 113: Frontend Pagination - Research

**Researched:** 2026-01-29
**Domain:** React pagination UI, TanStack Query pagination patterns, custom field sorting
**Confidence:** HIGH

## Summary

This phase implements traditional pagination controls for the People list, switching from client-side "load all then filter" to server-side paginated queries using the `/rondo/v1/people/filtered` endpoint built in Phase 111. The frontend will use `useQuery` (not `useInfiniteQuery`) since we're implementing traditional pagination with page numbers, not infinite scroll.

Key challenges:
1. Integrating pagination with existing filter UI (labels, ownership, modified date, birth year)
2. Implementing custom ACF field sorting (DATA-09) on the backend
3. Managing TanStack Query cache across page transitions
4. Building responsive pagination controls that work on mobile
5. Preserving filter state when navigating between pages

**Primary recommendation:** Use `useFilteredPeople` hook (already implemented in Phase 111) with page parameter, add pagination controls component at bottom of list, implement custom field sorting by extending the filtered endpoint's ORDER BY clause.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| TanStack Query | ^5.17.0 | Server state management | Already used throughout frontend, handles caching/pagination |
| React | ^18.2.0 | UI framework | Project foundation |
| Lucide React | ^0.309.0 | Icon library | Used for UI icons (ChevronLeft, ChevronRight, etc.) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| React Router | ^6.21.0 | URL state management | Optional - for syncing page to URL params |
| Tailwind CSS | ^3.4.0 | Styling | Already established for UI components |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| useQuery with page param | useInfiniteQuery | Infinite query is for infinite scroll - we're using traditional pagination |
| Traditional pagination | Infinite scroll | Phase context explicitly chose traditional pagination for goal-oriented tasks |
| Page number buttons | Only prev/next | Context says "full pagination with prev/next AND page numbers" |
| Client-side URL state | Local state only | URL state enables shareable links (out of scope for now but easy to add) |

**Installation:**
```bash
# No additional packages needed
# All dependencies already installed in package.json
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── hooks/
│   └── usePeople.js                 # Already has useFilteredPeople hook
├── pages/People/
│   └── PeopleList.jsx               # Add pagination controls here
└── components/
    └── Pagination.jsx               # New: reusable pagination component
```

### Pattern 1: TanStack Query with Page Parameter
**What:** Use existing `useFilteredPeople` hook, pass page number in filters object
**When to use:** Always - this is the core data fetching pattern
**Example:**
```javascript
// Source: src/hooks/usePeople.js line 104-129 (existing implementation)
// Already implemented in Phase 111, just need to use it with page param

export function useFilteredPeople(filters = {}) {
  const params = {
    page: filters.page || 1,
    per_page: filters.perPage || 100,
    labels: filters.labels || [],
    ownership: filters.ownership || 'all',
    modified_days: filters.modifiedDays || null,
    birth_year_from: filters.birthYearFrom || null,
    birth_year_to: filters.birthYearTo || null,
    orderby: filters.orderby || 'first_name',
    order: filters.order || 'asc',
  };

  return useQuery({
    queryKey: peopleKeys.filtered(params),
    queryFn: async () => {
      const response = await prmApi.getFilteredPeople(params);
      return response.data;
    },
    placeholderData: (previousData) => previousData, // Keep prev data while fetching
  });
}

// Usage in PeopleList:
const [page, setPage] = useState(1);
const [filters, setFilters] = useState({
  labels: [],
  ownership: 'all',
  birthYearFrom: null,
  orderby: 'first_name',
  order: 'asc',
});

const { data, isLoading } = useFilteredPeople({
  ...filters,
  page
});

// data.people: array of person objects
// data.total: total count
// data.page: current page
// data.total_pages: total number of pages
```

### Pattern 2: Pagination Controls Component
**What:** Reusable pagination UI with prev/next and page numbers
**When to use:** At bottom of any paginated list
**Example:**
```javascript
// New component: src/components/Pagination.jsx
import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function Pagination({
  currentPage,
  totalPages,
  onPageChange,
  itemsPerPage = 100,
  totalItems = 0,
}) {
  // Calculate range of page numbers to display
  const getPageNumbers = () => {
    const maxVisible = 7; // e.g., [1, 2, 3, ..., 12, 13, 14]

    if (totalPages <= maxVisible) {
      return Array.from({ length: totalPages }, (_, i) => i + 1);
    }

    // Show: first, ..., current-1, current, current+1, ..., last
    const pages = [];
    pages.push(1);

    if (currentPage > 3) {
      pages.push('...');
    }

    for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
      pages.push(i);
    }

    if (currentPage < totalPages - 2) {
      pages.push('...');
    }

    pages.push(totalPages);

    return pages;
  };

  const pages = getPageNumbers();
  const startItem = (currentPage - 1) * itemsPerPage + 1;
  const endItem = Math.min(currentPage * itemsPerPage, totalItems);

  return (
    <div className="flex flex-col sm:flex-row items-center justify-between gap-4 py-4">
      {/* Info text */}
      <div className="text-sm text-gray-600 dark:text-gray-400">
        Showing {startItem}-{endItem} of {totalItems.toLocaleString()} people
      </div>

      {/* Page controls */}
      <div className="flex items-center gap-2">
        {/* Previous button */}
        <button
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage === 1}
          className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
          aria-label="Previous page"
        >
          <ChevronLeft className="w-4 h-4" />
        </button>

        {/* Page numbers */}
        {pages.map((page, idx) => (
          page === '...' ? (
            <span key={`ellipsis-${idx}`} className="px-2 text-gray-500">
              ...
            </span>
          ) : (
            <button
              key={page}
              onClick={() => onPageChange(page)}
              className={`px-3 py-1.5 text-sm rounded-lg transition-colors ${
                page === currentPage
                  ? 'bg-accent-600 text-white'
                  : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200'
              }`}
              aria-label={`Go to page ${page}`}
              aria-current={page === currentPage ? 'page' : undefined}
            >
              {page}
            </button>
          )
        ))}

        {/* Next button */}
        <button
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage === totalPages}
          className="btn-secondary disabled:opacity-50 disabled:cursor-not-allowed"
          aria-label="Next page"
        >
          <ChevronRight className="w-4 h-4" />
        </button>
      </div>

      {/* Page info (mobile) */}
      <div className="text-sm text-gray-600 dark:text-gray-400 sm:hidden">
        Page {currentPage} of {totalPages}
      </div>
    </div>
  );
}
```

### Pattern 3: Filter Changes Reset to Page 1
**What:** When any filter changes, reset page to 1
**When to use:** Always - prevents showing "Page 5 of 2" after filtering
**Example:**
```javascript
// In PeopleList.jsx
const [page, setPage] = useState(1);
const [filters, setFilters] = useState({
  labels: [],
  ownership: 'all',
  // ... other filters
});

// Reset page when filters change
useEffect(() => {
  setPage(1);
}, [
  filters.labels,
  filters.ownership,
  filters.modifiedDays,
  filters.birthYearFrom,
  filters.birthYearTo,
  filters.orderby,
  filters.order,
]);

// Or use a single filter change handler
const handleFilterChange = (newFilters) => {
  setFilters(newFilters);
  setPage(1); // Always reset to page 1
};
```

### Pattern 4: Custom Field Sorting (Backend)
**What:** Extend filtered endpoint to support sorting by custom ACF fields
**When to use:** For DATA-09 requirement
**Example:**
```php
// In includes/class-rest-people.php, get_filtered_people() method
// Add validation for custom field sorting

// In route args:
'orderby' => [
    'default'           => 'first_name',
    'validate_callback' => function($param) {
        $allowed_post_fields = ['first_name', 'last_name', 'modified'];

        // Check if it's a custom field (starts with 'custom_')
        if (strpos($param, 'custom_') === 0) {
            $field_name = substr($param, 7); // Remove 'custom_' prefix

            // Validate field exists and is active
            $manager = new \Stadion\CustomFields\Manager();
            $field = $manager->get_field('person', $field_name);

            if (!$field || !$field['active']) {
                return false;
            }

            // Only allow sortable field types
            $sortable_types = ['text', 'textarea', 'number', 'date', 'select'];
            return in_array($field['type'], $sortable_types);
        }

        return in_array($param, $allowed_post_fields, true);
    },
],

// In query building:
if (strpos($orderby, 'custom_') === 0) {
    $field_name = substr($orderby, 7);

    // Get field type to determine sort method
    $manager = new \Stadion\CustomFields\Manager();
    $field = $manager->get_field('person', $field_name);

    // JOIN custom field meta
    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s";
    $prepare_values[] = $field_name;

    // Sort based on field type
    if ($field['type'] === 'number') {
        // Cast to numeric for proper number sorting
        $order_clause = "ORDER BY CAST(cf.meta_value AS DECIMAL(10,2)) $order";
    } elseif ($field['type'] === 'date') {
        // Cast to date for proper date sorting
        $order_clause = "ORDER BY STR_TO_DATE(cf.meta_value, '%Y%m%d') $order";
    } else {
        // Text-based sorting
        $order_clause = "ORDER BY cf.meta_value $order";
    }
}
```

### Pattern 5: Loading States
**What:** Show skeleton/spinner while fetching page data
**When to use:** Always - provides visual feedback during navigation
**Example:**
```javascript
// In PeopleList.jsx
const { data, isLoading, isFetching } = useFilteredPeople({ ...filters, page });

// Show skeleton on initial load
if (isLoading) {
  return (
    <div className="space-y-2">
      {Array.from({ length: 10 }).map((_, i) => (
        <div key={i} className="card p-4 animate-pulse">
          <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
        </div>
      ))}
    </div>
  );
}

// Show subtle indicator when fetching new page (placeholderData keeps prev data visible)
return (
  <div className="relative">
    {isFetching && (
      <div className="absolute top-0 right-0 mt-2 mr-2">
        <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-accent-600" />
      </div>
    )}

    <PersonListView people={data.people} />

    <Pagination
      currentPage={data.page}
      totalPages={data.total_pages}
      totalItems={data.total}
      onPageChange={setPage}
    />
  </div>
);
```

### Pattern 6: Empty States
**What:** Show helpful message when no results match filters
**When to use:** When `data.people.length === 0`
**Example:**
```javascript
// In PeopleList.jsx
if (!isLoading && data?.people.length === 0) {
  // Check if filters are active
  const hasActiveFilters =
    filters.labels.length > 0 ||
    filters.ownership !== 'all' ||
    filters.modifiedDays !== null ||
    filters.birthYearFrom !== null;

  if (hasActiveFilters) {
    // No results with filters
    return (
      <div className="card p-12 text-center">
        <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
          <Filter className="w-6 h-6 text-gray-400 dark:text-gray-500" />
        </div>
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">
          No people match your filters
        </h3>
        <p className="text-gray-500 dark:text-gray-400 mb-4">
          Try adjusting your filters to see more results.
        </p>
        <button onClick={clearFilters} className="btn-secondary">
          Clear all filters
        </button>
      </div>
    );
  } else {
    // No people at all (should be rare)
    return (
      <div className="card p-12 text-center">
        <h3 className="text-lg font-medium text-gray-900 dark:text-gray-50 mb-1">
          No people found
        </h3>
        <p className="text-gray-500 dark:text-gray-400 mb-4">
          Add your first person to get started.
        </p>
        <button onClick={() => setShowPersonModal(true)} className="btn-primary">
          Add Person
        </button>
      </div>
    );
  }
}
```

### Anti-Patterns to Avoid
- **Using useInfiniteQuery:** We're implementing traditional pagination, not infinite scroll
- **Not resetting page on filter changes:** Results in "Page 10 of 3" errors
- **Fetching all pages on mount:** Defeats the purpose of pagination - only fetch current page
- **Manual cache management:** Let TanStack Query handle cache via queryKey changes
- **Pagination controls at top only:** Context specifies "bottom of list only"
- **Not using placeholderData:** Causes jarring flash when navigating pages (should keep previous data visible)
- **Invalidating queries after navigation:** Use placeholderData instead - invalidate only after mutations

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Page number generation | Manual array building | Existing ellipsis pattern | Complex edge cases (1 page, 2 pages, many pages) |
| Query key management | Manual cache keys | TanStack Query queryKey | Automatic cache invalidation and deduplication |
| Loading states | Custom loading flags | isFetching, isLoading | TanStack Query provides granular loading states |
| Pagination UI | Custom component | Consider existing patterns | Check if other lists (todos, dates) use pagination |
| Number formatting | Manual toLocaleString() | Existing formatters | Project may have utility functions |
| ACF field validation | Custom checks | CustomFields Manager API | Already has field validation/lookup methods |

**Key insight:** TanStack Query v5 uses `placeholderData` (not `keepPreviousData`) to show previous data while fetching new page. This prevents UI flash during page transitions.

## Current Implementation Analysis

### Existing Code
The current PeopleList component (src/pages/People/PeopleList.jsx):
- Uses `usePeople()` hook which fetches ALL people in a loop (lines 440, 49-86)
- Filters and sorts client-side in `useMemo` (lines 546-802)
- No pagination - displays all filtered results at once
- Sort controls at top (lines 811-835)
- Filter dropdown with chips (lines 837-1031)

### What Needs to Change
1. **Switch to useFilteredPeople hook:** Replace `usePeople()` with `useFilteredPeople({ ...filters, page })`
2. **Remove client-side filtering:** Backend handles all filtering now
3. **Remove client-side sorting (mostly):** Backend handles standard sorts, but custom field sorting needs backend support
4. **Add page state:** `const [page, setPage] = useState(1)`
5. **Add pagination component:** At bottom of list (after table, line ~1145)
6. **Add filter reset logic:** Reset page to 1 when filters change
7. **Update sort handler:** Pass orderby/order to backend instead of sorting locally

### What Can Stay
1. Filter UI (labels, birth year, modified date, ownership) - just change where values go
2. Sort controls UI - just change handler to update filters instead of local state
3. Table structure and styling
4. Selection logic (bulk operations)
5. Modals (person edit, bulk operations)

## Performance Considerations

### Query Optimization Strategy
1. **TanStack Query caching:** Automatically caches pages by query key - no manual cache management needed
2. **placeholderData:** Keeps previous data visible while fetching new page (smooth transition)
3. **Prefetching (optional):** Could prefetch next page on hover/focus for instant navigation
4. **Custom field sorting:** Each custom field adds a JOIN - monitor performance with Query Monitor

### Expected Frontend Performance
| Action | Expected Time | Notes |
|--------|---------------|-------|
| Initial page load | <0.2s | Backend query + network + render |
| Page navigation | <0.15s | Cache hit OR backend query (with placeholderData, no UI flash) |
| Filter change | <0.2s | Reset to page 1, new backend query |
| Sort change | <0.15s | Stay on same page, re-query with new sort |

### TanStack Query Configuration
```javascript
// Current configuration (from App.jsx or main.jsx - should verify)
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30000, // 30 seconds (per ROADMAP.md)
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

// For filtered people specifically (in usePeople.js):
export function useFilteredPeople(filters = {}) {
  return useQuery({
    queryKey: peopleKeys.filtered(params),
    queryFn: async () => { ... },
    staleTime: 30000, // Matches global config
    placeholderData: (previousData) => previousData, // Keep prev data visible
  });
}
```

## Common Pitfalls

### Pitfall 1: Not Resetting Page on Filter Changes
**What goes wrong:** User is on page 10, applies filter that only has 2 pages of results, sees empty page
**Why it happens:** Page state isn't synchronized with filter state
**How to avoid:** Use `useEffect` to reset page to 1 whenever filters change
**Warning signs:** Empty pages after filtering, "Page X of Y" where X > Y

### Pitfall 2: Using useInfiniteQuery Instead of useQuery
**What goes wrong:** Infinite query accumulates all pages, doesn't match pagination UI pattern
**Why it happens:** Misunderstanding of TanStack Query pagination patterns
**How to avoid:** Use `useQuery` with page parameter for traditional pagination
**Warning signs:** `data.pages` array instead of single page object, memory grows with each page view

### Pitfall 3: Not Handling Empty Results
**What goes wrong:** Blank screen when no results match filters
**Why it happens:** No empty state UI
**How to avoid:** Check `data.people.length === 0` and show helpful message with clear filters button
**Warning signs:** User confusion when filters eliminate all results

### Pitfall 4: Missing Loading States
**What goes wrong:** Page appears frozen during navigation, no visual feedback
**Why it happens:** Not using `isFetching` indicator
**How to avoid:** Show subtle spinner when `isFetching === true` (placeholderData keeps content visible)
**Warning signs:** User clicks multiple times thinking it didn't work

### Pitfall 5: Too Many Custom Field JOINs
**What goes wrong:** Custom field sorting is slow with many people
**Why it happens:** Each custom field sort adds a meta JOIN
**How to avoid:** Monitor query performance, consider limiting sortable custom fields or adding indexes
**Warning signs:** Backend queries >0.1s (check Query Monitor)

### Pitfall 6: Breaking Bulk Selection Across Pages
**What goes wrong:** Selecting people on page 1, navigating to page 2, selection is lost or shows wrong people
**Why it happens:** Selection state not properly scoped to person IDs
**How to avoid:** Store selection as Set of person IDs (not indexes), clear selection on filter/sort changes
**Warning signs:** Selected IDs don't match visible people after navigation

## Integration Points

### Backend Integration
- **Endpoint:** `/rondo/v1/people/filtered` (implemented in Phase 111)
- **Additional work needed:** Add custom field sorting support (DATA-09)
- **Custom Fields Manager:** Use `\Stadion\CustomFields\Manager` to validate sortable fields

### Frontend Hook Integration
```javascript
// Already exists: src/hooks/usePeople.js line 104-129
// Just need to add custom field sorting parameter

export function useFilteredPeople(filters = {}) {
  const params = {
    page: filters.page || 1,
    per_page: filters.perPage || 100,
    labels: filters.labels || [],
    ownership: filters.ownership || 'all',
    modified_days: filters.modifiedDays || null,
    birth_year_from: filters.birthYearFrom || null,
    birth_year_to: filters.birthYearTo || null,
    orderby: filters.orderby || 'first_name',
    order: filters.order || 'asc',
    // Custom field sorting: orderby can be 'custom_<field_name>'
  };

  return useQuery({
    queryKey: peopleKeys.filtered(params),
    queryFn: async () => {
      const response = await prmApi.getFilteredPeople(params);
      return response.data;
    },
    placeholderData: (previousData) => previousData,
  });
}
```

### Custom Field Sorting Integration
Need to fetch available custom fields to populate sort dropdown:
```javascript
// Already exists: src/hooks/usePeople.js or create new hook
const { data: customFields } = useQuery({
  queryKey: ['custom-fields-metadata', 'person'],
  queryFn: async () => {
    const response = await prmApi.getCustomFieldsMetadata('person');
    return response.data;
  },
});

// Filter to sortable fields
const sortableFields = customFields?.filter(f =>
  ['text', 'textarea', 'number', 'date', 'select'].includes(f.type)
) || [];

// Add to sort dropdown
<select value={sortField} onChange={(e) => setSortField(e.target.value)}>
  <option value="first_name">First Name</option>
  <option value="last_name">Last Name</option>
  <option value="modified">Last Modified</option>
  <option value="organization">Team</option>
  <option value="labels">Labels</option>
  {sortableFields.map(field => (
    <option key={field.name} value={`custom_${field.name}`}>
      {field.label}
    </option>
  ))}
</select>
```

## Testing Strategy

### Manual Testing Checklist
1. **Pagination:**
   - Page 1 displays first 100 people
   - Page 2 displays next 100 people
   - Last page displays remaining people (<100 if total not divisible by 100)
   - Page numbers update correctly
   - "Showing X-Y of Z people" is accurate
   - Prev button disabled on page 1
   - Next button disabled on last page

2. **Filter Integration:**
   - Changing label filter resets to page 1
   - Changing ownership filter resets to page 1
   - Changing modified date filter resets to page 1
   - Changing birth year filter resets to page 1
   - Filter chips display correctly
   - Clear filters button resets all filters and page

3. **Sorting:**
   - Sort by first name (asc/desc) works
   - Sort by last name (asc/desc) works
   - Sort by modified date (asc/desc) works
   - Sort by custom text field works
   - Sort by custom number field works (numeric sort, not alphabetic)
   - Sort by custom date field works (chronological order)
   - Changing sort stays on current page (doesn't reset to page 1)

4. **Loading States:**
   - Initial load shows skeleton
   - Page navigation shows subtle loading indicator
   - Previous data stays visible during navigation (no flash)
   - Filter changes show loading state

5. **Empty States:**
   - No people at all shows "Add first person" message
   - No results with filters shows "Adjust filters" message
   - Clear filters button works from empty state

6. **Bulk Selection:**
   - Selecting people on one page works
   - Selection persists when navigating pages (if IDs stored correctly)
   - OR selection clears when navigating pages (if that's the design choice)
   - Bulk operations work with selected people across pages

7. **Mobile:**
   - Pagination controls are usable on mobile
   - Page numbers collapse appropriately
   - "Showing X-Y of Z" is readable on small screens

### Performance Testing
1. Navigate through 14+ pages (1400+ people) - should be fast
2. Change filters while on page 10 - should reset to page 1 quickly
3. Rapidly click page numbers - should not cause race conditions
4. Check Network tab - should see single request per page change
5. Check TanStack Query DevTools - cache should grow with each page visited

## Implementation Checklist

### Backend (Custom Field Sorting)
- [ ] Update `/rondo/v1/people/filtered` route args to accept `custom_<field_name>` in orderby
- [ ] Validate custom field exists and is active using CustomFields Manager
- [ ] Restrict sortable types to: text, textarea, number, date, select
- [ ] Add conditional JOIN for custom field meta when orderby starts with 'custom_'
- [ ] Use type-specific CAST for number/date sorting (DECIMAL, STR_TO_DATE)
- [ ] Test sorting performance with Query Monitor
- [ ] Document custom field sorting in endpoint comments

### Frontend (Pagination UI)
- [ ] Create `src/components/Pagination.jsx` component
- [ ] Add prev/next buttons with proper disabled states
- [ ] Implement page number display with ellipsis for many pages
- [ ] Add "Showing X-Y of Z" info text
- [ ] Make responsive for mobile (collapse page numbers, stack info text)
- [ ] Add accessibility attributes (aria-label, aria-current)

### Frontend (PeopleList Integration)
- [ ] Replace `usePeople()` with `useFilteredPeople({ ...filters, page })`
- [ ] Add `const [page, setPage] = useState(1)`
- [ ] Remove client-side filtering logic (filteredAndSortedPeople useMemo)
- [ ] Remove client-side sorting logic for standard fields
- [ ] Add useEffect to reset page to 1 when filters change
- [ ] Update sort handler to pass orderby/order to backend
- [ ] Add Pagination component at bottom of list
- [ ] Update loading state to use placeholderData pattern
- [ ] Add empty state for no results with filters
- [ ] Fetch custom fields metadata for sort dropdown
- [ ] Add custom fields to sort dropdown
- [ ] Test bulk selection across pages (decide: persist or clear)

### Testing
- [ ] Test pagination with 1400+ people (all pages load correctly)
- [ ] Test filter changes reset to page 1
- [ ] Test all sort options (including custom fields)
- [ ] Test empty states (no people, no filtered results)
- [ ] Test loading states (initial, navigation, filter changes)
- [ ] Test mobile responsiveness
- [ ] Test accessibility (keyboard navigation, screen readers)
- [ ] Performance test: page navigation should be <0.2s
- [ ] Performance test: custom field sorting should be <0.15s

## Questions for Planning

1. **Bulk selection across pages:** Should selection persist when navigating between pages, or clear?
   - **Recommendation:** Clear selection on page change (simpler, matches most UIs)

2. **Custom field sort dropdown:** Add to existing sort control, or separate control?
   - **Recommendation:** Add to existing select dropdown (keeps UI compact)

3. **Prefetching:** Should we prefetch next page on hover/focus?
   - **Recommendation:** No - adds complexity, current performance is good enough

4. **URL state:** Should current page be synced to URL query params?
   - **Recommendation:** No for now - out of scope, but easy to add later (REQUIREMENTS.md says it's a future feature)

5. **Items per page selector:** Fixed 100 or add dropdown (25/50/100/200)?
   - **Recommendation:** Fixed 100 (context says "Claude's discretion" - simpler is better)

## Open Questions

1. How should custom number fields with null values sort? (SQL CAST handles null as 0)
   - **Likely answer:** Sort nulls last (modify ORDER BY to use COALESCE or IS NULL check)

2. Should we keep the current sortedPeople logic for client-side custom field sorting as fallback?
   - **Likely answer:** No - fully migrate to backend sorting for consistency

3. What happens if user has page 5 bookmarked but only 2 pages of results exist?
   - **Likely answer:** Show empty state OR automatically redirect to last valid page

4. Should clicking on a column header sort by that column (like in table apps)?
   - **Likely answer:** Not in this phase - context shows explicit sort dropdown already exists

5. Should we add keyboard shortcuts for page navigation (arrow keys)?
   - **Likely answer:** Nice to have, but not in requirements - defer to future

## References

**Codebase:**
- Current PeopleList: `src/pages/People/PeopleList.jsx` (full component, needs refactoring)
- useFilteredPeople hook: `src/hooks/usePeople.js` line 104-129 (already implemented)
- Filtered endpoint: `includes/class-rest-people.php` line 914-1082 (needs custom field sorting)
- Custom Fields Manager: `includes/class-custom-fields-manager.php` (field validation)
- Query keys: `src/hooks/usePeople.js` line 8-17 (peopleKeys structure)

**TanStack Query Documentation:**
- useQuery: https://tanstack.com/query/latest/docs/react/reference/useQuery
- Pagination: https://tanstack.com/query/latest/docs/react/guides/paginated-queries
- placeholderData: https://tanstack.com/query/latest/docs/react/guides/placeholder-query-data

**React Patterns:**
- Pagination UI examples: Many open-source examples (Tailwind UI, Headless UI)
- Ellipsis algorithm: Common pattern for page number truncation

**Project References:**
- Phase 111 Research: `.planning/phases/111-server-side-foundation/111-RESEARCH.md`
- ROADMAP: `.planning/ROADMAP.md` (TanStack Query v5 patterns section)
- Phase context: `.planning/phases/113-frontend-pagination/113-CONTEXT.md`
- Requirements: `.planning/REQUIREMENTS.md` (PAGE-* and DATA-09)
