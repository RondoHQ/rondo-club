# Phase 151: Dynamic Filters - Context

**Gathered:** 2026-02-07
**Status:** Ready for planning

<domain>
## Phase Boundary

Derive filter options on the People list from actual database values instead of hardcoded arrays. Age group and member type filters (and any other text-value filters) become data-driven. Boolean and date-range filters stay as-is. Scope is the People list only — other list pages are not included.

</domain>

<decisions>
## Implementation Decisions

### Filter data delivery
- Claude's discretion on delivery mechanism (dedicated endpoint vs embedded in list response)
- Filter dropdowns always show all available values regardless of other active filters (no cross-filter narrowing)
- Claude's discretion on caching/freshness strategy
- Each filter option shows a count of matching people (e.g., "Junior (42)", "Senioren (87)")
- The "Alle" default option also shows a total count (e.g., "Alle (523)")

### Empty/stale value handling
- Hide zero-count values — only show options with at least 1 matching person
- If a user has an active filter and that value disappears from the database, keep the filter active and show empty results (user manually clears)
- Sort age groups in logical/natural order: youngest to oldest, with smart numeric extraction for insertion of new values (e.g., "Onder 20" inserts after "Onder 19", non-numeric values go at the end)
- Member types also sorted in a logical/meaningful order

### Default & fallback behavior
- Dropdowns are disabled/grayed out with a loading indicator while filter options load from the API
- If the filter options API call fails, show dropdowns in an error state with a retry button
- If a URL query param contains a filter value that doesn't exist in current filter options, silently ignore/drop that filter
- New values appearing via sync are purely automatic — no admin notification needed

### Scope of dynamic filters
- All text/string-value filters become dynamic (age group, member type)
- Boolean filters (volunteer, financial block) and date-range filters stay hardcoded
- Only the People list — Teams and Important Dates pages are not in scope
- Build a generic/reusable filter infrastructure so future phases can easily make additional meta fields dynamic

### Claude's Discretion
- Delivery mechanism (dedicated endpoint vs embedded response)
- Caching/freshness strategy for filter options
- Technical implementation of the generic filter system
- Loading indicator design
- Error state design with retry

</decisions>

<specifics>
## Specific Ideas

- Counts next to every filter option including "Alle" — the user wants to see how many people match each value
- Smart insertion for ordering: extract numeric parts from values like "Onder 7" to place new values in the correct position automatically
- Generic filter system: build it so any postmeta field can become a dynamic filter with minimal additional code

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 151-dynamic-filters*
*Context gathered: 2026-02-07*
