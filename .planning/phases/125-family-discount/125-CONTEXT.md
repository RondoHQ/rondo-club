# Phase 125: Family Discount - Context

**Gathered:** 2026-01-31
**Status:** Ready for planning

<domain>
## Phase Boundary

Youth members at same address receive tiered family discounts. System groups members by normalized address, applies 25% discount to second youth member, 50% to third and beyond. Discount applies to youngest/cheapest first (descending by base fee). Recreants and donateurs do not receive family discount.

</domain>

<decisions>
## Implementation Decisions

### Address matching
- Match on **postal code + house number** only (street name ignored)
- House number additions ARE significant: 12A and 12B are different addresses (separate families)
- Normalize postal codes: remove spaces, convert to uppercase ("1234 ab" → "1234AB")
- Members with missing or incomplete address data are excluded from family grouping (pay full fee, no error)

### Claude's Discretion
- Exact normalization implementation details
- How to extract/parse house number and addition from address fields
- Internal data structure for family groups

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

*Phase: 125-family-discount*
*Context gathered: 2026-01-31*
