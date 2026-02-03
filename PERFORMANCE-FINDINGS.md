# Performance Analysis: stadion.svawc.nl

**Date:** 2026-02-03
**Page tested:** Dashboard (authenticated)
**Tool:** Chrome DevTools Performance Trace

## Core Web Vitals

| Metric | Value | Rating |
|--------|-------|--------|
| **LCP** | 2,118 ms | Needs improvement (target: < 2,500ms, ideal: < 1,800ms) |
| **CLS** | 0.00 | Excellent |
| **TTFB** | 292 ms | Good |

## Summary

The main performance bottleneck is **render delay** (1,825ms = 86% of LCP time). This is caused by slow API responses blocking the React app from rendering content.

## Critical API Response Times

These endpoints are in the critical rendering path:

| API Endpoint | Response Time | Notes |
|--------------|---------------|-------|
| `/wp/v2/people?per_page=100&_embed=true` | **4,742 ms** | Main bottleneck |
| `/stadion/v1/dashboard` | 2,061 ms | Dashboard data |
| `/stadion/v1/people/filtered?...vog_missing=1` | 1,449 ms | VOG filter query |
| `/stadion/v1/calendar/today-meetings` | 1,272 ms | Calendar widget |
| `/stadion/v1/todos?status=open` | 1,111 ms | Todos widget |
| `/stadion/v1/todos?status=awaiting` | 1,118 ms | Todos widget |
| `/stadion/v1/user/me` | 851 ms | User context |

## Issues Identified

### 1. Slow People API (Critical)

The `/wp/v2/people?per_page=100&_embed=true` endpoint takes nearly 5 seconds. This appears to be called for search/autocomplete functionality even on dashboard load.

**Recommendations:**
- Don't load this on dashboard if not immediately needed
- Reduce `per_page` significantly (10-20 for autocomplete)
- Remove `_embed=true` if embedded data isn't used
- Add server-side caching (transients or object cache)
- Consider a lighter endpoint for search suggestions

### 2. Duplicate API Calls

The following endpoints are called multiple times during dashboard load:

- `/stadion/v1/user/me` - called 2x
- `/stadion/v1/dashboard` - called 2x
- `/stadion/v1/todos?status=open` - called 2x
- `/stadion/v1/todos?status=awaiting` - called 2x
- `/stadion/v1/calendar/today-meetings` - called 2x
- `/stadion/v1/people/filtered` - called 2x

**Likely cause:** React components remounting or missing request deduplication.

**Recommendations:**
- Check for `useEffect` dependencies causing refetches
- Implement request deduplication (React Query, SWR, or custom)
- Check if `<StrictMode>` in development is causing double renders (not an issue in production, but verify)

### 3. Deep JavaScript Module Chain

The critical path has 5 levels of dynamic imports before API calls start:

```
main.js (295ms)
  → utils.js (323ms)
    → Dashboard.js (572ms)
      → main.js (580ms)
        → API calls begin
```

**Recommendations:**
- Bundle dashboard-critical code together
- Preload the Dashboard chunk: `<link rel="modulepreload" href="Dashboard-xxx.js">`
- Consider code-splitting at route level only, not component level for critical path

### 4. Dashboard Endpoint Optimization

The `/stadion/v1/dashboard` endpoint takes 2+ seconds.

**Recommendations:**
- Profile the PHP code for this endpoint
- Check for N+1 query issues
- Add object caching for expensive queries
- Consider caching the entire response with a short TTL (30-60 seconds)

## What's Working Well

- **TTFB (292ms):** Server initial response is fast
- **CLS (0.00):** No layout shifts - good job on reserving space
- **Third parties:** Minimal (only Gravatar, 3.2KB)
- **Static asset caching:** Proper 1-year cache headers
- **Compression:** Brotli compression enabled via Cloudflare
- **Protocol:** HTTP/3 in use

## Suggested Priority

1. **High:** Investigate why `/wp/v2/people` is called on dashboard load - likely unnecessary
2. **High:** Fix duplicate API calls (easy win, halves the requests)
3. **Medium:** Optimize `/stadion/v1/dashboard` endpoint response time
4. **Medium:** Add server-side caching for expensive queries
5. **Low:** Optimize JS bundle chain with modulepreload

## Testing Methodology

Performance trace was captured with:
- Chrome DevTools Performance panel
- No CPU throttling
- No network throttling
- Authenticated session
- Page reload with cache enabled

To reproduce: Open DevTools → Performance tab → Click reload button → Wait for trace to complete.
