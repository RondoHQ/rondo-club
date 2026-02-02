# Phase 130: Frontend Season Selector - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Season toggle UI that switches the contributie table between actual data (current season) and forecast projections (next season). Users can view projected fees based on current membership. Includes dropdown selector with clear visual distinction between views.

</domain>

<decisions>
## Implementation Decisions

### Column behavior
- Nikki and Saldo columns **instant hide** when switching to forecast view — not rendered, table reflows immediately
- Remaining columns **expand to fill** the freed space proportionally
- **Symmetrical behavior** when switching back — columns reappear instantly, others shrink to accommodate
- No tooltip or explanation for missing columns — the forecast indicator is sufficient context

### Claude's Discretion
- Dropdown placement and styling (inline with title, toolbar, etc.)
- Visual treatment for forecast distinction (badge, banner, color scheme)
- Loading/transition behavior during season switch
- Exact animation timing if any subtle transitions are added

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches for the undiscussed areas.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 130-frontend-season-selector*
*Context gathered: 2026-02-02*
