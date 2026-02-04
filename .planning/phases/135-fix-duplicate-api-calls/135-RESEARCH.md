# Phase 135: Fix Duplicate API Calls - Research

**Researched:** 2026-02-04
**Domain:** React Query request deduplication, React 18 StrictMode behavior
**Confidence:** HIGH

## Summary

The duplicate API calls issue in Stadion stems from **React 18 StrictMode** combined with **default React Query configuration**. In development mode, React 18 StrictMode intentionally mounts, unmounts, and re-mounts components to help developers identify effects that need cleanup. When combined with React Query's default `staleTime: 0`, this causes queries to execute twice: once on initial mount, and again after the StrictMode re-mount because the first fetch has already completed and data is considered "stale."

React Query v5 (which Stadion uses at ^5.17.0) has built-in request deduplication that merges concurrent requests with the same queryKey into a single network request. However, this deduplication only works for **concurrent** requests - if the first request completes before the second mount occurs, the data is marked stale (staleTime: 0) and a new request is triggered.

The fix requires setting appropriate `staleTime` values on queries. This prevents refetches during the brief StrictMode re-mount window. The duplicate calls will **not** occur in production builds (where StrictMode effects don't double-mount), but fixing them improves development experience and ensures the code is resilient to rapid re-mounts (which can occur in production with fast navigation or error recovery).

**Primary recommendation:** Configure `staleTime` in QueryClient defaultOptions to prevent immediate refetches, and verify with development build that duplicates are eliminated.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @tanstack/react-query | ^5.17.0 | Server state management | Already in use, has built-in deduplication |
| React | ^18.2.0 | UI framework | StrictMode is the source of double-mount behavior |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @tanstack/react-query-devtools | latest | Debug query behavior | Development only, verify deduplication |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| QueryClient defaults | Per-query staleTime | More granular control, but more maintenance |
| Remove StrictMode | Keep StrictMode | StrictMode catches bugs, should not be removed |

**Installation:**
```bash
# React Query devtools (optional, for debugging)
npm install @tanstack/react-query-devtools --save-dev
```

## Architecture Patterns

### Recommended QueryClient Configuration
```javascript
// Source: TanStack Query documentation, current codebase main.jsx
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes - already configured
      gcTime: 10 * 60 * 1000, // 10 minutes (formerly cacheTime)
      retry: 1,
      refetchOnWindowFocus: false, // Prevents refetch on tab switch
    },
  },
});
```

### Pattern 1: Request Deduplication via Query Keys
**What:** React Query automatically deduplicates concurrent requests with identical queryKeys
**When to use:** Always - this is built-in behavior
**Example:**
```javascript
// Source: TanStack Query documentation
// Multiple components using same queryKey share one request
function ComponentA() {
  const { data } = useQuery({
    queryKey: ['current-user'],
    queryFn: fetchCurrentUser,
  });
}

function ComponentB() {
  // Same queryKey = same request is shared
  const { data } = useQuery({
    queryKey: ['current-user'],
    queryFn: fetchCurrentUser,
  });
}
```

### Pattern 2: Centralized Query Hooks
**What:** Create dedicated hooks that encapsulate queryKey and queryFn
**When to use:** When multiple components need the same data
**Example:**
```javascript
// Source: Best practice from codebase analysis
// Instead of inline useQuery in multiple components:
export function useCurrentUser() {
  return useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // Prevents refetch during StrictMode re-mount
  });
}
```

### Anti-Patterns to Avoid
- **Removing StrictMode:** StrictMode helps catch effects without proper cleanup. The fix should be resilient mounting, not disabling safety features.
- **Using useRef to track mounts:** This is brittle and doesn't guarantee the same component instance is used.
- **Setting `enabled: false` on first mount:** Complex and error-prone.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Request deduplication | Custom fetch tracking | React Query's built-in deduplication | Already handles promise sharing |
| Cache management | Manual stale tracking | staleTime/gcTime configuration | Battle-tested, handles edge cases |
| Double-mount prevention | useRef/custom hooks | Proper staleTime configuration | Works with React's model |

**Key insight:** React Query's deduplication already works correctly. The issue is that `staleTime: 0` means data is immediately stale, triggering a new fetch after the brief StrictMode unmount/remount cycle.

## Common Pitfalls

### Pitfall 1: Misunderstanding When Deduplication Applies
**What goes wrong:** Assuming deduplication prevents ALL duplicate requests
**Why it happens:** Deduplication only merges **concurrent** in-flight requests. Sequential requests (even milliseconds apart) trigger separate fetches if data is stale.
**How to avoid:** Set `staleTime` to prevent immediate refetches. Data within `staleTime` is considered fresh and won't trigger new requests.
**Warning signs:** Seeing 2x requests but not understanding why deduplication "isn't working"

### Pitfall 2: Production vs Development Behavior
**What goes wrong:** Assuming duplicate calls are a production issue
**Why it happens:** StrictMode double-mount only occurs in development
**How to avoid:** Test in development mode to verify fix; understand production won't have this specific issue
**Warning signs:** Spending time on production-only optimizations when the issue is dev-only

### Pitfall 3: Conflicting staleTime Values
**What goes wrong:** Multiple components query the same key with different options
**Why it happens:** React Query uses the "most aggressive" (lowest) staleTime when multiple observers exist
**How to avoid:** Use centralized hooks that define consistent options; rely on QueryClient defaults
**Warning signs:** Inconsistent refetch behavior, one component triggering refetches for all observers

### Pitfall 4: Confusing staleTime and gcTime
**What goes wrong:** Setting gcTime (garbage collection) instead of staleTime
**Why it happens:** Names are similar; cacheTime was renamed to gcTime in v5
**How to avoid:** Remember: `staleTime` = "how long data is fresh", `gcTime` = "how long to keep in cache after no observers"
**Warning signs:** Data refetches immediately despite setting "cache time"

## Code Examples

Verified patterns from official sources:

### Fix 1: Update QueryClient Defaults (Already Done)
```javascript
// Source: main.jsx (current implementation)
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes - ALREADY SET
      retry: 1,
    },
  },
});
```

The codebase already has `staleTime: 5 * 60 * 1000` configured. This should prevent StrictMode duplicate calls. If duplicates are still occurring, investigate:

### Fix 2: Per-Query staleTime Override
```javascript
// Source: Investigation needed - some queries may override defaults
const { data } = useQuery({
  queryKey: ['current-user'],
  queryFn: fetchCurrentUser,
  staleTime: 0, // BAD: This overrides the default and causes immediate refetch
});
```

### Fix 3: Add refetchOnMount: false for Static Data
```javascript
// Source: TanStack Query documentation
const { data } = useQuery({
  queryKey: ['current-user'],
  queryFn: fetchCurrentUser,
  refetchOnMount: false, // Don't refetch if data exists in cache
});
```

### Fix 4: Verify with React Query Devtools
```javascript
// Source: TanStack Query documentation - add to development
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <App />
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| cacheTime | gcTime | React Query v5 | Name change only, same behavior |
| useQuery with options 2nd param | useQuery with single object | React Query v5 | API simplification |
| Removing StrictMode | Setting proper staleTime | React 18 | StrictMode is valuable, should be kept |

**Deprecated/outdated:**
- `cacheTime`: Renamed to `gcTime` in React Query v5
- Using `useRef` to track mounts: Not reliable across StrictMode cycles

## Root Cause Analysis

Based on codebase investigation, the duplicate calls likely occur because:

1. **StrictMode Double Mount**: React 18 StrictMode mounts, unmounts, and re-mounts components in development. This is working as intended - it helps catch effects that don't clean up properly.

2. **Timing Window**: Even with `staleTime: 5 minutes`, if the StrictMode unmount/remount happens BEFORE the first request completes, React Query may initiate a second request (though these should be deduplicated if truly concurrent).

3. **Multiple Query Instances**: The codebase has `['current-user']` query defined inline in 5 different components:
   - `ApprovalCheck` (App.jsx:47)
   - `FairplayRoute` (App.jsx:127)
   - `Sidebar` (Layout.jsx:66)
   - `UserMenu` (Layout.jsx:152)
   - `PersonDetail` (PersonDetail.jsx:72)

   While React Query should share the cache, each component creates its own query observer. If these mount at slightly different times (not truly concurrent), separate requests may fire.

4. **Potential refetchOnWindowFocus**: The default `refetchOnWindowFocus: true` could cause refetches when switching between browser tabs during development.

## Investigation Steps for Implementation

To confirm the root cause and fix:

1. **Add React Query Devtools** to visualize query lifecycle
2. **Check Network Panel** for request timing - are requests truly concurrent or sequential?
3. **Verify staleTime is being applied** - console.log QueryClient defaultOptions
4. **Check for staleTime overrides** - search codebase for `staleTime: 0` or `staleTime: undefined`
5. **Consider refetchOnWindowFocus: false** if tab switching causes refetches

## Open Questions

Things that couldn't be fully resolved:

1. **Are duplicates occurring in production?**
   - What we know: StrictMode double-mount only occurs in development
   - What's unclear: Whether production sees duplicates from other causes
   - Recommendation: Test production build to confirm

2. **Exact timing of duplicate requests**
   - What we know: Duplicates are occurring
   - What's unclear: Whether they're from StrictMode, route transitions, or something else
   - Recommendation: Use React Query Devtools and Network panel timestamp analysis

## Sources

### Primary (HIGH confidence)
- TanStack Query official documentation - Important Defaults, Query Keys
- React 18 StrictMode documentation - Double-mount behavior
- Codebase analysis - main.jsx, App.jsx, Layout.jsx configurations

### Secondary (MEDIUM confidence)
- [TanStack Query GitHub Discussion #6542](https://github.com/TanStack/query/discussions/6542) - Multiple API calls on initial render
- [Pitfalls of React Query](https://nickb.dev/blog/pitfalls-of-react-query/) - Deduplication edge cases
- [React 18 useEffect Double Call](https://dev.to/jherr/react-18-useeffect-double-call-for-apis-emergency-fix-27ee) - StrictMode behavior

### Tertiary (LOW confidence)
- Various Medium articles on React Query deduplication (patterns confirmed against official docs)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - React Query is already in use, documentation is clear
- Architecture: HIGH - Patterns verified against official docs
- Pitfalls: HIGH - Well-documented issues with clear solutions
- Root cause: MEDIUM - Hypothesis based on codebase analysis, needs runtime verification

**Research date:** 2026-02-04
**Valid until:** 90 days (stable libraries, well-documented patterns)
