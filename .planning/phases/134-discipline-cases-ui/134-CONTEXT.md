# Phase 134: Discipline Cases UI - Context

**Gathered:** 2026-02-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete user interface for viewing discipline cases. This includes a list page at `/discipline-cases` with table view and season filtering, plus a "Tuchtzaken" tab on person detail pages showing cases linked to that person. Cases are read-only (synced from Sportlink). Access is restricted to users with the `fairplay` capability (implemented in Phase 133).

</domain>

<decisions>
## Implementation Decisions

### Table Layout & Columns
- Columns: Person, Match, Sanction, Fee (not the full set from success criteria)
- Match column combines match-description and match-date in one cell
- Person column links to person profile (standard profile page, not directly to tab)
- Fee displayed with € prefix and decimals (e.g., €25,00)
- Click row to expand inline showing additional fields (charges, full sanction text)

### Season Filter Behavior
- Default selection: current season (using `get_current_season` helper)
- Include "Alle seizoenen" option to view all cases across seasons
- Hide empty seasons from dropdown (only show seasons with cases)
- Filter positioned above table, left-aligned

### Person Tab Integration
- Reuse same table component as list page, minus Person column
- No season filter on person tab — show all their cases
- Hide Tuchtzaken tab entirely if person has zero discipline cases
- Row expand behavior same as list page (inline details)

### Claude's Discretion
- Loading skeleton/spinner design
- Exact column widths and responsive behavior
- Empty state messaging on list page when season has no cases
- Sorting implementation details (date column sortable per success criteria)

</decisions>

<specifics>
## Specific Ideas

- Table should follow existing patterns in Stadion (PeopleList, TeamsList, etc.)
- Dutch labels throughout (Tuchtzaken, Seizoen, etc.)
- Currency formatting consistent with Dutch locale (€25,00 not €25.00)

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 134-discipline-cases-ui*
*Context gathered: 2026-02-03*
