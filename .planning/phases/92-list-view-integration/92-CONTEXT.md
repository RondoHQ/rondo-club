# Phase 92: List View Integration - Context

**Gathered:** 2026-01-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Custom fields can appear as columns in People and Organizations list views. Admin can toggle visibility and configure column order. Creating new field types or changing detail view behavior is out of scope.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

User delegated all decisions to Claude. The following areas are open for Claude to decide during planning/implementation:

**Column rendering:**
- How different field types display in narrow columns
- Truncation strategy for text fields
- Use of icons, badges, or color indicators
- Tooltip behavior for truncated content

**Show in list toggle:**
- Where the toggle lives (per-field setting in admin, column picker in list view, or both)
- UI pattern for enabling/disabling columns

**Column ordering:**
- Mechanism for setting custom field column order
- Whether ordering happens in settings, list view, or both

**Default behavior:**
- Whether custom fields are shown by default or hidden until enabled
- Behavior for newly created fields

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches that match existing Caelis patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 92-list-view-integration*
*Context gathered: 2026-01-19*
