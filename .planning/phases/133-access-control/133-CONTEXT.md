# Phase 133: Access Control - Context

**Gathered:** 2026-02-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Capability-based access restriction for discipline case data. Register `fairplay` capability and enforce it at route level (React) and element level (tabs, nav items). Users with fairplay can view discipline cases; users without cannot access or see any indication the feature exists.

</domain>

<decisions>
## Implementation Decisions

### Denial behavior
- Direct URL access to `/discipline-cases` shows access denied page
- Access denied message: simple "You don't have permission to view this page" with back button
- Tuchtzaken tab on person detail pages: hidden completely for users without fairplay
- Discipline Cases navigation item: hidden completely for users without fairplay
- Consistent approach: feature is invisible to unauthorized users, not disabled

### Capability management
- No management UI needed — managed via WordPress user capabilities directly
- Administrators automatically get fairplay capability on theme activation
- New administrators automatically inherit fairplay capability (not just on initial activation)
- Capability assignments persist through theme deactivation/reactivation cycles

### Claude's Discretion
- Implementation approach for capability checking in React (context, hook, etc.)
- REST API enforcement strategy (whether to also block API access or just UI)
- How to pass capability status from WordPress to React frontend

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 133-access-control*
*Context gathered: 2026-02-03*
