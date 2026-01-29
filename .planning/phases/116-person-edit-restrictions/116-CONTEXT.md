# Phase 116: Person Edit Restrictions - Context

**Gathered:** 2026-01-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Remove delete, address adding, and work history editing from PersonDetail UI. API remains fully functional for automation. This is purely UI restriction — no backend changes to REST endpoints.

</domain>

<decisions>
## Implementation Decisions

### Visual removal approach
- Hide controls completely (not disabled/grayed out)
- No messaging or explanation to users — controls simply aren't present
- Unconditional removal for all users (no role-based exceptions)
- Other edit actions beyond the three specified (delete, add address, work history) need case-by-case review during planning

### Work history display
- Clicking work history items does nothing — plain text display
- Remove all interactive visual cues (no hover effect, no pointer cursor)
- Hide all controls: no edit buttons, no delete buttons, no add button — entire section is read-only
- Show work history section even when empty (consistent section presence)

### Claude's Discretion
- Exact CSS changes for removing hover/cursor effects
- How to handle any edge cases discovered during implementation
- Address section styling (not explicitly discussed but follows same "hide completely" pattern)

</decisions>

<specifics>
## Specific Ideas

- Controls should simply not render rather than being conditionally styled
- Work history should look like static content, not an interactive list

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 116-person-edit-restrictions*
*Context gathered: 2026-01-29*
