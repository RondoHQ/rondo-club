# Phase 98: Admin Management - Context

**Gathered:** 2026-01-21
**Status:** Ready for planning

<domain>
## Phase Boundary

Admin UI for managing all feedback — viewing/filtering feedback from all users, changing status, assigning priority, ordering items, and managing application passwords. User-facing feedback submission is complete (Phase 97).

</domain>

<decisions>
## Implementation Decisions

### Application Password UI
- Dedicated "API Access" tab in Settings
- List shows: name, last used date, revoke button
- Creating new password opens modal with one-click copy button
- Password only visible once (in modal), dismiss closes forever
- Revoke requires confirmation dialog before deletion

### Claude's Discretion
- Admin feedback list layout (table vs cards)
- Status workflow controls placement
- Sorting and filtering implementation
- Priority assignment UI pattern
- Confirmation dialog styling

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches for admin list and status workflow.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 98-admin-management*
*Context gathered: 2026-01-21*
