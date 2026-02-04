# Phase 140: Frontend Messaging - Research

**Researched:** 2026-02-04
**Domain:** React UI messaging patterns, Tailwind CSS info components, accessibility
**Confidence:** HIGH

## Summary

This phase requires adding persistent informational messages to the Tasks UI to communicate that tasks are personal and only visible to the current user. The research focused on React info message patterns, Tailwind CSS styling approaches, accessibility requirements, and icon usage best practices.

**Key findings:**
- The codebase already has established patterns for info messages using Tailwind utility classes
- Lucide React provides the `Info` icon which is perfect for informational messages
- Non-dismissible persistent messages should use static HTML, not ARIA alert roles (which are for dynamic content)
- The Dutch text is already defined: "Taken zijn alleen zichtbaar voor jou" (list page) and "Deze taak is alleen zichtbaar voor jou" (modal)

**Primary recommendation:** Use inline informational boxes with `bg-blue-50` styling and the `Info` icon from Lucide React, matching existing patterns in the codebase. Place statically without ARIA alert role since content is not dynamically added.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Tailwind CSS | 3.4 | Utility-first styling | Already project standard, has all utilities needed |
| lucide-react | 0.309.0 | Icon library | Already in project, provides Info icon |
| React | 18.2.0 | UI framework | Project foundation |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| clsx | 2.1.0 | Conditional class names | Complex conditional styling |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Inline message | Toast notification | Toast would be dismissible and temporary, requirement is persistent |
| Custom component | Direct inline HTML | No benefit to abstracting - only used in 2 locations |
| AlertCircle icon | Info icon | AlertCircle implies warning/error, Info is more neutral |

**Installation:**
No new packages required - all dependencies already present.

## Architecture Patterns

### Recommended Implementation Approach

**Static inline messages** - Not reusable components
- Only 2 usage locations (list page header, modal top)
- Messages are static, not dynamic
- No complex logic or state needed
- Direct inline HTML is simpler than component abstraction

### Pattern 1: Page Header Info Message
**What:** Persistent informational banner in page header area
**When to use:** To communicate contextual information that applies to entire page
**Example:**
```jsx
// Source: Existing codebase patterns + research findings
// In TodosList.jsx, after the <h1> header:

<div className="flex items-start gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm dark:bg-blue-900/30 dark:border-blue-700">
  <Info className="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
  <p className="text-blue-700 dark:text-blue-300">
    Taken zijn alleen zichtbaar voor jou
  </p>
</div>
```

**Placement:** Within the page header section, below the title and action buttons, above the filter controls.

### Pattern 2: Modal Top Info Message
**What:** Informational note at top of modal form
**When to use:** To provide context about the form being filled out
**Example:**
```jsx
// Source: Existing modal patterns + research findings
// In GlobalTodoModal.jsx, after modal header, before form fields:

<div className="mx-4 mt-4 mb-2 flex items-start gap-2 p-2 bg-blue-50 border border-blue-200 rounded-lg text-xs dark:bg-blue-900/30 dark:border-blue-700">
  <Info className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
  <p className="text-blue-700 dark:text-blue-300">
    Deze taak is alleen zichtbaar voor jou
  </p>
</div>
```

**Placement:** Inside modal container, after the header section, before the form element starts.

### Styling Pattern (Existing Codebase)

The project already uses consistent info message styling:

```jsx
// Informational (blue) - for neutral information
bg-blue-50 border-blue-200 text-blue-700
dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-300

// Warning (amber/yellow) - for caution
bg-amber-50 border-amber-200 text-amber-700
dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-300

// Error (red) - for errors
bg-red-50 border-red-200 text-red-700
dark:bg-red-900/20 dark:border-red-800 dark:text-red-300

// Success (green) - for success
bg-green-50 border-green-200 text-green-700
dark:bg-green-900/20 dark:border-green-800 dark:text-green-300
```

**For this phase:** Use blue (informational) variant.

### Anti-Patterns to Avoid

- **Don't use ARIA alert role:** Alert role is for dynamically added content that screen readers should announce immediately. These messages are static and present on page load, so no role attribute is needed. Using alert role here would cause unnecessary screen reader announcements.

- **Don't make dismissible:** User decision explicitly requires non-dismissible messages. Adding a close button contradicts the requirement.

