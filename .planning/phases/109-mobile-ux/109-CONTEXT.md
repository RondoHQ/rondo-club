# Phase 109: Mobile UX - Context

**Gathered:** 2026-01-28
**Status:** Ready for planning

<domain>
## Phase Boundary

Provide native-like mobile gestures (pull-to-refresh) and prevent iOS-specific UX issues (overscroll bounce causing accidental reloads in standalone mode). This phase is about gesture behavior and feel, not new features.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

User delegated all implementation decisions to Claude. The following approach should follow PWA best practices and match Stadion's existing patterns:

**Pull-to-refresh:**
- Enable on list views (People, Teams, Important Dates) where data refresh makes sense
- Use standard pull distance threshold (~60-80px) for native feel
- Refresh should invalidate TanStack Query cache for current view
- Disable when user is scrolled down (only trigger at top of scroll container)

**Refresh indicator:**
- Use a simple spinner/loading indicator at top of content area
- Match existing Stadion loading patterns (likely the LoadingSpinner component)
- Show during pull and while refresh is in progress
- Position below any fixed headers but above content

**Overscroll prevention:**
- Prevent iOS rubber-band bounce in standalone mode to avoid accidental navigation
- Use CSS `overscroll-behavior: none` on body/main container
- Allow normal scroll within content, only block at boundaries
- Consider allowing pull-to-refresh gesture while still preventing problematic bounce

**View-specific handling:**
- List views (People, Teams, Dates): pull-to-refresh enabled
- Detail views (Person detail, Team detail): pull-to-refresh enabled (refreshes entity data)
- Modal/form views: no pull-to-refresh (could interfere with form interaction)
- Settings: no pull-to-refresh (static content)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches that feel native on iOS and Android.

Reference: Should feel like native iOS/Android app refresh behavior — familiar gesture, responsive feedback.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 109-mobile-ux*
*Context gathered: 2026-01-28*
