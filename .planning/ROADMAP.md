# Roadmap: v9.0 People List Performance & Customization

## Overview

This milestone transforms the People list from load-all-at-once to server-side pagination with filtering/sorting, and adds dynamic per-user column customization. Enables Stadion to scale beyond 1400+ contacts by moving data operations from client-side JavaScript to server-side SQL queries.

## Phase Structure

### Phase 111: Server-Side Foundation

**Goal:** People data can be filtered and sorted at the database layer.

**Dependencies:** None

**Requirements:**
- DATA-01: Server returns paginated people (100 per page by default)
- DATA-02: Server filters people by label taxonomy (multiple labels, OR logic)
- DATA-03: Server filters people by ownership (mine/shared/all)
- DATA-04: Server filters people by modified date (within last N days)
- DATA-06: Server sorts people by first_name (asc/desc)
- DATA-07: Server sorts people by last_name (asc/desc)
- DATA-08: Server sorts people by modified date (asc/desc)
- DATA-10: Custom endpoint uses $wpdb JOIN to fetch posts + meta in single query
- DATA-13: Access control is preserved in custom $wpdb queries (unapproved users see nothing)
- DATA-14: All filter parameters are validated/escaped to prevent SQL injection

**Plans:**
- Plan 1: Backend Filtered Endpoint (Wave 1) - `111-plan-1-filtered-endpoint.md`
- Plan 2: Frontend Hook Integration (Wave 2) - `111-plan-2-frontend-hook.md`

**Success Criteria:**
1. User can fetch people with pagination (page=1, per_page=100) via `/stadion/v1/people/filtered` endpoint
2. User can filter people by one or more labels (OR logic: person has ANY selected label)
3. User can filter people by ownership (mine/shared/all options)
4. User can filter people by modified date (last 7/30/90 days)
5. User can sort people by first name, last name, or modified date in ascending or descending order
6. Unapproved users see empty results (access control enforced in custom SQL)
7. All filter inputs are validated and escaped (no SQL injection vulnerabilities)
8. Custom $wpdb query fetches posts and meta in single JOIN query (no N+1 queries)

---

### Phase 112: Birthdate Denormalization

**Goal:** Birthdate is fast to filter without ACF repeater queries.

**Dependencies:** Phase 111

**Requirements:**
- DATA-05: Server filters people by birth year (range: from-to)
- DATA-11: Birthdate is denormalized to person post_meta (_birthdate) for fast filtering
- DATA-12: Birthdate syncs when birthday important_date is created/updated/deleted

**Plans:** 2 plans

Plans:
- [x] 112-01-PLAN.md — Birthdate sync hooks and WP-CLI migration
- [x] 112-02-PLAN.md — Birth year filter on filtered endpoint

**Success Criteria:**
1. Birthdate is stored in `wp_postmeta` with key `_birthdate` (full date, denormalized from important_date)
2. Birthdate updates automatically when birthday important_date is created
3. Birthdate updates automatically when birthday important_date is modified
4. Birthdate clears automatically when birthday important_date is deleted
5. User can filter people by birth year (derived from _birthdate) via filtered endpoint
6. Birthdate filter executes in <0.1s (no slow ACF repeater LIKE queries)

---

### Phase 113: Frontend Pagination

**Goal:** People list displays paginated data with navigation controls and custom field sorting.

**Dependencies:** Phase 112

**Requirements:**
- PAGE-01: PeopleList displays paginated results (100 per page)
- PAGE-02: User can navigate between pages (prev/next/page numbers)
- PAGE-03: Current page and total pages are displayed
- PAGE-04: Filter changes reset to page 1
- PAGE-05: Loading indicator shows while fetching page
- PAGE-06: Empty state when no results match filters
- DATA-09: Server sorts people by custom ACF fields (text, number, date types)

**Plans:** 2 plans

Plans:
- [x] 113-01-PLAN.md — Backend custom field sorting support
- [x] 113-02-PLAN.md — Frontend pagination UI integration

