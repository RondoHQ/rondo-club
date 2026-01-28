# Phase 108: Offline Support - Context

**Gathered:** 2026-01-28
**Status:** Ready for planning

<domain>
## Phase Boundary

Enable basic offline functionality showing cached data when network unavailable. Users can view previously loaded contacts/teams/dates when offline, see a clear offline indicator, load static assets from cache, and see a helpful fallback page for uncached routes.

</domain>

<decisions>
## Implementation Decisions

### Offline Indicator
- Bottom banner (persistent) — fixed at bottom of screen while offline
- Subtle/muted styling — gray or neutral color, unobtrusive
- Icon + text: "You're offline" with wifi-off icon
- Show brief "Back online" confirmation (2-3 seconds) when connection returns

### Cached Data Display
- Data looks normal — no visual difference from fresh data, banner is the only indicator
- Everything in TanStack Query cache is viewable offline
- Free navigation — let user navigate anywhere, show fallback for uncached routes

### Offline Fallback Page
- Message + illustration — friendly graphic with explanation
- Simple icon style — large wifi-off icon, not custom illustration
- Friendly/casual tone: "Looks like you're offline. We can't load this page right now."
- Actions: Retry + Go back buttons

### Write Behavior Offline
- Block all writes with message — disable save buttons
- All form inputs disabled (read-only) when offline
- Banner is enough context — no additional inline messages needed
- Delete actions blocked consistently with other writes

### Claude's Discretion
- Search/filter behavior offline — decide based on complexity
- Exact banner positioning and animation
- Implementation of retry mechanism
- Static asset caching strategy

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

*Phase: 108-offline-support*
*Context gathered: 2026-01-28*
