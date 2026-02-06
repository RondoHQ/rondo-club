# Project Research Summary

**Project:** Stadion v9.0 - People List Performance & Customization
**Domain:** WordPress/React CRM Data Management
**Researched:** 2026-01-29
**Confidence:** HIGH

## Executive Summary

This research covers the addition of infinite scroll with server-side filtering/sorting and per-user column customization to Stadion's existing People list. The domain is well-understood: modern CRM contact lists require server-side pagination to scale beyond 100+ records, with filters and sorting applied at the database layer rather than client-side. The current implementation loads all 1400+ people client-side, which works but has reached its scalability limit.

The recommended approach builds on Stadion's existing architecture without new dependencies. All necessary tools are present: TanStack Query v5.17.0 includes `useInfiniteQuery` for pagination, WordPress REST API provides native pagination headers, and `$wpdb` enables custom JOIN queries for ACF field filtering. The key technical shift is moving from client-side operations (fetch all, filter in-memory) to server-side operations (filter/sort in SQL, fetch in pages). User preferences are stored in WordPress user_meta, following Stadion's existing pattern for dashboard settings.

The critical risk is **access control bypass** in custom SQL queries. Stadion's `RONDO_Access_Control` class filters data through WordPress's `pre_get_posts` hook, but custom `$wpdb` queries bypass this entirely. All custom endpoints must explicitly apply access control checks before running queries. Secondary risks include SQL injection via unsanitized filter parameters, ACF JOIN performance degradation with 4+ meta filters, and TanStack Query cache invalidation issues with infinite scroll. Each has well-documented mitigation strategies from the research.

## Key Findings

### Recommended Stack

No new dependencies required. All necessary technologies are already present in Stadion's stack and properly versioned for this work.

**Core technologies:**
- **TanStack Query v5.17.0**: Server state with `useInfiniteQuery` for pagination — already integrated, v5 includes `maxPages` to limit memory usage
- **WordPress REST API**: Native pagination with `per_page`, `page` params and `X-WP-Total` headers — no custom pagination logic needed
- **WordPress $wpdb**: Custom JOIN queries for ACF field sorting/filtering — essential for performance with ACF repeater fields
- **WordPress user_meta**: Per-user preference storage — pattern already used for `theme_preferences` and `dashboard_settings`
- **Intersection Observer API**: Native browser API for infinite scroll detection — no react-intersection-observer library needed

**Key finding:** The project does NOT need react-virtualized, react-window, or custom table libraries. Current dataset size (1400 people) works fine with standard infinite scroll. Virtual scrolling is only needed above 5,000+ records.

### Expected Features

Research distinguishes between table stakes (users expect this), differentiators (competitive advantage), and anti-features (common mistakes to avoid).

**Must have (table stakes):**
- Server-side filtering by labels, birth year, modified date, team — users expect filters to work on ALL data, not just loaded records
- Server-side sorting by core fields and ACF custom fields — same expectation across entire dataset
- Total count display ("Showing X of Y people") — users want to know dataset size
- Loading indicators with skeleton screens — feedback during data fetching
- Persistent scroll position on navigation back — browser handles this for traditional pagination, requires state management for infinite scroll
- Column visibility toggle and order persistence — standard table feature in 2026

**Should have (competitive differentiators):**
- Virtual scrolling for 10K+ records — not needed now, defer to future if dataset grows
- Intelligent prefetching — TanStack Query supports this, improves perceived performance
- Saved filter presets — high complexity, defer to v2+
- Column drag-and-drop reordering — medium complexity, nice-to-have enhancement
- Export filtered results to CSV — useful for power users

**Defer (v2+):**
- Real-time updates via WebSocket — very high complexity, overkill for Stadion's use case
- Multi-column sorting (sort by last name, then first name) — requires backend support for multiple `orderby` parameters
- Keyboard navigation (arrow keys, Enter to open) — accessibility benefit, not critical for v9.0

**Critical anti-features to avoid:**
- Client-side filtering on incomplete data — current problem, must use server-side for datasets >100 records
- True infinite scroll without pagination underneath — breaks back button, impossible to share position
- Too many columns by default — causes horizontal scrolling death, default to 4-6 key columns
- Fetch all data then virtualize — current Stadion approach, works until ~5K records but wrong pattern
- Non-sticky table headers — users lose context when scrolling

### Architecture Approach

Stadion's WordPress/React split architecture is well-suited for this enhancement. The system uses two REST API namespaces: `/wp/v2/*` for standard WordPress endpoints and `/rondo/v1/*` for custom endpoints. The new functionality fits naturally into this pattern by adding `/rondo/v1/people/filtered` for server-side operations and `/rondo/v1/user/list-preferences` for column settings.

