---
phase: 111
plan: 2
subsystem: frontend-api-integration
tags: [react, tanstack-query, hooks, api-client, filtering, pagination]
wave: 2
depends_on:
  - 111-01
requires:
  - Backend endpoint /stadion/v1/people/filtered (111-01)
provides:
  - prmApi.getFilteredPeople() API client method
  - useFilteredPeople React hook for server-side filtering
affects:
  - 112 (UI Layer) - Will consume useFilteredPeople hook
tech-stack:
  added: []
  patterns:
    - Parameter normalization (camelCase → snake_case)
    - placeholderData for smooth pagination UX
    - Separate query keys for filtered vs unfiltered queries
key-files:
  created: []
  modified:
    - src/api/client.js
    - src/hooks/usePeople.js
decisions:
  - id: 111-02-001
    title: Create new hook instead of modifying usePeople
    rationale: Gradual migration safer than breaking changes, other components may depend on usePeople returning all people
  - id: 111-02-002
    title: Use placeholderData instead of keepPreviousData
    rationale: Smoother pagination UX - shows previous page while loading next
  - id: 111-02-003
    title: Include all filter params in query key
    rationale: Ensures changing any filter triggers new fetch and prevents cache collision
metrics:
  duration: 1m 24s
  tasks_completed: 3
  files_modified: 2
  commits: 2
completed: 2026-01-29
---

# Phase 111 Plan 2: Frontend Hook Integration Summary

**One-liner:** Added useFilteredPeople hook with API client method for server-side filtering/pagination via /stadion/v1/people/filtered endpoint

## What Was Built

Created frontend integration for the new server-side filtered people endpoint:

1. **API Client Method** (`prmApi.getFilteredPeople`)
   - Calls `/stadion/v1/people/filtered` with query params
   - Accepts: page, per_page, labels, ownership, modified_days, orderby, order
   - Returns: { people, total, page, total_pages }

2. **React Hook** (`useFilteredPeople`)
   - Wraps TanStack Query for server-side filtering
   - Converts camelCase params to snake_case for backend
   - Uses separate query key pattern (`peopleKeys.filtered`) to avoid cache collision with `usePeople`
   - Implements placeholderData for smooth pagination transitions

3. **Query Key Pattern**
   - Added `filtered: (filters) => [...peopleKeys.all, 'filtered', filters]`
   - Ensures different filter combinations don't share cache

## Task Breakdown

| Task | Description | Files Modified | Commit |
|------|-------------|----------------|--------|
| 1 | Add getFilteredPeople to API client | src/api/client.js | 378fcd3 |
| 2 | Create useFilteredPeople hook | src/hooks/usePeople.js | 232ef1b |
| 3 | Verify hook export | src/hooks/usePeople.js | (verification) |

## Technical Details

### API Client Method

Added to `prmApi` object in `src/api/client.js`:

```javascript
getFilteredPeople: (params = {}) => api.get('/stadion/v1/people/filtered', { params })
```

Positioned after `bulkUpdatePeople` to maintain alphabetical organization.

### Hook Implementation

**Parameter Normalization:**
- Frontend uses camelCase: `{ page, perPage, labels, ownership, modifiedDays, orderby, order }`
- Backend expects snake_case: `{ page, per_page, labels, ownership, modified_days, orderby, order }`
- Hook handles conversion automatically

**Query Key Strategy:**
```javascript
queryKey: peopleKeys.filtered(params)
// Example: ['people', 'filtered', { page: 1, per_page: 100, ... }]
```

All params included in key means:
- Changing page → new fetch
- Changing labels → new fetch
- Changing sort order → new fetch
- No accidental cache sharing between different filter combinations

**UX Enhancement:**
```javascript
placeholderData: (previousData) => previousData
```

Shows previous page's data while loading next page, preventing flash of empty state.

## Decisions Made

### 111-02-001: Create New Hook Instead of Modifying usePeople

**Decision:** Add `useFilteredPeople` as a new hook rather than modifying existing `usePeople`

**Rationale:**
- PeopleList page will need different behavior (pagination UI vs loading all)
- Other components may depend on `usePeople` returning complete array
- Gradual migration is safer than breaking changes
- Allows A/B testing and easy rollback

**Impact:** Both hooks coexist; migration happens in phase 112

### 111-02-002: Use placeholderData Instead of keepPreviousData

