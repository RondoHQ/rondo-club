---
phase: quick
plan: 015
subsystem: ui-feedback
tags: [react, ui-ux, filtering, people-list]

requires: []
provides:
  - filtered-count-display
affects: []

tech-stack:
  added: []
  patterns: [url-based-state, cross-component-communication]

key-files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx
    - src/components/layout/Layout.jsx

decisions:
  - id: url-params-for-state
    choice: Use URL search params to communicate filtered count to header
    rationale: Avoids prop drilling and context, leverages existing URL-based filter state pattern

metrics:
  duration: 67s
  completed: 2026-01-30
---

# Quick Task 015: People List Filtered Count in Header Summary

**One-liner:** Display filtered result count next to "Leden" title in header when filters are active

## What Was Built

Implemented a filtered count display in the page header that shows users how many results match their filter criteria on the People list. When filters are active, the header displays "Leden (42)" with the count in smaller, muted text. When no filters are active, it shows just "Leden".

### Implementation Approach

Used URL search params as the communication mechanism between PeopleList component and the Header component:

1. **PeopleList.jsx** - Added useEffect that watches `hasActiveFilters`, `totalPeople`, and `isLoading` state
   - When filters are active and data is loaded, sets `filteredCount` URL param to the current total
   - When filters are cleared, removes the `filteredCount` param
   - Uses `replace: true` to avoid polluting browser history

2. **Layout.jsx** - Enhanced Header component to read and display the count
   - Reads `filteredCount` from URL search params using `useSearchParams` hook
   - Conditionally renders count in parentheses next to page title
   - Styling: smaller text (text-sm vs text-lg), muted colors (gray-500/400), normal weight

### Technical Details

**URL-based state pattern benefits:**
- No prop drilling through component tree
- No need for global state/context
- Works with existing URL-based filter persistence
- Simple, lightweight solution

**Visual design:**
- Count appears in parentheses for clarity: "Leden (42)"
- Smaller font size (text-sm) compared to title (text-lg)
- Muted colors to avoid competing with title
- Dark mode support with appropriate color variants

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Add filtered count to URL params when filters active | 38961f3 |
| 2 | Display filtered count in Header component | f01c1cd |
| 3 | Build and deploy to production | N/A (dist/ in .gitignore) |

## Deviations from Plan

None - plan executed exactly as written.

## Verification

### Manual Testing on Production

After deployment to https://stadion.svawc.nl/, the feature should exhibit the following behavior:

- [ ] Navigate to /people with no filters → Header shows "Leden" without count
- [ ] Apply any filter (e.g., birth year, label, Type lid) → Header shows "Leden (XX)" with filtered count
- [ ] Apply multiple filters → Count updates correctly
- [ ] Clear one filter → Count updates
- [ ] Clear all filters → Count disappears
- [ ] Pagination works correctly → Shows total filtered count, not page count
- [ ] Dark mode → Muted count text is visible with appropriate contrast

### Edge Cases Handled

✓ Loading state - count only appears after data is loaded (prevents flash of wrong count)
✓ Filter changes - count updates immediately when filters change
✓ URL manipulation - reading count from params is safe (displays what's in URL)
✓ No filters - param is properly removed when filters are cleared

## Next Phase Readiness

**Blockers:** None

**Concerns:** None

**Recommendations:**
- Pattern could be extended to other list views (Teams, Commissies) if similar feedback is desired
- Could add animation/transition when count changes for smoother UX (optional enhancement)

## Key Learnings

1. **URL params for cross-component communication** - Simple, effective alternative to context/state management for loosely coupled components
2. **Replace history entries** - Using `{ replace: true }` with setSearchParams prevents cluttering browser history with filter state changes
3. **Conditional rendering with fragments** - Clean pattern for optional display elements that don't need wrapper divs

## Production Deployment

✓ Built production assets with `npm run build`
✓ Deployed to production via `bin/deploy.sh`
✓ WordPress and SiteGround caches cleared
✓ Feature live at https://stadion.svawc.nl/

**Note:** dist/ folder is gitignored, so build artifacts are not committed. Production deployment is the source of truth for built assets.
