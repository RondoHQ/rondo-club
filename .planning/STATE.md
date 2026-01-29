# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-29)

**Core value:** Personal CRM with multi-user collaboration, now with scalable people list
**Current focus:** v9.0 People List Performance & Customization

## Current Position

Phase: 112 of 5 (Birthdate Denormalization)
Plan: 1 of 2 in phase
Status: In progress
Last activity: 2026-01-29 - Completed 112-01-PLAN.md

Progress: [███░░░░░░░] 30% (3/10 plans complete, 1/5 phases complete)

## Milestone History

- v1.0 Tech Debt Cleanup - shipped 2026-01-13
- v2.0 Multi-User - shipped 2026-01-13
- v3.0 Testing Infrastructure - shipped 2026-01-14
- v4.0 Calendar Integration - shipped 2026-01-17
- v5.0 Google Contacts Sync - shipped 2026-01-18
- v6.0 Custom Fields - shipped 2026-01-21
- v7.0 Dutch Localization - shipped 2026-01-25
- v8.0 PWA Enhancement - shipped 2026-01-28

## Performance Metrics

**v9.0 Milestone:**
- Total phases: 5
- Total requirements: 27
- Requirements mapped: 27/27 (100%)
- Phases complete: 1/5 (20%)
- Plans complete: 3/10 (30%)
- Plans remaining: 7

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
v8.0 milestone decisions archived to milestones/v8.0-ROADMAP.md.

**v9.0 architectural decisions (from research):**
- Traditional pagination over infinite scroll (better for goal-oriented tasks)
- Birth year denormalization required (ACF repeater queries too slow)
- 100 per page default (research: optimal range 20-100)
- Server-side filtering/sorting (client-side doesn't scale beyond 100 records)
- User_meta storage for column preferences (follows theme_preferences pattern)

**v9.0 implementation decisions (from execution):**

| ID | Decision | Plan | Date |
|----|----------|------|------|
| 111-01-001 | Use wpdb with LEFT JOIN for meta fields | 111-01 | 2026-01-29 |
| 111-01-002 | Use INNER JOIN for taxonomy filters | 111-01 | 2026-01-29 |
| 111-01-003 | Post-query fetch for thumbnail and labels | 111-01 | 2026-01-29 |
| 111-02-001 | Create new hook instead of modifying usePeople | 111-02 | 2026-01-29 |
| 111-02-002 | Use placeholderData instead of keepPreviousData | 111-02 | 2026-01-29 |
| 111-02-003 | Include all filter params in query key | 111-02 | 2026-01-29 |
| 112-01-001 | Store birthdate as YYYY-MM-DD in _birthdate meta | 112-01 | 2026-01-29 |
| 112-01-002 | Use save_post_important_date hook at priority 20 | 112-01 | 2026-01-29 |
| 112-01-003 | Sync on save, clear on permanent delete only | 112-01 | 2026-01-29 |
| 112-01-004 | Migration uses suppress_filters => true | 112-01 | 2026-01-29 |

### Pending Todos

1 todo in `.planning/todos/pending/`

### Blockers/Concerns

**Known risks for v9.0 (from research):**
- ✅ Access control bypass in custom $wpdb queries - MITIGATED in 111-01 (explicit is_user_approved() check)
- ✅ SQL injection via unsanitized filter params - MITIGATED in 111-01 (whitelist + wpdb->prepare())
- JOIN performance degradation with 4+ meta filters (limit to 3-4, use caching)
- TanStack Query stale data after mutations (use resetQueries() not invalidateQueries())

**New concerns from 111-01:**
- Performance testing needed with 1400+ records and complex filters
- Cache strategy undefined (consider transient caching for repeated queries)
- N+1 queries for thumbnails/labels (acceptable for paginated results but should monitor)

**New concerns from 111-02:**
- Cache invalidation strategy for filtered queries (need to invalidate peopleKeys.all, not just peopleKeys.lists)
- Initial page load performance with 100 results (may need to adjust default perPage)
- Empty state handling when filters return 0 results (UI must distinguish "no people" vs "no matches")

**Pre-existing lint errors:** 143 ESLint errors in unrelated files (not blocking)

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 008 | WYSIWYG custom fields render as HTML | 2026-01-29 | 8f84f75 | [008-wysiwyg-html-render](./quick/008-wysiwyg-html-render/) |
| 009 | Person header job display improvements | 2026-01-29 | cdcf587 | [009-person-header-job-display](./quick/009-person-header-job-display/) |
| 010 | VOG status indicator and Sportlink link | 2026-01-29 | 0857a5f | [010-vog-status-indicator-and-sportlink-link-](./quick/010-vog-status-indicator-and-sportlink-link-/) |

## Session Continuity

Last session: 2026-01-29 14:25 UTC
Stopped at: Completed 112-01-PLAN.md
Resume file: None

Next: Execute 112-02-PLAN.md or continue to Phase 113
