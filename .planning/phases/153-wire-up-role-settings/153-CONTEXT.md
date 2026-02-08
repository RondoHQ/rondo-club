# Phase 153: Wire Up Role Settings - Context

**Gathered:** 2026-02-07
**Status:** Ready for planning

<domain>
## Phase Boundary

Replace the last hardcoded role array in the frontend with a settings-driven lookup. The backend volunteer status calculation (VolunteerStatus class) already reads from WordPress options — only the TeamDetail.jsx player/staff split still uses a hardcoded array.

**Already complete (from v19.1.0):**
- Volunteer status calculation reads player roles from `rondo_player_roles` option (criteria 1)
- Volunteer status respects excluded roles from `rondo_excluded_roles` option (criteria 3)
- REST API endpoint `/rondo/v1/volunteer-roles/settings` returns current role configuration

**Remaining work:**
- TeamDetail.jsx line 330 has hardcoded `playerRoles` array that must be replaced with settings lookup (criteria 2)

</domain>

<decisions>
## Implementation Decisions

### Data fetching strategy
- Claude's discretion on approach (dedicated API call, shared TanStack Query hook, or extending team response)
- The existing `/rondo/v1/volunteer-roles/settings` endpoint already returns `player_roles` array

### Claude's Discretion
- How to fetch player roles in TeamDetail (hook vs inline call vs extending response)
- Fallback behavior if settings unavailable
- Caching strategy for role settings data

</decisions>

<specifics>
## Specific Ideas

- The existing `VolunteerStatus::get_player_roles()` and the REST endpoint both return the same data — frontend should consume the API version
- Role settings rarely change, so aggressive caching is appropriate

</specifics>

<deferred>
## Deferred Ideas

- Tabbed settings restructure (General / Roles / Fees tabs) — cosmetic improvement, not required for v20.0 goals
- Member count display on role settings page — already implemented in v19.1.0 UI

</deferred>

---

*Phase: 153-wire-up-role-settings*
*Context gathered: 2026-02-07*
