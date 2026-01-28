# Phase 109: Mobile UX - Research

**Researched:** 2026-01-28
**Domain:** Mobile PWA gestures - pull-to-refresh and iOS overscroll prevention
**Confidence:** HIGH

## Summary

This phase focuses on two complementary mobile UX improvements for the Stadion PWA: implementing pull-to-refresh gestures on list and detail views, and preventing iOS-specific overscroll bounce behavior in standalone mode that can trigger accidental page reloads.

The research confirms a straightforward approach using `react-simple-pull-to-refresh` library combined with CSS `overscroll-behavior` for native-feeling interactions. The library has zero dependencies, 36K+ weekly downloads, and specific iOS Safari fixes (v1.2.4+). Since Safari 16+ fully supports `overscroll-behavior`, a CSS-only solution works for the standalone PWA overscroll prevention.

For cache invalidation on refresh, Stadion's existing TanStack Query patterns using `invalidateQueries` integrate naturally with the pull-to-refresh callback - simply calling `queryClient.invalidateQueries({ queryKey: [...] })` triggers background refetches of active queries.

**Primary recommendation:** Use `react-simple-pull-to-refresh` (v1.3.3+) for pull-to-refresh with the existing Tailwind spinner pattern, combined with `overscroll-behavior: none` on the main content container to prevent iOS bounce.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| react-simple-pull-to-refresh | ^1.3.3 | Pull-to-refresh gesture | Zero dependencies, iOS Safari fix, React 18 support, 36K+ downloads/week |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| CSS overscroll-behavior | Native | Prevent iOS bounce | Apply to scrollable containers in standalone mode |
| @tanstack/react-query (existing) | ^5.17.0 | Cache invalidation | Already in use - `invalidateQueries` for refresh callback |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| react-simple-pull-to-refresh | Custom hook implementation | More control but ~100 lines of touch handling code, edge cases |
| react-simple-pull-to-refresh | use-pull-to-refresh | Hook-based but requires more manual DOM manipulation |
| react-simple-pull-to-refresh | react-pull-to-refresh | Older library, window.undefined issues with SSR |

**Installation:**
```bash
npm install react-simple-pull-to-refresh
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── components/
│   └── PullToRefreshWrapper.jsx  # Reusable PTR component with Stadion styling
├── hooks/
│   └── (existing hooks unchanged)
└── pages/
    ├── People/PeopleList.jsx     # Wrap content with PullToRefreshWrapper
    ├── Teams/TeamsList.jsx
    ├── Dates/DatesList.jsx
    └── etc.
```

### Pattern 1: PullToRefreshWrapper Component
**What:** A reusable wrapper component that provides pull-to-refresh with Stadion's visual style
**When to use:** List views (People, Teams, Dates, Commissies) and detail views (PersonDetail, TeamDetail)
**Example:**
```jsx
// Source: react-simple-pull-to-refresh docs + Stadion patterns
import PullToRefresh from 'react-simple-pull-to-refresh';

function PullToRefreshWrapper({ onRefresh, children }) {
  const refreshingContent = (
    <div className="flex justify-center py-4">
      <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-accent-600 dark:border-accent-400" />
    </div>
  );

  const pullingContent = (
    <div className="flex justify-center py-4">
      <div className="h-6 w-6 border-2 border-gray-300 dark:border-gray-600 rounded-full" />
    </div>
  );

  return (
    <PullToRefresh
      onRefresh={onRefresh}
      pullDownThreshold={67}
      maxPullDownDistance={95}
      resistance={1}
      refreshingContent={refreshingContent}
      pullingContent={pullingContent}
    >
      {children}
    </PullToRefresh>
  );
}
```

### Pattern 2: TanStack Query Integration
**What:** Using invalidateQueries in the refresh callback to trigger data refetch
**When to use:** In all views that use pull-to-refresh
**Example:**
```jsx
// Source: TanStack Query docs + Stadion usePeople.js pattern
import { useQueryClient } from '@tanstack/react-query';
import { peopleKeys } from '@/hooks/usePeople';

function PeopleList() {
  const queryClient = useQueryClient();

  const handleRefresh = async () => {
    // invalidateQueries marks as stale and refetches active queries
    await queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
  };

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      {/* list content */}
    </PullToRefreshWrapper>
  );
}
```

### Pattern 3: Overscroll Prevention via CSS
**What:** Use CSS `overscroll-behavior: none` to prevent iOS bounce
**When to use:** On the main scrollable container in Layout
**Example:**
```css
/* Source: MDN overscroll-behavior docs */
/* Apply to the main scrollable area */
.main-scroll-container {
  overscroll-behavior-y: none;
  -webkit-overflow-scrolling: touch;
}
```

Or via Tailwind plugin/utility (if not available, add custom CSS):
```jsx
<main className="flex-1 overflow-y-auto [overscroll-behavior-y:none]">
  {children}
</main>
```

