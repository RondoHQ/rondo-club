# Phase 162: Foundation - Tailwind v4 & Tokens - Research

**Researched:** 2026-02-09
**Domain:** Tailwind CSS v4 migration, CSS-first configuration, design tokens
**Confidence:** HIGH

## Summary

Tailwind CSS v4 represents a ground-up rewrite released January 22, 2025, transitioning from JavaScript-based configuration (`tailwind.config.js`) to CSS-first configuration using `@theme` blocks. The framework now uses the Oxide engine (Rust-based), delivering 5x faster full builds and 100x faster incremental builds. Configuration moves entirely into CSS using `@import "tailwindcss"` and `@theme` directives with CSS custom properties.

For this phase, the primary task is upgrading from Tailwind v3.4 to v4.0, replacing the existing `tailwind.config.js` with CSS-based `@theme` configuration in `src/index.css`, defining brand color tokens in OKLCH color space, loading Montserrat via Fontsource, and creating a custom gradient utility. The project currently has extensive dark mode usage (1,877 occurrences across 64 JSX files), which must be preserved using v4's `@custom-variant` directive for class-based dark mode strategy.

**Primary recommendation:** Use the official `@tailwindcss/upgrade` tool for initial migration, then manually define brand tokens in OKLCH format within `@theme` blocks, configure class-based dark mode with `@custom-variant`, and create gradient utility with `@utility` directive.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `tailwindcss` | `^4.0.0` | Core framework | Official v4 release with Oxide engine |
| `@tailwindcss/vite` | `^4.0.0` | Vite integration | Official plugin for Vite, replaces PostCSS setup |
| `@tailwindcss/typography` | `^0.5.19` → v4 compatible | Prose styles | Updated for v4, imported via `@plugin` directive |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@fontsource/montserrat` | `^5.x` | Montserrat font (weights 600, 700) | Self-hosted web font for heading elements |
| PostCSS | Can be removed | No longer required | v4 handles imports and autoprefixing automatically |
| Autoprefixer | Can be removed | No longer required | Built into v4 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| OKLCH colors | Hex colors | OKLCH provides wider P3 gamut, better perceptual uniformity; hex limits to sRGB |
| `@fontsource/montserrat` | Google Fonts CDN | Fontsource gives better control, no external requests, better performance |
| `@utility` directive | Inline arbitrary values | Custom utilities work with variants (hover, dark, responsive) |

**Installation:**
```bash
npm install tailwindcss@next @tailwindcss/vite@next
npm install @fontsource/montserrat
npm uninstall autoprefixer postcss-import  # No longer needed
```

## Architecture Patterns

### Recommended Project Structure
Current structure remains valid. Changes are confined to:
```
src/
├── index.css           # Replace @tailwind directives with @import "tailwindcss" + @theme blocks
├── main.jsx            # Import Montserrat font here
└── [components/pages]  # No changes to JSX files (unless using deprecated utilities)

vite.config.js          # Add @tailwindcss/vite plugin
tailwind.config.js      # DELETE (migrate to CSS)
postcss.config.js       # SIMPLIFY or DELETE
```

### Pattern 1: CSS-First Configuration with @theme
**What:** Define design tokens as CSS custom properties in `@theme` blocks
**When to use:** All color, font, spacing, and design token definitions
**Example:**
```css
@import "tailwindcss";

@theme {
  /* Brand colors in OKLCH (P3 gamut) */
  --color-electric-cyan: oklch(0.7 0.15 195);
  --color-electric-cyan-light: oklch(0.8 0.15 195);
  --color-bright-cobalt: oklch(0.55 0.2 250);
  --color-deep-midnight: oklch(0.3 0.15 250);
  --color-obsidian: oklch(0.15 0.02 260);

  /* Font families */
  --font-display: "Montserrat", sans-serif;
  --font-body: "Inter", system-ui, sans-serif;
}
```
**Source:** [Tailwind CSS v4.0 Blog](https://tailwindcss.com/blog/tailwindcss-v4)

### Pattern 2: Class-Based Dark Mode with @custom-variant
**What:** Override default `prefers-color-scheme` dark mode with class-based strategy
**When to use:** When app uses manual dark mode toggle (existing pattern in this project)
**Example:**
```css
@import "tailwindcss";

@custom-variant dark (&:where(.dark, .dark *));

