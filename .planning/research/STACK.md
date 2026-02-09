# Technology Stack: Design Refresh

**Project:** Rondo Club Design Refresh
**Researched:** 2026-02-09
**Confidence:** HIGH

## Executive Summary

The design refresh requires migrating from Tailwind CSS v3.4 to v4, which represents a fundamental architectural shift. Tailwind v4 uses a new CSS-first configuration model, a Vite plugin architecture, and eliminates PostCSS/autoprefixer dependencies. Font loading should use the self-hosted Fontsource approach for optimal performance. Glass morphism and gradients are native Tailwind v4 features requiring no additional libraries.

## Changes Required

### 1. Tailwind CSS v4 Migration

**Current:** Tailwind CSS v3.4.0 with PostCSS/autoprefixer
**New:** Tailwind CSS v4.x with @tailwindcss/vite plugin

| Package | Action | Version | Why |
|---------|--------|---------|-----|
| `tailwindcss` | Update | `^4.1.0` | Core framework with new Rust-based engine (100x faster builds) |
| `@tailwindcss/vite` | Add | `^4.1.0` | Required for Vite integration in v4 |
| `@tailwindcss/typography` | Keep | `^0.5.19` | Typography plugin compatible with v4 |
| `postcss` | Remove | - | No longer needed; v4 uses Lightning CSS internally |
| `autoprefixer` | Remove | - | Built into v4 via Lightning CSS |

**Configuration changes:**

| File | v3.4 Approach | v4 Approach |
|------|---------------|-------------|
| `tailwind.config.js` | JavaScript configuration file | **DELETED** - CSS-first config |
| `vite.config.js` | Not used for Tailwind | **REQUIRED** - Add `@tailwindcss/vite` plugin |
| `src/index.css` | `@tailwind base/components/utilities` | `@import "tailwindcss"` |

**Breaking changes:**
- Dark mode: `darkMode: 'class'` → CSS-based theming with `@theme` directive
- Color system: Static colors → CSS variables (already using this pattern!)
- Content paths: Moved to CSS `@source` directive
- Custom plugins: Must be rewritten for v4 architecture

**Browser support:**
- **Minimum:** Safari 16.4+, Chrome 111+, Firefox 128+
- **Impact:** Modern CSS features like `@property`, `color-mix()`, CSS nesting
- **Decision:** Acceptable for sports club internal tool (not public-facing)

**Why v4 over v3.4:**
- 100x faster incremental builds (44ms → 5ms)
- Native gradient support with `bg-linear-to-*`, `bg-radial`, `bg-conic`
- Native backdrop-blur utilities (no config needed)
- CSS variables for colors (matches existing pattern)
- Better tree-shaking and smaller production bundles

**Migration effort:** Medium
- Automated migration tool available (`@tailwindcss/upgrade`)
- Must rewrite `tailwind.config.js` → CSS `@theme` blocks
- Must update dark mode implementation (remove `class` strategy)
- Must audit custom CSS using `@layer` directives

### 2. Font Loading

**Current:** Inter via system fonts (`font-sans: ['Inter', 'system-ui', 'sans-serif']`)
**New:** Montserrat (headings) + system-ui (body) via Fontsource

| Package | Version | Purpose | Why |
|---------|---------|---------|-----|
| `@fontsource/montserrat` | `^5.2.8` | Self-hosted Montserrat font | No Google Fonts network requests, GDPR-friendly, subset loading for performance |

**Why Fontsource over alternatives:**

| Approach | Pros | Cons | Verdict |
|----------|------|------|---------|
| **Fontsource** | Self-hosted, subset loading, Vite integration, no external requests | Adds ~100KB to bundle (minimized with subsets) | ✅ **Recommended** |
| Google Fonts CDN | Zero bundle size, global CDN caching | Privacy concerns, network dependency, FOUT risk | ❌ Avoid |
| Manual @font-face | Full control | Manual updates, no subset tooling | ❌ More work |

**Implementation:**
```javascript
// In src/main.jsx
import '@fontsource/montserrat/600.css';  // Semi-Bold for headings
import '@fontsource/montserrat/700.css';  // Bold for headings

// In Tailwind CSS config (CSS-first in v4)
@theme {
  --font-heading: Montserrat, system-ui, sans-serif;
  --font-body: system-ui, sans-serif;
}
```

**Weight selection:**
- 600 (Semi-Bold): Primary headings (h2, h3)
- 700 (Bold): Page titles (h1), buttons with gradient
- Body text: system-ui (no additional font needed)

