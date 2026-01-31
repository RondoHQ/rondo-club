---
phase: 126
plan: 02
subsystem: contributie-ui
status: complete
completed: 2026-01-31
duration: 5 min
tags:
  - contributie
  - membership-fees
  - react
  - rest-api
  - ui
  - list-view

dependencies:
  requires:
    - 126-01
  provides:
    - rest-api-fees-endpoint
    - contributie-list-page
    - fee-data-visualization
  affects:
    - 126-03

tech-stack:
  added: []
  patterns:
    - rest-endpoint-pattern
    - tanstack-query-hooks
    - sortable-table-ui

key-files:
  created:
    - src/pages/Contributie/ContributieList.jsx
    - src/hooks/useFees.js
  modified:
    - includes/class-rest-api.php
    - src/components/layout/Layout.jsx
    - src/App.jsx
    - src/api/client.js

decisions:
  - title: "Contributie positioned between Leden and VOG"
    rationale: "Logical grouping - Contributie is a sub-function of Leden management"
    context: "NAV-01 requirement"
    date: 2026-01-31

  - title: "Client-side sorting for fee list"
    rationale: "Small dataset (< 500 members), simpler implementation, instant feedback"
    context: "Performance acceptable for expected data volume"
    date: 2026-01-31

  - title: "Category sort order: mini, pupil, junior, senior, recreant, donateur"
    rationale: "Age progression, then membership type - mirrors organizational hierarchy"
    context: "Default sort provides intuitive grouping"
    date: 2026-01-31

  - title: "Amber row highlighting for pro-rata members"
    rationale: "Visual distinction for mid-season joins, matches pro-rata color coding"
    context: "Helps identify special cases at a glance"
    date: 2026-01-31

  - title: "Green percentage for family discounts"
    rationale: "Positive reinforcement color for savings, distinguishes from pro-rata"
    context: "Clear visual separation of discount types"
    date: 2026-01-31
---

# Phase 126 Plan 02: Contributie List UI Summary

**One-liner:** REST endpoint and React list page displaying calculated membership fees with category badges, family discounts, and pro-rata highlighting

## What Was Built

Complete Contributie list view with REST API integration:

1. **REST API Endpoint** (`/stadion/v1/fees`)
   - Returns calculated fees for all members
   - Optional season parameter (defaults to current season)
   - Includes full fee breakdown: base, family discount, pro-rata, final
   - Sorted by category priority then name

2. **Data Fetching Layer**
   - Added `getFeeList` to `prmApi` client
   - Created `useFees.js` hook with TanStack Query
   - Proper query key structure for caching

3. **Navigation Integration**
   - Added Contributie entry with Coins icon
   - Positioned between Leden and VOG with indent
   - Added route `/contributie` in App.jsx
   - Updated page title function

4. **ContributieList Page Component**
   - 7-column table: Name, Category, Leeftijdsgroep, Basis, Gezin, Pro-rata, Bedrag
   - Sortable columns: Name, Category, Basis, Bedrag
   - Category badges with color coding (green/blue/purple/orange/gray/yellow)
   - Amber row highlight for pro-rata < 100%
   - Green percentage display for family discounts
   - Totals footer showing base and final sums
   - Loading, error, and empty states
   - Pull-to-refresh support
   - Click member name to navigate to person detail

## Technical Implementation

**REST Endpoint:**
```php
// includes/class-rest-api.php
public function get_fee_list( $request ) {
    $season = $request->get_param( 'season' ) ?? $fees->get_season_key();

    // Query all persons, calculate fees via MembershipFees class
    foreach ( $query->posts as $person ) {
        $fee_data = $fees->calculate_full_fee( $person->ID, $registration_date, $season );
        // Skip non-calculable, build results array
    }

    // Sort by category priority, then name
    usort( $results, ... );

    return [ 'season' => $season, 'total' => count, 'members' => $results ];
}
```

**React Hook:**
```javascript
// src/hooks/useFees.js
export function useFeeList(params = {}) {
  return useQuery({
    queryKey: feeKeys.list(params),
    queryFn: async () => {
      const response = await prmApi.getFeeList(params);
      return response.data;
    },
  });
}
```

