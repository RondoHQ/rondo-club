# Phase 122: Tracking & Polish - Context

**Gathered:** 2026-01-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can track VOG email status and view history. This includes filtering the VOG list by email status (sent/not sent) and viewing email history on person profile pages. The infrastructure should support future email tracking beyond VOG.

</domain>

<decisions>
## Implementation Decisions

### Filter UI
- Dropdown filter in VOG list header, positioned next to existing controls
- Three options: Alle / Niet verzonden / Wel verzonden
- Show counts per option (e.g., "Niet verzonden (12)")

### Email History Display
- Email history appears in the existing activity timeline on person profile
- Each entry shows: date, template type (nieuw/vernieuwing), sent by whom
- Visually distinct from regular notes/activities — use email icon and different styling
- Clicking an email entry expands to show the actual content that was sent

### Status Indicators
- New "Verzonden" column in VOG list table showing the email send date
- Empty cell / dash when no email has been sent
- Column is sortable (by send date)
- Date format: absolute dates like "28 jan 2026"

### History Scope
- Build general email tracking infrastructure, not VOG-specific
- Use existing comment/activity system with email type
- Store actual rendered content (snapshot of what was sent)
- Only log successful sends, not failures

### Claude's Discretion
- Exact placement of dropdown within header controls
- Email icon choice and styling details
- How to structure the expandable email content view
- Comment type naming and metadata structure

</decisions>

<specifics>
## Specific Ideas

- Email entries in timeline should feel like audit trail items — clear record of what happened
- The dropdown with counts helps users quickly see how many people still need follow-up

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 122-tracking-polish*
*Context gathered: 2026-01-30*
