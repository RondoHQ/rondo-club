# Domain Pitfalls: Design System Refresh

**Domain:** React SPA design system migration (dark mode removal, dynamic color removal, brand refresh)
**Researched:** 2026-02-09
**Project Context:** Production app with 40+ pages, 1902 dark mode class instances, 339 accent color usages

## Critical Pitfalls

### Pitfall 1: Incomplete Dark Mode Class Removal Causes Visual Inconsistencies

**What goes wrong:**
The app has 1902 instances of `dark:` classes across 68 files. Missing even a small percentage during removal (e.g., 5% = 95 instances) creates inconsistent styling where some components still respond to dark mode state. This manifests as broken layouts, invisible text on white backgrounds, or white text on white backgrounds in production.

**Why it happens:**
- Manual search-and-replace misses complex cases (e.g., `dark:hover:bg-gray-700`, conditional classes in JavaScript template literals)
- Dark mode classes in dynamically generated strings aren't caught by static analysis
- Copy-pasted code blocks contain hidden dark mode classes
- Grep/regex patterns don't account for all syntax variations

**Consequences:**
- Components render with broken contrast (white on white, dark on dark)
- User-facing visual bugs in production affecting usability
- Requires emergency hotfix deployment
- Loss of user trust if accessibility is compromised

**Prevention:**
1. **Automated detection first:** Run grep for all `dark:` patterns before starting
2. **Multiple search patterns:** Use regex variations: `dark:`, `className.*dark`, `\bdark\b`
3. **Build-time validation:** Add ESLint rule or build script that fails if `dark:` classes detected
4. **Visual regression testing:** Screenshot testing on key pages to catch styling breaks
5. **Systematic file-by-file approach:** Check off each file as completed rather than bulk find-replace

**Detection:**
- Grep returns > 0 results for `dark:` after migration
- Screenshots show contrast/visibility issues
- Build warnings from custom linter rule

**Phase assignment:** Phase 1 (Foundation) must include automated detection tooling

---

### Pitfall 2: CSS Variable References Break When Accent System Removed

**What goes wrong:**
The app uses 339 instances of `accent-` color utilities across 60 files. The dynamic accent system relies on CSS variables (`var(--color-accent-600)`) defined in `index.css` (lines 118-355). Removing these variables without replacing ALL references causes components to lose all color styling, rendering as black/gray or invisible.

**Why it happens:**
- CSS variables fail silently—browsers just ignore undefined variables
- Tailwind purges unused classes in production, but can't detect broken variable references
- Classes like `bg-accent-600` compile to `background-color: var(--color-accent-600)`, which breaks when variable is removed
- Component styles that reference accent colors in computed/dynamic class names are harder to find

**Consequences:**
- Buttons, badges, links lose all brand color in production
- Components render in gray/default colors, looking unprofessional
- "Working in dev, broken in prod" scenario if dev uses cached CSS
- Requires full redeploy of all assets

**Prevention:**
1. **Map before delete:** Create mapping of old accent colors to new brand colors BEFORE removing anything
2. **Two-phase migration:**
   - Phase 1: Replace all `accent-*` usages with new brand colors
   - Phase 2: Remove CSS variable definitions from index.css
3. **Grep verification:** After replacement, grep for any remaining `accent-` references
4. **Test production build locally:** Run `npm run build && npm run preview` to catch purge issues
5. **Keep fallback values:** Temporarily keep old CSS variables with fallback to new colors during transition

**Detection:**
- Components render without expected brand colors
- DevTools show `var(--color-accent-*)` resolving to nothing
- Grep finds orphaned `accent-` class references after variable removal

**Phase assignment:** Phase 2 (Color System Migration) must be two-step: replace usages first, remove definitions second

---

### Pitfall 3: Tailwind v3→v4 Migration Breaks Custom Color Configuration

**What goes wrong:**
The project currently uses Tailwind v3.4 (confirmed in package.json). If upgrading to v4 as part of the refresh, the entire configuration system changes from JavaScript (`tailwind.config.js`) to CSS-based (`@theme` directive). Custom colors defined in the config (lines 10-36 of tailwind.config.js) won't work in v4 without migration.

**Why it happens:**
- Tailwind v4 fundamentally changes how configuration works (no more `tailwind.config.js`)
- Custom colors must be redefined using CSS custom properties with `--color-*` namespace
- The `darkMode: 'class'` setting (line 3) has been replaced with `selector` strategy in v3.4.1+
- Arbitrary values syntax changed (commas no longer work, must use underscores)
- Container utility options (`center`, `padding`) no longer exist in v4

