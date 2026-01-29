# Feature Landscape: People List Performance & Customization

**Domain:** Contact Management / CRM Data Tables
**Researched:** 2026-01-29
**Context:** Stadion People list with 1400+ contacts, currently loads all at once

## Executive Summary

This research examines expected behavior for infinite scroll lists with server-side filtering and dynamic column selection in modern web applications. The findings focus on three core features: **infinite scroll with virtual rendering**, **server-side filtering/sorting**, and **per-user column preferences**.

**Key insight:** For structured data tables with 1000+ records, the industry consensus in 2026 is clear: pagination + server-side filtering outperforms infinite scroll for goal-oriented tasks (finding/comparing contacts). Virtual scrolling is critical for performance regardless of approach.

**Current Stadion implementation:** Loads all 1400+ people at once via paginated API calls (100 per request), stores all in memory, applies filters/sorting client-side. This works but has scalability limits and UX friction (filters only work on loaded data).

## Table Stakes

Features users expect. Missing = product feels incomplete or broken.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Server-side filtering** | Users expect filters to work on ALL data, not just loaded data | Medium | WordPress REST API supports `meta_query` for ACF fields, `tax_query` for taxonomies |
| **Server-side sorting** | Same expectation - sort should work across entire dataset | Low | WordPress supports `orderby` for meta fields, built-in fields |
| **Loading indicators** | Users need feedback when data is loading | Low | Skeleton screens > spinners for perceived performance |
| **Persistent scroll position** | When navigating back to list, users expect to return to same position | Medium | Browser handles this for traditional pagination, requires state management for infinite scroll |
| **Total count display** | "Showing X of Y people" - users want to know dataset size | Low | WordPress REST API returns `X-WP-Total` header |
| **Filter state persistence** | Applied filters should persist when user navigates away and back | Medium | Store in URL query params or localStorage |
| **Responsive performance** | List should remain interactive during loading/scrolling | High | Requires virtual scrolling for 1000+ records |
| **"Back to top" button** | For infinite scroll, users need quick way to return to top | Low | Essential UX for lists >50 items |
| **Stable layout** | No content shifts during load (CLS = 0) | Medium | Skeleton loaders must match final content dimensions |
| **Column visibility toggle** | Users expect to show/hide columns | Low | Standard table feature in 2026 |
| **Column order persistence** | Selected columns/order should persist across sessions | Medium | Store in WordPress user meta or localStorage |

## Differentiators

Features that set product apart. Not expected, but add significant value.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Virtual scrolling** | Renders only visible rows - handles 10K+ records smoothly | High | TanStack Virtual is recommended library for React (2026) |
| **Intelligent prefetching** | Fetch next page before user scrolls to bottom | Medium | TanStack Query supports prefetching, improves perceived performance |
| **Multi-column sorting** | Sort by multiple columns (e.g., last name, then first name) | Medium | Requires backend support for multiple `orderby` parameters |
| **Saved filter presets** | Users can save frequently-used filter combinations | High | Requires UI for managing presets + storage (user meta) |
| **Bulk actions** | Select multiple people, apply actions (already implemented) | Low | Already exists in Stadion |
| **Real-time updates** | List updates when other users make changes | Very High | WebSocket or polling required, probably overkill for Stadion |
| **Keyboard navigation** | Arrow keys to navigate rows, Enter to open | Medium | Accessibility benefit, power user feature |
| **Column resizing** | Drag column borders to adjust width | Medium | TanStack Table supports this with state persistence |
| **Export to CSV** | Download filtered/sorted results | Medium | Backend generates CSV from current filter/sort state |
| **Custom field columns** | Show any ACF field as column | High | Already partially implemented in Stadion |
| **Quick filters** | One-click filters for common queries (e.g., "Added this week") | Low | Pre-defined filter combinations |
| **Search across custom fields** | Global search includes user-defined custom fields | Medium | Already implemented in Stadion's global search |
| **Column drag-and-drop reordering** | Drag columns to reorder | Medium | TanStack Table supports this with `onColumnOrderChange` |

