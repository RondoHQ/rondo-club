---
wave: 2
depends_on:
  - "1"
files_modified:
  - src/api/client.js
  - src/hooks/usePeople.js
autonomous: true
estimated_time: 20 minutes
---

# Plan 2: Frontend Hook Integration

## Goal

Add frontend integration for the new `/stadion/v1/people/filtered` endpoint by creating an API client method and a new `useFilteredPeople` hook that supports server-side filtering, sorting, and pagination.

## Context

The existing `usePeople` hook fetches ALL people in a loop (100 per page until done), then returns the complete array. This doesn't scale to 1400+ contacts.

The new `useFilteredPeople` hook will:
1. Call the new filtered endpoint with parameters
2. Return paginated results (not all at once)
3. Support filter/sort parameters in the query key for proper caching

**Important:** We create a NEW hook rather than modifying `usePeople` because:
- The PeopleList page will need different behavior (pagination UI)
- Other components may depend on `usePeople` returning all people
- Gradual migration is safer than breaking changes

## Tasks

<task id="1">
<title>Add getFilteredPeople to API client</title>
<file>src/api/client.js</file>
<action>edit</action>
<description>
Add a new method `getFilteredPeople` to the `prmApi` object. This method calls the new backend endpoint.

Find the `prmApi` object (around line 109) and add this new method. Add it after the `bulkUpdatePeople` method (around line 120), maintaining alphabetical-ish organization:

```javascript
// Filtered people with server-side pagination/filtering/sorting
getFilteredPeople: (params = {}) => api.get('/stadion/v1/people/filtered', { params }),
```

The params object will be passed directly as query parameters:
- `page` - Page number (default: 1)
- `per_page` - Results per page (default: 100, max: 100)
- `labels` - Array of label term IDs
- `ownership` - 'mine', 'shared', or 'all'
- `modified_days` - Only people modified within N days
- `orderby` - 'first_name', 'last_name', or 'modified'
- `order` - 'asc' or 'desc'

The response shape from the backend is:
```javascript
{
  people: [...],      // Array of person objects
  total: 1423,        // Total matching people
  page: 1,            // Current page
  total_pages: 15     // Total pages
}
```
</description>
</task>

<task id="2">
<title>Create useFilteredPeople hook</title>
<file>src/hooks/usePeople.js</file>
<action>edit</action>
<description>
Add a new `useFilteredPeople` hook that uses the filtered endpoint. Add this after the existing `usePeople` hook (around line 85) and before `usePerson`.

First, update the `peopleKeys` object at the top of the file to add a new key pattern for filtered queries:

```javascript
// Query keys
export const peopleKeys = {
  all: ['people'],
  lists: () => [...peopleKeys.all, 'list'],
  list: (filters) => [...peopleKeys.lists(), filters],
  filtered: (filters) => [...peopleKeys.all, 'filtered', filters],  // Add this line
  details: () => [...peopleKeys.all, 'detail'],
  detail: (id) => [...peopleKeys.details(), id],
  timeline: (id) => [...peopleKeys.detail(id), 'timeline'],
  dates: (id) => [...peopleKeys.detail(id), 'dates'],
  todos: (id) => [...peopleKeys.detail(id), 'todos'],
};
```

Then add the new hook after the existing `usePeople` hook:

