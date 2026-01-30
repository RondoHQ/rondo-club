# Phase 120: VOG List Page - Context

**Gathered:** 2026-01-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Display a filtered list of volunteers who need VOG action. List shows only volunteers with huidig-vrijwilliger=true AND (no datum-vog OR datum-vog 3+ years ago). Users can view volunteer details and see who needs a new VOG vs renewal. Bulk actions and email sending are Phase 121.

</domain>

<decisions>
## Implementation Decisions

### Navigation placement
- VOG appears as indented sub-item under People in sidebar
- Uses FileCheck icon (document with checkmark)
- Shows count badge with number of volunteers needing VOG action (e.g., "VOG (12)")

### List layout & columns
- Column order: Name → KNVB ID → Email → Phone → Datum VOG
- All columns are sortable
- Default sort: Datum VOG oldest first (nulls first, then oldest dates)
- Only the name is clickable (links to person profile), not the entire row

### Visual indicators
- Badge next to name distinguishes volunteer type:
  - "Nieuw" (blue) — volunteer has never had a VOG
  - "Vernieuwing" (purple) — volunteer's VOG expired (3+ years)
- No urgency styling for very old expirations — all treated equally
- Show indicator when VOG email was already sent (icon with date on hover)

### Empty & edge states
- Empty state shows success message: "Alle vrijwilligers hebben een geldige VOG" with checkmark icon
- Volunteers without email: show normally with empty email cell
- Volunteers without phone: show empty cell (consistent with email)
- No count header on page (count already visible in nav badge)

### Claude's Discretion
- Exact badge styling and positioning
- Loading state design
- Hover states and micro-interactions
- Error handling for failed data loads

</decisions>

<specifics>
## Specific Ideas

- Navigation should feel natural as a "sub-view" of People, since VOG is about managing a subset of people
- Badge labels are Dutch: "Nieuw" and "Vernieuwing"
- Success empty state should feel positive/celebratory, not just neutral

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 120-vog-list-page*
*Context gathered: 2026-01-30*
