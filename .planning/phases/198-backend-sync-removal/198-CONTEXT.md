# Phase 198: Backend Sync Removal - Context

**Gathered:** 2026-02-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Delete all Google Contacts and Calendar sync PHP classes and REST endpoints from the backend. This is a pure removal phase — no new functionality is added. OAuth cleanup and frontend removal happen in Phase 199.

</domain>

<decisions>
## Implementation Decisions

### Data cleanup approach
- Code-only removal — delete PHP class files and endpoint registrations
- Do NOT clean up orphaned database entries (options, user meta, transients related to Google sync)
- Orphaned data is harmless and the database is small enough that it doesn't matter

### Cron event handling
- Explicitly deregister all sync-related WP-Cron hooks using `wp_clear_scheduled_hook()`
- Add deregistration calls in `functions.php` or a one-time cleanup to ensure no phantom cron events keep firing
- This keeps the cron system tidy even though WordPress handles missing callbacks gracefully

### Claude's Discretion
- Order of file deletion (Contacts first vs Calendar first)
- Whether to split into two plans (one per sync system) or combine into one
- How to handle any shared utility code between Contacts and Calendar sync

</decisions>

<specifics>
## Specific Ideas

No specific requirements — the roadmap already lists exact files to delete and success criteria to meet.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 198-backend-sync-removal*
*Context gathered: 2026-02-20*
