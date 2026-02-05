# Phase 146: Integration Cleanup - Context

**Gathered:** 2026-02-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire FreeScout integration to read its base URL from club config API (hidden when not set), remove all AWC/svawc.nl-specific references from documentation and code, and verify the theme is installable by any club without code changes.

</domain>

<decisions>
## Implementation Decisions

### FreeScout integration
- FreeScout link in PersonDetail reads base URL from club config API
- When FreeScout URL is not configured (empty), the link is hidden entirely
- No discussion needed — behavior is clear from success criteria

### Documentation cleanup
- Replace svawc.nl with example.com as placeholder domain throughout docs
- Replace AWC/SV AWC references with "your club" phrasing (e.g., "your club's accent color")
- Keep Dutch terms (e.g., "leden") — this is a Dutch-origin project, don't translate
- CHANGELOG.md entries are historical record — leave AWC references in past entries as-is
- Update .env.example to use example.com placeholders instead of svawc.nl

### Installability verification
- Full codebase grep scan for awc, AWC, svawc references across ALL files (not just source)
- Rename everything found, including internal keys (ACF field keys, database meta keys) — not just surface text
- If renaming internal keys requires data migration, include that in the plan
- The scan is the verification method: zero AWC/svawc hits in source = installable

### Claude's Discretion
- Exact grep patterns and exclusion list for the scan
- Order of operations for renaming (docs first vs code first)
- How to handle any edge cases found during the scan

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

*Phase: 146-integration-cleanup*
*Context gathered: 2026-02-05*