**Major components:**

1. **Custom People Query Builder** (`includes/class-people-query-builder.php`) — Builds efficient `$wpdb` queries with conditional JOINs for ACF fields, applies filters at SQL level, handles pagination with LIMIT/OFFSET, integrates AccessControl checks
2. **Filtered People REST Endpoint** (`/rondo/v1/people/filtered`) — Receives filter/sort/page params, uses Query Builder for data fetching, transforms results with existing `transformPerson()` pattern, returns paginated response with `{ people: [], pagination: {} }`
3. **User Preferences REST Endpoints** (`/rondo/v1/user/list-preferences`) — GET returns user's column preferences with defaults, PATCH updates preferences with validation, stores in `wp_usermeta` with key `stadion_people_list_preferences`
4. **usePeopleInfinite Hook** (`src/hooks/usePeopleInfinite.js`) — Replaces current `usePeople()` for list view, uses `useInfiniteQuery` with `initialPageParam`, `getNextPageParam`, and `maxPages` (v5 requirement), flattens pages for rendering
5. **useUserPreferences Hook** (`src/hooks/useUserPreferences.js`) — Fetches/updates column preferences with optimistic updates for instant feedback

**Data flow change:** Current implementation uses `usePeople()` to fetch ALL people in a while loop (100 per request), then filters/sorts client-side in `PeopleList.jsx`. New implementation fetches 20-50 records per page on-demand, with filters/sorting applied server-side before transmission. TanStack Query's `maxPages` option limits cached pages to prevent memory growth.

**Performance optimization:** Custom SQL queries use conditional aggregation (CASE statements) instead of multiple LEFT JOINs on `wp_postmeta`. For work_history filtering (ACF repeater), denormalize `_current_team_id` to separate meta field to avoid slow LIKE queries on serialized data. Cache expensive queries with WordPress transients (5 min TTL), invalidate on person create/update/delete.

### Critical Pitfalls

Research identified 12 pitfalls across three severity levels. The top 5 require explicit prevention strategies.

1. **Access Control Bypass with Custom $wpdb Queries** — Custom SQL queries bypass WordPress's `pre_get_posts` filter, which is how `RONDO_Access_Control` enforces user approval checks. Prevention: Always call `$access_control->is_user_approved()` before running queries OR add reusable `get_sql_where_clause()` method to AccessControl class that returns SQL fragment like `"p.ID = 0"` for unapproved users. Test with unapproved user account to verify they see empty list.

2. **SQL Injection via Unsanitized Filter Parameters** — Filter values and ACF field names inserted directly into SQL without proper escaping. Prevention: Always use `$wpdb->prepare()` with correct placeholders (%s, %d), whitelist ACF field names from `acf_get_fields()` configuration, sanitize filter arrays with `array_map('absint')` for IDs and `sanitize_text_field()` for strings. NEVER build dynamic column/table names from user input without whitelist validation first.

3. **Post_Meta JOIN Performance Degradation** — Each ACF filter adds another LEFT JOIN to `wp_postmeta`, causing queries to slow from 50ms to 2-5s with 3-4 filters active. Prevention: Use STRAIGHT_JOIN hint to force MySQL join order, add composite index on `(post_id, meta_key, meta_value)`, limit JOINs to 3-4 maximum (fall back to two-step query if more), cache filter results aggressively with transients (5 min), denormalize frequently-filtered fields like current_team to separate meta field.

4. **TanStack Query Stale Data After Mutations** — User creates/edits person, returns to list, sees old data because `useInfiniteQuery` with high `staleTime` doesn't refetch automatically. Prevention: Invalidate AND refetch on mutations with `queryClient.invalidateQueries({ queryKey: ['people'], refetchType: 'active' })`, use shorter staleTime (30 seconds not Infinity), implement optimistic updates for create/edit, use `resetQueries()` instead of `invalidateQueries()` to clear all pages after mutations.

5. **Infinite Scroll Doesn't Refetch All Pages on Invalidation** — User scrolls to page 5, creates person, returns to list. Only page 1 refetches, pages 2-5 show old data. Prevention: Use `queryClient.resetQueries()` instead of `invalidateQueries()` to clear all cached pages after mutations, optimistically insert new items into page 1's cache, use `maxPages: 3-10` to limit cached pages, show "data may be stale" warning on deep pages with refresh button.

