# Phase 137: Query Deduplication - Research

**Researched:** 2026-02-04
**Domain:** React Query (TanStack Query) optimization
**Confidence:** HIGH

## Summary

Query deduplication in TanStack Query v5 is a built-in feature that automatically handles concurrent requests with the same queryKey. The current issue in Stadion involves three components (ApprovalCheck, FairplayRoute, Sidebar) independently calling the same `current-user` query, and VOG count being fetched on every navigation.

The solution requires extracting shared queries into custom hooks with the `queryOptions` helper pattern, establishing proper staleTime for the VOG count badge, and ensuring consistent queryKey usage across components. React Query's automatic deduplication means no special code is needed beyond using the same queryKey - multiple useQuery calls will share the same network request and cached data.

**Primary recommendation:** Extract current-user into a `useCurrentUser()` custom hook using queryOptions pattern, and configure VOG count with appropriate staleTime (5 minutes) to cache between navigations.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @tanstack/react-query | v5.x | Server state management | Industry standard for React data fetching with built-in deduplication, caching, and background refetching |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @tanstack/react-query-devtools | v5.x | Development debugging | View query cache, inspect stale/fresh status, debug deduplication |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| TanStack Query | SWR | Similar features but less TypeScript support and smaller ecosystem |
| TanStack Query | RTK Query | More opinionated, requires Redux setup |
| Custom hooks | react-query-auth | Pre-built auth patterns but adds dependency for simple use case |

**Installation:**
Already installed in Stadion (see package.json)

## Architecture Patterns

### Recommended Project Structure
```
src/
├── hooks/           # Custom hooks including query hooks
│   ├── useAuth.js   # Update: should call useCurrentUser
│   └── queries/     # NEW: Centralized query definitions
│       ├── user.js  # userQueries with queryOptions
│       └── vog.js   # VOG-specific queries
```