**Consequences:**
- Build completely fails if v4 is installed without config migration
- Custom `club` and `primary` color palettes become undefined
- All `bg-club-600`, `text-primary-500` classes stop working
- Entire app renders in default Tailwind colors
- Production deployment blocked until config is fixed

**Prevention:**
1. **Decide version strategy first:** Determine if v4 upgrade is in scope for this milestone
2. **If staying on v3:** Document decision, skip v4-specific changes, update safely to latest v3.x
3. **If upgrading to v4:**
   - Use official migration tool: `npx @tailwindcss/upgrade@next`
   - Migrate config in dedicated phase BEFORE design changes
   - Convert custom colors to CSS variables in `@theme` directive
   - Test build succeeds before making component changes
4. **Version lock:** Pin Tailwind version in package.json during migration to prevent unexpected upgrades

**Detection:**
- Build fails with Tailwind configuration errors
- Custom color classes render as undefined
- DevTools show missing color values
- Upgrade tool reports migration issues

**Phase assignment:** Phase 0 (Pre-flight) should decide v3 vs v4 and complete any Tailwind version migration BEFORE design system changes

---

### Pitfall 4: Gradient Text WebKit-Prefix Breaks in Firefox/Safari

**What goes wrong:**
Gradient text requires `-webkit-background-clip: text` and `-webkit-text-fill-color: transparent`. Without proper vendor prefixing and fallbacks, text becomes invisible in older browsers or renders without gradient in Firefox.

**Why it happens:**
- `background-clip: text` is not fully standardized—requires `-webkit-` prefix
- Firefox 49+ supports it, but older versions don't
- IE11 and Opera Mini (Presto engine) don't support it at all
- `-webkit-text-fill-color: transparent` can make text invisible on mobile Safari without proper background
- CSS purging in production may remove vendor prefixes if not properly configured

**Consequences:**
- Gradient text appears as solid color or invisible in unsupported browsers
- Accessibility failure: text completely disappears for some users
- Brand identity inconsistent across browsers
- Mobile Safari users report "missing text" bugs

**Prevention:**
1. **Always include vendor prefix:** Use both `-webkit-background-clip: text` and `background-clip: text`
2. **Add color fallback:** Include `color` property before gradient for unsupported browsers
3. **Feature detection:** Use `@supports` to test for `background-clip: text` support
4. **Test matrix:** Test on Firefox (latest), Safari (iOS + macOS), Chrome, Edge
5. **PostCSS autoprefixer:** Ensure autoprefixer is configured to add vendor prefixes (already in project at package.json:41)
6. **Tailwind plugin:** Create custom utility class that includes all necessary prefixes

**Example safe implementation:**
```css
.gradient-text {
  color: #006935; /* Fallback */
  background: linear-gradient(to right, #006935, #22c560);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

@supports not (background-clip: text) {
  .gradient-text {
    color: #006935; /* Override for unsupported browsers */
    -webkit-text-fill-color: currentColor;
  }
}
```

**Detection:**
- Text invisible or wrong color in browser testing
- DevTools show unsupported property warnings
- Feature detection with `@supports` returns false

**Phase assignment:** Phase 3 (Visual Elements) must include cross-browser gradient testing

---

### Pitfall 5: Glass Morphism Backdrop-Filter Destroys Mobile Performance

**What goes wrong:**
Glass morphism effects using `backdrop-filter: blur()` trigger GPU compositing, causing severe performance degradation on mobile devices (lag, frame drops, battery drain). On low-end Android devices or iOS low-power mode, the effect may be disabled entirely or cause the app to become unusable.

**Why it happens:**
- `backdrop-filter` forces GPU to recompute blur on every frame
- Heavy blur values (blur(20px+)) are exponentially expensive
- Mobile GPUs are significantly weaker than desktop GPUs
- Stacking multiple blurred layers multiplies the performance cost
- iOS low-power mode automatically disables backdrop-filter
- Older mobile browsers don't support backdrop-filter at all (falls back to no background)

**Consequences:**
- App feels sluggish/unresponsive on mobile (60% of users)
- Scrolling stutters or drops to 15-20 fps
- Battery drains rapidly during usage
- iOS low-power mode users see transparent backgrounds without blur (broken UI)
- User complaints about "slow" or "broken" app
- High bounce rate from mobile users

**Prevention:**
1. **Limit blur intensity:** Use blur(10px) or less, never exceed blur(15px)
2. **Reduce blurred elements:** Apply only to key UI (modals, navigation), not cards/lists
3. **No stacking:** Never stack multiple backdrop-filter elements
4. **Mobile-specific values:** Use media queries to reduce blur on mobile
5. **Fallback background:** Provide solid semi-transparent background when backdrop-filter unsupported
6. **Hardware acceleration:** Add `transform: translateZ(0)` to trigger GPU compositing
7. **Feature detection:** Use `@supports` to provide fallback for unsupported browsers
8. **Performance testing:** Test on actual low-end Android device, not just iPhone