**Success Criteria:**
1. People list displays 100 people per page (not all 1400+ at once)
2. User can navigate between pages using prev/next buttons
3. User can navigate to specific page via page number buttons
4. Page indicator shows "Page X of Y" and "Showing X-Y of Z people"
5. Changing any filter (labels, ownership, modified date, birth year) resets to page 1
6. Loading skeleton shows while fetching page data
7. Empty state message displays when no people match current filters
8. User can sort people by custom ACF fields (text, number, date field types)
9. Custom field sorting works with pagination (server-side sort, not client-side)

---

### Phase 114: User Preferences Backend

**Goal:** Column preferences persist per user in server storage.

**Dependencies:** Phase 113

**Requirements:**
- COL-03: Column preferences persist per user (stored in user_meta)

**Plans:** TBD

Plans:
- [ ] TBD

**Success Criteria:**
1. User can save column preferences via `/stadion/v1/user/list-preferences` PATCH endpoint
2. User can retrieve column preferences via `/stadion/v1/user/list-preferences` GET endpoint
3. Column preferences are stored in `wp_usermeta` with key `stadion_people_list_preferences`
4. Default preferences include visible columns from ACF field config (active fields only)
5. Preferences validate against current custom field definitions (reject deleted fields)
6. Multiple users have independent preferences (not shared)

---

### Phase 115: Column Preferences UI

**Goal:** Users can customize which columns appear and in what order.

**Dependencies:** Phase 114

**Requirements:**
- COL-01: User can show/hide columns on PeopleList
- COL-02: User can reorder columns via drag-and-drop
- COL-04: Available columns include: name, team, labels, modified, all active custom fields
- COL-05: Column width preferences persist per user
- COL-06: Settings modal provides column customization UI
- COL-07: "Tonen als kolom in lijstweergave" removed from custom field settings (replaced by per-user selection)

**Plans:** TBD

Plans:
- [ ] TBD

**Success Criteria:**
1. User can open column settings modal from People list header
2. User can toggle column visibility (show/hide) for name, team, labels, modified, and all active custom fields
3. User can reorder columns via drag-and-drop in settings modal
4. User can adjust column widths by dragging column dividers
5. Column preferences (visibility, order, width) persist across sessions
6. Column preferences (visibility, order, width) sync between browser tabs
7. "Tonen als kolom in lijstweergave" checkbox removed from Settings > Custom Fields form
8. People list renders only visible columns in user's preferred order with preferred widths

---

## Progress

| Phase | Status | Requirements | Completion |
|-------|--------|--------------|------------|
| Phase 111: Server-Side Foundation | ✓ Complete | DATA-01, DATA-02, DATA-03, DATA-04, DATA-06, DATA-07, DATA-08, DATA-10, DATA-13, DATA-14 | 100% |
| Phase 112: Birthdate Denormalization | ✓ Complete | DATA-05, DATA-11, DATA-12 | 100% |
| Phase 113: Frontend Pagination | ✓ Complete | PAGE-01, PAGE-02, PAGE-03, PAGE-04, PAGE-05, PAGE-06, DATA-09 | 100% |
| Phase 114: User Preferences Backend | Pending | COL-03 | 0% |
| Phase 115: Column Preferences UI | Pending | COL-01, COL-02, COL-04, COL-05, COL-06, COL-07 | 0% |

## Requirement Coverage