```javascript
/**
 * Hook for fetching filtered and paginated people
 *
 * Uses the server-side filtering endpoint for efficient queries
 * on large datasets (1400+ contacts).
 *
 * @param {Object} filters - Filter and pagination options
 * @param {number} filters.page - Page number (default: 1)
 * @param {number} filters.perPage - Results per page (default: 100, max: 100)
 * @param {number[]} filters.labels - Array of label term IDs (OR logic)
 * @param {string} filters.ownership - 'mine', 'shared', or 'all' (default: 'all')
 * @param {number} filters.modifiedDays - Only people modified within N days
 * @param {string} filters.orderby - 'first_name', 'last_name', or 'modified' (default: 'first_name')
 * @param {string} filters.order - 'asc' or 'desc' (default: 'asc')
 * @returns {Object} TanStack Query result with data, isLoading, error, etc.
 */
export function useFilteredPeople(filters = {}) {
  // Normalize filter keys for backend (snake_case)
  const params = {
    page: filters.page || 1,
    per_page: filters.perPage || 100,
    labels: filters.labels || [],
    ownership: filters.ownership || 'all',
    modified_days: filters.modifiedDays || null,
    orderby: filters.orderby || 'first_name',
    order: filters.order || 'asc',
  };

  return useQuery({
    // Include all filter params in query key for proper cache separation
    queryKey: peopleKeys.filtered(params),
    queryFn: async () => {
      const response = await prmApi.getFilteredPeople(params);

      // Response is already in correct shape from backend:
      // { people: [...], total: N, page: N, total_pages: N }
      return response.data;
    },
    // Keep previous data while fetching new page for smoother UX
    placeholderData: (previousData) => previousData,
  });
}
```

Key design decisions:
1. **Separate query key** (`peopleKeys.filtered`) ensures filtered queries don't collide with full-list queries
2. **All params in query key** means changing any filter triggers a new fetch (correct behavior)
3. **placeholderData** keeps showing old data while new page loads (smoother pagination UX)
4. **Parameter normalization** converts camelCase frontend to snake_case backend
5. **No transformation** - backend already returns optimized shape

Note: The existing `usePeople` hook is preserved unchanged so other components continue to work.
</description>
</task>

<task id="3">
<title>Export the new hook</title>
<file>src/hooks/usePeople.js</file>
<action>verify</action>
<description>
Verify that `useFilteredPeople` is properly exported from the file.

The function should be defined with `export function useFilteredPeople(...)` so it's automatically exported.

Verify you can import it in another file:
```javascript
import { useFilteredPeople } from '@/hooks/usePeople';
```
</description>
</task>

## Verification

<verification>
<checklist>
- [ ] `prmApi.getFilteredPeople` method exists in client.js
- [ ] `prmApi.getFilteredPeople({ page: 1 })` returns a promise
- [ ] `useFilteredPeople` hook is exported from usePeople.js
- [ ] `peopleKeys.filtered` generates correct query key
- [ ] Hook returns `{ data, isLoading, error }` structure
- [ ] `data.people` contains array of people
- [ ] `data.total` is the total count
- [ ] `data.page` is the current page
- [ ] `data.total_pages` is calculated correctly
- [ ] Changing page parameter triggers new fetch
- [ ] Changing labels triggers new fetch
- [ ] No ESLint errors in modified files
- [ ] `npm run build` succeeds
</checklist>
</verification>

## Must-Haves (Goal-Backward Verification)

For the phase goal "People data can be filtered and sorted at the database layer", the frontend integration must:

1. **Call the new endpoint** - Not the old `/wp/v2/people` endpoint
2. **Pass all filter parameters** - labels, ownership, modified_days, orderby, order
3. **Handle pagination** - page and per_page parameters
4. **Cache correctly** - Different filter combinations must not share cache

## Testing

After implementing, test in browser console:

```javascript
// Test the API client directly
const response = await window.stadionConfig.api.get('/stadion/v1/people/filtered', {
  params: { page: 1, per_page: 10, orderby: 'last_name', order: 'asc' }
});
console.log(response.data);
// Should show: { people: [...], total: N, page: 1, total_pages: M }
```

## Rollback

If issues arise:
1. The existing `usePeople` hook is unchanged
2. PeopleList can continue using `usePeople` as fallback
3. Remove `getFilteredPeople` and `useFilteredPeople` to revert

## Notes for Future Plans

Phase 112 (PAGE-01 through PAGE-06) will update the PeopleList component to:
1. Use `useFilteredPeople` instead of `usePeople`
2. Add pagination UI (prev/next buttons, page numbers)
3. Add filter controls (label dropdown, ownership toggle)
4. Handle empty states when no results match filters

This plan only adds the hook - the UI changes are in a later phase.
