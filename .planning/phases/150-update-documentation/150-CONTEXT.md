# Phase 150: Update Documentation - Context

**Gathered:** 2026-02-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix stale "important dates" references in documentation after v19.0 removed that system. Birthdate is now a direct person field, not an Important Date CPT.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
- All documentation updates — mechanical find-and-fix
- How to phrase removed features (Important Dates CPT, `/dates` route)
- Whether to remove entire sections or update them

</decisions>

<specifics>
## Specific Ideas

No specific requirements — standard documentation cleanup approach.

**Files to update (from audit):**
- `docs/carddav.md` — Birthday reference
- `docs/api-leden-crud.md` — birth_year derived from important date
- `docs/frontend-architecture.md` — `/dates` route reference
- `docs/ical-feed.md` — iCal subscription for important dates
- `docs/multi-user.md` — Upcoming important dates reference

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 150-update-documentation*
*Context gathered: 2026-02-06*
