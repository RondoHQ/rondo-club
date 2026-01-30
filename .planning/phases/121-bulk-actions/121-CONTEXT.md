# Phase 121: Bulk Actions - Context

**Gathered:** 2026-01-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can select multiple volunteers in the VOG list and perform actions on them: send VOG emails (auto-selecting new vs renewal template) and mark as "VOG requested". Single-person actions and email history tracking are out of scope.

</domain>

<decisions>
## Implementation Decisions

### Selection interface
- Checkboxes in left-most column (first column before Name)
- Header checkbox to toggle all visible rows
- Selected rows get subtle background highlight (blue/gray tint)
- Selection count displayed in floating action toolbar ("X geselecteerd")

### Claude's Discretion
- Action toolbar design and positioning (floating bottom, sticky top, etc.)
- Button styling and icons in toolbar
- Confirmation dialog wording and design
- Progress indication during email sending
- Success/error feedback display
- Handling of edge cases (no email, already sent, partial failures)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches for action toolbar, confirmation dialogs, and feedback states.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 121-bulk-actions*
*Context gathered: 2026-01-30*
