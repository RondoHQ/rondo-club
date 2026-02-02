# Phase 131: Forecast Export - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Export forecast data to Google Sheets for budget planning. Users can export the forecast view (next season projections) using the same export mechanism as current season data.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

User deferred all implementation decisions to Claude. Apply these principles:

- **Export trigger**: Use existing export button/flow — same placement and interaction as current season export
- **Sheet structure**: Match existing contributie export layout, excluding Nikki columns (as forecast has no billing data)
- **Forecast identification**: Include season key and "(Prognose)" in sheet title to distinguish from actual data
- **Success feedback**: Match existing export feedback patterns
- **Error handling**: Match existing export error patterns

</decisions>

<specifics>
## Specific Ideas

No specific requirements — apply standard patterns from existing export functionality.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 131-forecast-export*
*Context gathered: 2026-02-02*
