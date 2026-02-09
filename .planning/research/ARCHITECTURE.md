# Architecture Integration: Rondo Brand Design

**Project:** Rondo Club Design Refresh
**Researched:** 2026-02-09
**Confidence:** HIGH

## Executive Summary

The new brand design from STYLE.md requires replacing the current dynamic theming system with fixed brand colors and new visual patterns. The integration involves three architectural layers:

1. **Design Token Layer** — Replace dynamic accent colors with fixed Tailwind tokens
2. **Component Layer** — Update 69 JSX components to use new brand classes
3. **Configuration Layer** — Remove WordPress theme customization system

The current architecture uses a sophisticated theming system with CSS variables, dynamic color generation, dark mode support, and user-selectable accent colors. The new design removes this flexibility in favor of a consistent, light-only brand identity.

**Critical architectural decision:** Tailwind CSS v3.4 (current) vs v4.0 (STYLE.md uses). Recommend staying on v3.4 for stability and use compatible syntax.

## Current Architecture Analysis

### Layer 1: Color Token System

**Current implementation:**

```
tailwind.config.js
├── accent-* scale (50-900) mapped to CSS variables
├── club static colors (#006935 green scale)
└── darkMode: 'class' enabled

src/index.css
├── :root CSS variables (--color-accent-*)
├── [data-accent="X"] variants for 8 colors
└── .dark variants inverting scale for dark mode
```

**Dynamic behavior:**
- `useTheme.js` (385 lines) injects CSS variables at runtime
- WordPress option `rondo_accent_color` provides base hex
- Color scale generated via `lightenHex()` and `darkenHex()` functions
- Supports system/light/dark color schemes with localStorage persistence

**Usage scope:**
- 549 references to `accent-*` classes across codebase
- 1,877 `dark:*` class usages
- Color used in: buttons, links, focus rings, avatars, badges, active states

### Layer 2: WordPress Configuration

**Backend storage:**

```php
class-club-config.php
├── get_accent_color() → #006935 default
├── WordPress Options API storage
└── Exposed to React via window.rondoConfig
```

**Frontend consumption:**

```javascript
functions.php
├── Localizes rondoConfig to React
│   ├── accentColor: ClubConfig::get_accent_color()
│   └── siteName: ClubConfig::get_club_name()
└── Settings page includes color picker (react-colorful)
```

### Layer 3: PWA Integration

