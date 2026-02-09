# Phase 157: Fee Category REST API - Context

**Gathered:** 2026-02-09
**Status:** Ready for planning

<domain>
## Phase Boundary

Expose full fee category definitions through the REST API and support CRUD operations for managing categories per season. The settings GET endpoint returns complete category objects (not just amounts), the settings POST endpoint supports adding/editing/removing/reordering categories, and the fee list endpoint includes category metadata so the frontend needs no hardcoded mappings. Validation catches duplicate slugs and missing required fields.

</domain>

<decisions>
## Implementation Decisions

### Fee list category metadata
- The GET `/fees` endpoint includes a top-level `categories` key alongside the member list
- This provides labels, sort order, and other category metadata in a single API call
- Frontend gets everything it needs without a second request

### Claude's Discretion
- **CRUD approach:** Whether to use full replacement (frontend sends entire config) or individual operations (add/edit/remove/reorder). Choose based on codebase patterns and what makes the Settings UI (Phase 158) simplest to build.
- **Validation strictness:** Whether to allow completely empty configs or require minimum coverage. Follow the Phase 156 error handling pattern (silent for missing config, loud for invalid data).
- **Error response format:** How to structure validation errors and warnings (duplicate age classes = warning per requirements).
- **Season parameter handling:** How the API handles season selection for CRUD. Follow existing patterns from the current `update_membership_fee_settings` endpoint.
- **Slug generation/validation:** Whether slugs are user-provided or auto-generated from labels.
- **Endpoint structure:** Whether to extend the existing `/membership-fees/settings` endpoints or create new ones.

</decisions>

<specifics>
## Specific Ideas

- The existing `update_membership_fee_settings` POST endpoint currently takes flat fee values (`mini: 100, senior: 200`) — this needs to evolve to handle full category objects
- The existing GET settings endpoint already returns two seasons (current + next) — maintain this pattern
- Category data structure is already defined from Phase 155: slug-keyed objects with `{ label, amount, age_classes, is_youth, sort_order }`
- Per REQUIREMENTS.md API-04: duplicate age class assignment produces a warning, not a hard error

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 157-fee-category-rest-api*
*Context gathered: 2026-02-09*
