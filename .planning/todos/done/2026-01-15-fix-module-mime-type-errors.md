---
created: 2026-01-15T13:23
title: Fix recurring module MIME type errors
area: ui
files:
  - dist/
  - functions.php
  - vite.config.js
---

## Problem

Regularly encountering the error:
```
Failed to load module script: Expected a JavaScript-or-Wasm module script but the server responded with a MIME type of "text/html". Strict MIME type checking is enforced for module scripts per HTML spec.
```

This was previously thought resolved via production deploy (see `.planning/todos/done/console-mime-type-errors.md`) but continues to occur.

The error happens when:
1. Browser requests a JS module file (e.g., `filter-BuO7Y3N7.js`)
2. Server returns HTML instead (typically a 404 error page or WordPress HTML)
3. Browser rejects HTML as invalid module script

Likely causes:
- Stale build artifacts with outdated chunk hashes
- Race condition between deploy and cache clear
- Code splitting chunks not synced with manifest.json
- WordPress rewrite rules catching JS requests and returning HTML

## Solution

TBD - Requires deeper investigation:
1. Check if issue occurs in dev mode or only production
2. Verify manifest.json is being read correctly by functions.php
3. Check server rewrite rules for .js files
4. Consider adding cache-busting query params
5. May need to configure WordPress to explicitly serve .js with correct MIME type
