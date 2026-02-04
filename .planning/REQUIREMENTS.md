# Requirements: Stadion v14.0 Performance Optimization

**Defined:** 2026-02-04
**Core Value:** Eliminate unnecessary API calls and optimize dashboard load time

## v14.0 Requirements

### Duplicate API Calls

- [x] **DUP-01**: Dashboard page load makes single API call per endpoint (not 2x)
- [x] **DUP-02**: All page transitions make single API call per endpoint
- [x] **DUP-03**: React Query properly deduplicates concurrent requests

### Modal Optimization

- [x] **MOD-01**: QuickActivityModal does not load people data until opened
- [x] **MOD-02**: TodoModal does not load people data until opened
- [x] **MOD-03**: GlobalTodoModal does not load people data until opened

### Query Deduplication

- [x] **QRY-01**: Single `current-user` query shared across ApprovalCheck, FairplayRoute, and Sidebar
- [x] **QRY-02**: VOG count is cached and not re-fetched on every page navigation

### Backend Optimization

- [ ] **BE-01**: `count_open_todos()` uses `COUNT(*)` or `found_posts` instead of fetching all IDs
- [ ] **BE-02**: `count_awaiting_todos()` uses `COUNT(*)` or `found_posts` instead of fetching all IDs

## Out of Scope

| Feature | Reason |
|---------|--------|
| Server-side caching (Redis/transients) | Future enhancement, current focus is eliminating unnecessary calls |
| JS bundle optimization | Already optimized in v2.5, LCP issue is API response time |
| Database indexing | No evidence of slow queries, issue is duplicate/unnecessary calls |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DUP-01 | Phase 135 | Complete |
| DUP-02 | Phase 135 | Complete |
| DUP-03 | Phase 135 | Complete |
| MOD-01 | Phase 136 | Complete |
| MOD-02 | Phase 136 | Complete |
| MOD-03 | Phase 136 | Complete |
| QRY-01 | Phase 137 | Complete |
| QRY-02 | Phase 137 | Complete |
| BE-01 | Phase 138 | Pending |
| BE-02 | Phase 138 | Pending |

**Coverage:**
- v14.0 requirements: 10 total
- Mapped to phases: 10
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-04*
*Last updated: 2026-02-04 after Phase 137 completion*
