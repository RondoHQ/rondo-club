# Phase 84: Settings & Person UI - Context

**Gathered:** 2026-01-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Users can manage their Google Contacts sync connection in Settings and view sync status per contact on Person detail pages. This phase completes the UI layer for the sync infrastructure built in Phases 79-83.

Note: Much of the Settings UI already exists from Phase 82-03 (sync frequency dropdown, "Sync Now" button, bulk export). This phase focuses on completing the remaining requirements and adding Person-level integration.

</domain>

<decisions>
## Implementation Decisions

### Settings Connection Card
- Continue existing card pattern in Contacts subtab
- Display: connected Google account email, last sync time, linked contact count
- Show error count if any sync errors occurred (non-disruptive, info only)
- Connect/disconnect buttons with existing styling

### Sync Status Display
- Last sync time shown as relative ("5 minutes ago") with full timestamp on hover
- Contact counts: total linked, successfully synced, with errors
- Error display: collapse to "X errors" with expandable details if user wants them
- Match existing UI patterns from Calendar sync display

### Sync Preferences
- Sync frequency: dropdown with 15min, hourly, 6hr, daily options (already exists from 82-03)
- Conflict resolution: default "Stadion wins" strategy is automatic — no user-facing toggle needed
- Keep UI simple since conflict resolution logs to activity timeline anyway

### Person Page Integration
- Add "View in Google Contacts" external link for synced contacts
- Display as small link icon near contact info (not prominent — utility feature)
- Only show when person has `_google_contact_id` meta
- Link format: `https://contacts.google.com/person/{resourceName}`

### Claude's Discretion
- Exact placement of "View in Google" link on Person detail
- Sync status section layout and spacing
- Error detail formatting and truncation
- Loading states and transitions

</decisions>

<specifics>
## Specific Ideas

- Settings UI should feel consistent with existing Calendar sync display
- "View in Google Contacts" link should be subtle, not dominant — most users won't need it
- Error information is audit trail, not actionable — display accordingly

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 84-settings-person-ui*
*Context gathered: 2026-01-17*
