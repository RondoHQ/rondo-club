# Phase 107: PWA Foundation - Context

**Gathered:** 2026-01-28
**Status:** Ready for planning

<domain>
## Phase Boundary

Make Stadion installable on iOS and Android with proper platform-specific support. Users can add to home screen and launch in standalone mode with correct icons, splash screens, and safe area handling.

</domain>

<decisions>
## Implementation Decisions

### App Identity
- App name on home screen: "Stadion" (full name)
- Short name for constrained spaces: "Stadion" (same)
- Description: "Club data management"
- Category: Sports/Sports clubs
- Scope: Restricted to Stadion domain only — external links open in browser

### Visual Branding
- Icon design: Stadium symbol representing the app name
- Icon color: Match user's configured accent color from settings
- Splash screen: Stadium icon centered with "Stadion" text below
- Theme adaptation: Dark splash on dark mode, light splash on light mode

### Install Behavior
- Display mode: Standalone (no browser chrome)
- Orientation: Any (allow rotation for tablet use)
- Start URL: /dashboard
- External links: Open in system browser (new window)

### iOS Specifics
- Status bar: Match light/dark theme (dark text on light, light text on dark)
- Safe areas: Use safe area insets — content never under notch/Dynamic Island
- Splash screens: Generate for all iOS device sizes (iPhone SE through 15 Pro Max)
- Text scaling: Allow iOS accessibility text size preferences

### Claude's Discretion
- Exact splash screen generation approach
- Specific icon sizes to include in manifest
- Service worker registration timing
- Meta tag implementation details

</decisions>

<specifics>
## Specific Ideas

- Icon should be a recognizable stadium/arena symbol
- Splash screens need both light and dark variants
- External links (like Google Calendar OAuth flows) must open in real browser to work correctly

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 107-pwa-foundation*
*Context gathered: 2026-01-28*