**Dynamic theme integration:**
- Favicon SVG color changes per accent selection
- Meta theme-color tags for light/dark modes
- PWA manifest theme_color (currently green #006935)

## New Brand Design Requirements

### Color Palette (from STYLE.md)

**Brand tokens to add:**

| Token | Hex | Replaces |
|-------|-----|----------|
| `electric-cyan` | `#0891B2` | Primary actions, links, focus rings (was accent-600) |
| `electric-cyan-light` | `#22D3EE` | Gradients, decorative elements (was accent-400) |
| `bright-cobalt` | `#2563EB` | Gradient endpoints, secondary actions (new) |
| `deep-midnight` | `#1E3A8A` | Dark accents (new) |
| `obsidian` | `#0F172A` | Footer/darkest elements (was gray-900) |

**Keep slate scale:** Current slate-50 through slate-900 matches STYLE.md requirements.

**Remove:**
- All accent-* CSS variable mapping
- Dark mode variants
- Multi-color accent system (club, orange, teal, indigo, emerald, violet, pink, fuchsia, rose)

### Component Patterns

**Button variants (NEW):**

```css
.btn-primary (gradient)
  bg: linear-gradient(135deg, #0891B2, #2563EB)
  text: white
  hover: translateY(-2px) + colored shadow

.btn-secondary (solid)
  bg: #2563EB (bright-cobalt)
  text: white
  same hover lift

.btn-glass (transparent)
  bg: transparent
  border: #E2E8F0 (slate-200)
  hover: subtle darken
```

**Current button variants to replace:**

```css
.btn-primary (current)
  bg: accent-600 / dark:gray-700 with accent border

.btn-secondary (current)
  bg: white / dark:gray-800

.btn-danger (keep, update colors)
  bg: red-600 / dark:red-500
```

**Card patterns (NEW):**

```css
.card
  bg: #F8FAFC (slate-50)
  border: 1px solid #E2E8F0 (slate-200)
  + 3px gradient top border (pseudo-element)
  shadow: 0 4px 16px rgba(0,0,0,0.06)
  rounded: 1rem
  hover: darker bg + stronger shadow
```

**Current card pattern:**

```css
.card
  bg: white / dark:gray-800
  border: gray-200 / dark:gray-700
  rounded-lg (0.5rem)
  shadow-sm
```

**Glass panels (NEW):**
- Same as cards but no gradient top border
- Used for header (rgba(255,255,255,0.85) + blur(10px))

### Visual Effects (NEW)

1. **Gradient text** — h3 elements get cyan-to-cobalt gradient
2. **Decorative blobs** — radial gradients with blur(80px), opacity 0.4
3. **Hover lifts** — buttons/cards translate -2px with shadow
4. **Focus rings** — 3px cyan glow on inputs

## Integration Points

### Point 1: Tailwind Configuration

**File:** `tailwind.config.js`

**Changes:**

```javascript
// REMOVE
darkMode: 'class',
colors: {
  accent: { 50-900 mapped to CSS variables },
  club: { ... },
  primary: { ... }  // unused yellow scale
}

// ADD
colors: {
  'electric-cyan': '#0891B2',
  'electric-cyan-light': '#22D3EE',
  'bright-cobalt': '#2563EB',
  'deep-midnight': '#1E3A8A',
  'obsidian': '#0F172A',
  // slate scale already exists in Tailwind defaults
}

// ADD gradient utilities
backgroundImage: {
  'brand-gradient': 'linear-gradient(135deg, #0891B2 0%, #2563EB 100%)',
  'brand-gradient-light': 'linear-gradient(135deg, #06B6D4 0%, #1D4ED8 100%)',
}
```

**Note:** Tailwind v3.4 syntax (not v4.0 @theme as in STYLE.md).

### Point 2: Base Styles

**File:** `src/index.css`

**Remove (lines 117-355):**
- All `:root` CSS variable definitions
- All `[data-accent="X"]` variants
- All `.dark` color scale inversions

**Update:**

```css
@layer base {
  body {
    @apply bg-slate-50 text-slate-800;  // was gray-50/gray-900
    /* Remove: dark:bg-gray-900 dark:text-gray-100 */
  }

  /* Keep iOS safe area utilities */
  /* Keep transition support */
  /* Remove: @media (prefers-reduced-motion) transitions */
}

/* Update timeline content styles */
.timeline-content a {
  @apply text-electric-cyan hover:text-bright-cobalt underline;
  /* Remove: dark:text-accent-400 dark:hover:text-accent-300 */
}
```

**Add new component classes:**

```css
@layer components {
  .btn-primary {
    @apply btn bg-brand-gradient text-white hover:shadow-lg;
    transition: all 200ms ease;
  }
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(8, 145, 178, 0.3);
  }

  .btn-secondary {
    @apply btn bg-bright-cobalt text-white hover:shadow-lg;
  }

  .btn-glass {
    @apply btn bg-transparent border border-slate-200 hover:bg-slate-50;
  }

  .card {
    @apply bg-slate-50 rounded-2xl border border-slate-200 relative;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
  }
  .card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(135deg, #0891B2, #2563EB);
    border-radius: 1rem 1rem 0 0;
  }

  .gradient-text {
    background: linear-gradient(135deg, #06B6D4, #1D4ED8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
}
```

### Point 3: Remove Theme Hook

**File:** `src/hooks/useTheme.js`

**Action:** DELETE entire file (385 lines)

**Justification:** No longer need dynamic color injection, dark mode toggle, or accent color switching.

**Dependent code to update:**
- Settings page theme controls
- Any component importing `useTheme`

### Point 4: WordPress Configuration Cleanup

**Files:**
- `includes/class-club-config.php`
- `functions.php`

**Changes:**

```php
// class-club-config.php
// REMOVE: get_accent_color() method
// REMOVE: OPTION_ACCENT_COLOR constant
// KEEP: get_club_name() for site branding

// functions.php (localize script)
wp_localize_script('rondo-main', 'rondoConfig', [
  'siteName' => $club_config->get_club_name(),
  // REMOVE: 'accentColor' => $club_config->get_accent_color(),
]);
```

### Point 5: Settings Page Updates

**File:** Search for Settings page with color picker

**Remove:**
- Color scheme selector (light/dark/system)
- Accent color picker (react-colorful)
- Any UI for theme customization

**Keep:**
- Club name input (still needed)

### Point 6: Component Updates

**Scope:** 69 JSX files with component patterns

**Update strategy:**

1. **Find/replace patterns:**

```javascript
// Buttons
"bg-accent-600" → "bg-electric-cyan"
"hover:bg-accent-700" → "hover:bg-bright-cobalt"
"text-accent-600" → "text-electric-cyan"
"focus:ring-accent-500" → "focus:ring-electric-cyan"

// Remove all dark: variants
"dark:bg-gray-800" → remove
"dark:text-gray-100" → remove
// (1,877 occurrences)

// Cards
"bg-white" → "bg-slate-50" (for cards)
"border-gray-200" → "border-slate-200"

// Links
"text-accent-600 hover:text-accent-700" → "text-electric-cyan hover:text-bright-cobalt"
```

2. **Manual component updates:**

**DashboardCard.jsx:**
- Add gradient top border to card
- Remove dark mode header bg
- Update icon colors to electric-cyan

**Layout.jsx (Sidebar):**
- Update logo color to electric-cyan
- Remove dark mode classes
- Update active nav state to use electric-cyan

**All modal components:**
- Remove dark:bg-gray-800 overlays
- Update to light slate backgrounds
- Remove dark mode contrast fixes

### Point 7: PWA Manifest & Favicon

**Files:**
- `public/manifest.json` (if exists)
- Favicon generation in `useTheme.js` (being deleted)

**Static replacements:**

```json
// manifest.json
{
  "theme_color": "#0891B2",  // electric-cyan
  "background_color": "#F8FAFC"  // slate-50
}
```

**Favicon:** Use static SVG with electric-cyan fill (no dynamic generation).

## Component Dependencies & Build Order

### Phase 1: Foundation (No Dependencies)
**Must complete first — breaks existing system**

1. **Update Tailwind config** — Add brand tokens, remove accent/club scales
2. **Update index.css** — Remove CSS variables, add new component classes
3. **Delete useTheme.js** — Remove dynamic theming hook
4. **Update WordPress config** — Remove accent color backend

**Verification:** Build succeeds, no runtime errors (UI will be broken but app runs)

### Phase 2: Core Components (Depends on Phase 1)
**Foundation of UI system**

5. **Button classes** — Update all btn-primary/secondary/danger usages
6. **Card component** — DashboardCard.jsx with gradient top border
7. **Layout system** — Sidebar, Header, main containers

**Verification:** Navigation, cards, buttons render correctly

### Phase 3: Feature Components (Depends on Phase 2)
**Can be done in parallel after Phase 2**

8. **Modals** (14 components) — Remove dark mode, update backgrounds
9. **Forms** — Input styles, focus rings, labels
10. **Lists** — People list, team list, todo list styling
11. **Timeline** — Activity cards, note cards, todo items
12. **Dashboard widgets** — Stats cards, reminder cards, meeting cards

**Verification:** All features render correctly, no visual regressions

### Phase 4: Polish & Cleanup (Depends on Phase 3)
**Final pass**

13. **Settings page** — Remove theme controls
14. **PWA assets** — Update manifest, favicon
15. **Remove dead code** — Search for remaining accent-* references
16. **Dark mode cleanup** — Remove all dark: classes (find/replace)

**Verification:** No console errors, no unused CSS, Lighthouse scores maintained

## Data Flow Changes

### Before (Dynamic Theming)

```
WordPress Options (rondo_accent_color: #006935)
  ↓
ClubConfig::get_accent_color()
  ↓
functions.php localizes → window.rondoConfig.accentColor
  ↓
useTheme.js reads → injects CSS variables to :root
  ↓
Tailwind classes (accent-600) → var(--color-accent-600)
  ↓
Rendered color in UI
```

**User can customize:** Accent color, light/dark mode preference

### After (Static Branding)

```
Tailwind config (electric-cyan: #0891B2)
  ↓
Tailwind classes (bg-electric-cyan) → #0891B2
  ↓
Rendered color in UI
```

**User can customize:** Nothing (consistent brand identity)

## Breaking Changes

### For Users

1. **No theme customization** — Cannot change accent color
2. **No dark mode** — Light theme only
3. **Different visual style** — Gradients, rounder cards, cyan/blue palette

### For Developers

1. **accent-* classes removed** — Must use electric-cyan, bright-cobalt
2. **dark: classes non-functional** — Remove all dark mode variants
3. **useTheme hook removed** — Any component using it will break
4. **Card structure changed** — New gradient top border via ::before

## Risk Assessment

| Risk | Severity | Mitigation |
|------|----------|------------|
| Breaking all 69 components simultaneously | HIGH | Phase 1 breaks build; must complete Phases 1-2 in single session |
| Missing dark: class cleanup causes confusion | MEDIUM | Use grep to find all 1,877 instances, automated find/replace |
| Gradient performance on low-end devices | LOW | CSS gradients are hardware-accelerated |
| Users expect dark mode | MEDIUM | Document removal in changelog, no technical risk |
| Tailwind v3 vs v4 syntax mismatch | LOW | STYLE.md uses v4 @theme, we use v3 extend.colors |

## New Components Needed

### 1. GradientText Component

```jsx
// src/components/GradientText.jsx
export default function GradientText({ children, className = '' }) {
  return (
    <span className={`gradient-text ${className}`}>
      {children}
    </span>
  );
}
```

**Usage:** Wrap section headings for brand gradient effect.

### 2. DecorativeBlob Component (Optional)

```jsx
// src/components/DecorativeBlob.jsx
export default function DecorativeBlob({ position = 'top-right' }) {
  const positionClasses = {
    'top-right': 'top-0 right-0',
    'bottom-left': 'bottom-0 left-0',
  };

  return (
    <div
      className={`absolute ${positionClasses[position]} w-96 h-96 -z-10 pointer-events-none`}
      style={{
        background: 'radial-gradient(circle, rgba(34, 211, 238, 0.4), transparent 70%)',
        filter: 'blur(80px)',
      }}
    />
  );
}
```

**Usage:** Add to dashboard background for visual interest (per STYLE.md).

### 3. GlassPanel Component

```jsx
// src/components/GlassPanel.jsx
export default function GlassPanel({ children, className = '' }) {
  return (
    <div
      className={`bg-white/85 backdrop-blur-md border border-slate-200 rounded-2xl p-6 ${className}`}
      style={{
        boxShadow: '0 4px 16px rgba(0,0,0,0.06)',
      }}
    >
      {children}
    </div>
  );
}
```

**Usage:** Header, special containers (per STYLE.md glass morphism pattern).

## Modified Components

### High-Impact (Structure Changes)

| Component | Change Type | Complexity |
|-----------|-------------|------------|
| `DashboardCard.jsx` | Add ::before gradient border, remove dark mode | MEDIUM |
| `Layout.jsx` | Update sidebar, header, remove useTheme import | HIGH |
| `index.css` | Complete rewrite of component layer | HIGH |
| `tailwind.config.js` | Remove accent system, add brand tokens | MEDIUM |

### Medium-Impact (Class Updates Only)

| Component Type | Count | Change Type |
|----------------|-------|-------------|
| Modals | 14 | Remove dark:*, update backgrounds |
| Form components | 8 | Update focus rings, borders |
| List views | 5 | Update hover states, borders |
| Cards | 12 | Update to slate-50 backgrounds |

### Low-Impact (Minor Color Changes)

| Component Type | Count | Change Type |
|----------------|-------|-------------|
| Buttons | ~50 usages | Update btn-primary classes |
| Links | ~100 usages | accent-600 → electric-cyan |
| Icons | ~200 usages | Update text colors |

## Testing Strategy

### Visual Regression Checkpoints

1. **After Phase 1:** App runs, no console errors (UI broken expected)
2. **After Phase 2:** Core layout renders, buttons clickable, cards visible
3. **After Phase 3:** All features functional, forms submittable
4. **After Phase 4:** No dark mode remnants, clean build

### Manual Test Scenarios

- [ ] Dashboard loads with gradient cards
- [ ] Navigation highlights active page with cyan
- [ ] Buttons show gradient and lift on hover
- [ ] Modals open with light backgrounds
- [ ] Forms show cyan focus rings
- [ ] All pages accessible and functional
- [ ] PWA installs with correct theme color
- [ ] No console warnings about missing CSS variables

## Performance Considerations

### Before (Dynamic Theming)

- 385 lines of useTheme.js executed on every mount
- Runtime CSS variable injection
- Dark mode media query listener
- localStorage reads/writes for preferences

### After (Static Branding)

- Zero runtime theme logic
- Static Tailwind classes compiled to CSS
- No JavaScript color calculations
- Smaller bundle (remove useTheme, react-colorful)

**Estimated bundle savings:** ~30KB (useTheme + react-colorful)

## Rollback Strategy

If design refresh causes issues:

1. **Git revert** to before Phase 1
2. **Backup tailwind.config.js** before changes
3. **Keep useTheme.js** in a backup file for one release cycle
4. **Document breaking changes** in CHANGELOG.md

**Point of no return:** After deploying Phase 1-2, rolling back requires full rebuild.

## Open Questions for Validation

1. **Typography:** STYLE.md uses Montserrat for headings — need to add web font?
2. **Gradient performance:** Acceptable on mobile devices with many cards?
3. **Accessibility:** Cyan/blue palette meets WCAG AA contrast ratios?
4. **User feedback:** How to communicate dark mode removal to users?
5. **Settings page:** What remains after removing theme controls?

## References

- **STYLE.md:** `/Users/joostdevalk/Code/rondo/STYLE.md`
- **Current Tailwind config:** `/Users/joostdevalk/Code/rondo/rondo-club/tailwind.config.js`
- **Current theme hook:** `/Users/joostdevalk/Code/rondo/rondo-club/src/hooks/useTheme.js`
- **Current base styles:** `/Users/joostdevalk/Code/rondo/rondo-club/src/index.css`
- **Club config backend:** `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-club-config.php`