| Requirement | Description | Phase |
|-------------|-------------|-------|
| DATA-01 | Server returns paginated people | Phase 111 |
| DATA-02 | Server filters people by label taxonomy | Phase 111 |
| DATA-03 | Server filters people by ownership | Phase 111 |
| DATA-04 | Server filters people by modified date | Phase 111 |
| DATA-05 | Server filters people by birth year | Phase 112 |
| DATA-06 | Server sorts people by first_name | Phase 111 |
| DATA-07 | Server sorts people by last_name | Phase 111 |
| DATA-08 | Server sorts people by modified date | Phase 111 |
| DATA-09 | Server sorts people by custom ACF fields | Phase 113 |
| DATA-10 | Custom endpoint uses $wpdb JOIN | Phase 111 |
| DATA-11 | Birthdate denormalized to post_meta | Phase 112 |
| DATA-12 | Birthdate syncs with important_date | Phase 112 |
| DATA-13 | Access control preserved in custom queries | Phase 111 |
| DATA-14 | Filter parameters validated/escaped | Phase 111 |
| PAGE-01 | PeopleList displays paginated results | Phase 113 |
| PAGE-02 | User can navigate between pages | Phase 113 |
| PAGE-03 | Current page and total pages displayed | Phase 113 |
| PAGE-04 | Filter changes reset to page 1 | Phase 113 |
| PAGE-05 | Loading indicator while fetching | Phase 113 |
| PAGE-06 | Empty state when no results | Phase 113 |
| COL-01 | User can show/hide columns | Phase 115 |
| COL-02 | User can reorder columns via drag-drop | Phase 115 |
| COL-03 | Column preferences persist per user | Phase 114 |
| COL-04 | Available columns include core + custom fields | Phase 115 |
| COL-05 | Column width preferences persist | Phase 115 |
| COL-06 | Settings modal provides customization UI | Phase 115 |
| COL-07 | Remove "show in list view" from custom field settings | Phase 115 |

**Coverage:** 27/27 requirements mapped (100%)

## Deferred to Future Version

- Full-text search across name, email, phone, notes (SRCH-01)
- Quick filter presets (SRCH-02)
- Saved filters that persist across sessions (SRCH-03)
- Virtual scrolling for 5000+ record lists (ADV-01)
- Infinite scroll option (ADV-02)
- URL-based filter state (ADV-03)
- Multi-column sorting (ADV-04)
- Export filtered results
- Teams/Dates list optimization (extend pattern after People proven)

## Key Implementation Notes

### Backend Patterns (from research)

**Critical pitfalls to avoid:**
1. **Access Control Bypass:** Custom $wpdb queries bypass `pre_get_posts` hook. MUST call `is_user_approved()` before all queries.
2. **SQL Injection:** Always use `$wpdb->prepare()` with correct placeholders, whitelist ACF field names from `acf_get_fields()`.
3. **JOIN Performance:** Limit to 3-4 meta JOINs maximum, use STRAIGHT_JOIN hint, add composite index if needed.
4. **Cache Invalidation:** Use `resetQueries()` not `invalidateQueries()` after mutations to clear all cached pages.
5. **Memory Leaks:** Always use pagination, never `posts_per_page: -1` in custom queries.

**Query optimization:**
- Use conditional aggregation (CASE statements) instead of multiple LEFT JOINs when possible
- Cache expensive queries with transients (5 min TTL), invalidate on create/update/delete
- Test with Query Monitor to verify all queries <0.1s

### Frontend Patterns (from research)

**TanStack Query v5 patterns:**
- Use `useInfiniteQuery` with `initialPageParam`, `getNextPageParam`, `maxPages: 10`
- Use `staleTime: 30000` (30 seconds) not Infinity
- Debounce filter changes 300ms to prevent race conditions
- Pass AbortSignal to axios for automatic request cancellation

**Column customization:**
- Store preferences in user_meta: `{ visible_columns: [], column_order: [], column_widths: {} }`
- Validate preferences against current ACF field definitions on load
- Use optimistic updates for instant feedback on preference changes

### Scope Decisions (Research-Informed)

- **Traditional pagination over infinite scroll:** Research shows pagination is better for goal-oriented tasks (finding specific person)
- **Birthdate denormalization required:** ACF repeater LIKE queries too slow for server-side filtering (store full date for future filter flexibility)
- **100 per page default:** Balance between data transfer and UX (research: 20-100 is optimal range)
- **No virtual scrolling yet:** Current dataset (1400 people) doesn't need it (only needed above 5000+ records)