**Performance impact:**
- ~60KB per weight (gzipped)
- Total: ~120KB additional bundle size
- Mitigated by: Vite code splitting, browser caching

### 3. Gradient Support

**Current:** Custom CSS gradient classes (if any)
**New:** Native Tailwind v4 gradient utilities

**No additional packages required.** Tailwind v4 includes expanded gradient APIs.

**Built-in utilities:**
- `bg-linear-to-r` / `bg-linear-to-br` - Linear gradients with direction
- `bg-linear-[45deg]` - Custom angle support
- `bg-radial` - Radial gradients from center
- `bg-conic` - Conic gradients (sweep effect)
- `from-cyan-500`, `via-blue-600`, `to-indigo-800` - Color stops

**Color interpolation:**
- Default: `oklab` color space (better than RGB)
- Smoother color transitions, no muddy mid-tones

**Brand gradient (cyan → cobalt):**
```css
/* Tailwind v4 classes */
bg-linear-to-r from-cyan-500 to-blue-600
```

**Why native over libraries:**
- Zero dependencies
- Tree-shakeable (unused gradients removed)
- JIT compilation (only used gradients in CSS)
- Responsive variants (`hover:`, `md:` work out of the box)

### 4. Glass Morphism Support

**Current:** None
**New:** Native Tailwind v4 backdrop utilities

**No additional packages required.** Tailwind v4 includes backdrop-filter utilities.

**Built-in utilities:**
- `backdrop-blur-sm` / `backdrop-blur-md` / `backdrop-blur-lg` - Gaussian blur
- `backdrop-blur-[12px]` - Custom blur values
- `bg-white/30` - Background with opacity (30% white)
- `border border-white/20` - Semi-transparent borders

**Glass morphism pattern:**
```css
/* Header example */
backdrop-blur-md bg-white/80 border-b border-white/20 shadow-lg
```

**Browser support:**
- Safari: Full support (16.4+)
- Chrome: Full support (111+)
- Firefox: Full support (128+)
- **No fallback needed** for minimum browser requirements

**Why native over libraries:**
- Zero dependencies
- Works with existing Tailwind utilities (hover, responsive, dark mode)
- Better performance (CSS-native, GPU-accelerated)
- No JavaScript runtime cost

## What NOT to Add

| Package | Why Avoid |
|---------|-----------|
| `tailwindcss-glassmorphism` | Unnecessary; v4 has native backdrop-blur |
| `tailwind-gradient-mask-image` | Overkill; native gradients sufficient |
| `@google-fonts/montserrat` | Deprecated; Fontsource is maintained |
| `postcss-import` | v4 handles imports natively |
| `tailwindcss-animate` | Not needed for this milestone; add later if animations required |
| CSS-in-JS libraries | Conflicts with Tailwind utility-first approach |

## Installation Steps

### Phase 1: Remove Legacy Dependencies
```bash
npm uninstall postcss autoprefixer
```

### Phase 2: Install Tailwind v4
```bash
npm install -D tailwindcss@^4.1.0 @tailwindcss/vite@^4.1.0
```

### Phase 3: Install Fonts
```bash
npm install @fontsource/montserrat@^5.2.8
```

### Phase 4: Configuration Changes

**vite.config.js:**
```javascript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';  // NEW

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),  // NEW - Add before React plugin
    // ... other plugins
  ],
  // ... rest of config
});
```

**src/index.css:**
```css
/* BEFORE (v3.4) */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* AFTER (v4) */
@import "tailwindcss";

/* Define brand colors with @theme */
@theme {
  --color-electric-cyan: #0891B2;
  --color-bright-cobalt: #2563EB;
  --color-deep-midnight: #1E3A8A;
  --color-obsidian: #0F172A;

  --font-heading: Montserrat, system-ui, sans-serif;
  --font-body: system-ui, sans-serif;
}

/* Content paths with @source */
@source "src/**/*.{js,jsx}";
```

**DELETE:** `tailwind.config.js` (no longer used in v4)

## Integration Points

### With Existing Dynamic Accent System
**Challenge:** Current system uses CSS variables (`--color-accent-*`) injected by ClubConfig.
**Solution:** Replace with static brand colors in v4. Remove dynamic accent feature.

| Current | New |
|---------|-----|
| `accent-600` (dynamic) | `cyan-600` / `blue-600` (static brand colors) |
| `[data-accent="..."]` selectors | Remove; single brand gradient only |
| Dark mode accent variants | Remove; light-only design |

### With Existing Dark Mode
**Challenge:** Current `darkMode: 'class'` config uses `.dark` class toggling.
**Solution:** Remove dark mode entirely. Design is light-only.

