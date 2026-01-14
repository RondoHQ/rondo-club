# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-13)

**Core value:** Personal CRM with multi-user collaboration capabilities
**Current focus:** v3.1 Pending Response Tracking — Todo post type + response tracking

## Current Position

Milestone: v3.1 Pending Response Tracking
Phase: 27 of 28 (Pending Response UI) — COMPLETE
Plan: 2 of 2 in current phase — All plans complete
Status: Phase 27 complete, ready for Phase 28
Last activity: 2026-01-14 — Completed 27-02-PLAN.md (Complete Awaiting Response UI)

Progress: █████░░░░░ 24%

## Completed Milestones

| Milestone | Phases | Plans | Shipped |
|-----------|--------|-------|---------|
| v1.0 Tech Debt Cleanup | 1-6 | 11 | 2026-01-13 |
| v2.0 Multi-User | 7-11 | 20 | 2026-01-13 |
| v2.1 Bulk Operations | 12-13 | 3 | 2026-01-13 |
| v2.2 List View Polish | 14-15 | 4 | 2026-01-13 |
| v2.3 List View Unification | 16-18 | 3 | 2026-01-13 |
| v2.4 Bug Fixes | 19 | 2 | 2026-01-13 |
| v2.5 Performance | 20 | 3 | 2026-01-13 |
| v3.0 Testing Infrastructure | 21-23 | 7 | 2026-01-13 |

**Total:** 8 milestones, 23 phases, 53 plans completed

## Deferred Issues

See `.planning/ISSUES.md`:
- ~~ISS-006: Remove card view from People~~ — RESOLVED in Phase 16
- ~~ISS-007: Move person image to its own column~~ — RESOLVED in Phase 16
- ~~ISS-008: Organizations list interface~~ — RESOLVED in Phase 17-18

**0 issues remaining**

## Decisions Made (v3.0 → v3.1)

| Phase | Decision | Rationale |
|-------|----------|-----------|
| 21 | PHPUnit via wp-browser (Codeception) | WordPress-specific test types (WPLoader, WPUnit) |
| 21 | Separate test database (`caelis_test`) | Isolation from dev/prod data |
| 21 | MySQL via Homebrew | Local MySQL CLI needed for test database |
| 21 | Theme symlink in WP themes dir | WPLoader requires theme in standard location |
| 22 | Parallel test execution | 3 independent plans run concurrently via agents |
| 22 | Direct share priority fix | Bug fix: shares checked before visibility denial |
| 22 | Unique test IDs pattern | wp_generate_password(6) for test isolation |
| 23 | REST server manual init | WPUnit tests need explicit PRM_REST_* instantiation |
| 23 | Boolean params as strings | REST API validation requires 'true' not true |
| 23 | DELETE verifies post_status | Access control filter runs after DELETE, check trash |
| - | Backend-first testing | Playwright deferred to future milestone |
| 24 | Todo CPT not Comment | Todos are posts, not comments; allows richer metadata |
| 24 | Migration deletes originals | Clean cutover; no duplicate data after migration |
| 24 | Update existing tests | SearchDashboardTest uses CPT-based todos now |
| 25 | Merge todos into timeline with sorting | Combined CPT query with comments and sorted by created date |
| 25 | Broad timeline invalidation in dashboard | Dashboard hooks invalidate ['people', 'timeline'] since personId not available |
| 26 | Auto-timestamp on state change | awaiting_response_since set/cleared automatically when awaiting_response changes |
| 26 | gmdate for timestamps | UTC timestamps for consistency across timezones |
| 27 | Checkbox for both new/existing todos | Awaiting response toggleable at any time |
| 27 | Urgency color scheme | Yellow 0-2d, orange 3-6d, red 7+d for visibility |
| 27 | GlobalTodoModal awaiting checkbox | Mark todos as awaiting at creation time |
| 27 | Consistent badge pattern | Clock icon + day count across all todo displays |

## Roadmap Evolution

- Milestone v2.4 created: Bug Fixes (Phase 19+)
- Phase 19 complete: Important Date Polish (2 plans)
- Milestone v2.4 complete: All date bugs fixed, CLI migration ready
- Milestone v2.4 archived: Git tag v2.4 created
- Milestone v2.5 created: Performance, 1 phase (Phase 20)
- Phase 20 complete: Bundle Optimization (3 plans)
- Milestone v2.5 archived: Git tag v2.5 created
- Milestone v3.0 created: Testing Infrastructure, 3 phases (Phase 21-23)
- Phase 22 complete: Access Control Tests (3 plans via parallel execution)
- Phase 23 complete: REST API & Data Model Tests (3 plans via parallel execution)
- Milestone v3.0 complete: Testing Infrastructure with 120 tests
- Milestone v3.1 created: Pending Response Tracking, 5 phases (Phase 24-28)

## Session Continuity

Last session: 2026-01-14
Stopped at: Completed Phase 27 Plan 02 (Complete Awaiting Response UI)
Resume file: None

## Accumulated Context

### Pending Todos

1 todo in `.planning/todos/pending/`:
1. Add label management interface (ui)

Completed todos in `.planning/todos/done/`:
1. Testing framework — PHPUnit done in v3.0 (Playwright deferred)
2. React bundle chunking — Done in v2.5
3. Console MIME type errors — Resolved via production deploy
4. Add pending response tracking — Now in v3.1 milestone
5. Convert todos to custom post type — Now in v3.1 milestone

## Next Steps

- `/gsd:plan-phase 28` — Create plan for Todo Filtering/Statistics
- `/gsd:verify-work 27` — Verify Phase 27 (Pending Response UI complete)
- `/gsd:progress` — Check overall progress