@theme {
  /* Theme definitions */
}
```
**Source:** [Tailwind CSS v4 Dark Mode Docs](https://tailwindcss.com/docs/dark-mode)

### Pattern 3: Custom Gradient Utilities with @utility
**What:** Create reusable gradient utilities that work with all variants
**When to use:** For brand-specific gradients used across components
**Example:**
```css
@utility bg-brand-gradient {
  background: linear-gradient(
    135deg,
    var(--color-electric-cyan),
    var(--color-bright-cobalt)
  );
}
```
**Usage:**
```html
<div class="bg-brand-gradient hover:bg-brand-gradient dark:bg-brand-gradient">
  Gradient background works with all variants
</div>
```
**Source:** [Tailwind CSS v4 Functions and Directives](https://tailwindcss.com/docs/functions-and-directives)

### Pattern 4: Font Loading with Fontsource
**What:** Import specific font weights at app entry point
**When to use:** For custom fonts like Montserrat
**Example:**
```javascript
// src/main.jsx
import '@fontsource/montserrat/600.css';  // Semi-bold
import '@fontsource/montserrat/700.css';  // Bold
```
**Source:** [Fontsource Montserrat](https://fontsource.org/fonts/montserrat/install)

### Anti-Patterns to Avoid
- **Don't keep `tailwind.config.js`:** Mixing JS config with CSS config causes conflicts. Migrate fully to CSS-first.
- **Don't use hex for brand colors in v4:** OKLCH provides wider gamut and better perceptual consistency.
- **Don't forget to migrate border colors:** v4 defaults to `currentColor` instead of `gray-200`. Add explicit colors: `border border-gray-200`.
- **Don't use deprecated `@tailwind` directives:** Use `@import "tailwindcss"` instead.
- **Don't define gradients in `@theme`:** Use `@utility` directive for gradient utilities.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Color conversion | Manual hex → OKLCH converter | [UIColors.app](https://uicolors.app/tailwind-colors) or [Hex2Tailwind](https://hex2tailwind.com/) | Accurate ΔE00 color matching, P3 gamut support |
| Migration script | Custom find/replace script | `npx @tailwindcss/upgrade` | Official upgrade tool handles 90% of breaking changes |
| Font subsetting | Manual WOFF2 generation | Fontsource packages | Pre-optimized, multiple formats, automatic subsetting |
| Gradient generators | Custom gradient utilities by hand | `@utility` directive with theme vars | Variant support (hover, dark, responsive) built-in |

**Key insight:** Tailwind v4's architecture is fundamentally different from v3. Custom migration scripts will miss edge cases that the official tooling handles (variant stacking order, opacity syntax, ring defaults, etc.). The color space migration (sRGB → P3/OKLCH) requires perceptual color matching, not mathematical conversion.

## Common Pitfalls

### Pitfall 1: Border Color Regression
**What goes wrong:** Borders disappear or become black because v4 defaults to `currentColor` instead of `gray-200`
**Why it happens:** v3 had opinionated border color defaults; v4 follows browser standards
**How to avoid:**
- Run `npx @tailwindcss/upgrade` which adds explicit border colors
- Or add base styles to preserve v3 behavior:
```css
@layer base {
  *, ::after, ::before, ::backdrop, ::file-selector-button {
    border-color: var(--color-gray-200, currentColor);
  }
}
```
**Warning signs:** Gray borders turning black, disappearing borders on cards/inputs
**Source:** [Tailwind CSS v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)

### Pitfall 2: Dark Mode Breaks After Migration
**What goes wrong:** `dark:` classes stop working after upgrading
**Why it happens:** v4 defaults to `prefers-color-scheme` strategy; class-based strategy requires explicit configuration
**How to avoid:**
```css
@import "tailwindcss";