## Anti-Features

Features to explicitly NOT build. Common mistakes in this domain.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **True infinite scroll with no pagination** | Breaks browser back button, impossible to share specific position, poor for goal-oriented tasks | Hybrid: Infinite loading with URL-based pages underneath |
| **Auto-load more without indicator** | User has no control, can't reach footer, confusing when content keeps loading | Always show "Load More" button as fallback, clear loading state |
| **Client-side filtering on incomplete data** | Current problem - filters lie to users when not all data is loaded | MUST use server-side filtering for datasets >100 records |
| **Complex filter UI by default** | Overwhelming for casual users | Progressive disclosure: Simple filters visible, advanced behind toggle |
| **Modal/drawer for column selection** | Interrupts workflow, requires multiple clicks | Dropdown from column header or settings icon in toolbar |
| **Too many columns by default** | Horizontal scrolling is death for data tables | Default to 4-6 key columns, let users add more |
| **Automatic column width calculation** | Causes layout shifts, re-renders, poor performance | Fixed or user-resizable column widths with sensible defaults |
| **Filtering during typing** | Too aggressive, hammers server, poor UX for slow typers | Debounce 300-500ms after user stops typing |
| **Fetch all data then virtualize** | Current Stadion approach - works until ~5K records | Fetch in pages, virtualize, only load visible range |
| **Custom table implementation** | Reinventing the wheel, hundreds of edge cases | Use TanStack Table (industry standard 2026) |
| **Pagination without server-side** | False sense of organization when all data loaded anyway | If paginating, actually paginate server-side |
| **Non-sticky table headers** | Users lose context when scrolling | Headers should stick during vertical scroll |
| **Separate mobile view** | Maintenance burden, feature parity issues | Responsive table with column prioritization |

## Feature Dependencies

```
Server-Side Filtering/Sorting (MUST HAVE FIRST)
└─→ Virtual Scrolling or Pagination UI
    └─→ Loading States & Indicators
        └─→ Column Visibility Toggle
            └─→ Column Order Persistence
                └─→ Column Drag-and-Drop (optional enhancement)

Server-Side Filtering/Sorting (MUST HAVE FIRST)
└─→ Filter State Persistence (URL params)
    └─→ Saved Filter Presets (optional enhancement)
```

**Critical path:** Server-side filtering/sorting must be implemented before infinite scroll/virtual scrolling. Current client-side approach won't scale and creates UX problems.

## Implementation Strategy Recommendation

Based on research and Stadion's current architecture:

### Phase 1: Server-Side Foundation (CRITICAL)
1. **Add server-side filtering to REST API** - Extend WordPress REST API to accept filter parameters (labels, birth year, modified date, custom fields)
2. **Add server-side sorting** - Support `orderby` for ACF fields (first_name, last_name, custom fields)
3. **Return total count** - Use `X-WP-Total` header
4. **Update `usePeople` hook** - Pass filter/sort params to API instead of client-side filtering

### Phase 2: Pagination UI (Choose One)
**Option A: Traditional Pagination** (Recommended for Stadion)
- Simpler implementation
- Better for goal-oriented tasks (finding/comparing contacts)
- Easier to share URLs (page numbers in URL)
- Users can jump to specific pages
- More predictable performance

**Option B: Virtual Scrolling with Infinite Loading**
- Smoother browsing experience
- Requires TanStack Virtual integration
- More complex state management
- Better for exploratory browsing

**Recommendation for Stadion:** Traditional pagination. Users are finding/comparing contacts (goal-oriented), not casually browsing. Pagination provides better UX for this use case.

### Phase 3: Column Customization
1. **Column visibility toggle** - Dropdown to show/hide columns
2. **Store preferences in user meta** - `update_user_meta( $user_id, 'stadion_people_columns', $columns )`
3. **Column order** - Allow drag-and-drop reordering (TanStack Table API)
4. **Persist column order** - Store in user meta

