---
created: 2026-01-15T15:30
title: Fix React DOM removeChild errors for real
area: ui
files:
  - src/components/* (likely culprit)
  - vendor-CYdfjHz2.js:40 (minified React DOM error location)
---

## Problem

Recurring `NotFoundError: Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.` errors in production.

This was previously documented as "benign" in Phase 56, but the error keeps happening and indicates a real synchronization issue between React's virtual DOM and the actual DOM.

**Error stack (minified):**
```
at Jd (vendor-CYdfjHz2.js:40:25982)
at Nt (vendor-CYdfjHz2.js:40:25688)
at Jd (vendor-CYdfjHz2.js:40:26437)
...
```

The stack shows React's reconciliation/unmount logic failing when trying to remove DOM nodes that no longer exist in their expected parent.

**Likely causes:**
1. Direct DOM manipulation (e.g., via refs) conflicting with React's render cycle
2. Browser extensions modifying the DOM
3. Third-party scripts injecting/removing elements
4. Race conditions in component unmounting
5. Portals or modals being removed while React still thinks they're mounted

## Solution

1. Add source maps to production build or debug locally to find exact component
2. Look for patterns:
   - Components using `dangerouslySetInnerHTML`
   - Direct DOM manipulation via refs
   - Rapid mount/unmount cycles (modals, tooltips, dropdowns)
   - Components conditionally rendering with external state
3. Consider wrapping problematic areas in error boundaries
4. Check for StrictMode double-render issues in development