@custom-variant dark (&:where(.dark, .dark *));
```
**Warning signs:** Dark mode only follows OS preference, ignores `.dark` class on `<html>`
**Source:** [GitHub Discussion #16517](https://github.com/tailwindlabs/tailwindcss/discussions/16517)

### Pitfall 3: Plugin Import Syntax Changed
**What goes wrong:** `@tailwindcss/typography` plugin not recognized
**Why it happens:** v4 uses `@plugin` directive in CSS, not JS config `plugins: []` array
**How to avoid:**
```css
@import "tailwindcss";
@plugin "@tailwindcss/typography";
```
**Warning signs:** `.prose` class has no effect, build warnings about unrecognized plugins
**Source:** [Tailwind v4 vs v3 Guide](https://frontend-hero.com/tailwind-v4-vs-v3)

### Pitfall 4: Shadow/Radius Scale Renamed
**What goes wrong:** `shadow-sm`, `rounded-sm` produce different sizes than expected
**Why it happens:** v4 renamed scales: `shadow-sm` → `shadow-xs`, `shadow` → `shadow-sm`, etc.
**How to avoid:** Use upgrade tool or manually update:
- `shadow-sm` → `shadow-xs`
- `shadow` → `shadow-sm`
- `rounded-sm` → `rounded-xs`
**Warning signs:** UI elements appear larger/smaller than before
**Source:** [Tailwind CSS v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)

### Pitfall 5: Important Modifier Position Flipped
**What goes wrong:** `!flex` no longer works
**Why it happens:** v4 moves `!` to suffix position for consistency with other modifiers
**How to avoid:** Use `flex!` instead of `!flex`
**Warning signs:** `!important` styles not applying, specificity issues
**Source:** [Tailwind CSS v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)

### Pitfall 6: OKLCH Color Conversion Loss
**What goes wrong:** Brand colors look different after hex → OKLCH conversion
**Why it happens:** Mathematical conversion doesn't preserve perceptual appearance
**How to avoid:** Use perceptual color matching tools with ΔE00 algorithm:
- [UIColors.app](https://uicolors.app/tailwind-colors) (click-to-copy OKLCH)
- [Hex2Tailwind](https://hex2tailwind.com/) (ΔE00 matching)
**Warning signs:** Brand colors appear duller, off-hue, or different saturation
**Source:** [OKLCH Explained Guide](https://trypeek.app/blog/oklch-explained-what-it-is-why-tailwind-v4-uses-it-how-to-convert/)

## Code Examples

Verified patterns from official sources:

### Vite Configuration
```javascript
// vite.config.js
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],
});
```
**Source:** [Tailwind CSS v4 Installation](https://tailwindcss.com/docs/installation/using-vite)

### Complete CSS Configuration (index.css)
```css
@import "tailwindcss";

/* Enable class-based dark mode */
@custom-variant dark (&:where(.dark, .dark *));

/* Import typography plugin */
@plugin "@tailwindcss/typography";

/* Define brand tokens */
@theme {
  /* Brand colors (OKLCH for P3 gamut) */
  --color-electric-cyan: oklch(0.69 0.14 196);          /* #0891B2 equivalent */
  --color-electric-cyan-light: oklch(0.79 0.14 196);    /* #22D3EE equivalent */
  --color-bright-cobalt: oklch(0.55 0.19 264);          /* #2563EB equivalent */
  --color-deep-midnight: oklch(0.35 0.12 264);          /* #1E3A8A equivalent */
  --color-obsidian: oklch(0.16 0.02 264);               /* #0F172A equivalent */

  /* Typography */
  --font-display: "Montserrat", sans-serif;
  --font-body: "Inter", system-ui, sans-serif;
}

/* Custom gradient utility */
@utility bg-brand-gradient {
  background: linear-gradient(
    135deg,
    var(--color-electric-cyan),
    var(--color-bright-cobalt)
  );
}

/* Preserve iOS safe area utilities */
@layer base {
  html {
    @apply antialiased;
    min-height: calc(100% + env(safe-area-inset-top));
  }

  body {
    @apply bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100;
    padding-left: env(safe-area-inset-left);
    padding-right: env(safe-area-inset-right);
  }
}
```
**Source:** Combined from [v4 Blog](https://tailwindcss.com/blog/tailwindcss-v4) and [Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide)

### Font Loading (main.jsx)
```javascript
// src/main.jsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.jsx';
import './index.css';

// Load Montserrat weights for headings
import '@fontsource/montserrat/600.css';  // Semi-bold
import '@fontsource/montserrat/700.css';  // Bold

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
```
**Source:** [Fontsource Montserrat](https://www.npmjs.com/package/@fontsource/montserrat)

### Using Brand Colors in Components
```jsx
// Before (v3 with custom config colors)
<div className="bg-club-600 text-white dark:bg-club-700">

// After (v4 with @theme tokens)
<div className="bg-electric-cyan text-white dark:bg-bright-cobalt">

// Using gradient utility
<div className="bg-brand-gradient text-white">
```

### Applying Font Families
```css
/* Define in @theme */
@theme {
  --font-display: "Montserrat", sans-serif;
}

/* Use in CSS or components */
h1, h2, h3, h4, h5, h6 {
  font-family: var(--font-display);
}
```

Or in JSX:
```jsx
<h1 className="font-[family-name:var(--font-display)] font-semibold">
  Heading with Montserrat