**Example safe implementation:**
```css
.glass-card {
  /* Fallback for unsupported browsers */
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

@supports (backdrop-filter: blur(10px)) {
  .glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    transform: translateZ(0); /* Hardware acceleration */
  }

  /* Reduce blur on mobile */
  @media (max-width: 768px) {
    .glass-card {
      backdrop-filter: blur(5px);
    }
  }
}
```

**Detection:**
- Frame rate drops below 30fps on mobile (use DevTools performance profiling)
- Browser DevTools show high GPU usage
- User reports of laggy/slow app
- Visual artifacts or missing blur effects in testing

**Phase assignment:** Phase 3 (Visual Elements) must include mobile performance testing

---

## Moderate Pitfalls

### Pitfall 6: Font Loading FOUT/FOIT Without Optimization

**What goes wrong:**
Adding custom font (Montserrat) without proper loading strategy causes Flash of Unstyled Text (FOUT) or Flash of Invisible Text (FOIT). Users see system fonts for 1-3 seconds before Montserrat loads, or see invisible text while waiting for font.

**Why it happens:**
- Default Google Fonts loading doesn't use optimal font-display strategy
- Loading all 18 Montserrat variants (Thin 100 to Black 900 + italics) adds significant data
- No preloading means fonts load after CSS parsing
- WOFF2 compression not leveraged properly

**Prevention:**
1. **Load only needed weights:** If design uses Regular (400) and Bold (700), don't load all 18 variants
2. **Use font-display: swap:** Ensures text visible immediately with fallback font
3. **Preload critical fonts:** Add `<link rel="preload">` for primary font files
4. **Self-host via google-webfonts-helper:** Better control over loading, enables preload
5. **Subset characters:** If app is Latin-only, don't load full Unicode range

**Phase assignment:** Phase 1 (Foundation) must define font loading strategy

---

### Pitfall 7: Missing Dark Mode Cleanup in Backend PHP

**What goes wrong:**
Frontend classes are cleaned but backend PHP templates/functions that set dark mode data attributes remain, causing JavaScript errors or unexpected behavior.

**Why it happens:**
- Search focused on `.jsx` files, missed `.php` files
- `data-accent` attribute (line 133+ in index.css) controlled by backend
- Dark mode toggle logic may be in WordPress settings

**Prevention:**
1. **Search PHP files:** Grep for `dark`, `data-accent`, theme toggle in `*.php`
2. **Remove backend logic:** Delete dark mode toggle UI in settings
3. **Remove data attributes:** Clean up `data-accent` attribute setting in PHP
4. **Database cleanup:** Remove dark mode user preferences from user meta

**Phase assignment:** Phase 1 (Foundation) must include backend cleanup

---

### Pitfall 8: Production Build CSS Purging Removes Needed Classes

**What goes wrong:**
Tailwind's production build purges classes that appear unused, but are actually applied dynamically (JavaScript template literals, conditional classes). New brand colors might be purged if only used in dynamic contexts.

**Why it happens:**
- Tailwind scans files for class names but can't detect runtime-generated classes
- Template literals like `bg-${color}-600` aren't detected by static analysis
- Safelist configuration incomplete

**Prevention:**
1. **Avoid dynamic class composition:** Use `clsx` with full class names, not `bg-${variable}`
2. **Update safelist:** Add all brand color utilities to `safelist` in tailwind.config.js
3. **Test production build:** Always run `npm run build && npm run preview` before deploying
4. **Visual regression testing:** Screenshot comparisons between dev and prod builds

**Phase assignment:** Phase 2 (Color System) must include production build validation

---

### Pitfall 9: Transition Effects Break During Color System Migration

**What goes wrong:**
The app has transition declarations for `background-color, border-color, color` (lines 56-64 in index.css). Changing color values without updating transitions causes jarring visual jumps.

**Why it happens:**
- Old accent colors have different hue/saturation than new brand colors
- Transition timing optimized for old color scheme
- Hover states reference deleted color variables

**Prevention:**
1. **Review transition targets:** Ensure all transitioned properties still valid after color migration
2. **Test all interactive states:** Hover, focus, active, disabled states on buttons/inputs
3. **Update timing if needed:** New colors may need different duration (e.g., 200ms vs 150ms)
4. **Respect prefers-reduced-motion:** Already implemented (lines 67-74), ensure preserved

**Phase assignment:** Phase 2 (Color System) must include interaction state testing

---

## Minor Pitfalls