### Pattern 4: View-Specific Refresh Keys
**What:** Map each view to its appropriate query keys for invalidation
**When to use:** Each page that implements pull-to-refresh
**Example:**
```jsx
// View-specific refresh handlers
const refreshHandlers = {
  PeopleList: () => queryClient.invalidateQueries({ queryKey: ['people', 'list'] }),
  PersonDetail: (id) => queryClient.invalidateQueries({ queryKey: ['people', 'detail', id] }),
  TeamsList: () => queryClient.invalidateQueries({ queryKey: ['teams'] }),
  TeamDetail: (id) => queryClient.invalidateQueries({ queryKey: ['teams', id] }),
  DatesList: () => queryClient.invalidateQueries({ queryKey: ['important-dates'] }),
  Dashboard: () => queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
};
```

### Anti-Patterns to Avoid
- **Enabling pull-to-refresh in modals:** Can interfere with form scrolling and input focus
- **Calling refetch() instead of invalidateQueries():** Less efficient, doesn't benefit from TanStack Query's smart background refetching
- **Using overscroll-behavior: contain instead of none:** `contain` still allows the rubberbanding visual effect, only `none` fully prevents it
- **Adding pull-to-refresh to Settings:** Static content doesn't need refresh capability

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Pull gesture detection | Custom touchstart/move/end handlers | react-simple-pull-to-refresh | Touch event edge cases on iOS, inertia handling, dampening |
| Pull resistance curve | Manual exponential function | Library's `resistance` prop | Already tuned for native feel |
| iOS overscroll prevention | Complex JS scroll locking | CSS `overscroll-behavior: none` | Native browser support since Safari 16 |
| Refresh spinner animation | Custom CSS keyframes | Stadion's existing spinner pattern | Consistency with LoadingSpinner usage |

**Key insight:** Touch gesture handling has many edge cases (touchcancel, multi-touch, scroll vs pull detection) that are better handled by a battle-tested library than custom code.

## Common Pitfalls

### Pitfall 1: Pull-to-Refresh Triggers While Scrolled Down
**What goes wrong:** User tries to scroll up but triggers refresh instead
**Why it happens:** Library doesn't automatically detect scroll position
**How to avoid:** react-simple-pull-to-refresh handles this by design - only triggers at scroll position 0
**Warning signs:** Accidental refreshes during normal scrolling

### Pitfall 2: iOS Safari Bounce Still Occurs
**What goes wrong:** Despite `overscroll-behavior: none`, iOS still bounces in standalone mode
**Why it happens:** CSS property applied to wrong element (body instead of scroll container) or Safari version < 16
**How to avoid:** Apply to the actual scrolling element (`<main>`), not just `<body>`
**Warning signs:** Bounce effect visible when pulling down at top of page

### Pitfall 3: Refresh Promise Never Resolves
**What goes wrong:** Spinner keeps spinning forever
**Why it happens:** `onRefresh` callback doesn't return a Promise or the Promise never resolves
**How to avoid:** Always return the Promise from `invalidateQueries()` which resolves when refetch completes
**Warning signs:** Loading indicator stuck after network request completes

### Pitfall 4: Double Refresh on iOS
**What goes wrong:** Both custom PTR and native iOS PTR trigger
**Why it happens:** `overscroll-behavior` not applied, allowing native gesture through
**How to avoid:** Ensure `overscroll-behavior: none` is applied to scroll container
**Warning signs:** Two loading states appear, or page reloads entirely

### Pitfall 5: Pull-to-Refresh Interferes with Modal Scrolling
**What goes wrong:** Modals become unusable because PTR triggers inside them
**Why it happens:** PTR wrapper is parent of modal content
**How to avoid:** Don't wrap modal content with PullToRefresh; modals render outside the main content flow via portals
**Warning signs:** Cannot scroll modal content without triggering refresh

## Code Examples

### Complete PullToRefreshWrapper Component
```jsx
// src/components/PullToRefreshWrapper.jsx
// Source: react-simple-pull-to-refresh API + Stadion design patterns
import PullToRefresh from 'react-simple-pull-to-refresh';

/**
 * Wrapper component providing pull-to-refresh with Stadion styling.
 *
 * @param {Function} onRefresh - Async function called on refresh, must return Promise
 * @param {boolean} isPullable - Whether pull-to-refresh is enabled (default: true)
 * @param {React.ReactNode} children - Content to wrap
 */
export default function PullToRefreshWrapper({
  onRefresh,
  isPullable = true,
  children
}) {
  // Stadion-style spinner matching existing loading patterns
  const refreshingContent = (
    <div className="flex justify-center py-4">
      <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-accent-600 dark:border-accent-400" />
    </div>
  );

  // Subtle indicator while pulling
  const pullingContent = (
    <div className="flex justify-center py-4 text-gray-400 dark:text-gray-500">
      <svg
        className="w-6 h-6 transition-transform duration-200"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M19 14l-7 7m0 0l-7-7m7 7V3"
        />
      </svg>
    </div>
  );

  return (
    <PullToRefresh
      onRefresh={onRefresh}
      isPullable={isPullable}
      pullDownThreshold={67}
      maxPullDownDistance={95}
      resistance={1}
      refreshingContent={refreshingContent}
      pullingContent={pullingContent}
      className="min-h-full"
    >
      {children}
    </PullToRefresh>
  );
}
```

