# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-17)

**Core value:** Seamless bidirectional sync between Caelis and Google Contacts with Caelis as source of truth
**Current focus:** Phase 84 - Settings & Person UI (Complete)

## Current Position

Milestone: v5.0 Google Contacts Sync
Phase: 84 of 85 (Settings & Person UI)
Plan: 02 of 02 (complete)
Status: Phase complete
Last activity: 2026-01-18 - Completed 84-02-PLAN.md

Progress: [█████████░] ~90%

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
| v3.1 Pending Response Tracking | 24-28 | 9 | 2026-01-14 |
| v3.2 Person Profile Polish | 29-31 | 3 | 2026-01-14 |
| v3.3 Todo Enhancement | 32-34 | 3 | 2026-01-14 |
| v3.4 UI Polish | 35-37 | 3 | 2026-01-14 |
| v3.5 Bug Fixes & Polish | 38-39 | 2 | 2026-01-14 |
| v3.6 Quick Wins & Performance | 40-41 | 2 | 2026-01-14 |
| v3.7 Todo UX Polish | 42 | 1 | 2026-01-15 |
| v3.8 Theme Customization | 43-46 | 10 | 2026-01-15 |
| v4.0 Calendar Integration | 47-55 | 11 | 2026-01-15 |
| v4.1 Bug Fixes & Polish | 56-57 | 3 | 2026-01-15 |
| v4.2 Settings & Stability | 58-60 | 3 | 2026-01-15 |
| v4.3 Performance & Documentation | 61-63 | 5 | 2026-01-16 |
| v4.4 Code Organization | 64-66 | 6 | 2026-01-16 |
| v4.5 Calendar Sync Control | 67-68 | 3 | 2026-01-16 |
| v4.6 Dashboard & Polish | 69-70 | 2 | 2026-01-16 |
| v4.7 Dark Mode & Activity Polish | 71-72 | 4 | 2026-01-17 |
| v4.8 Meeting Enhancements | 73-76 | 6 | 2026-01-17 |
| v4.9 Dashboard & Calendar Polish | 77-78 | 4 | 2026-01-17 |
**Total:** 29 milestones, 78 phases, 144 plans completed

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

**v5.0 Decisions:**
| Decision | Rationale | Phase |
|----------|-----------|-------|
| Separate callback endpoint for contacts OAuth | Different post-auth behavior (redirect to subtab=contacts, set pending_import flag) | 79-01 |
| User-level connection storage for contacts | Contacts sync is account-wide, unlike calendar which is per-resource | 79-01 |
| No token revocation on disconnect | User may have Calendar connected with same Google account | 79-01 |
| Pending import flag triggers Phase 80 auto-import | Decouple OAuth completion from import processing | 79-01 |
| Default to readwrite access mode | Bidirectional sync requires write access | 79-02 |
| Add email scope to OAuth request | Reliable user identification in connected state display | 79-02 |
| Skip contacts without email | Match by email only - no name-based matching per CONTEXT.md | 80-01 |
| Fill gaps only on duplicate match | Never overwrite existing Caelis data | 80-01 |
| Google IDs as post meta | Store _google_contact_id, _google_etag for future sync | 80-01 |
| Synchronous import endpoint | Simpler implementation, async can be added later if needed | 80-02 |
| Return full stats object | Detailed UI feedback with all import counts | 80-02 |
| Auto-import via useEffect on flag | Ensures import starts even if status already loaded | 80-03 |
| Query invalidation after import | Refresh people, companies, dates, dashboard after import | 80-03 |
| Mirror import class structure | Consistency and maintainability for export class | 81-01 |
| Check readwrite access before export | User may only have readonly scope | 81-01 |
| Retry on etag conflict | Google requires etag; conflicts need automatic handling | 81-01 |
| Async export via WP-Cron | Avoid blocking contact save operations | 81-02 |
| Follow existing async pattern | Use same WP-Cron pattern as calendar rematch for consistency | 81-02 |
| Sequential bulk export with 100ms delay | Avoid Google API rate limits (max 10 req/sec) | 81-03 |
| Progress callback parameter | Future CLI integration for Phase 85 | 81-03 |
| Check if cron schedule exists before adding | Calendar sync may have already registered 15-minute interval | 82-01 |
| Default sync frequency of 60 minutes | Hourly balances freshness with API quota | 82-01 |
| Placeholder sync_user implementation | Establishes infrastructure, Plan 02 adds actual delta logic | 82-01 |
| Fall back to full import when syncToken missing/expired | Graceful degradation ensures sync always completes | 82-02 |
| Remove Google meta but preserve Caelis data on unlink | User's data is valuable, only association is removed | 82-02 |
| Only push linked contacts (with _google_contact_id) | Don't auto-create Google contacts for unlinked Caelis contacts | 82-02 |
| Pull from Google first, then push local changes | Have latest Google state before pushing changes | 82-02 |
| Default sync frequency is 60 minutes (hourly) | Balances freshness with API quotas | 82-03 |
| Manual sync bypasses frequency check | Allows immediate sync on user request | 82-03 |
| Frequency options: 15min, hourly, 6hr, daily | Standard cron-like intervals | 82-03 |
| Use before_delete_post hook for deletion sync | Fires before meta deletion, only on permanent delete | 83-02 |
| Treat 404 as success for Google deletion | Contact already deleted in Google is desired state | 83-02 |
| Never block local deletion on API errors | Local operation must always complete | 83-02 |
| Snapshot stored as _google_synced_fields post meta | Enables three-way conflict comparison | 83-01 |
| Three-way comparison for conflict detection | Compare Google vs Caelis vs snapshot to detect actual conflicts | 83-01 |
| Log conflicts as TYPE_ACTIVITY with sync_conflict type | Audit trail for automated conflict resolution | 83-01 |
| Sync history stored in connection meta (last 10) | Efficient storage for UI display without unbounded growth | 84-01 |
| google_contact_id returns null for unlinked | Clean API contract for frontend | 84-01 |
| Error indicator using details/summary for collapsible view | Progressive disclosure without complex JS state | 84-02 |
| Google link placement in header next to name | Subtle accessibility without crowding Contact Information card | 84-02 |

### Pending Todos

2 active todos in `.planning/todos/pending/`:
- Add import from Twenty CRM (api)
- Debug add email to attendee from meeting view fails (api)

See `/gsd:check-todos` for full list.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-01-18 00:23 UTC
Stopped at: Completed 84-02-PLAN.md
Resume file: None

## Next Steps

- Phase 84 Settings & Person UI complete
- Next: Phase 85 Polish & CLI
- Then: v5.0 milestone complete
