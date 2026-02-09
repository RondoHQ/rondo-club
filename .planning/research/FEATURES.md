# Feature Landscape

**Domain:** React SPA Design System Refresh (Brand-Aligned)
**Researched:** 2026-02-09

## Table Stakes

Features users expect in modern design system refreshes. Missing any = incomplete migration, users notice inconsistency.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Fixed brand color palette | Users expect consistent brand identity across web properties; dynamic user colors undermine brand recognition | Low | Replaces existing dynamic accent color picker. Must update all `accent-*` references to fixed gradient colors |
| Gradient buttons (primary/secondary/ghost) | Modern UI convention 2026; signals visual hierarchy and interactivity | Low | Three variants expected: filled gradient (CTA), outlined (secondary), glass (tertiary) |
| Card gradient top border | Established pattern for visual accent without overwhelming content; signals category/status | Low | 3px top border with gradient, consistent across all card components |
| Consistent focus ring styling | WCAG 2.4.7 requirement; users expect visible focus indicators | Low | Must update all inputs/buttons to use new brand gradient colors in focus rings |
| Typography consistency | Users notice font mismatches immediately; undermines brand trust | Low | Apply Montserrat to headings throughout (currently using Inter for all text) |
| Responsive gradient behavior | Gradients must work across mobile/tablet/desktop without banding or performance issues | Medium | Test gradient rendering on different screen densities and devices |
| Dark/light mode parity | If keeping dark mode, both modes must have equivalent visual weight and polish | Medium | Decision point: remove dark mode entirely OR adapt gradients/glass for dark mode |

## Differentiators

Features that set this design refresh apart. Not expected, but make the app feel premium and on-brand.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Glass morphism header | Creates modern, layered visual depth; signals premium product quality | Medium | backdrop-filter: blur(12px), rgba background with 10-40% opacity, requires colorful content underneath to "pop" |
| Gradient text in headings | Reinforces brand identity in every page; creates visual cohesion with rondo.club website | Low | Use bg-clip-text with gradient, ensure fallback color for accessibility |
| Animated gradient borders | Creates micro-delight; signals active/interactive state | High | CSS @property with conic-gradient animation. Skip for MVP—complexity vs impact ratio poor |
| PWA theme-color gradient | Browser chrome matches app gradient on mobile; seamless branded experience | Low | Update meta theme-color to electric-cyan, maintain existing dynamic favicon color logic |
| Hover gradient shifts | Button gradients subtly shift on hover; reinforces interactivity | Medium | Use gradient angle rotation or stop position shift, ensure 60fps performance |
| Input gradient focus ring | Focus indicators use brand gradient instead of solid color | Medium | Box-shadow with gradient requires workaround (pseudo-element or multiple shadows), accessibility concerns if contrast insufficient |

## Anti-Features

Features to explicitly NOT build or migrate.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Dynamic user color picker | Undermines brand consistency; maintenance burden for multi-color support across 40+ pages | Remove Settings > Appearance > Accent Color section entirely. Hardcode electric-cyan/bright-cobalt gradient |
| Dark mode (if removing) | If removing: reduces maintenance surface, simplifies gradient/glass implementation, forces single polished experience | Remove dark mode toggle, remove all dark:* Tailwind classes, remove useTheme colorScheme logic, simplify CSS variables |
| Gradient backgrounds on large surfaces | Causes visual fatigue, accessibility issues (text contrast), poor for data-dense interfaces | Use gradients only for accents: buttons, borders, headings. Keep cards/modals solid white/gray |
| Glass morphism on interactive elements | NN/g best practice: never apply transparency to buttons, toggles, navigation—reduces perceivability | Apply glass only to header, avoid on buttons, forms, CTAs |
| Per-component gradient customization | Creates inconsistency, maintenance nightmare, contradicts design system purpose | Define 2-3 gradient presets in Tailwind config, apply uniformly |
| Animated gradient on scroll | Performance killer on mobile; distracting in data-heavy app | Static gradients only, reserve animation for explicit hover/focus states |

## Feature Dependencies

```
Fixed Brand Palette
  ├─> Gradient Buttons (depends on defined gradient stops)
  ├─> Card Top Border (depends on gradient definition)
  ├─> Focus Ring Styling (depends on brand colors)
  └─> PWA theme-color (depends on primary brand color)

Typography Update
  └─> Gradient Text in Headings (must be applied to Montserrat headings)

Glass Morphism Header
  └─> Requires gradient background or colorful content layer underneath

Remove Dynamic Color Picker (if doing)
  ├─> Simplifies CSS variables (no runtime injection)
  ├─> Simplifies useTheme.js (remove accentColor state)
  └─> Enables deletion of ~200 lines of color generation logic

Remove Dark Mode (if doing)
  ├─> Simplifies all component styling (no dark:* variants)
  ├─> Simplifies CSS variables (~50% reduction)
  ├─> Simplifies useTheme.js (remove colorScheme state)
  └─> Enables deletion of ~150 lines of dark mode logic
```

## MVP Recommendation