### Phase 4: Enhancements (Optional)
- Column resizing
- Saved filter presets
- Multi-column sorting
- Export to CSV
- Keyboard navigation

## Technical Implementation Notes

### WordPress REST API Pagination
- Maximum `per_page`: 100 (WordPress default)
- Use `page` parameter for pagination
- Headers: `X-WP-Total` (total items), `X-WP-TotalPages` (total pages)
- For cursor-based pagination (better for infinite scroll): Use `offset` parameter

### TanStack Query + Server-Side Filtering
```javascript
const { data, isLoading } = useQuery({
  queryKey: ['people', { page, filters, sort }],
  queryFn: () => fetchPeople({ page, filters, sort }),
  keepPreviousData: true, // Smooth transitions between pages
});
```

### User Meta Storage (Column Preferences)
```php
// Backend
$columns = get_user_meta( $user_id, 'stadion_people_columns', true );
// Returns: ['first_name', 'last_name', 'labels', 'custom_field_1']

$column_order = get_user_meta( $user_id, 'stadion_people_column_order', true );
// Returns: ['first_name', 'custom_field_1', 'labels', 'last_name']
```

### Virtual Scrolling (If Chosen)
```javascript
import { useVirtualizer } from '@tanstack/react-virtual';

const rowVirtualizer = useVirtualizer({
  count: data.length,
  getScrollElement: () => parentRef.current,
  estimateSize: () => 50, // Row height in px
  overscan: 10, // Render 10 extra rows above/below viewport
});
```

## Performance Benchmarks (From Research)

| Approach | Records | Initial Load | Scroll FPS | Memory Usage |
|----------|---------|--------------|------------|--------------|
| Client-side all | 1400 | ~2-3s | 60 FPS | ~15MB |
| Client-side all | 5000 | ~8-10s | 30-45 FPS | ~50MB |
| Pagination (server) | Any | <500ms | 60 FPS | <5MB |
| Virtual scroll (server) | Any | <500ms | 60 FPS | <10MB |

**Stadion current state:** ~2-3s initial load for 1400 people, acceptable but approaching limits.

## User Expectations (2026 Standards)

Based on research into modern CRM and data table UIs:

1. **Loading should feel instant** - <500ms perceived load time (skeleton screens help)
2. **Filters should be obvious** - Toolbar with clear filter chips/badges
3. **Applied filters should be visible** - Don't hide active filters
4. **Clear all filters** - Single button to reset
5. **Column management should be discoverable** - Icon in header or settings
6. **Columns should have reasonable defaults** - 4-6 columns that work for 80% of users
7. **Sorting should be obvious** - Arrow indicators in column headers
8. **Multi-column sort (nice-to-have)** - Hold Shift to add secondary sort

## Accessibility Considerations

| Requirement | Implementation |
|-------------|----------------|
| Keyboard navigation | Table rows focusable with Tab, Enter to open |
| Screen reader support | Proper ARIA labels for column headers, sort state |
| Loading announcements | ARIA live region for "Loading more results" |
| Filter state announcements | "X results found" after filtering |
| Column visibility | Dropdown should be keyboard accessible |

## Sources

