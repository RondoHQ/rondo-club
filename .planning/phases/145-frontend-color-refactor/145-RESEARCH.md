# Phase 145: Frontend & Color Refactor - Research

**Researched:** 2026-02-05
**Domain:** React UI integration, Tailwind CSS dynamic theming, color system refactoring
**Confidence:** HIGH

## Summary

This phase integrates the Phase 144 backend club configuration API into the frontend Settings UI and performs a comprehensive rename of the AWC color system to a dynamic club color system. The implementation involves four parallel workstreams: (1) building an admin-only club configuration section in Settings.jsx, (2) updating the existing accent color picker to include club color as the first option, (3) integrating club color into login/favicon/PWA branding, and (4) performing a complete codebase rename from `awc` to `club` color keys.

The codebase already has a mature theming system built on CSS custom properties (CSS variables) that supports runtime color switching without recompilation. The existing `useTheme` hook manages dark/light modes and accent colors via localStorage with immediate DOM updates. Phase 144 provides a fully functional REST API at `/rondo/v1/config` (GET for all users, POST for admin only) that returns `club_name`, `accent_color` (hex), and `freescout_url` with sensible defaults (#006935 green fallback).

**Primary recommendation:** Use react-colorful (2.8 KB) with HexColorInput for the admin color picker, extend the existing useTheme hook to support club color as a dynamic accent option, and leverage the established CSS custom properties pattern for live preview with no page reload.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| react-colorful | 2.8 KB | Color picker component | Tiny bundle size, zero dependencies, supports hex input, widely adopted for React color picking |
| TanStack Query | (existing) | Server state management | Already in use for Settings API calls, handles optimistic updates and cache invalidation |
| Tailwind CSS | 3.4 | Utility CSS with CSS variables | Already configured with accent color system via custom properties, supports runtime theming |
| Axios | (existing) | API client | Project's standard HTTP client with WordPress nonce injection |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| date-fns | (existing) | Date formatting | Not needed for this phase |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| react-colorful | @uiw/react-color | Larger bundle (15+ KB), more features not needed |
| react-colorful | react-color (archived) | No longer maintained, 100+ KB bundle |
| CSS custom properties | Inline styles | Would break existing theme system, no dark mode support |

**Installation:**
```bash
npm install react-colorful
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── pages/Settings/
│   ├── Settings.jsx          # Add ClubConfigSection component
│   └── ClubConfigSection.jsx # New: Admin-only config UI (optional separate file)
├── hooks/
│   └── useTheme.js           # Extend to support 'club' accent with dynamic hex
├── index.css                 # Rename [data-accent="awc"] to [data-accent="club"]
└── api/
    └── client.js             # Existing - no changes needed
```

### Pattern 1: CSS Custom Properties for Runtime Theming

**What:** Define theme colors as CSS custom properties (`--color-accent-*`) that can be updated via JavaScript without rebuilding CSS.

**When to use:** When colors need to change based on user input or API data without page reload.

**Example:**
```javascript
// Current implementation in useTheme.js applyTheme()
function applyTheme(effectiveColorScheme, accentColor) {
  const root = document.documentElement;
  root.setAttribute('data-accent', accentColor);
  // CSS automatically picks up new --color-accent-* values from index.css
}
```

**For club color (dynamic hex):**
```javascript
// Extend applyTheme to inject club hex as CSS variables
function applyTheme(effectiveColorScheme, accentColor, clubColorHex = null) {
  const root = document.documentElement;

  if (accentColor === 'club' && clubColorHex) {
    // Generate color scale from single hex (use color-2-name or manual scale)
    root.style.setProperty('--color-accent-600', clubColorHex);
    root.style.setProperty('--color-accent-500', lighten(clubColorHex, 10));
    // ... set all shades
  }

  root.setAttribute('data-accent', accentColor);
}
```

**Source:** [CSS Variables for React Devs - Josh W. Comeau](https://www.joshwcomeau.com/css/css-variables-for-react-devs/)

### Pattern 2: Live Preview with CSS Variable Injection

**What:** Update CSS custom properties in real-time as user interacts with color picker, providing instant visual feedback before saving.

**When to use:** Admin settings interfaces where users need to see the impact of color changes immediately.

**Example:**
```javascript
// In ClubConfigSection component
const [previewColor, setPreviewColor] = useState(clubColor);

const handleColorChange = (color) => {
  setPreviewColor(color);
  // Immediately inject into DOM for preview
  document.documentElement.style.setProperty('--color-accent-600', color);
};

const handleSave = async () => {
  await updateConfig({ accent_color: previewColor });
  // TanStack Query will refetch and update window.stadionConfig
};
```

**Source:** [How we made our product more personalized with CSS Variables and React - Geckoboard](https://medium.com/geckoboard-under-the-hood/how-we-made-our-product-more-personalized-with-css-variables-and-react-b29298fde608)

### Pattern 3: Admin-Only UI Sections

**What:** Conditionally render UI sections based on user capabilities, hiding admin controls from regular users.

**When to use:** Settings pages where different users see different configuration options.

**Example:**
```javascript
// Already implemented in Settings.jsx (line 48)
const isAdmin = config.isAdmin || false;

// In render:
{isAdmin && (
  <div className="card p-6 mb-6">
    <h2 className="text-lg font-semibold mb-4">Club Configuration</h2>
    {/* Admin-only fields */}
  </div>
)}
```

**Existing pattern:** Settings.jsx already filters admin-only tabs (line 75), reuse this pattern.

### Pattern 4: Color Scale Generation from Single Hex

**What:** Generate full Tailwind color scale (50-900) from a single hex color value for consistent theming.

**When to use:** When accepting arbitrary user colors that need to work across light/dark modes and various UI contexts.

**Options:**
1. **Manual lighten/darken:** Use HSL conversion to generate lighter/darker shades
2. **Use existing Tailwind colors:** Map club hex to closest Tailwind color scale
3. **Fixed shades:** Use club color only for primary shades (500/600), keep green for lighter/darker

**Recommendation:** Option 3 for simplicity - use club hex for primary interactive elements (500-600 in light mode, 400-500 in dark mode), keep existing green scale for other shades. This avoids color generation complexity while providing clear branding.

### Anti-Patterns to Avoid

- **Don't rebuild Tailwind at runtime** - Tailwind classes like `bg-club-500` are compiled, use CSS variables instead
- **Don't store color in component state only** - Color must be in DOM (data-accent attribute) for CSS to apply
- **Don't use inline styles for theming** - Breaks existing dark mode system which relies on CSS custom properties
- **Don't fetch config on every Settings mount** - Use window.stadionConfig for initial load, TanStack Query for updates

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Color picker UI | Custom canvas-based picker | react-colorful | 2.8 KB, zero dependencies, handles hex/RGB/HSL, mobile-friendly, accessibility built-in |
| Color format conversion | Manual RGB/hex conversion | Browser-native CSS color parsing | More reliable, handles edge cases (3-digit hex, rgb(), etc.) |
| Hex validation | Regex `/^#[0-9A-F]{6}$/i` | WordPress sanitize_hex_color() | Already validated server-side, frontend only needs to display valid values |
| PWA manifest updates | JavaScript manifest patching | Meta tag override | Manifests are static JSON, use `<meta name="theme-color">` which can be updated via JS |
| Favicon dynamic color | Complex SVG manipulation | Data URI with template string | Current codebase pattern (useTheme.js line 116-130) works perfectly, just extend for club color |

**Key insight:** Color systems in web apps are 90% CSS architecture, 10% color picker widget. Don't over-engineer the widget when the existing CSS custom properties pattern already solves runtime theming.

## Common Pitfalls

### Pitfall 1: Manifest theme_color is Static

**What goes wrong:** Attempting to update manifest.webmanifest with JavaScript after page load has no effect. PWAs load the manifest once on installation.

**Why it happens:** Web app manifests are static JSON files that browsers cache. The `theme_color` field in manifest.webmanifest is only read during PWA installation.

**How to avoid:**
- Use `<meta name="theme-color">` tags which CAN be updated via JavaScript
- Current implementation already uses this pattern (functions.php lines 625-626)
- Update the meta tag content when club color changes

**Warning signs:**
- Trying to fetch/modify manifest.webmanifest with JavaScript
- Expecting PWA chrome color to change without meta tag updates

**Source:** [How to Provide Light and Dark Theme Color Variants in PWA - DEV Community](https://dev.to/fedtti/how-to-provide-light-and-dark-theme-color-variants-in-pwa-1mml)

### Pitfall 2: Tailwind Classes Can't Be Runtime-Generated

**What goes wrong:** Using string interpolation to create Tailwind classes like `bg-${color}-500` doesn't work in production builds.

**Why it happens:** Tailwind purges unused classes at build time. Dynamic class names aren't in the source code as complete strings, so they get purged.

**How to avoid:**
- Use CSS custom properties (`bg-accent-500` with `--color-accent-500` variable)
- This is already the codebase pattern (tailwind.config.js lines 11-22)
- Never use template strings for Tailwind class names

**Warning signs:**
- Classes work in dev mode but not in production build
- Trying to dynamically generate class names from variables

**Source:** [Applying dynamic styles with Tailwind CSS - LogRocket Blog](https://blog.logrocket.com/applying-dynamic-styles-tailwind-css/)

### Pitfall 3: AWC-to-Club Rename Must Be Complete

**What goes wrong:** Partial rename leaves the app in an inconsistent state where some users see AWC colors, others see club colors, leading to confusion.

**Why it happens:** The rename touches multiple layers: Tailwind config, CSS custom properties, JavaScript constants, and localStorage values. Missing any layer breaks the system.

**How to avoid:**
- Rename in all layers simultaneously:
  1. `tailwind.config.js` - rename `awc` key to `club`
  2. `index.css` - rename `[data-accent="awc"]` to `[data-accent="club"]`
  3. `useTheme.js` - rename ACCENT_COLORS array and ACCENT_HEX keys
  4. `Settings.jsx` - rename accentColorClasses mapping
  5. Add migration: if localStorage has 'awc', auto-switch to 'club' on load
- Test with existing users who have 'awc' saved in localStorage

**Warning signs:**
- Color picker shows "AWC" and "Club" as separate options
- Some components use old color, others use new color
- Users report color reverting after page refresh

### Pitfall 4: Live Preview Without Debouncing

**What goes wrong:** Updating CSS variables on every color picker drag event can cause performance issues and excessive re-renders.

**Why it happens:** Color pickers fire onChange dozens of times per second during dragging. Each call triggers style recalculation.

**How to avoid:**
- Debounce is NOT needed for CSS variable updates (browser handles efficiently)
- DO update CSS variables immediately for smooth preview
- ONLY debounce API calls or expensive computations
- CSS custom property updates are cheap - 1-2ms per update

**Warning signs:**
- Sluggish color picker dragging
- Debouncing the visual preview (makes it feel broken)
- Confusing "preview" vs "saved" state in UI

**Source:** [Use CSS Variables instead of React Context - Epic React by Kent C. Dodds](https://www.epicreact.dev/css-variables)

### Pitfall 5: Forgetting Dark Mode Color Scales

**What goes wrong:** Club color looks perfect in light mode but invisible or harsh in dark mode.

**Why it happens:** Light mode uses darker shades (500-700) for contrast on white, dark mode needs lighter shades (300-500) for contrast on dark gray. Using the same shade values in both modes fails.

**How to avoid:**
- Current codebase already handles this (index.css lines 237-354)
- When injecting club color, set BOTH light and dark variants:
  ```javascript
  // Light mode - use darker shade
  root.style.setProperty('--color-accent-600', clubColorHex);
  // Dark mode - use lighter tinted version
  const lightened = lighten(clubColorHex, 30);
  root.style.setProperty('--color-accent-400', lightened);
  ```
- Test in both light and dark modes before saving

**Warning signs:**
- Color invisible in dark mode
- Insufficient contrast warnings in browser DevTools
- Users with dark mode see different branding than light mode users

## Code Examples

Verified patterns from official sources and existing codebase:

### Extending useTheme for Club Color

```javascript
// src/hooks/useTheme.js
// Add club color support with dynamic hex from window.stadionConfig

const ACCENT_COLORS = ['club', 'orange', 'teal', 'indigo', 'emerald', 'violet', 'pink', 'fuchsia', 'rose'];

const ACCENT_HEX = {
  club: () => window.stadionConfig?.accentColor || '#006935', // Dynamic
  orange: '#f97316',
  teal: '#14b8a6',
  // ... rest
};

function applyTheme(effectiveColorScheme, accentColor) {
  const root = document.documentElement;

  // Handle dynamic club color
  if (accentColor === 'club') {
    const clubHex = typeof ACCENT_HEX.club === 'function'
      ? ACCENT_HEX.club()
      : ACCENT_HEX.club;

    // Set CSS variables for club color
    // Use club hex for primary shades, existing green scale for others
    root.style.setProperty('--color-accent-600', clubHex);
    // Calculate lighter/darker variants as needed
  }

  root.setAttribute('data-accent', accentColor);
  updateFavicon(accentColor);
  updateThemeColorMeta(accentColor);
}

// Auto-migrate 'awc' to 'club' in loadPreferences()
function loadPreferences() {
  // ... existing code ...
  if (parsed.accentColor === 'awc') {
    parsed.accentColor = 'club';
  }
  // ... rest
}
```

**Source:** Current codebase pattern in src/hooks/useTheme.js

### Club Configuration Section with react-colorful

```javascript
// src/pages/Settings/Settings.jsx
import { HexColorPicker, HexColorInput } from 'react-colorful';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import apiClient from '@/api/client';

function ClubConfigSection({ isAdmin, clubConfig }) {
  const [clubName, setClubName] = useState(clubConfig.club_name);
  const [clubColor, setClubColor] = useState(clubConfig.accent_color);
  const [freescoutUrl, setFreescoutUrl] = useState(clubConfig.freescout_url);
  const [saving, setSaving] = useState(false);

  const queryClient = useQueryClient();

  const updateMutation = useMutation({
    mutationFn: (updates) =>
      apiClient.post('/rondo/v1/config', updates),
    onSuccess: (data) => {
      // Update window.stadionConfig for immediate UI refresh
      window.stadionConfig.clubName = data.club_name;
      window.stadionConfig.accentColor = data.accent_color;
      window.stadionConfig.freescoutUrl = data.freescout_url;

      // Invalidate queries that depend on config
      queryClient.invalidateQueries(['config']);

      // If user is using 'club' accent, force theme refresh
      const currentAccent = localStorage.getItem('theme-preferences');
      if (currentAccent?.includes('"accentColor":"club"')) {
        // Trigger theme re-application with new club color
        document.dispatchEvent(new Event('themechange'));
      }
    },
  });

  const handleColorChange = (color) => {
    setClubColor(color);
    // Live preview - inject immediately
    document.documentElement.style.setProperty('--color-accent-600', color);
  };

  const handleSave = async () => {
    setSaving(true);
    await updateMutation.mutateAsync({
      club_name: clubName,
      accent_color: clubColor,
      freescout_url: freescoutUrl,
    });
    setSaving(false);
  };

  if (!isAdmin) return null;

  return (
    <div className="card p-6 mb-6">
      <h2 className="text-lg font-semibold mb-4">Club Configuration</h2>
      <p className="text-sm text-gray-600 mb-6 dark:text-gray-400">
        Configure club-wide settings. Changes apply to all users.
      </p>

      <div className="space-y-4">
        {/* Club Name */}
        <div>
          <label className="label">Club Name</label>
          <input
            type="text"
            value={clubName}
            onChange={(e) => setClubName(e.target.value)}
            className="input"
            placeholder="e.g., AWC Amsterdam"
          />
        </div>

        {/* Club Color */}
        <div>
          <label className="label">Club Color</label>
          <div className="flex gap-4 items-start">
            <HexColorPicker color={clubColor} onChange={handleColorChange} />
            <div>
              <HexColorInput
                color={clubColor}
                onChange={handleColorChange}
                className="input w-32"
                prefixed
                placeholder="#000000"
              />
              <p className="text-xs text-gray-500 mt-2">
                Live preview - changes apply when saved
              </p>
            </div>
          </div>
        </div>

        {/* FreeScout URL */}
        <div>
          <label className="label">FreeScout URL</label>
          <input
            type="url"
            value={freescoutUrl}
            onChange={(e) => setFreescoutUrl(e.target.value)}
            className="input"
            placeholder="https://support.example.com"
          />
        </div>

        {/* Save Button */}
        <button
          onClick={handleSave}
          disabled={saving}
          className="btn-primary"
        >
          {saving ? 'Saving...' : 'Save Changes'}
        </button>
      </div>
    </div>
  );
}
```

**Source:** react-colorful documentation + existing Settings.jsx patterns

### Updated Accent Color Picker with Club Option

```javascript
// src/pages/Settings/Settings.jsx - Update accentColorClasses
const accentColorClasses = {
  club: 'bg-accent-600', // Uses CSS variable, not hardcoded
  orange: 'bg-orange-500',
  // ... rest
};

// In render:
<div className="flex flex-wrap gap-3">
  {ACCENT_COLORS.map((color) => {
    const isSelected = accentColor === color;
    const displayName = color === 'club'
      ? 'Club'
      : color.charAt(0).toUpperCase() + color.slice(1);

    return (
      <button
        key={color}
        onClick={() => setAccentColor(color)}
        className={`
          w-10 h-10 rounded-full transition-transform hover:scale-110
          ${accentColorClasses[color]}
          ${isSelected ? `ring-2 ring-offset-2` : ''}
        `}
        title={displayName}
        aria-label={`Select ${displayName} accent color`}
      />
    );
  })}
</div>

<p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
  Selected: <span className="font-medium">{
    accentColor === 'club'
      ? 'Club'
      : accentColor.charAt(0).toUpperCase() + accentColor.slice(1)
  }</span>
  {accentColor === 'club' && ' (updates when admin changes club color)'}
</p>
```

**Source:** Current Settings.jsx implementation (lines 972-1002)

### Dynamic Favicon Update for Club Color

```javascript
// src/hooks/useTheme.js - Extend updateFavicon()
function updateFavicon(accentColor) {
  if (typeof document === 'undefined') return;

  let hex;
  if (accentColor === 'club') {
    // Get club color from window.stadionConfig
    hex = window.stadionConfig?.accentColor || '#006935';
  } else {
    hex = ACCENT_HEX[accentColor] || ACCENT_HEX.orange;
  }

  // SVG stadium icon with dynamic color
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="${hex}">
    <path fill-rule="evenodd" d="M12 2C6.5 2 2 5.5 2 9v6c0 3.5 4.5 7 10 7s10-3.5 10-7V9c0-3.5-4.5-7-10-7zm0 2c4.4 0 8 2.7 8 5s-3.6 5-8 5-8-2.7-8-5 3.6-5 8-5zm0 4c-2.2 0-4 .9-4 2s1.8 2 4 2 4-.9 4-2-1.8-2-4-2z" clip-rule="evenodd"/>
  </svg>`;

  const dataUrl = `data:image/svg+xml,${encodeURIComponent(svg)}`;

  let link = document.querySelector('link[rel="icon"]');
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }
  link.type = 'image/svg+xml';
  link.href = dataUrl;
}
```

**Source:** Current implementation in src/hooks/useTheme.js (lines 110-131)

### Theme-Color Meta Tag Updates

```javascript
// src/hooks/useTheme.js - Extend updateThemeColorMeta()
function updateThemeColorMeta(accentColor) {
  if (typeof document === 'undefined') return;

  let lightHex, darkHex;

  if (accentColor === 'club') {
    // Use club color from config
    const clubColor = window.stadionConfig?.accentColor || '#006935';
    lightHex = clubColor;
    // For dark mode, lighten by ~30% (simplified)
    darkHex = lightenColor(clubColor, 30);
  } else {
    lightHex = ACCENT_HEX[accentColor] || ACCENT_HEX.orange;
    darkHex = ACCENT_HEX_DARK[accentColor] || ACCENT_HEX_DARK.orange;
  }

  // Update light mode theme-color
  const lightMeta = document.querySelector('meta[name="theme-color"][media*="light"]');
  if (lightMeta) lightMeta.content = lightHex;

  // Update dark mode theme-color
  const darkMeta = document.querySelector('meta[name="theme-color"][media*="dark"]');
  if (darkMeta) darkMeta.content = darkHex;
}
```

**Source:** Current implementation in src/hooks/useTheme.js (lines 138-155)

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Static color palette | CSS custom properties | Tailwind 3.0+ (2021) | Enables runtime theming without rebuild |
| react-color (archived) | react-colorful | 2020 | 50x smaller bundle, better mobile support |
| Inline styles for themes | data-* attributes + CSS | Modern React (2019+) | Better performance, CSS handles repaints |
| PWA manifest theme-color only | Meta tag override | PWA spec evolution | Allows dynamic theme colors post-install |
| Complex color management libraries | Browser-native color parsing | Modern browsers (2020+) | No dependencies, better reliability |

**Deprecated/outdated:**
- **react-color:** Archived in 2020, 100+ KB bundle, no React 18 support
- **Hardcoded color classes:** `bg-awc-600` in components breaks theming, use `bg-accent-600` instead
- **JavaScript color calculations:** Modern CSS supports color-mix() for transparency/blending
- **Rebuilding Tailwind at runtime:** Never possible, always use CSS variables for dynamic colors

## Open Questions

Things that couldn't be fully resolved:

1. **Color scale generation for arbitrary hex values**
   - What we know: Full Tailwind scale (50-900) requires algorithmic generation from single hex
   - What's unclear: Whether to implement full scale generation or use club color only for primary shades
   - Recommendation: Use club hex only for shades 500-600 (primary interactive elements), keep existing green scale for lighter/darker shades. This provides clear branding without complex color math. If full scale needed later, investigate color-2-name or chroma-js libraries.

2. **Login page club branding**
   - What we know: Current Login.jsx redirects to WordPress login (line 12), doesn't render custom UI
   - What's unclear: Whether WordPress login page can be styled with club color/name from theme
   - Recommendation: Phase 144 verification shows WordPress login hooks exist (functions.php line 1122). Create custom login page styling via `login_enqueue_scripts` hook that injects club color/name. This is standard WordPress theme practice.

3. **PWA manifest static theme_color**
   - What we know: manifest.webmanifest has hardcoded #006935 (line 1), manifests are static JSON
   - What's unclear: Whether to generate manifest.webmanifest dynamically via PHP or leave static
   - Recommendation: Leave manifest.webmanifest static with default green. The meta tags (which CAN be updated) override manifest values per PWA spec. Users who installed PWA before club color configuration will get default green in manifest, but meta tags provide correct color in-app.

## Sources

### Primary (HIGH confidence)
- Existing codebase files:
  - `/Users/joostdevalk/Code/stadion/src/hooks/useTheme.js` - Current theming implementation
  - `/Users/joostdevalk/Code/stadion/src/pages/Settings/Settings.jsx` - Settings UI patterns
  - `/Users/joostdevalk/Code/stadion/src/index.css` - CSS custom properties setup
  - `/Users/joostdevalk/Code/stadion/tailwind.config.js` - Tailwind configuration
  - `/Users/joostdevalk/Code/stadion/includes/class-club-config.php` - Backend API model
  - `/Users/joostdevalk/Code/stadion/includes/class-rest-api.php` - REST endpoint implementation
- Phase 144 verification: Complete backend API with GET/POST `/rondo/v1/config`
- [react-colorful npm](https://www.npmjs.com/package/react-colorful) - Official package documentation
- [react-colorful GitHub](https://github.com/omgovich/react-colorful) - Library source and examples

### Secondary (MEDIUM confidence)
- [CSS Variables for React Devs - Josh W. Comeau](https://www.joshwcomeau.com/css/css-variables-for-react-devs/) - CSS variable patterns
- [How we made our product more personalized with CSS Variables and React - Geckoboard](https://medium.com/geckoboard-under-the-hood/how-we-made-our-product-more-personalized-with-css-variables-and-react-b29298fde608) - Live preview pattern
- [Use CSS Variables instead of React Context - Epic React by Kent C. Dodds](https://www.epicreact.dev/css-variables) - Performance guidance
- [Applying dynamic styles with Tailwind CSS - LogRocket Blog](https://blog.logrocket.com/applying-dynamic-styles-tailwind-css/) - Tailwind dynamic color guidance
- [How to Provide Light and Dark Theme Color Variants in PWA - DEV Community](https://dev.to/fedtti/how-to-provide-light-and-dark-theme-color-variants-in-pwa-1mml) - PWA theming patterns
- [Meta Theme Color and Trickery - CSS-Tricks](https://css-tricks.com/meta-theme-color-and-trickery/) - Theme-color meta tag usage

### Tertiary (LOW confidence - context only)
- [Can I use SVG favicons](https://caniuse.com/link-icon-svg) - Browser compatibility (58% support)
- Multiple WebSearch results on color pickers (validated against npm for react-colorful)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - react-colorful verified via npm, existing stack confirmed in package.json and codebase
- Architecture: HIGH - Patterns verified in existing codebase (useTheme.js, Settings.jsx, index.css)
- Pitfalls: HIGH - Based on Tailwind documentation, PWA spec, and existing codebase patterns
- Color scale generation: MEDIUM - Implementation approach recommended but not verified in production
- Login page styling: MEDIUM - WordPress hooks exist but specific implementation not verified
- PWA manifest dynamic generation: MEDIUM - Spec-based recommendation, not tested

**Research date:** 2026-02-05
**Valid until:** 60 days (stable domain - React patterns, Tailwind CSS, PWA spec are mature)