**Component Structure:**
- `SortableHeader`: Reusable sortable column header with arrows
- `FeeRow`: Row component with conditional styling
- `EmptyState`: Empty state with Coins icon
- `ContributieList`: Main page with client-side sorting and totals

**Styling Approach:**
- Category badges use semantic colors (green for mini, blue for pupil, etc.)
- Pro-rata rows: `bg-amber-50/50 dark:bg-amber-900/10`
- Family discounts: `text-green-600 dark:text-green-400`
- Responsive table with horizontal scroll on mobile

## Verification Results

✅ **REST Endpoint:**
- `/stadion/v1/fees` returns correct JSON structure
- Season parameter validation works
- Category sorting matches expected order

✅ **Navigation:**
- Contributie appears between Leden and VOG in sidebar
- Coins icon renders correctly
- Indent matches VOG styling

✅ **List Page:**
- All 7 columns display correctly
- Sorting works on Name, Category, Basis, Bedrag
- Pro-rata rows highlighted in amber
- Family discounts show green percentage
- Totals footer calculates correctly
- Click member name navigates to `/people/:id`

✅ **Build & Deploy:**
- `npm run lint` passes for new files
- `npm run build` completes successfully
- Deployed to production via `bin/deploy.sh`
- Caches cleared on production

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] ESLint case block declaration error**
- **Found during:** Task 4 - lint check
- **Issue:** Lexical declaration in case block without braces
- **Fix:** Wrapped `case 'category':` block in braces
- **Files modified:** `src/pages/Contributie/ContributieList.jsx`
- **Commit:** `26686acd`

No other deviations - plan executed as written.

## Files Changed

**Created:**
- `src/pages/Contributie/ContributieList.jsx` (339 lines) - Main list page component
- `src/hooks/useFees.js` (25 lines) - TanStack Query hook for fee data

**Modified:**
- `includes/class-rest-api.php` (+104 lines) - Added `/fees` endpoint and callback
- `src/api/client.js` (+2 lines) - Added `getFeeList` to prmApi
- `src/components/layout/Layout.jsx` (+4 lines) - Added Contributie nav entry, Coins icon, page title
- `src/App.jsx` (+3 lines) - Added ContributieList lazy import and route

## Architecture Notes

**Data Flow:**
1. User visits `/contributie`
2. `ContributieList` component mounts
3. `useFeeList()` hook fetches from `/stadion/v1/fees`
4. REST endpoint queries all persons, calls `MembershipFees->calculate_full_fee()`
5. Results sorted by category priority, then name
6. Component renders table with client-side re-sorting capability

**Caching Strategy:**
- TanStack Query caches response with key `['fees', 'list', params]`
- Pull-to-refresh invalidates cache
- Season parameter included in cache key for future multi-season support

**Performance:**
- Client-side sorting acceptable for expected dataset (< 500 members)
- Single REST call loads all data
- Category badge color lookup via constant object
- Totals calculated via reduce in render (memoization not needed for this size)

## Next Phase Readiness

**For 126-03 (Pro-rata Admin UI):**
- ✅ REST endpoint structure in place (can extend for season switching)
- ✅ Category badges established (reusable in detail view)
- ✅ Percentage formatting helper (`formatPercentage`) available
- ✅ Navigation pattern established (can add "Edit" action in 126-03)

**Potential Enhancements:**
- Season selector (currently defaults to current season)
- Export to CSV functionality
- Filtering by category
- Search by member name

**No blockers for next plan.**

## Commits

| Hash | Type | Description |
|------|------|-------------|
| 3b9e4426 | feat | Add REST endpoint for membership fee list |
| d51c70d3 | feat | Add API client method and useFeeList hook |
| 729a83bd | feat | Add Contributie navigation and route |
| 95423f1e | feat | Create ContributieList page component |
| 26686acd | fix | Wrap case block in braces to fix eslint error |
| ffd0e0cc | chore | Build and deploy production assets |

**Total:** 6 commits (5 feature, 1 fix, 1 chore)

---
*Summary created: 2026-01-31*
*Execution time: 5 min*
