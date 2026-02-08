# Phase 155: Fee Category Data Model - Context

**Gathered:** 2026-02-08
**Status:** Ready for planning

<domain>
## Phase Boundary

Store fee category definitions per season in the existing `rondo_membership_fees_{season}` WordPress option. Define the new data structure, provide read/write helpers, and update the season copy-forward mechanism to work with full categories. No auto-migration — data will be manually populated.

</domain>

<decisions>
## Implementation Decisions

### Data structure
- Option value is a slug-keyed object: `{ "senior": { label, amount, age_min, age_max, is_youth, sort_order }, ... }`
- Amounts live inside each category object only — no top-level amount keys
- The option contains categories only — no wrapper object, no version field, no metadata
- `is_youth` is an explicit boolean on each category, not inferred from age ranges

### Migration
- No auto-migration code. This is a single-club app; user will manually populate the new data format
- No WP-CLI seed command, no settings page seeding
- No validation on read — trust the data in the option

### Season copy-forward
- Updated in Phase 155 (not deferred to 156) since the data model is changing
- Copy-forward clones everything as-is: slugs, labels, amounts, age ranges, youth flags, sort order
- Admin adjusts amounts for the new season later via settings UI (Phase 158)
- If previous season has no categories, new season gets an empty categories object

### Backward compatibility
- Phase 155 changes the data shape with no backward compatibility layer
- Existing code that reads fee amounts directly will break — fixed in Phase 156
- **Do not deploy Phase 155 alone** — deploy only after Phase 156 is also complete
- No temporary scaffolding, debug logging, or feature flags — keep it clean

### Claude's Discretion
- Helper function signatures and internal organization
- Where to place the helper class/functions in the codebase
- How to structure the copy-forward update

</decisions>

<specifics>
## Specific Ideas

- The existing `rondo_membership_fees_{season}` option key is reused — just the value shape changes
- Sort order is an explicit field per category (not implicit from array position, since it's a keyed object)

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 155-fee-category-data-model*
*Context gathered: 2026-02-08*