- **Don't use toast/notification system:** Toasts are temporary and dismissible by nature. Requirement is for persistent, always-visible messaging.

- **Don't abstract into reusable component:** Only 2 usage locations with slightly different text/sizing. Component abstraction adds complexity without benefit.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Icon library | Custom SVG icons | lucide-react (already installed) | Consistent design, optimized bundle size, maintained |
| Dark mode utilities | Manual dark mode classes | Tailwind's dark: prefix | Built-in, consistent, supports system preferences |
| Responsive layout | Custom breakpoint logic | Tailwind responsive utilities | Standardized breakpoints, tested |

**Key insight:** For this simple phase, everything needed already exists in the codebase. No custom solutions required.

## Common Pitfalls

### Pitfall 1: Using ARIA Alert Role Incorrectly
**What goes wrong:** Adding `role="alert"` to static messages causes screen readers to announce them aggressively when they shouldn't
**Why it happens:** Misunderstanding that alert role is for *dynamic* content changes, not static content
**How to avoid:** Only use `role="alert"` for content that is injected/updated after page load. Static messages need no role attribute.
**Warning signs:** Screen reader testing shows double announcements or unexpected announcements on page load

**Reference:** [MDN ARIA Alert Role](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/alert_role), [A11Y Collective Guide](https://www.a11y-collective.com/blog/aria-alert/)

### Pitfall 2: Inconsistent Dark Mode Styling
**What goes wrong:** Forgetting dark mode variants, resulting in illegible text in dark mode
**Why it happens:** Testing only in light mode during development
**How to avoid:** Always add dark: variants for bg, border, and text colors. Use the established pattern: `bg-blue-50 dark:bg-blue-900/30`
**Warning signs:** White/light colored text on white background in dark mode, or vice versa

### Pitfall 3: Icon Not Aligning Properly
**What goes wrong:** Icon shifts vertically and doesn't align with first line of text
**Why it happens:** Default flexbox alignment centers icon vertically against entire text block, not first line
**How to avoid:** Add `flex-shrink-0 mt-0.5` to icon to prevent shrinking and add slight top margin
**Warning signs:** Icon appears centered vertically in middle of multi-line text instead of aligned with first line

### Pitfall 4: Forgetting to Import Info Icon
**What goes wrong:** Build error or missing icon in UI
**Why it happens:** Copy-pasting JSX without updating imports
**How to avoid:** Add `Info` to the lucide-react import statement at top of file
**Warning signs:** ESLint error about undefined variable, or blank space where icon should be

## Code Examples

Verified patterns from codebase analysis:

### Complete TodosList.jsx Header Section
```jsx
// Source: TodosList.jsx structure + research recommendations
import { CheckSquare, Square, Clock, Plus, Info } from 'lucide-react';

// Inside component return, in header section:
<div className="space-y-6">
  {/* Header */}
  <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <h1 className="text-2xl font-bold">Taken</h1>
    <div className="flex items-center gap-2">
      <button
        onClick={() => setShowGlobalTodoModal(true)}
        className="btn-primary text-sm flex items-center gap-2"
      >
        <Plus className="w-4 h-4" />
        Taak toevoegen
      </button>
    </div>
  </div>

  {/* Info message - personal tasks */}
  <div className="flex items-start gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm dark:bg-blue-900/30 dark:border-blue-700">
    <Info className="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
    <p className="text-blue-700 dark:text-blue-300">
      Taken zijn alleen zichtbaar voor jou
    </p>
  </div>

  {/* Filter controls */}
  <div className="flex flex-wrap items-center gap-3">
    {/* existing filter buttons */}
  </div>

  {/* Rest of page... */}
</div>
```

### Complete GlobalTodoModal.jsx with Info Message
```jsx
// Source: GlobalTodoModal.jsx structure + research recommendations
import { X, User, ChevronDown, Search, Plus, Info } from 'lucide-react';

// Inside modal return:
<div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md mx-4">
    <div className="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
      <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-50">Taak toevoegen</h2>
      <button
        onClick={handleClose}
        className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        disabled={createTodo.isPending}
      >
        <X className="w-5 h-5" />
      </button>
    </div>

    {/* Info message - personal tasks */}
    <div className="mx-4 mt-4 mb-2 flex items-start gap-2 p-2 bg-blue-50 border border-blue-200 rounded-lg text-xs dark:bg-blue-900/30 dark:border-blue-700">
      <Info className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
      <p className="text-blue-700 dark:text-blue-300">
        Deze taak is alleen zichtbaar voor jou
      </p>
    </div>

    <form onSubmit={handleSubmit} className="p-4">
      {/* Existing form fields */}
    </form>
  </div>
</div>
```

### Size Variants Pattern
```jsx
// Page-level message (larger, more prominent)
<div className="flex items-start gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm dark:bg-blue-900/30 dark:border-blue-700">
  <Info className="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
  <p className="text-blue-700 dark:text-blue-300">Message text</p>
</div>

// Modal message (smaller, more compact)
<div className="flex items-start gap-2 p-2 bg-blue-50 border border-blue-200 rounded-lg text-xs dark:bg-blue-900/30 dark:border-blue-700">
  <Info className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
  <p className="text-blue-700 dark:text-blue-300">Message text</p>
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Bootstrap alert components | Tailwind utility classes | ~2020-2021 | Smaller bundle, more flexibility, no JS needed |
| Font Awesome icons | Lucide React (fork of Feather) | ~2022-2023 | Tree-shakable, only import used icons |
| Manual dark mode | Tailwind dark: prefix | Tailwind 2.0 (2020) | Automatic system preference detection |
| role="alert" everywhere | role="alert" only for dynamic | WCAG 2.1+ | Better screen reader UX |

**Deprecated/outdated:**
- Font Awesome with full icon set: Modern projects use tree-shakable icon libraries like Lucide
- Bootstrap/Material UI alert components: Utility-first CSS makes custom alerts simpler
- Overusing ARIA roles: Modern guidance emphasizes semantic HTML first, ARIA only when needed

## Open Questions

Things that couldn't be fully resolved:

1. **Should the info message link to documentation?**
   - What we know: User decided on simple text, no mention of links
   - What's unclear: Whether future documentation might be useful
   - Recommendation: Start with plain text as decided. Links can be added later if needed.

2. **Should the message appear on every page load or remember that user has seen it?**
   - What we know: User explicitly requested "always visible (not dismissible)"
   - What's unclear: N/A - decision is clear
   - Recommendation: Always show, never hide.

3. **Color choice - blue vs other options?**
   - What we know: Blue is standard for informational messages (not warnings or errors)
   - What's unclear: User has discretion over "visual treatment"
   - Recommendation: Use blue (`bg-blue-50`) matching existing codebase patterns for neutral information. Amber/yellow would imply warning, which isn't accurate.

## Sources

### Primary (HIGH confidence)
- Codebase analysis - TodosList.jsx, GlobalTodoModal.jsx, PersonEditModal.jsx - Existing patterns verified
- Tailwind config - tailwind.config.js - Color system and utilities confirmed
- Package.json - lucide-react 0.309.0 confirmed installed
- [Lucide React Documentation](https://lucide.dev/guide/packages/lucide-react) - Info icon usage
- [Lucide Icons - Info Icon](https://lucide.dev/icons/info) - Icon specification

### Secondary (MEDIUM confidence)
- [MDN ARIA Alert Role](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/alert_role) - When to use/not use alert role
- [A11Y Collective ARIA Alert Guide](https://www.a11y-collective.com/blog/aria-alert/) - Best practices for alerts
- [Flowbite Tailwind CSS Alerts](https://flowbite.com/docs/components/alerts/) - Common Tailwind patterns
- [Carbon Design System Notification Pattern](https://carbondesignsystem.com/patterns/notification-pattern/) - When to use persistent vs dismissible
- [Primer Banner Component](https://primer.style/components/banner/) - Banner design patterns

### Tertiary (LOW confidence)
- Various component library examples (Material UI, Polaris, etc.) - General patterns only

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All dependencies already in project, verified via package.json
- Architecture: HIGH - Patterns exist in codebase, verified via file analysis
- Pitfalls: HIGH - Based on accessibility documentation and common React/Tailwind mistakes
- Visual design: MEDIUM - User has discretion, recommendations based on codebase patterns

**Research date:** 2026-02-04
**Valid until:** 2026-03-04 (30 days - stable technology, unlikely to change)
