---
created: 2026-01-15T13:21
title: Fix removeChild Node NotFoundError
area: ui
files:
  - src/
---

## Problem

Regularly encountering the error:
```
Uncaught NotFoundError: Failed to execute 'removeChild' on 'Node': The node to be removed is not a child of this node.
```

This is a common React/DOM synchronization issue that typically occurs when:
1. React tries to unmount a component whose DOM node was already removed (e.g., by a third-party library or direct DOM manipulation)
2. A parent component re-renders while a child is being unmounted
3. React Portal interactions where the portal container is removed before the portal content
4. Concurrent rendering issues with React 18's StrictMode

The error appears intermittently, suggesting a race condition or timing issue in component lifecycle.

## Solution

TBD - Requires investigation to identify the specific component/interaction causing the issue. Possible approaches:
- Check for direct DOM manipulation that conflicts with React
- Look for portal usage patterns
- Review component unmounting logic
- Check for state updates on unmounted components
- Investigate third-party library interactions
