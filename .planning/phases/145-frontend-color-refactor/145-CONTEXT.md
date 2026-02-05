# Phase 145: Frontend & Color Refactor - Context

**Gathered:** 2026-02-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Users see club configuration in Settings UI and AWC color scheme becomes dynamic club color throughout the application. This phase delivers: (1) an admin-only club configuration section in Settings, (2) club color integration in the user accent picker, (3) login/favicon/PWA branding from club config, and (4) a full codebase rename from `awc` to `club` color keys.

</domain>

<decisions>
## Implementation Decisions

### Settings UI layout
- Club configuration appears as the **first section** on the existing Settings page, above user-specific settings
- **Hidden for non-admin users** — regular users don't see the club config section at all
- All three fields (name, color, FreeScout URL) in a **single "Club Configuration" section**, stacked vertically
- Color field uses a **visual color picker with hex input** — click swatch to open picker, or type hex directly

### Color picker behavior
- Club color appears as the **first swatch** in the user accent color picker, labeled "Club"
- Users who selected "Club" as their accent **auto-update** when admin changes the club color — always reflects current club color
- **Live preview** in Settings — color swatch and app theme update as admin picks colors, before saving
- **"Club" is the default** accent color for new users — they can change it in their own Settings

### Login & branding
- Login screen uses the **club color** for primary accent (buttons, links)
- Login screen **shows the club name** as a heading/subtitle — makes it feel branded
- Favicon SVG **dynamically uses the club color** so browser tab reflects the club's brand
- PWA manifest `theme_color` and `background_color` **use the club color**

### AWC→Club rename
- **Auto-migrate on load** — if a user has 'awc' saved as their accent, the app treats it as 'club' automatically, no DB migration needed
- **Remove all AWC-specific code comments** (e.g., `// AWC green`, `// AWC accent`) — keep generic comments
- **Green (#006935) remains the fallback default** when no club color is configured
- **Full rename everywhere** — Tailwind config, CSS classes in components (`bg-awc-500` → `bg-club-500`), index.css custom properties, useTheme.js, Settings.jsx — complete consistency

### Claude's Discretion
- Exact color picker component/library choice
- Settings section heading copy and field labels
- Login page layout details for club name placement
- How favicon SVG is dynamically generated/served
- Implementation details for live preview (debouncing, CSS variable injection, etc.)

</decisions>

<specifics>
## Specific Ideas

- Club color swatch should be first in the accent picker, clearly labeled "Club" — not just another color option
- Login should feel branded to the club — color + name visible before login
- Live preview in Settings so admin sees the impact immediately while configuring

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 145-frontend-color-refactor*
*Context gathered: 2026-02-05*
