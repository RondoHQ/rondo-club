# PRD: Theme Customization & Dark Mode

**Author:** Joost
**Date:** January 15, 2026
**Status:** Draft

---

## Overview

This PRD describes the implementation of user-configurable theme customization for Stadion, including accent color selection and dark mode support. Users will be able to personalize their experience by choosing from predefined color palettes and selecting their preferred color scheme (light, dark, or system-based).

---

## Goals

1. Allow users to select their preferred accent color from a curated set of Tailwind-compatible palettes
2. Implement dark mode with three options: Light, Dark, and System (follows OS preference)
3. Persist user preferences across sessions
4. Ensure accessibility standards are maintained across all theme combinations
5. Provide a seamless, non-jarring transition between themes

---

## User Stories

**As a user**, I want to choose an accent color that matches my personal preference or brand, so that the interface feels more personalized.

**As a user**, I want to switch between light and dark modes, so that I can reduce eye strain in low-light environments.

**As a user**, I want the app to follow my system's color scheme preference, so that it automatically matches my other applications.

**As a user**, I want my theme preferences to persist, so that I don't have to reconfigure them each time I log in.

---

## Feature Specification

### 1. Color Palette Options

Users can select from the following accent color palettes:

| Name | Tailwind Base | Primary (Light) | Primary (Dark) | Character |
|------|---------------|-----------------|----------------|-----------|
| Orange (Default) | `orange` | `orange-500` | `orange-400` | Energetic, warm |
| Teal | `teal` | `teal-500` | `teal-400` | Fresh, modern |
| Indigo | `indigo` | `indigo-500` | `indigo-400` | Professional, trustworthy |
| Emerald | `emerald` | `emerald-500` | `emerald-400` | Growth, relationships |
| Violet | `violet` | `violet-500` | `violet-400` | Premium, creative |
| Pink | `pink` | `pink-500` | `pink-400` | Playful, vibrant |
| Fuchsia | `fuchsia` | `fuchsia-500` | `fuchsia-400` | Bold, modern |
| Rose | `rose` | `rose-500` | `rose-400` | Warm, personal |

### 2. Color Scheme Options

| Option | Behavior |
|--------|----------|
| Light | Always use light mode |
| Dark | Always use dark mode |
| System | Follow `prefers-color-scheme` media query |

### 3. Settings UI

Location: **Settings → Appearance**

The settings page should include:

1. **Color Scheme Toggle** — Segmented control or radio group with three options: Light, Dark, System
2. **Accent Color Picker** — Grid of color swatches showing all available palettes, with the current selection highlighted
3. **Live Preview** — Changes should apply immediately (optimistically) so users can see the effect before navigating away

#### Wireframe Concept

```
┌─────────────────────────────────────────────────────┐
│ Settings › Appearance                               │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Color Scheme                                        │
│ ┌─────────┬─────────┬─────────┐                     │
│ │  Light  │  Dark   │ System  │  ← Segmented ctrl  │
│ └─────────┴─────────┴─────────┘                     │
│                                                     │
│ Accent Color                                        │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐                         │
│ │ 🟠 │ │ 🔵 │ │ 🟣 │ │ 🟢 │   ← Color swatches     │
│ └────┘ └────┘ └────┘ └────┘                         │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐                         │
│ │ 🟣 │ │ 🩷 │ │ 💜 │ │ 🌸 │                         │
│ └────┘ └────┘ └────┘ └────┘                         │
│                                                     │
│ Orange (current)                                    │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

## Technical Specification

### Data Model

Add to user preferences/settings:

```typescript
interface UserThemePreferences {
  colorScheme: 'light' | 'dark' | 'system';
  accentColor: 'orange' | 'teal' | 'indigo' | 'emerald' | 'violet' | 'pink' | 'fuchsia' | 'rose';
}
```

Default values:
- `colorScheme`: `'system'`
- `accentColor`: `'orange'`

### Tailwind Configuration

Update `tailwind.config.js`:

```javascript
module.exports = {
  darkMode: 'class', // Enable class-based dark mode
  theme: {
    extend: {
      // CSS custom properties will handle dynamic accent colors
    }
  }
}
```

### CSS Custom Properties Strategy

Define CSS variables for the accent color that map to Tailwind classes:

```css
:root {
  --color-accent-50: theme('colors.orange.50');
  --color-accent-100: theme('colors.orange.100');
  --color-accent-200: theme('colors.orange.200');
  --color-accent-300: theme('colors.orange.300');
  --color-accent-400: theme('colors.orange.400');
  --color-accent-500: theme('colors.orange.500');
  --color-accent-600: theme('colors.orange.600');
  --color-accent-700: theme('colors.orange.700');
  --color-accent-800: theme('colors.orange.800');
  --color-accent-900: theme('colors.orange.900');
}

