# Console MIME Type Errors

**Source:** User report during Phase 23 execution
**Created:** 2026-01-13
**Priority:** Medium

## Issue

Getting console errors like:
```
filter-BuO7Y3N7.js:1 Failed to load module script: Expected a JavaScript-or-Wasm module script but the server responded with a MIME type of "text/html". Strict MIME type checking is enforced for module scripts per HTML spec.
```

## Analysis

This typically occurs when:
1. A JavaScript module file can't be found (404) and the server returns an HTML error page
2. The server isn't configured to serve .js files with proper MIME type
3. Build hash changed but old references exist in HTML

## Likely Cause

After `npm run build`, the dist/assets/ files get new hashes. If the HTML page references old hashes, the server returns 404 HTML error pages instead of JS files.

## Solution

1. Clear browser cache
2. Re-run `npm run build` to regenerate manifest
3. Verify dist/.vite/manifest.json matches loaded URLs
4. Redeploy to production with fresh build

## Related

- Phase 20 (v2.5 Performance) introduced code splitting which creates these hashed chunk files