| Current | Action |
|---------|--------|
| `dark:bg-gray-900` classes | Remove all `dark:*` variants |
| Dark mode toggle UI | Remove from Settings page |
| CSS variables for dark colors | Remove from `index.css` |

### With Build Process
**No changes required.** Vite plugin integrates seamlessly with existing build.

| Build Command | Status |
|---------------|--------|
| `npm run dev` | ✅ HMR works with @tailwindcss/vite |
| `npm run build` | ✅ Production builds unchanged |
| `npm run preview` | ✅ Preview unchanged |

### With WordPress Theme
**No changes required.** Theme still loads from `dist/` manifest.

| Asset | Status |
|-------|--------|
| `dist/assets/*.css` | ✅ Generated by Vite |
| `dist/assets/*.js` | ✅ Generated by Vite |
| `.vite/manifest.json` | ✅ Read by PHP `functions.php` |

## Validation Checklist

### Tailwind v4 Migration
- [ ] `tailwindcss@^4.1.0` installed
- [ ] `@tailwindcss/vite@^4.1.0` installed
- [ ] `postcss` and `autoprefixer` removed
- [ ] `vite.config.js` updated with `@tailwindcss/vite` plugin
- [ ] `tailwind.config.js` deleted
- [ ] `src/index.css` uses `@import "tailwindcss"`
- [ ] `@theme` blocks define brand colors
- [ ] `@source` directive specifies content paths
- [ ] `npm run dev` starts without errors
- [ ] `npm run build` completes successfully

### Font Loading
- [ ] `@fontsource/montserrat@^5.2.8` installed
- [ ] Import 600 and 700 weights in `src/main.jsx`
- [ ] `--font-heading` defined in `@theme`
- [ ] Montserrat renders in headings
- [ ] system-ui renders in body text

### Gradients
- [ ] `bg-linear-to-r from-cyan-500 to-blue-600` works
- [ ] Gradient buttons render correctly
- [ ] Gradient headings render correctly

### Glass Morphism
- [ ] `backdrop-blur-md` works in header
- [ ] `bg-white/80` semi-transparency works
- [ ] No visual regressions on Safari/Chrome/Firefox

## Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| Breaking changes in v4 | High | Use automated migration tool, thorough testing |
| Dark mode removal causes user confusion | Medium | Clear communication, remove toggle cleanly |
| Browser support excludes older devices | Low | Internal tool for club admins (modern devices expected) |
| Bundle size increase from fonts | Low | Only 2 weights (~120KB), browser caching effective |
| Color system conflicts | Medium | Audit all `accent-*` usage, replace with brand colors |

## Sources

**Tailwind CSS v4:**
- [Tailwind CSS v4.0 Announcement](https://tailwindcss.com/blog/tailwindcss-v4)
- [Upgrade Guide - Tailwind CSS](https://tailwindcss.com/docs/upgrade-guide)
- [Complete Migration Guide - Dev Genius](https://blog.devgenius.io/the-ultimate-guide-to-migrating-from-tailwind-css-3-to-tailwind-css-4-214f8eddc4b9)
- [Vite + React Setup Guide - DEV Community](https://dev.to/lord_potato_c8a8c0086ffb5/tailwind-css-v4-vite-react-setup-the-clean-way-338j)
- [Browser Support Requirements](https://tailwindcss.com/docs/compatibility)
- [@tailwindcss/vite - npm](https://www.npmjs.com/package/@tailwindcss/vite)

**Gradients:**
- [Tailwind CSS v4 Gradients Made Simple - Indie Hackers](https://www.indiehackers.com/post/tailwind-css-v4-gradients-made-simple-0ef34ff370)
- [Text Gradients in Tailwind v4 - Kyle Goggin](https://www.kylegoggin.com/blog/text-gradients-in-tailwind-v4/)

**Glass Morphism:**
- [Creating Glassmorphism Effects - Epic Web Dev](https://www.epicweb.dev/tips/creating-glassmorphism-effects-with-tailwind-css)
- [Glassmorphism with Tailwind CSS - FlyOnUI](https://flyonui.com/blog/glassmorphism-with-tailwind-css/)
- [Backdrop Blur - Tailwind CSS](https://tailwindcss.com/docs/backdrop-blur)

**Fonts:**
- [@fontsource/montserrat - npm](https://www.npmjs.com/package/@fontsource/montserrat)
- [Fontsource Montserrat](https://fontsource.org/fonts/montserrat)

**Architecture:**
- [PostCSS autoprefixer discussion - GitHub](https://github.com/tailwindlabs/tailwindcss/discussions/15518)
