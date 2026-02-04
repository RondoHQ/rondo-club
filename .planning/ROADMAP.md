# Roadmap: Stadion v14.0 Performance Optimization

## Overview

Eliminate unnecessary API calls and optimize dashboard load time by fixing duplicate requests on page load, implementing conditional data loading for modals, deduplicating React Query queries across components, and optimizing backend count queries to use proper SQL COUNT operations instead of fetching all records.

## Phases

- [ ] **Phase 135: Fix Duplicate API Calls** - Investigate and eliminate 2x API calls on every page load
- [ ] **Phase 136: Modal Lazy Loading** - Conditionally load data only when modals are opened
- [ ] **Phase 137: Query Deduplication** - Share current-user query and cache VOG count
- [ ] **Phase 138: Backend Query Optimization** - Replace posts_per_page=-1 with COUNT queries

## Phase Details

### Phase 135: Fix Duplicate API Calls
**Goal**: All page loads make single API call per endpoint (not 2x)

**Depends on**: Nothing (first phase)

**Requirements**: DUP-01, DUP-02, DUP-03

**Success Criteria** (what must be TRUE):
  1. Dashboard page load shows one request per endpoint in network panel (not duplicate request IDs)
  2. Page transitions (People -> Dashboard -> Teams) show single API call per endpoint
  3. React Query request waterfall shows deduplication working (concurrent requests merged)

**Plans:** 2 plans

Plans:
- [x] 135-01-PLAN.md - Optimize QueryClient defaults (add refetchOnWindowFocus: false)
- [ ] 135-02-PLAN.md - Migrate to createBrowserRouter data router pattern (gap closure)

### Phase 136: Modal Lazy Loading
**Goal**: Modals with people selectors do not load data until opened

**Depends on**: Phase 135

**Requirements**: MOD-01, MOD-02, MOD-03

**Success Criteria** (what must be TRUE):
  1. Dashboard loads without fetching people data (network panel shows no /people/filtered call)
  2. QuickActivityModal fetches people data only when user clicks to open modal
  3. TodoModal and GlobalTodoModal fetch people data only when opened

**Plans**: TBD

Plans:
- [ ] 136-01: TBD (to be planned)

### Phase 137: Query Deduplication
**Goal**: Single shared queries for current-user and cached VOG count

**Depends on**: Phase 136

**Requirements**: QRY-01, QRY-02

**Success Criteria** (what must be TRUE):
  1. Network panel shows single current-user API call on app load (not 3x from ApprovalCheck, FairplayRoute, Sidebar)
  2. VOG count is fetched once and cached with appropriate staleTime (not on every navigation)
  3. React Query devtools shows shared queryKey usage across components

**Plans**: TBD

Plans:
- [ ] 137-01: TBD (to be planned)

### Phase 138: Backend Query Optimization
**Goal**: Todo count queries use SQL COUNT instead of fetching all records

**Depends on**: Phase 137

**Requirements**: BE-01, BE-02

**Success Criteria** (what must be TRUE):
  1. Dashboard stats load faster (no posts_per_page=-1 queries in Query Monitor)
  2. Backend count_open_todos() uses wp_count_posts() or wpdb COUNT query
  3. Backend count_awaiting_todos() uses wp_count_posts() or wpdb COUNT query

**Plans**: TBD

Plans:
- [ ] 138-01: TBD (to be planned)

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 135. Fix Duplicate API Calls | 0/1 | Planned | - |
| 136. Modal Lazy Loading | 0/TBD | Not started | - |
| 137. Query Deduplication | 0/TBD | Not started | - |
| 138. Backend Query Optimization | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-04*
*Last updated: 2026-02-04*
