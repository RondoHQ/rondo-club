---
created: 2026-01-15T13:21
title: Fix React/DOM Node synchronization errors
area: ui
files:
  - src/
---

## Problem

Regularly encountering two related DOM errors:

1. `Uncaught NotFoundError: Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.`

2. `NotFoundError: Failed to execute 'insertBefore' on 'Node': The node before which the new node is to be inserted is not a child of this node.`

Both are React/DOM synchronization issues that typically occur when:
1. React tries to manipulate a DOM node that was already removed or moved (e.g., by a third-party library or direct DOM manipulation)
2. A parent component re-renders while a child is being mounted/unmounted
3. React Portal interactions where the portal container is modified before the portal content
4. Concurrent rendering issues with React 18's StrictMode

The errors appear intermittently, suggesting a race condition or timing issue in component lifecycle.

## Solution

TBD - Requires investigation to identify the specific component/interaction causing the issue. Possible approaches:
- Check for direct DOM manipulation that conflicts with React
- Look for portal usage patterns
- Review component mounting/unmounting logic
- Check for state updates on unmounted components
- Investigate third-party library interactions