### Pitfall 10: Safe Area Insets Lost During CSS Refactor

**What goes wrong:**
Custom safe area utilities (lines 29-54 in index.css) for iOS notch/Dynamic Island are accidentally removed during CSS cleanup.

**Prevention:**
- Mark safe area CSS as "DO NOT REMOVE" with comments
- Test on iOS device with notch after changes
- Verify `env(safe-area-inset-*)` values still applied

**Phase assignment:** Phase 1 (Foundation) must preserve iOS utilities

---

### Pitfall 11: Timeline Content Styles Hardcoded to Accent Colors

**What goes wrong:**
Timeline content links (line 79 in index.css) use `text-accent-600 dark:text-accent-400`. If not updated to new brand colors, all timeline links break styling.

**Prevention:**
- Search for `@layer` custom styles that reference accent colors
- Update timeline styles to use new brand colors
- Test timeline view with links after migration

**Phase assignment:** Phase 2 (Color System) must update custom layer styles

---

### Pitfall 12: Component Class Dark Mode References in CSS

**What goes wrong:**
Custom component classes (`.btn-primary`, `.btn-secondary`, `.input`, `.card`) at lines 363-389 all have `dark:` variants that must be removed.

**Prevention:**
- Clean up `@layer components` section in index.css
- Remove all `dark:` variants from custom components
- Update to light-only design
- Verify all custom components render correctly

**Phase assignment:** Phase 1 (Foundation) must clean component layer classes

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Tailwind Version Decision | Accidental v4 upgrade breaks config | Pin Tailwind version in package.json, document v3/v4 decision |
| Dark Mode Removal | Incomplete removal leaves visual bugs | Automated grep validation, ESLint rule, build-time checks |
| Color System Migration | CSS variable references break | Two-phase approach: replace usages first, remove definitions second |
| Gradient Text | Browser incompatibility, invisible text | Vendor prefixes, feature detection, fallback colors |
| Glass Morphism | Mobile performance collapse | Blur limits, mobile-specific values, fallback backgrounds, actual device testing |
| Font Loading | FOUT/FOIT poor UX | font-display: swap, preload, subset, load only needed weights |
| Production Build | CSS purging removes needed classes | Safelist configuration, test prod build before deploy |
| Backend Cleanup | PHP templates still set dark mode | Search PHP files, remove data-accent logic |

---

## Sources

### HIGH Confidence (Official Documentation)

- [Tailwind CSS v3 to v4 Upgrade Guide](https://tailwindcss.com/docs/upgrade-guide) - Official migration documentation
- [Tailwind CSS v4 Migration Discussion #15913](https://github.com/tailwindlabs/tailwindcss/discussions/15913) - Custom colors migration guidance
- [MDN: background-clip](https://developer.mozilla.org/en-US/docs/Web/CSS/background-clip) - Browser support and vendor prefixes
- [Tailwind Dark Mode Documentation](https://tailwindcss.com/docs/dark-mode) - Dark mode configuration

### MEDIUM Confidence (Official Sources + Community)

- [Migrating from Tailwind CSS v3 to v4: A Complete Developer's Guide](https://dev.to/elechipro/migrating-from-tailwind-css-v3-to-v4-a-complete-developers-guide-cjd) - Community migration guide
- [Upgrading TailwindCSS v3 to v4](https://iamjeremie.me/post/2025-06/upgrading-tailwindcss-v3-to-v4/) - Real-world migration experience
- [Tailwind Dark Mode Not Working in Production Discussion #4358](https://github.com/tailwindlabs/tailwindcss/discussions/4358) - Production build pitfalls
- [CSS Gradient Text Browser Compatibility](https://css-tricks.com/snippets/css/gradient-text/) - Gradient text implementation

### MEDIUM Confidence (Verified Web Search)

- [Next-level Frosted Glass with backdrop-filter](https://medium.com/@kaklotarrahul79/next-level-frosted-glass-with-backdrop-filter-456e0271ab9d) - Glass morphism performance
- [Implementing Liquid Glass UI in React Native: Complete Guide 2025](https://cygnis.co/blog/implementing-liquid-glass-ui-react-native/) - Mobile performance considerations
- [Optimizing Web Fonts: FOIT vs FOUT vs Font Display Strategies](https://talent500.com/blog/optimizing-fonts-foit-fout-font-display-strategies/) - Font loading optimization
- [Google Fonts FOUT Knowledge](https://fonts.google.com/knowledge/glossary/fout) - Official Google Fonts guidance

### LOW Confidence (Needs Validation)

- Design token migration patterns - General web search results, not specific to this stack
- React Component CSS Variable Refactoring - Community discussions without official verification