</h1>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| JS config (`tailwind.config.js`) | CSS-first (`@theme` blocks) | v4.0 (Jan 2025) | 5x faster builds, runtime theme variables |
| `@tailwind base/components/utilities` | `@import "tailwindcss"` | v4.0 | Single import, auto vendor prefixing |
| `plugins: [typography()]` in JS | `@plugin "@tailwindcss/typography"` | v4.0 | CSS-based plugin system |
| Hex/RGB colors | OKLCH colors | v4.0 | Wider P3 gamut, perceptual uniformity |
| `!important` prefix: `!flex` | Suffix: `flex!` | v4.0 | Consistency with variant modifiers |
| Border default: `gray-200` | Border default: `currentColor` | v4.0 | Less opinionated, follows standards |
| PostCSS + Autoprefixer required | Built-in handling | v4.0 | Simpler setup, fewer dependencies |
| Content paths in JS config | Auto-discovery | v4.0 | Zero config, respects `.gitignore` |

**Deprecated/outdated:**
- `@tailwind base; @tailwind components; @tailwind utilities;` → Use `@import "tailwindcss"`
- Opacity utilities: `bg-opacity-*`, `text-opacity-*` → Use slash syntax: `bg-black/50`, `text-red-500/75`
- `flex-shrink-*`, `flex-grow-*` → Use `shrink-*`, `grow-*`
- `outline-none` → Use `outline-hidden`
- `transform-none` → Use property-specific resets: `scale-none`, `rotate-none`

## Open Questions

1. **OKLCH color matching accuracy**
   - What we know: Tools exist for hex → OKLCH conversion with ΔE00 matching
   - What's unclear: How to verify visual accuracy in production across different displays
   - Recommendation: Test on multiple devices (sRGB, P3 displays), use browser DevTools color picker to compare

2. **Dark mode CSS variable strategy**
   - What we know: Can define colors in `@theme`, apply different values with `dark:` classes
   - What's unclear: Best pattern for maintaining 1,877 existing `dark:` utility classes with new tokens
   - Recommendation: Keep existing approach (explicit `dark:` variants), map old color names to new tokens

3. **Performance impact of Fontsource vs system fonts**
   - What we know: Fontsource provides self-hosted fonts with good caching
   - What's unclear: Performance delta compared to system font stack (`sans-serif`)
   - Recommendation: Load only required weights (600, 700), subset characters if bundle size is issue

4. **@utility vs @layer for gradient**
   - What we know: `@utility` works with all variants automatically
   - What's unclear: Any edge cases where `@layer utilities` would be better
   - Recommendation: Use `@utility` for variant support (this is the v4 recommended pattern)

## Sources

### Primary (HIGH confidence)
- [Tailwind CSS v4.0 Official Release Blog](https://tailwindcss.com/blog/tailwindcss-v4) - v4 features, CSS-first configuration
- [Tailwind CSS v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide) - Breaking changes, migration steps
- [Tailwind CSS v4 Dark Mode Docs](https://tailwindcss.com/docs/dark-mode) - Class-based dark mode configuration
- [Tailwind CSS v4 Functions and Directives](https://tailwindcss.com/docs/functions-and-directives) - `@utility`, `@theme`, `@custom-variant`
- [Fontsource Montserrat NPM](https://www.npmjs.com/package/@fontsource/montserrat) - Installation, usage
- [@tailwindcss/vite NPM](https://www.npmjs.com/package/@tailwindcss/vite) - Vite plugin setup

### Secondary (MEDIUM confidence)
- [Installing Tailwind CSS with Vite (v4 Plugin Guide)](https://tailkits.com/blog/install-tailwind-css-with-vite/) - Verified against official docs
- [Tailwind CSS v4 Migration Guide - DEV Community](https://dev.to/ippatev/migration-guide-tailwind-css-v3-to-v4-f5h) - Community experience
- [OKLCH Explained Guide](https://trypeek.app/blog/oklch-explained-what-it-is-why-tailwind-v4-uses-it-how-to-convert/) - OKLCH color space rationale
- [GitHub Discussion #16517](https://github.com/tailwindlabs/tailwindcss/discussions/16517) - Common migration issues

### Tertiary (LOW confidence)
- Various Medium articles on migration experiences - Use for pitfall awareness, verify with official docs

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Official packages and versions verified via NPM and official docs
- Architecture: HIGH - Patterns verified in official v4 documentation and release blog
- Pitfalls: HIGH - Documented in official upgrade guide and GitHub discussions

**Research date:** 2026-02-09
**Valid until:** 2026-04-09 (60 days - v4 is stable, color/font patterns are long-lived)

**Browser requirements:** Safari 16.4+, Chrome 111+, Firefox 128+ (verified in official docs)