### Integration in PeopleList
```jsx
// src/pages/People/PeopleList.jsx (partial)
// Source: Existing Stadion pattern + TanStack Query invalidation
import { useQueryClient } from '@tanstack/react-query';
import { peopleKeys } from '@/hooks/usePeople';
import PullToRefreshWrapper from '@/components/PullToRefreshWrapper';

export default function PeopleList() {
  const queryClient = useQueryClient();
  const { data: people, isLoading, error } = usePeople();

  const handleRefresh = async () => {
    // Invalidate people list - triggers background refetch of active query
    await queryClient.invalidateQueries({ queryKey: peopleKeys.lists() });
  };

  return (
    <PullToRefreshWrapper onRefresh={handleRefresh}>
      <div className="space-y-4">
        {/* existing list content */}
      </div>
    </PullToRefreshWrapper>
  );
}
```

### CSS for Overscroll Prevention
```css
/* src/index.css - add to existing base styles */
/* Source: MDN overscroll-behavior docs */

/* Prevent iOS standalone mode bounce/reload on overscroll */
@supports (overscroll-behavior-y: none) {
  .app-root {
    overscroll-behavior-y: none;
  }

  /* Also apply to main content area for nested scroll containers */
  main {
    overscroll-behavior-y: none;
  }
}
```

### Alternative: Tailwind Arbitrary Property
```jsx
// In Layout.jsx - alternative inline approach
<main className="flex-1 overflow-y-auto p-4 lg:p-6 [overscroll-behavior-y:none]">
  {children}
</main>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| JavaScript scroll locking (iNoBounce) | CSS `overscroll-behavior` | Safari 16, Sept 2022 | No JS needed, better performance |
| Custom touch handlers | Specialized libraries | 2020+ | Fewer edge cases, maintained |
| Full page reload on mobile | TanStack Query invalidation | With React Query adoption | Seamless background refresh |

**Deprecated/outdated:**
- **iNoBounce library:** No longer needed since Safari 16 supports `overscroll-behavior` natively
- **`-webkit-overflow-scrolling: touch`:** Still useful for smooth scrolling but doesn't prevent bounce
- **Fixed body position tricks:** Complex JS workarounds replaced by CSS property

## Open Questions

1. **Pull-to-refresh on Dashboard?**
   - What we know: Dashboard aggregates multiple queries (stats, reminders, todos)
   - What's unclear: Should PTR refresh all dashboard data or specific sections?
   - Recommendation: Enable with `queryClient.invalidateQueries({ queryKey: ['dashboard'] })` which refreshes all dashboard-related queries

2. **Commissies and other list views**
   - What we know: Phase context mentions People, Teams, Dates specifically
   - What's unclear: Should CommissiesList also have PTR?
   - Recommendation: Yes, include all list views for consistency (CommissiesList, TodosList, FeedbackList)

## Sources

### Primary (HIGH confidence)
- [MDN overscroll-behavior](https://developer.mozilla.org/en-US/docs/Web/CSS/overscroll-behavior) - CSS property documentation
- [Can I Use overscroll-behavior](https://caniuse.com/css-overscroll-behavior) - Safari 16+ full support confirmed
- [react-simple-pull-to-refresh GitHub](https://github.com/thmsgbrt/react-simple-pull-to-refresh) - Library API, React 18 support, iOS fix

### Secondary (MEDIUM confidence)
- [TanStack Query Invalidation Docs](https://tanstack.com/query/v5/docs/react/guides/query-invalidation) - invalidateQueries pattern
- [TanStack Query Discussion #2468](https://github.com/TanStack/query/discussions/2468) - refetch vs invalidateQueries comparison

### Tertiary (LOW confidence)
- [LogRocket Pull-to-Refresh Tutorial](https://blog.logrocket.com/implementing-pull-to-refresh-react-tailwind-css/) - Implementation patterns
- [Strictmode Custom Hook Article](https://www.strictmode.io/articles/react-pull-to-refresh) - Alternative hook-based approach
- [Chrome Developers Blog](https://developer.chrome.com/blog/overscroll-behavior) - overscroll-behavior use cases

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - react-simple-pull-to-refresh is well-maintained, React 18 compatible, has iOS fixes
- Architecture: HIGH - Patterns follow existing Stadion conventions and TanStack Query best practices
- Pitfalls: HIGH - iOS overscroll-behavior support verified via Can I Use, library issues documented on GitHub

**Research date:** 2026-01-28
**Valid until:** 60 days (stable domain, CSS property fully supported, library actively maintained)