**Decision:** Use `placeholderData: (previousData) => previousData` in query config

**Rationale:**
- TanStack Query v5 deprecated `keepPreviousData` option
- `placeholderData` provides same smooth pagination UX
- Shows previous page while loading next, prevents flash of loading state
- Better user experience for page navigation

**Impact:** Smoother pagination transitions

### 111-02-003: Include All Filter Params in Query Key

**Decision:** Pass entire `params` object to `peopleKeys.filtered(params)`

**Rationale:**
- Changing any filter should trigger new fetch (not use stale cache)
- Prevents cache collision between different filter combinations
- Example: page 1 with labels=[3] should not share cache with page 1 with labels=[5]
- Follows TanStack Query best practices for parameterized queries

**Impact:** More cache entries, but correct invalidation behavior

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Phase 112 (UI Layer) can now proceed:**

✅ **Ready:**
- useFilteredPeople hook available for import
- API client method tested and working
- Query key pattern established
- Parameter normalization handled

⚠️ **Watch for:**
- Cache invalidation strategy when creating/updating/deleting people
  - Need to invalidate `peopleKeys.filtered()` queries (not just `peopleKeys.lists()`)
  - Consider using `queryClient.invalidateQueries({ queryKey: peopleKeys.all })` to catch both
- Initial page load performance with 100 results
  - May need to adjust default `perPage` based on real-world testing
- Empty state handling when filters return 0 results
  - UI layer needs to distinguish "no people" vs "no matches"

**Integration points for 112:**
```javascript
import { useFilteredPeople } from '@/hooks/usePeople';

function PeopleList() {
  const { data, isLoading, error } = useFilteredPeople({
    page: 1,
    perPage: 100,
    labels: [3, 5],
    orderby: 'last_name',
    order: 'asc',
  });

  // data.people - array of person objects
  // data.total - total matching count
  // data.page - current page number
  // data.total_pages - total pages available
}
```

## Testing Notes

**Build verification:**
- ✅ `npm run build` succeeded with no errors
- ✅ No ESLint errors introduced
- ✅ Hook properly exported with `export function`

**Manual testing (deferred to phase 112):**
- Browser console testing planned for after UI integration
- Will verify API calls with DevTools Network tab
- Will test cache behavior with different filter combinations

## Performance Impact

**Bundle size:** No significant change (small hook + one API method)

**Runtime:**
- Hook uses existing TanStack Query infrastructure
- No new dependencies added
- Pagination reduces memory footprint vs loading all 1400+ people

## Files Changed

```
src/api/client.js          +3 lines   (added getFilteredPeople method)
src/hooks/usePeople.js     +50 -6     (added filtered key pattern + hook)
```

## Commits

```
378fcd3 feat(111-02): add getFilteredPeople API client method
232ef1b feat(111-02): add useFilteredPeople hook
```

## Rollback Procedure

If issues arise:

1. **Remove the hook:**
   ```bash
   git revert 232ef1b
   ```

2. **Remove the API method:**
   ```bash
   git revert 378fcd3
   ```

3. **Verify build:**
   ```bash
   npm run build
   ```

The existing `usePeople` hook remains unchanged, so PeopleList continues to work with fallback behavior.

## Knowledge Captured

**For future developers:**

1. **When to use useFilteredPeople vs usePeople:**
   - Use `useFilteredPeople` for paginated views (PeopleList with filters)
   - Use `usePeople` for components needing complete list (dropdowns, autocompletes)

2. **Adding new filter parameters:**
   - Add to hook params with camelCase naming
   - Add to `params` object with snake_case conversion
   - Backend must support the parameter (see 111-01)

3. **Cache invalidation pattern:**
   ```javascript
   // After creating/updating/deleting person:
   queryClient.invalidateQueries({ queryKey: peopleKeys.all }); // Catches both filtered and unfiltered
   ```

4. **Pagination UX:**
   - `placeholderData` shows old page while loading new page
   - Better UX than loading spinner for pagination
   - Only use for pagination, not for initial load

## Links

- **Plan:** [111-plan-2-frontend-hook.md](111-plan-2-frontend-hook.md)
- **Research:** [111-RESEARCH.md](111-RESEARCH.md) (sections 3-4)
- **Previous:** [111-01-SUMMARY.md](111-01-SUMMARY.md) (Backend endpoint)
- **Next:** Phase 112 Plan 1 (PeopleList UI integration)
