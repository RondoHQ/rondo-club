# Requirements: Stadion v14.0 Performance Optimization

**Defined:** 2026-02-04
**Core Value:** Eliminate unnecessary API calls and optimize dashboard load time

## v14.0 Requirements

### Duplicate API Calls

- [ ] **DUP-01**: Dashboard page load makes single API call per endpoint (not 2x)
- [ ] **DUP-02**: All page transitions make single API call per endpoint
- [ ] **DUP-03**: React Query properly deduplicates concurrent requests

### Modal Optimization

- [ ] **MOD-01**: QuickActivityModal does not load people data until opened
- [ ] **MOD-02**: TodoModal does not load people data until opened
- [ ] **MOD-03**: GlobalTodoModal does not load people data until opened

### Query Deduplication

- [ ] **QRY-01**: Single `current-user` query shared across ApprovalCheck, FairplayRoute, and Sidebar
- [ ] **QRY-02**: VOG count is cached and not re-fetched on every page navigation

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
| DUP-01 | Phase 135 | Pending |
| DUP-02 | Phase 135 | Pending |
| DUP-03 | Phase 135 | Pending |
| MOD-01 | Phase 136 | Pending |
| MOD-02 | Phase 136 | Pending |
| MOD-03 | Phase 136 | Pending |
| QRY-01 | Phase 137 | Pending |
| QRY-02 | Phase 137 | Pending |
| BE-01 | Phase 138 | Pending |
| BE-02 | Phase 138 | Pending |

**Coverage:**
- v14.0 requirements: 10 total
- Mapped to phases: 10
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-04*
*Last updated: 2026-02-04 after initial definition*