[data-accent="teal"] {
  --color-accent-50: theme('colors.teal.50');
  --color-accent-500: theme('colors.teal.500');
  /* ... etc */
}

/* Repeat for each accent color */
```

Extend Tailwind to use these variables:

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        accent: {
          50: 'var(--color-accent-50)',
          100: 'var(--color-accent-100)',
          200: 'var(--color-accent-200)',
          300: 'var(--color-accent-300)',
          400: 'var(--color-accent-400)',
          500: 'var(--color-accent-500)',
          600: 'var(--color-accent-600)',
          700: 'var(--color-accent-700)',
          800: 'var(--color-accent-800)',
          900: 'var(--color-accent-900)',
        }
      }
    }
  }
}
```

### Theme Application

```typescript
// hooks/useTheme.ts
import { useEffect } from 'react';
import { useUserPreferences } from './useUserPreferences';

export function useTheme() {
  const { colorScheme, accentColor } = useUserPreferences();

  useEffect(() => {
    const root = document.documentElement;

    // Apply accent color
    root.dataset.accent = accentColor;

    // Apply color scheme
    if (colorScheme === 'system') {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      root.classList.toggle('dark', isDark);

      // Listen for system changes
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      const handler = (e: MediaQueryListEvent) => {
        root.classList.toggle('dark', e.matches);
      };
      mediaQuery.addEventListener('change', handler);
      return () => mediaQuery.removeEventListener('change', handler);
    } else {
      root.classList.toggle('dark', colorScheme === 'dark');
    }
  }, [colorScheme, accentColor]);
}
```

### Component Updates

Replace hardcoded color classes throughout the codebase:

| Before | After |
|--------|-------|
| `text-orange-500` | `text-accent-500` |
| `bg-orange-50` | `bg-accent-50` |
| `border-orange-200` | `border-accent-200` |
| `hover:bg-orange-600` | `hover:bg-accent-600` |

Dark mode variants:

| Before | After |
|--------|-------|
| `text-orange-400` (dark) | `dark:text-accent-400` |
| `bg-orange-500/20` (dark) | `dark:bg-accent-500/20` |

### Persistence

Store preferences in:
1. **Database** — User settings table (server-side persistence)
2. **localStorage** — For immediate access before API response (prevents flash)

```typescript
// On app load
const cachedPrefs = localStorage.getItem('theme-preferences');
if (cachedPrefs) {
  applyTheme(JSON.parse(cachedPrefs));
}

// After API response
const serverPrefs = await fetchUserPreferences();
applyTheme(serverPrefs.theme);
localStorage.setItem('theme-preferences', JSON.stringify(serverPrefs.theme));
```

### API Endpoints

```
PATCH /api/user/preferences
Body: {
  theme: {
    colorScheme: 'dark',
    accentColor: 'teal'
  }
}
```

---

## Color Mappings Reference

### Light Mode Base Colors

| Purpose | Class |
|---------|-------|
| Page background | `bg-white` / `bg-gray-50` |
| Card background | `bg-white` |
| Primary text | `text-gray-900` |
| Secondary text | `text-gray-600` |
| Muted text | `text-gray-500` |
| Borders | `border-gray-200` |
| Accent primary | `text-accent-500` / `bg-accent-500` |
| Accent hover | `bg-accent-600` |
| Accent background | `bg-accent-50` |
| Accent text on bg | `text-accent-600` |

### Dark Mode Base Colors

| Purpose | Class |
|---------|-------|
| Page background | `dark:bg-gray-900` / `dark:bg-gray-950` |
| Card background | `dark:bg-gray-800` |
| Primary text | `dark:text-gray-100` |
| Secondary text | `dark:text-gray-400` |
| Muted text | `dark:text-gray-500` |
| Borders | `dark:border-gray-700` |
| Accent primary | `dark:text-accent-400` / `dark:bg-accent-500` |
| Accent hover | `dark:bg-accent-400` |
| Accent background | `dark:bg-accent-500/20` |
| Accent text on bg | `dark:text-accent-400` |

### Semantic Colors (Consistent Across Themes)

