# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-29)

**Core value:** Personal CRM with multi-user collaboration, now with scalable people list
**Current focus:** v9.0 People List Performance & Customization

## Current Position

Phase: 115 of 5 (Column Preferences UI)
Plan: 1 of 3 in phase
Status: In progress
Last activity: 2026-01-29 - Completed 115-01-PLAN.md

Progress: [████████░░] 80% (8/10 plans complete, 4/5 phases in progress)

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
- Phases complete: 4/5 (80%)
- Plans complete: 8/10 (80%)
- Plans remaining: 2

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
| 112-02-001 | Single parameter treated as exact year match | 112-02 | 2026-01-29 |
| 112-02-002 | LEFT JOIN on _birthdate (not INNER) | 112-02 | 2026-01-29 |
| 112-02-003 | Validate years between 1900-2100 | 112-02 | 2026-01-29 |
| 113-01-001 | Use Manager::get_fields() for validation and type lookup | 113-01 | 2026-01-29 |
| 113-01-002 | Sort NULL values last via COALESCE and type-specific logic | 113-01 | 2026-01-29 |
| 113-01-003 | Secondary sort by first_name for consistent ordering | 113-01 | 2026-01-29 |
| 113-02-001 | Generate birth year range 1950-current instead of deriving from data | 113-02 | 2026-01-29 |
| 113-02-002 | Use placeholderData instead of keepPreviousData | 113-02 | 2026-01-29 |
| 113-02-003 | Show loading toast for page navigation, not full spinner | 113-02 | 2026-01-29 |
| 113-02-004 | Store label filter as IDs (not names) | 113-02 | 2026-01-29 |
| 114-01-001 | Store preferences in wp_usermeta with key 'stadion_people_list_preferences' | 114-01 | 2026-01-29 |
| 114-01-002 | Empty array and reset:true both reset to defaults | 114-01 | 2026-01-29 |
| 114-01-003 | Invalid column IDs filtered silently (not rejected) | 114-01 | 2026-01-29 |
| 114-01-004 | Core columns always available | 114-01 | 2026-01-29 |
| 115-01-001 | Store column_order separately from visible_columns in user_meta | 115-01 | 2026-01-29 |
| 115-01-002 | Store column_widths as object in separate user_meta key | 115-01 | 2026-01-29 |
| 115-01-003 | Use localStorage as instant cache for column widths to prevent flicker | 115-01 | 2026-01-29 |
| 115-01-004 | Debounce width updates by 300ms to prevent excessive API calls | 115-01 | 2026-01-29 |

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

**New concerns from 113-01:**
- ACF date field format assumption (code assumes Ymd, needs production verification)
- Custom field sorting performance with 1400+ records (requires authenticated testing)
- Full test coverage requires browser console with authentication session

**New concerns from 113-02:**
- Team column shows "-" for all rows (backend doesn't return team data in filtered endpoint)
- Sort by "Team" and "Labels" falls back to first_name (backend doesn't support these yet)
- Full pagination testing requires production access with authenticated session
- Performance benchmarks need production testing (< 500ms target)

**Pre-existing lint errors:** 143 ESLint errors in unrelated files (not blocking)

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 008 | WYSIWYG custom fields render as HTML | 2026-01-29 | 8f84f75 | [008-wysiwyg-html-render](./quick/008-wysiwyg-html-render/) |
| 009 | Person header job display improvements | 2026-01-29 | cdcf587 | [009-person-header-job-display](./quick/009-person-header-job-display/) |
| 010 | VOG status indicator and Sportlink link | 2026-01-29 | 0857a5f | [010-vog-status-indicator-and-sportlink-link-](./quick/010-vog-status-indicator-and-sportlink-link-/) |

## Session Continuity

Last session: 2026-01-29 17:49 UTC
Stopped at: Completed 115-01-PLAN.md
Resume file: None

Next: Phase 115 Plan 02 - Column Settings Modal