### Pattern 1: queryOptions Helper for Shared Queries
**What:** Define query configuration (queryKey + queryFn) in one place using `queryOptions` helper
**When to use:** When multiple components need the same query
**Example:**
```typescript
// src/hooks/queries/user.js
import { queryOptions } from '@tanstack/react-query'
import { prmApi } from '@/api/client'

export const userQueries = {
  currentUser: () => queryOptions({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - user data rarely changes
    retry: false,
  }),
}

// Usage in components
import { useQuery } from '@tanstack/react-query'
import { userQueries } from '@/hooks/queries/user'

function MyComponent() {
  const { data: user } = useQuery(userQueries.currentUser())
}
```
**Source:** [TanStack Query queryOptions docs](https://tanstack.com/query/latest/docs/framework/react/guides/query-options)

### Pattern 2: Custom Hook Wrapper for Common Queries
**What:** Wrap useQuery in a custom hook with the query configuration
**When to use:** When you want to encapsulate query logic and provide a clean API
**Example:**
```javascript
// src/hooks/useCurrentUser.js
import { useQuery } from '@tanstack/react-query'
import { userQueries } from '@/hooks/queries/user'

export function useCurrentUser() {
  return useQuery(userQueries.currentUser())
}

// All components use the same hook
// ApprovalCheck, FairplayRoute, Sidebar all call:
const { data: user, isLoading } = useCurrentUser()
```
**Source:** [React Query custom hook patterns](https://backbencher.dev/react-query-share-query-across-components-using-hooks)

### Pattern 3: Badge Count with Cached staleTime
**What:** Configure queries that power badge counts with appropriate staleTime to avoid refetching on every navigation
**When to use:** Counters, badges, notification counts that don't need real-time updates
**Example:**
```javascript
// src/hooks/useVOGCount.js
export function useVOGCount() {
  const { data, isLoading } = useFilteredPeople(
    {
      page: 1,
      perPage: 1, // Only need count
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
    },
    {
      staleTime: 5 * 60 * 1000, // 5 minutes - badge doesn't need immediate updates
    }
  );

  return {
    count: data?.total || 0,
    isLoading,
  };
}
```

### Anti-Patterns to Avoid
- **Different queryKeys for same data:** Using inconsistent queryKey structure across components prevents deduplication
- **Passing arguments to refetch():** refetch() doesn't accept arguments - use queryKey dependencies instead
- **Not including parameters in queryKey:** Dynamic filters/params must be in queryKey for proper cache separation
- **Using React Query for local state:** Don't use for form inputs, UI toggles - stick to server state only

**Source:** [Pitfalls of React Query](https://nickb.dev/blog/pitfalls-of-react-query/)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Request deduplication | Manual promise tracking/caching | TanStack Query's built-in deduplication | Query automatically shares promises when same queryKey is used in parallel |
| Cache invalidation | Manual cache clearing logic | queryClient.invalidateQueries() | Handles partial matching, refetch coordination |
| Background refetching | useEffect with intervals | staleTime + refetchInterval options | Handles tab visibility, online/offline, component lifecycle |
| Loading states | Multiple useState flags | useQuery's isLoading, isFetching, isRefetching | Built-in granular loading states |
| Auth user caching | React Context + localStorage | TanStack Query with staleTime: Infinity | Proper cache lifecycle, reactivity, no manual sync |

**Key insight:** TanStack Query's queryKey-based caching and automatic deduplication handles 90% of common data fetching patterns without custom code. The library is designed to work declaratively - let the queryKey drive behavior rather than imperative refetch calls.

**Source:** [React Query as State Manager](https://tkdodo.eu/blog/react-query-as-a-state-manager)

## Common Pitfalls

### Pitfall 1: Mounting Order Affects Deduplication Options
**What goes wrong:** When multiple components use the same queryKey with different options (e.g., different staleTime), React Query must merge them. For deduplication, staleTime always takes the **most pessimistic** (lowest) value.

**Why it happens:** Children mount before parents in React, so the child's query options can be overridden by parent's options.

**How to avoid:**
- Define query options centrally using `queryOptions` helper
- Don't override staleTime at component level - keep it in the query definition
- Use React Query DevTools to verify which options are active

**Warning signs:**
- Seeing refetches when you expect cached data
- Different components showing different loading states
- DevTools showing unexpected staleTime values

**Source:** [Understanding query deduplication option merging](https://github.com/TanStack/query/discussions/7056)

### Pitfall 2: staleTime vs gcTime Misconfiguration
**What goes wrong:** Setting gcTime (garbage collection time) lower than staleTime causes "fresh" data to be removed from cache prematurely, triggering unnecessary network requests.

**Why it happens:** Developers confuse the two options - gcTime controls cache retention for **inactive** queries, staleTime controls **freshness** for active queries.

**How to avoid:**
- **Best practice:** gcTime should always be >= staleTime
- Default gcTime is 5 minutes, so setting staleTime higher requires increasing gcTime too
- Example: If staleTime is 10 minutes, set gcTime to 15 minutes minimum

**Warning signs:**
- Network requests happening despite data being "fresh"
- Query returning undefined then immediately loading
- Cache "disappearing" before staleTime expires

**Source:** [staleTime vs cacheTime (gcTime)](https://dev.to/delisrey/react-query-staletime-vs-cachetime-hml)

### Pitfall 3: Default staleTime of 0 Causes Excessive Refetching
**What goes wrong:** With default staleTime: 0, every component mount triggers a background refetch, even if the previous component just unmounted. This causes badge counts and navigation queries to refetch constantly.

**Why it happens:** TanStack Query defaults to "always stale" for safety - ensuring fresh data. This is overly aggressive for most use cases.

**How to avoid:**
- Set global staleTime in QueryClient config (already done in Stadion: 5 minutes)
- Override per-query for specific needs
- For auth user data: staleTime can be very high (5-15 minutes) or even Infinity
- For badge counts: 3-5 minutes is reasonable

**Warning signs:**
- Network tab shows same API call repeating on every navigation
- Badge counts flickering/reloading on page change
- "Already approved" user seeing loading spinner on every route

**Source:** [Important Defaults](https://tanstack.com/query/v5/docs/react/guides/important-defaults)

### Pitfall 4: Not Using Same queryKey Across Components
**What goes wrong:** If components define their own queryKey instead of sharing, deduplication fails and each makes separate network requests.

**Why it happens:** Developers inline useQuery calls without extracting to shared hooks or query factories.

**How to avoid:**
- Extract queries to custom hooks (e.g., useCurrentUser)
- Use queryOptions helper to define once, use everywhere
- Centralize query definitions in hooks/queries/ directory
- Code review: Check for duplicate queryKey definitions

**Warning signs:**
- Network tab shows duplicate concurrent requests for same endpoint
- React Query DevTools shows multiple cache entries for same data
- Same API called 2-3 times on component mount

**Source:** [Deduplicating Parallel Queries](https://matthuggins.com/blog/posts/deduplicating-parallel-queries-in-tanstack-query-react-query)

## Code Examples

Verified patterns from official sources and current Stadion codebase:

### Current-User Query (Shared Across Components)

**Before (duplicated in 3 places):**
```javascript
// ApprovalCheck
const { data: user } = useQuery({
  queryKey: ['current-user'],
  queryFn: async () => {
    const response = await prmApi.getCurrentUser();
    return response.data;
  },
  retry: false,
});

// FairplayRoute (same query)
const { data: user } = useQuery({
  queryKey: ['current-user'],
  queryFn: async () => {
    const response = await prmApi.getCurrentUser();
    return response.data;
  },
  retry: false,
});

// Sidebar (same query again)
const { data: currentUser } = useQuery({
  queryKey: ['current-user'],
  queryFn: async () => {
    const response = await prmApi.getCurrentUser();
    return response.data;
  },
});
```

**After (shared via custom hook):**
```javascript
// src/hooks/useCurrentUser.js
import { useQuery } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useCurrentUser() {
  return useQuery({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - user rarely changes
    retry: false,
  });
}

// All components import and use
import { useCurrentUser } from '@/hooks/useCurrentUser';

const { data: user, isLoading } = useCurrentUser();
```

**Result:** Single network request, all components share cache, consistent options.

### VOG Count with Caching

**Current implementation (refetches on every navigation):**
```javascript
// src/hooks/useVOGCount.js
export function useVOGCount() {
  const { data, isLoading } = useFilteredPeople({
    page: 1,
    perPage: 1,
    huidigeVrijwilliger: '1',
    vogMissing: '1',
    vogOlderThanYears: 3,
  });

  return {
    count: data?.total || 0,
    isLoading,
  };
}
```

**Improved with staleTime:**
```javascript
// src/hooks/useVOGCount.js
export function useVOGCount() {
  const { data, isLoading } = useFilteredPeople(
    {
      page: 1,
      perPage: 1,
      huidigeVrijwilliger: '1',
      vogMissing: '1',
      vogOlderThanYears: 3,
    },
    {
      staleTime: 5 * 60 * 1000, // 5 minutes cache
    }
  );

  return {
    count: data?.total || 0,
    isLoading,
  };
}
```

**Result:** Badge count cached for 5 minutes, no refetch on navigation within that window.

### QueryOptions Pattern (Advanced)

```javascript
// src/hooks/queries/user.js
import { queryOptions } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export const userQueries = {
  currentUser: () => queryOptions({
    queryKey: ['current-user'],
    queryFn: async () => {
      const response = await prmApi.getCurrentUser();
      return response.data;
    },
    staleTime: 5 * 60 * 1000,
    retry: false,
  }),
};

// Usage in hooks
import { userQueries } from '@/hooks/queries/user';

export function useCurrentUser() {
  return useQuery(userQueries.currentUser());
}

// Can also be used directly
import { useQuery } from '@tanstack/react-query';
const { data } = useQuery(userQueries.currentUser());

// Or in prefetching
queryClient.prefetchQuery(userQueries.currentUser());
```

**Benefit:** Type-safe, reusable, can override options at call site if needed.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| cacheTime option | gcTime | TanStack Query v5 (2023) | Renamed for clarity - "garbage collection time" is more descriptive |
| Inline useQuery everywhere | queryOptions + custom hooks | Pattern emerged 2024 | Better code organization, type safety, DRY |
| Context for user data | React Query with staleTime | Ongoing best practice | Simpler code, automatic cache management, background refetching |
| Manual request deduplication | Built-in via queryKey | React Query v2+ (2020) | No custom code needed, works automatically |

**Deprecated/outdated:**
- **cacheTime:** Renamed to gcTime in v5 - old name still works but deprecated
- **Manual deduplication libraries:** react-query-dedup and similar - React Query handles this natively
- **useIsFetching() for loading states:** Prefer individual query loading states for better UX

**Source:** [Migrating to v5](https://tanstack.com/query/v5/docs/react/guides/migrating-to-v5)

## Open Questions

Things that couldn't be fully resolved:

1. **Should useAuth continue to exist or merge with useCurrentUser?**
   - What we know: useAuth currently reads from window.stadionConfig (synchronous, server-rendered)
   - What's unclear: Whether ApprovalCheck/FairplayRoute need the async query or could use server config
   - Recommendation: Keep useAuth for quick logged-in check, use useCurrentUser for full user data (capabilities, avatar, etc.)

2. **Optimal staleTime for VOG count badge**
   - What we know: Current implementation has no staleTime, refetches on every navigation
   - What's unclear: How often VOG data actually changes, user expectation for badge freshness
   - Recommendation: Start with 5 minutes (matches global default), can be tuned based on user feedback

3. **Should all query configurations move to queryOptions pattern?**
   - What we know: queryOptions provides type safety and centralization
   - What's unclear: Whether to refactor existing hooks (usePeople, useTeams, etc.) or just new ones
   - Recommendation: Use for new shared queries (useCurrentUser), leave existing hooks as-is unless problems arise

## Sources

### Primary (HIGH confidence)
- [TanStack Query v5 Documentation](https://tanstack.com/query/latest/docs/framework/react/overview) - Official docs
- [Query Options Guide](https://tanstack.com/query/latest/docs/framework/react/guides/query-options) - queryOptions pattern
- [Important Defaults](https://tanstack.com/query/v5/docs/react/guides/important-defaults) - staleTime, gcTime defaults
- [TanStack Query GitHub Discussions](https://github.com/TanStack/query/discussions/7056) - Option merging behavior

### Secondary (MEDIUM confidence)
- [Understanding staleTime vs gcTime](https://medium.com/@bloodturtle/understanding-staletime-vs-gctime-in-tanstack-query-e9928d3e41d4) - WebSearch verified with official docs
- [React Query Custom Hook Patterns](https://backbencher.dev/react-query-share-query-across-components-using-hooks) - Community best practices
- [Pitfalls of React Query](https://nickb.dev/blog/pitfalls-of-react-query/) - Experienced developer insights
- [Deduplicating Parallel Queries](https://matthuggins.com/blog/posts/deduplicating-parallel-queries-in-tanstack-query-react-query) - Practical patterns

### Tertiary (LOW confidence)
- [react-query-auth library](https://github.com/alan2207/react-query-auth) - Alternative approach, not needed for simple case
- Various Medium articles and blog posts - Referenced for patterns, verified against official docs

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - TanStack Query v5 is established, well-documented
- Architecture: HIGH - queryOptions pattern is official, custom hooks are standard React
- Pitfalls: HIGH - Verified through official GitHub discussions and documentation
- staleTime/gcTime: HIGH - Official docs provide clear guidance and examples

**Research date:** 2026-02-04
**Valid until:** 30 days (stable domain, v5 is mature, patterns are established)

**Key findings for planner:**
1. No new dependencies needed - everything already in place
2. Solution is extraction + configuration, not new code patterns
3. React Query automatically deduplicates - just need consistent queryKey usage
4. staleTime configuration is the key to preventing unnecessary refetches
