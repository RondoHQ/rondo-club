# Phase 126: Pro-rata & UI - Context

**Gathered:** 2026-01-31
**Status:** Ready for planning

<domain>
## Phase Boundary

Display calculated membership fees in a list view accessible via sidebar navigation. The list shows fee breakdowns including base fee, family discount, and pro-rata adjustments based on Sportlink join date. Users can filter to identify address mismatches for data quality review.

</domain>

<decisions>
## Implementation Decisions

### List layout & columns
- Primary sort order: by age group (Mini, Pupil, Junior, Senior, Recreant, Donateur)
- Only show fee-eligible members (skip those without age group or excluded from calculation)
- Detailed columns: Name, Age Group, Base Fee, Family Discount, Pro-rata, Final Amount
- Clicking a row links to the member's profile page

### Pro-rata presentation
- Show both percentage and calculated amount (e.g., "75% = €172")
- Highlight rows with pro-rata adjustments (< 100%) with distinct visual treatment
- Use Sportlink registration date as the join date source
- Members without Sportlink registration date: flag as data issue (show in list but mark as incomplete)

### Address mismatch filter
- Dropdown filter with options: All / Mismatches / Valid addresses
- Mismatch rows always show warning icon/color, even when not filtered
- Clicking warning shows tooltip with specific issue details

### Claude's Discretion
- Navigation placement in sidebar (requirement says "below Leden, above VOG")
- Empty state design when no fees calculated
- Exact visual treatment for pro-rata highlighting
- Definition of what constitutes an "address mismatch" based on data model analysis
- Tooltip implementation details

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches for list views and filtering.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 126-pro-rata-ui*
*Context gathered: 2026-01-31*