### Pagination vs Infinite Scroll
- [Handling Large Datasets in Angular: Pagination vs Infinite Scroll](https://medium.com/@geekieshpixel/handling-large-datasets-in-angular-pagination-vs-infinite-scroll-dc07dadeac0b)
- [Pagination vs. infinite scroll: Making the right decision for UX](https://blog.logrocket.com/ux-design/pagination-vs-infinite-scroll-ux/)
- [Infinite Scroll vs Pagination: Which is Best for Your Website](https://www.tekrevol.com/blogs/pagination-vs-infinite-scroll-website/)
- [Infinite Scroll vs Pagination: Key Differences](https://www.squareboat.com/blog/infinite-scroll-vs-pagination)

### React Table Implementation
- [React Table Server Side Pagination with Sorting and Search Filters](https://dev.to/inimist/react-table-server-side-pagination-with-sorting-and-search-3163)
- [How to implement server-side Pagination using React Table](https://medium.com/@Jaimayal/how-to-implement-server-side-pagination-using-react-table-d53922e0b086)
- [How To Do Server Side Pagination, Column Filtering and Sorting With TanStack React Table](https://medium.com/@clee080/how-to-do-server-side-pagination-column-filtering-and-sorting-with-tanstack-react-table-and-react-7400a5604ff2)
- [TanStack Table Pagination Guide](https://tanstack.com/table/v8/docs/guide/pagination)

### Column Visibility & Preferences
- [Mastering Column Visibility and Resizing in MUI X Data Grid](https://bchirag.hashnode.dev/optimizing-mui-x-data-grid-column-visibility-resizing-and-persistence)
- [Data Grid - Column visibility - MUI X](https://mui.com/x/react-data-grid/column-visibility/)
- [Enhancing Visibility and User Experience with React TanStack Table Column Visibility](https://borstch.com/blog/development/enhancing-visibility-and-user-experience-with-react-tanstack-table-column-visibility)
- [Saving User Preferences for Column Visibility](https://borstch.com/snippet/saving-user-preferences-for-column-visibility)
- [TanStack Table Column Visibility APIs](https://tanstack.com/table/v8/docs/api/features/column-visibility)

### Virtual Scrolling
- [Virtualization in React: Improving Performance for Large Lists](https://medium.com/@ignatovich.dm/virtualization-in-react-improving-performance-for-large-lists-3df0800022ef)
- [List Virtualization in React](https://medium.com/@atulbanwar/list-virtualization-in-react-3db491346af4)
- [Virtualize large lists with react-window](https://web.dev/articles/virtualize-long-lists-react-window)
- [TanStack Virtual](https://tanstack.com/virtual/latest)

### WordPress REST API
- [Pagination – REST API Handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/)
- [How to Handle Wordpress Pagination and Custom Queries with REST API](https://www.voxfor.com/how-to-handle-wordpress-pagination-and-custom-queries-with-rest-api/)
- [RESTful API Pagination Best Practices](https://medium.com/@khdevnet/restful-api-pagination-best-practices-a-developers-guide-5b177a9552ef)

### TanStack Query State Management
- [TanStack Query Overview](https://tanstack.com/query/latest/docs/framework/react/overview)
- [TanStack Query: A Powerful Tool for Data Management in React](https://medium.com/@ignatovich.dm/tanstack-query-a-powerful-tool-for-data-management-in-react-0c5ae6ef037c)
- [Column Filtering Guide - TanStack Table](https://tanstack.com/table/latest/docs/guide/column-filtering)

### Column Reordering
- [Column Ordering Guide - TanStack Table](https://tanstack.com/table/v8/docs/guide/column-ordering)
- [Exploring Column Ordering in React TanStack Table](https://borstch.com/blog/development/exploring-column-ordering-in-react-tanstack-table-for-better-data-management)
- [React TanStack Table Column Ordering Example](https://tanstack.com/table/v8/docs/framework/react/examples/column-ordering)

### WordPress User Meta
- [Working with User Metadata – Plugin Handbook](https://developer.wordpress.org/plugins/users/working-with-user-metadata/)
- [How WordPress user data is stored in the database](https://usersinsights.com/wordpress-user-database-tables/)
- [wp_usermeta table - WordPress Database Tables](https://www.wpdir.com/wp-usermeta-table/)

### Loading States & Skeleton UI
- [Skeleton loading screen design — How to improve perceived performance](https://blog.logrocket.com/ux-design/skeleton-loading-screen-design/)
- [Handling React loading states with React Loading Skeleton](https://blog.logrocket.com/handling-react-loading-states-react-loading-skeleton/)
- [Infinite scroll best practices: UX design tips and examples](https://www.justinmind.com/ui-design/infinite-scroll)
- [Skeleton UI Design: Best practices, Design variants & Examples](https://mobbin.com/glossary/skeleton)