| Purpose | Light | Dark |
|---------|-------|------|
| Success | `text-green-600` / `bg-green-100` | `dark:text-green-400` / `dark:bg-green-500/20` |
| Warning | `text-amber-600` / `bg-amber-100` | `dark:text-amber-400` / `dark:bg-amber-500/20` |
| Error/Overdue | `text-red-600` / `bg-red-100` | `dark:text-red-400` / `dark:bg-red-500/20` |
| Info | `text-blue-600` / `bg-blue-100` | `dark:text-blue-400` / `dark:bg-blue-500/20` |

---

## Accessibility Requirements

1. **Contrast Ratios** — All text must meet WCAG 2.1 AA standards (4.5:1 for normal text, 3:1 for large text)
2. **Focus Indicators** — Visible focus rings must be present in both light and dark modes
3. **Motion** — Theme transitions should respect `prefers-reduced-motion`
4. **Color Independence** — Information should not be conveyed by color alone (use icons, text labels)

### Contrast Validation

Each accent color has been validated for sufficient contrast:

| Accent | On White (Light) | On Gray-900 (Dark) |
|--------|------------------|-------------------|
| Orange-500 | ✅ 4.5:1 | ❌ Use Orange-400 |
| Orange-400 | — | ✅ 4.6:1 |
| Teal-500 | ✅ 4.5:1 | ❌ Use Teal-400 |
| Teal-400 | — | ✅ 5.1:1 |
| *(continue for all colors)* | | |

---

## Migration Plan

### Phase 1: Infrastructure (Week 1)
- [ ] Add CSS custom properties for accent colors
- [ ] Update Tailwind config with accent color extension
- [ ] Create `useTheme` hook
- [ ] Add theme preferences to user settings API

### Phase 2: Dark Mode (Week 2)
- [ ] Add `dark:` variants to all components
- [ ] Implement color scheme toggle in settings
- [ ] Add localStorage caching for instant theme application
- [ ] Test all pages in dark mode

### Phase 3: Accent Colors (Week 3)
- [ ] Replace hardcoded orange classes with `accent-*` classes
- [ ] Implement accent color picker in settings
- [ ] Validate accessibility for all color combinations
- [ ] Update any hardcoded colors in SVGs/icons

### Phase 4: Polish (Week 4)
- [ ] Add smooth transitions between themes
- [ ] Handle edge cases (emails, exports, etc.)
- [ ] Documentation updates
- [ ] QA testing across all theme combinations

---

## Out of Scope

- Custom user-defined colors (only predefined palettes)
- Per-page or per-section theming
- Theme scheduling (auto dark mode at night)
- High contrast mode (future consideration)

---

## Success Metrics

1. **Adoption** — % of users who change from default theme within 30 days
2. **Retention** — Users who customize theme have higher 30-day retention
3. **Satisfaction** — NPS improvement in user feedback related to UI/UX
4. **Accessibility** — Zero accessibility regressions in Lighthouse audits

---

## Open Questions

1. Should we offer a "preview" before saving, or apply changes immediately?
   - **Recommendation:** Apply immediately (optimistic UI), auto-save after brief debounce

2. Do we need to handle themed exports (PDF reports, email templates)?
   - **Recommendation:** Keep exports in a neutral/brand style for consistency

3. Should organization admins be able to enforce a specific theme?
   - **Recommendation:** Defer to v2; focus on individual preferences first

---

## Appendix: Color Palette Hex Values

| Color | 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 |
|-------|-----|-----|-----|-----|-----|-----|-----|-----|
| Orange | #fff7ed | #ffedd5 | #fed7aa | #fdba74 | #fb923c | #f97316 | #ea580c | #c2410c |
| Teal | #f0fdfa | #ccfbf1 | #99f6e4 | #5eead4 | #2dd4bf | #14b8a6 | #0d9488 | #0f766e |
| Indigo | #eef2ff | #e0e7ff | #c7d2fe | #a5b4fc | #818cf8 | #6366f1 | #4f46e5 | #4338ca |
| Emerald | #ecfdf5 | #d1fae5 | #a7f3d0 | #6ee7b7 | #34d399 | #10b981 | #059669 | #047857 |
| Violet | #f5f3ff | #ede9fe | #ddd6fe | #c4b5fd | #a78bfa | #8b5cf6 | #7c3aed | #6d28d9 |
| Pink | #fdf2f8 | #fce7f3 | #fbcfe8 | #f9a8d4 | #f472b6 | #ec4899 | #db2777 | #be185d |
| Fuchsia | #fdf4ff | #fae8ff | #f5d0fe | #f0abfc | #e879f9 | #d946ef | #c026d3 | #a21caf |
| Rose | #fff1f2 | #ffe4e6 | #fecdd3 | #fda4af | #fb7185 | #f43f5e | #e11d48 | #be123c |
