# Phase 148: Infrastructure Removal - Context

**Gathered:** 2026-02-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Delete all Important Date data from production and completely remove the Important Dates subsystem from the codebase. This includes the CPT, taxonomy, frontend components, routes, and REST endpoints. The birthdate functionality has moved to person records (Phase 147), making this infrastructure obsolete.

</domain>

<decisions>
## Implementation Decisions

### Data deletion approach
- Use WP-CLI commands for deletion (not direct SQL)
- No backup needed — data is redundant with Sportlink
- Silent deletion — no verbose output required
- Delete all date_type taxonomy terms (clean removal, no orphans)

### Removal order & verification
- Data first, then code — delete posts/terms while CPT still registered
- Single deploy at end — all removals in one commit, test once
- Remove "Datums" nav item entirely — no feature flag, clean break
- No URL redirects — /dates or /datums just 404, clean break

### Code cleanup scope
- Thorough removal — follow all references, remove dead code
- Delete ACF JSON field group file for important dates
- Update documentation — remove mentions from docs/ and API docs
- Remove related test files

### Claude's Discretion
- Exact order of file deletions within each category
- Whether to consolidate into one or two plans
- How to identify all dependent/dead code

</decisions>

<specifics>
## Specific Ideas

No specific requirements — standard removal approach following WordPress cleanup best practices.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 148-infrastructure-removal*
*Context gathered: 2026-02-06*