Prioritize (Phase 1 - Core Brand Identity):
1. **Fixed brand color palette** - Foundation for everything else
2. **Gradient buttons (primary + secondary)** - Most visible interactive elements, high impact
3. **Card gradient top border** - Low effort, high visual impact across dashboard
4. **Typography (Montserrat headings)** - Brand alignment, low risk
5. **Remove dynamic color picker** - Simplifies implementation, reduces maintenance

Defer (Phase 2 - Polish):
- Glass morphism header (requires testing backdrop-filter performance)
- Gradient text in headings (works better after typography is consistent)
- Hover gradient shifts (nice-to-have, non-critical)
- Input gradient focus rings (accessibility validation needed)

Defer (Future - If Requested):
- Animated gradient borders (high complexity, low ROI)
- Dark mode removal decision (requires user research, impacts existing users)

## Complexity Assessment by Feature Type

| Feature Type | Typical Implementation | Risk Level |
|--------------|----------------------|------------|
| Color Palette Swap | Update CSS variables, search/replace color references | Low - mechanical change |
| Gradient Buttons | Add Tailwind gradient utilities, update button component classes | Low - additive change |
| Glass Morphism | backdrop-filter CSS, test browser support (97% as of 2026), performance test on mobile | Medium - requires testing |
| Typography | Update Tailwind config, add Google Fonts, replace font-sans with font-heading selectively | Low - well-documented pattern |
| Remove Dynamic Features | Delete code, update tests, migration guide for existing users | Medium - breaking change management |
| Gradient Focus Rings | box-shadow gradients require workaround, contrast validation for WCAG | Medium - accessibility concerns |

## Migration Considerations

### Breaking Changes for Users

| Change | User Impact | Mitigation |
|--------|-------------|------------|
| Remove dynamic color picker | Users lose personalization, existing saved preferences ignored | Document in changelog, provide rationale (brand consistency) |
| Remove dark mode | Users in dark environments lose preferred mode | HIGH IMPACT - requires user research before committing. If removing, provide "coming soon" timeline for reintroduced dark mode with new design |
| Fixed gradient colors | Existing accent-* usage may have incorrect contrast ratios with new colors | Audit all text-on-accent uses, ensure WCAG AA compliance |

### Backward Compatibility Strategy

1. **CSS Variables as Abstraction Layer**: Keep `--color-accent-*` variables, update values to gradient-compatible colors. This minimizes component changes.
2. **Graceful Degradation**: Use `@supports (backdrop-filter: blur(12px))` for glass morphism, provide solid background fallback.
3. **Progressive Enhancement**: Apply gradient buttons first, then layer in hover effects, then glass morphism. Each layer independently valuable.

## Sources

### Glassmorphism & Modern UI (2026)
- [Dark Glassmorphism: The Aesthetic That Will Define UI in 2026](https://medium.com/@developer_89726/dark-glassmorphism-the-aesthetic-that-will-define-ui-in-2026-93aa4153088f)
- [12 Glassmorphism UI Features, Best Practices, and Examples](https://uxpilot.ai/blogs/glassmorphism-ui)
- [How to create a glassmorphism effect in React](https://blog.logrocket.com/how-to-create-glassmorphism-effect-react/)
- [Glassmorphism: Definition and Best Practices - NN/G](https://www.nngroup.com/articles/glassmorphism/)

### Tailwind CSS Gradient Patterns
- [Tailwind CSS Gradient | Pagedone](https://pagedone.io/docs/gradient)
- [How to create gradient borders with tailwindcss](https://dev.to/tailus/how-to-create-gradient-borders-with-tailwindcss-4gk2)
- [A guide to adding gradients with Tailwind CSS](https://blog.logrocket.com/guide-adding-gradients-tailwind-css/)
- [Create a Gradient Border With TailwindCSS and React](https://hackernoon.com/create-a-gradient-border-blog-postcard-using-tailwind-css-and-nextjs-a-how-to-guide)

### Focus Rings & Accessibility
- [Ring Color - Tailwind CSS](https://tailwindcss.com/docs/ring-color)
- [Tailwind CSS Outline vs Ring: Key Differences](https://www.codegenes.net/blog/what-s-the-difference-between-outline-and-ring-in-tailwind/)
- [Applying Global Focus Styles in Tailwind CSS](https://github.com/tailwindlabs/tailwindcss/discussions/13338)

### Design System Migration Best Practices
- [Design System Updates in Strapi 5](https://docs.strapi.io/cms/migration/v4-to-v5/breaking-changes/design-system)
- [How do you handle design system updates and changes without breaking existing components?](https://www.linkedin.com/advice/0/how-do-you-handle-design-system-updates-changes)
- [Tips and tricks for Design System migrations](https://medium.com/@nonisnilukshi/tips-and-tricks-for-design-system-migrations-5beafb8e58c5)
- [Visual Breaking Change in Design Systems](https://medium.com/eightshapes-llc/visual-breaking-change-in-design-systems-1e9109fac9c4)

### Brand Color Systems
- [UI Color Palette 2026: Best Practices](https://www.interaction-design.org/literature/article/ui-color-palette)
- [Create Consistent Color Palettes for Design Systems](https://hybridheroes.de/blog/consistent-ui-color-palettes/)
- [Creating A Design System: Building a Color Palette](https://www.uxpin.com/create-design-system-guide/build-color-palette-for-design-system)
