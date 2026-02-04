# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-03)

**Core value:** Personal CRM with team collaboration while maintaining relationship-focused experience
**Current focus:** Phase 138 - Backend Query Optimization (complete)

## Current Position

Phase: 138 of 138 (Backend Query Optimization)
Plan: 1 of 1 (complete)
Status: Phase complete - v14.0 milestone finished
Last activity: 2026-02-04 — Completed 138-01-PLAN.md

Progress: [██████████] 100%

## Performance Metrics

**Velocity:**
- Total plans completed: 10 (v14.0 milestone complete)
- Average duration: 2m 18s
- Total execution time: 23m 44s

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 132 | 1 | 2m 33s | 2m 33s |
| 133 | 1 | 3m 42s | 3m 42s |
| 134 | 3 | 6m 18s | 2m 06s |
| 135 | 2 | 4m 23s | 2m 12s |
| 136 | 1 | 2m 00s | 2m 00s |
| 137 | 1 | 2m 48s | 2m 48s |
| 138 | 1 | 2m 00s | 2m 00s |

### Recent Milestones

- v14.0 Performance Optimization (2026-02-04) - 4 phases, 4 plans
- v13.0 Discipline Cases (2026-02-03) - 3 phases, 5 plans
- v12.1 Contributie Forecast (2026-02-03) - 3 phases, 3 plans
- v12.0 Membership Fees (2026-02-01) - 7 phases, 15 plans
- v10.0 Read-Only UI for Sportlink Data (2026-01-29) - 3 phases, 3 plans

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

Recent decisions affecting current work:
- Investigation completed identifying 5 performance issues: duplicate API calls (all endpoints 2x), QuickActivityModal loading 1400+ people on dashboard, multiple current-user queries, VOG count on every navigation, backend posts_per_page=-1 for counts
- Disabled refetchOnWindowFocus in QueryClient to prevent tab-switch refetches (Phase 135)
- Documented React Query's built-in concurrent request deduplication - no additional code needed (Phase 135)
- Migrated from BrowserRouter to createBrowserRouter data router pattern (Phase 135-02)
- Routes now defined at module scope in router.jsx (Phase 135-02)
- Fixed ES module double-load: removed ?ver= from wp_enqueue_script for Vite bundles - browser ES module cache keys by full URL, so different query strings caused React to execute twice (Phase 135)
- Added enabled option to usePeople hook with default true for backward compatibility (Phase 136)
- Established modal lazy-loading pattern: usePeople({}, { enabled: isOpen }) (Phase 136)
- Created centralized useCurrentUser hook with 5-minute staleTime for query deduplication (Phase 137)
- Added options parameter to useFilteredPeople for staleTime/enabled configuration (Phase 137)
- Backend todo counts now use wp_count_posts() instead of get_posts() for efficient SQL COUNT (Phase 138)

### Pending Todos

1 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)

### Blockers/Concerns

None.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 036 | Change discipline cases route from /discipline-cases to /tuchtzaken | 2026-02-03 | 3fd7dfe9 | [036-change-route-to-tuchtzaken](./quick/036-change-route-to-tuchtzaken/) |
| 035 | Add Doorbelast and Card columns to discipline cases table | 2026-02-03 | 6f75c16f | [035-doorbelast-card-columns](./quick/035-doorbelast-card-columns/) |

## Session Continuity

Last session: 2026-02-04
Stopped at: Completed Phase 138 (Backend Query Optimization) - v14.0 complete
Resume file: None
Next: New milestone or feature request

---
*State updated: 2026-02-04*