**Secondary pitfalls:** Race conditions with rapid filter changes (fix: debounce 300ms, use TanStack Query's automatic request cancellation with AbortSignal), memory leaks with large result sets (fix: always use pagination, never `posts_per_page: -1`), nonce validation bypassed in custom endpoints (fix: use existing `check_user_approved()` permission callback from Base class), ACF repeater query complexity (fix: denormalize `_current_team_id` before implementing team filters).

## Implications for Roadmap

Based on research, the work naturally divides into 4 sequential phases. Backend foundation must come first (can't do infinite scroll without the endpoint), then frontend integration, then preferences (which depends on both).

### Phase 1: Server-Side Foundation

**Rationale:** Frontend can't implement infinite scroll or server-side filtering until the backend endpoint exists. This phase builds the data layer that all subsequent phases depend on. Starting here de-risks the entire milestone by proving query performance early.

**Delivers:** Custom `/rondo/v1/people/filtered` endpoint returning paginated, filtered, sorted people data with response format: `{ people: [], pagination: { page, per_page, total_items, total_pages, has_more } }`

**Addresses features:**
- Server-side filtering (labels, birth year, modified date, search) — table stakes feature
- Server-side sorting (core fields + ACF custom fields) — table stakes feature
- Pagination with total count — table stakes feature

**Avoids pitfalls:**
- **Pitfall 1 (Access Control Bypass)** — Must add `is_user_approved()` check before all queries
- **Pitfall 2 (SQL Injection)** — All filter params passed through `$wpdb->prepare()` with whitelist validation
- **Pitfall 6 (Memory Leaks)** — Enforce pagination limits (max per_page: 100, default: 20)
- **Pitfall 7 (Nonce Validation)** — Use existing `check_user_approved()` permission callback

**Technical implementation:**
1. Create `includes/class-people-query-builder.php` with efficient `$wpdb` JOIN queries
2. Add filtered endpoint to `includes/class-rest-people.php`
3. Add composite index on `wp_postmeta` if needed
4. Test with Query Monitor to verify query performance <0.1s

**Testing criteria:** Endpoint returns correct data for all filter combinations, respects AccessControl (unapproved users see nothing), all queries under 100ms with 1400 records, no SQL injection vulnerabilities.

### Phase 2: Infinite Scroll Frontend

**Rationale:** With backend endpoint proven, replace client-side loading with server-side pagination. This phase keeps existing filtering/sorting UI (state management unchanged) but moves data source from `usePeople()` to `usePeopleInfinite()`. Proving infinite scroll works before adding preferences complexity reduces integration risk.

**Delivers:** People list with infinite scroll, server-side filtering/sorting active, total count display, loading states, Intersection Observer triggering next page fetch

**Addresses features:**
- Infinite scroll pattern — replaces load-all approach
- Loading indicators — skeleton screens for first page load
- Persistent scroll position — browser handles automatically

**Avoids pitfalls:**
- **Pitfall 4 (Stale Data)** — Use `staleTime: 30000` (30 seconds) not Infinity, invalidate with `refetchType: 'active'`
- **Pitfall 5 (Race Conditions)** — Debounce filter changes 300ms, pass AbortSignal to axios
- **Pitfall 9 (Refetch Pages)** — Use `resetQueries()` on mutations, implement `maxPages: 10`

**Technical implementation:**
1. Create `src/hooks/usePeopleInfinite.js` using TanStack Query `useInfiniteQuery`
2. Add `getPeopleFiltered()` method to `src/api/client.js`
3. Modify `src/pages/People/PeopleList.jsx` to use new hook
4. Add Intersection Observer for scroll detection
5. Add "Load More" button as fallback

**Testing criteria:** Infinite scroll loads next page on reaching bottom, filters trigger query key change and refetch from page 1, sorting updates results without breaking scroll, creating person resets query and new person appears in list.

### Phase 3: User Preferences Backend

**Rationale:** Column preferences require storage and retrieval endpoints before frontend can implement the UI. Building backend first allows testing preference persistence independently of UI complexity. Pattern follows existing `theme_preferences` endpoint structure.

**Delivers:** `/rondo/v1/user/list-preferences` endpoints (GET/PATCH) storing `visible_columns`, `column_order`, `default_sort` in `wp_usermeta` with key `stadion_people_list_preferences`

**Addresses features:**
- Persistent column preferences — foundation for customization
- Default preferences for new users — pulls from custom fields metadata `show_in_list_view`

**Avoids pitfalls:**
- **Pitfall 10 (Sync Between Devices)** — Server-side storage ensures consistency across sessions
- Column preference conflicts — Validates preferences against current custom field definitions, removes deleted fields

**Technical implementation:**
1. Add endpoints to `includes/class-rest-api.php` (or new `class-rest-user-preferences.php`)
2. Implement GET with defaults from ACF field configuration
3. Implement PATCH with validation (whitelist allowed columns)
4. Store in user_meta with `get_user_meta()`/`update_user_meta()`

**Testing criteria:** Preferences persist across sessions, validation rejects invalid columns, defaults work for new users, multiple users have independent preferences.

### Phase 4: Column Preferences UI

**Rationale:** With backend preferences proven, build the UI for column customization. This is the final enhancement phase — not a blocker for core infinite scroll functionality. Can be deferred to v9.1 if needed.

**Delivers:** Column settings modal/dropdown with visibility toggle, drag-and-drop reordering, default sort selection, immediate save to server, people list renders only visible columns in user's preferred order

**Addresses features:**
- Column visibility toggle — table stakes feature
- Column order persistence — table stakes feature
- Column drag-and-drop reordering (if time) — nice-to-have differentiator

**Avoids pitfalls:**
- **Pitfall 10 (Sync)** — Save to server immediately on change, refetch on window focus
- **Pitfall 11 (Sort Indicator)** — Server includes sort metadata in response, UI uses as source of truth

**Technical implementation:**
1. Create `src/hooks/useUserPreferences.js` with optimistic updates
2. Build Column Settings component (modal or dropdown)
3. Add drag-and-drop with TanStack Table's `onColumnOrderChange`
4. Update `PeopleList.jsx` to render based on preferences
5. Show sync status indicator during save

**Testing criteria:** Column visibility changes persist on refresh, drag-and-drop reordering works, preferences sync between browser tabs (refetch on focus), default sort applies on initial load.

### Phase Ordering Rationale

- **Backend before frontend:** Can't implement infinite scroll without the endpoint. Building `/rondo/v1/people/filtered` first de-risks query performance and access control before frontend integration.
- **Infinite scroll before preferences:** Proves server-side pagination works with existing UI before adding preferences complexity. Reduces integration surface area.
- **Preferences backend before UI:** Allows testing storage/retrieval independently. Follows pattern of separating data layer from presentation.
- **Sequential not parallel:** Each phase depends on previous phase's output. Phase 2 needs Phase 1's endpoint, Phase 4 needs Phase 3's storage.

**Critical path:** Phases 1-2 deliver core functionality (infinite scroll with server-side filtering). Phases 3-4 add column customization enhancement. If timeline pressure, Phase 4 can be deferred to v9.1 without impacting core milestone goal.

### Research Flags

**Phases needing deeper research during planning:**
- **None** — All phases use well-documented patterns (TanStack Query infinite scroll, WordPress REST endpoints, user_meta storage). Research findings are comprehensive and high-confidence.

**Phases with standard patterns (skip research-phase):**
- **All phases** — TanStack Query's `useInfiniteQuery` is thoroughly documented with examples. WordPress REST API pagination and user_meta patterns are core WordPress functionality. ACF query optimization has extensive community knowledge.

**Validation checkpoints:**
- **Phase 1:** Test query performance with Query Monitor before proceeding. If queries exceed 0.1s, implement STRAIGHT_JOIN or caching before continuing.
- **Phase 2:** Test cache invalidation with create/edit person before proceeding. If stale data issues, adjust `staleTime` and invalidation strategy.
- **Phase 3:** Test with multiple users to verify preferences isolation before building UI.

**No research-phase calls needed** — Proceed directly to implementation using documented patterns from research files.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies already present and properly versioned. TanStack Query v5.17.0 confirmed to include `useInfiniteQuery` with `maxPages`. No new dependencies required. |
| Features | HIGH | Research drew from modern CRM UX patterns (2026 standards), multiple sources agree on table stakes vs. differentiators. Clear distinction between must-have and nice-to-have. |
| Architecture | HIGH | Proposed architecture fits naturally into existing Stadion patterns. Custom endpoints follow established `/rondo/v1/*` namespace. User preferences match `theme_preferences` pattern. |
| Pitfalls | HIGH | All critical pitfalls have documented mitigation strategies. Access control bypass, SQL injection, and JOIN performance are well-understood WordPress issues with proven solutions. |

**Overall confidence:** HIGH

All four research dimensions returned high-confidence findings with multiple corroborating sources. The domain (WordPress/React CRM lists) is well-understood, patterns are established, and pitfalls are documented with prevention strategies.

### Gaps to Address

**No critical gaps identified.** Research was thorough and conclusive across all dimensions.

**Minor validation points:**
- **Composite index performance:** Research recommends adding `CREATE INDEX idx_meta_key_value ON wp_postmeta (post_id, meta_key, meta_value(191))` but actual performance gain needs measurement with Stadion's dataset size. Decision: Test with Query Monitor during Phase 1 implementation, only add if queries exceed 0.1s.
- **Denormalization necessity:** Research suggests denormalizing `_current_team_id` to avoid ACF repeater LIKE queries, but impact depends on whether team filtering is implemented in v9.0 scope. Decision: If Phase 1 includes team filtering, implement denormalization. If not, defer until team filter feature is added.
- **maxPages optimal value:** Research suggests `maxPages: 3-10` but optimal value depends on user behavior patterns. Decision: Start with `maxPages: 10` during Phase 2, monitor memory usage, adjust if needed.

**Architecture decisions to validate during Phase 1:**
- Confirm `RONDO_Access_Control::is_user_approved()` is correct method for custom query filtering
- Verify ACF field names whitelist can be pulled from `acf_get_fields('group_person_fields')`
- Test `transformPerson()` function works with custom endpoint response structure

## Sources

### Primary (HIGH confidence)

**Stack Research:**
- [TanStack Query v5 useInfiniteQuery Reference](https://tanstack.com/query/v5/docs/framework/react/reference/useInfiniteQuery) — Infinite query API with v5 breaking changes
- [TanStack Query v5 Migration Guide](https://tanstack.com/query/v5/docs/react/guides/migrating-to-v5) — `maxPages` option and `initialPageParam` requirement
- [WordPress REST API Pagination Handbook](https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/) — Native pagination headers and parameters
- [MDN Intersection Observer API](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API) — Native scroll detection without libraries
- [WordPress Working with User Metadata](https://developer.wordpress.org/plugins/users/working-with-user-metadata/) — Official user_meta API documentation

**Performance Optimization:**
- [ACF WordPress Post Meta Query Performance Best Practices](https://www.advancedcustomfields.com/blog/wordpress-post-meta-query/) — Official ACF performance guidance
- [Delicious Brains SQL Query Optimization](https://deliciousbrains.com/sql-query-optimization/) — WordPress-specific query patterns
- [WordPress VIP WP_Query Performance](https://wpvip.com/blog/wp-query-performance/) — Enterprise-scale performance patterns

**Security:**
- [Patchstack SQL Injection Prevention Guide](https://patchstack.com/articles/sql-injection/) — WordPress SQL injection vectors and prevention
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — Official nonce validation documentation
- [WordPress Modular DS Plugin CVE-2026-23550](https://thehackernews.com/2026/01/critical-wordpress-modular-ds-plugin.html) — Recent authentication bypass case study

### Secondary (MEDIUM confidence)

**Features & UX:**
- [Pagination vs. infinite scroll: Making the right decision for UX](https://blog.logrocket.com/ux-design/pagination-vs-infinite-scroll-ux/) — LogRocket UX research (2025)
- [TanStack Table Pagination Guide](https://tanstack.com/table/v8/docs/guide/pagination) — Table pagination patterns
- [TanStack Table Column Visibility APIs](https://tanstack.com/table/v8/docs/api/features/column-visibility) — Column management patterns
- [Skeleton loading screen design](https://blog.logrocket.com/ux-design/skeleton-loading-screen-design/) — Loading state best practices

**Architecture Patterns:**
- [React Table Server Side Pagination with Sorting and Search Filters](https://dev.to/inimist/react-table-server-side-pagination-with-sorting-and-search-3163) — Server-side filtering implementation
- [Sorting/Orderby for custom meta fields in WordPress REST API](https://iamshishir.com/sorting-orderby-for-custom-meta-fields-in-wordpress/) — ACF field sorting in REST
- [TanStack Query: Caching, Pagination, and Infinite Scrolling](https://medium.com/@lakshaykapoor08/%EF%B8%8F-caching-pagination-and-infinite-scrolling-with-tanstack-query-4212b24d3806) — Infinite scroll implementation guide

**Pitfalls:**
- [TanStack Query Issue #5648](https://github.com/TanStack/query/issues/5648) — Programmatic invalidation issues
- [TanStack Query Discussion #7569](https://github.com/TanStack/query/discussions/7569) — Infinite query refetch behavior
- [WordPress Core Ticket #20134](https://core.trac.wordpress.org/ticket/20134) — Complex meta query performance
- [WooCommerce Issue #27746](https://github.com/woocommerce/woocommerce/issues/27746) — Double-left join performance problems

### Tertiary (LOW confidence)
- Various Medium/DEV.to articles on infinite scroll implementation — Used for pattern validation, not primary source
- Community forum discussions on ACF performance — Anecdotal but consistent with official guidance

---
*Research completed: 2026-01-29*
*Ready for roadmap: yes*
