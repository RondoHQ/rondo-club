# Phase 136: Modal Lazy Loading - Research

**Researched:** 2026-02-04
**Domain:** React/TanStack Query conditional data fetching
**Confidence:** HIGH

## Summary

This phase addresses the performance issue where modals with people selectors (QuickActivityModal, TodoModal, GlobalTodoModal) fetch all 1400+ people data immediately when the dashboard renders, even though users only need this data when opening a modal.

The solution is well-established in the codebase: use TanStack Query's `enabled` option to conditionally fetch data only when the modal's `isOpen` prop is `true`. This pattern is already implemented in `CommissieEditModal` and `TeamEditModal`, so we're applying a proven pattern, not inventing something new.

The fix is straightforward - modify the `usePeople` hook to accept an `enabled` option, then pass `enabled: isOpen` from each modal. Alternatively, use inline `useQuery` calls in the modals with `enabled: isOpen`. The first approach is cleaner as it maintains the shared hook pattern.

**Primary recommendation:** Modify `usePeople` hook to accept an `enabled` option (default: `true`), then update the three modals to pass `enabled: isOpen`.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @tanstack/react-query | ^5.17.0 | Server state management | Already in use, has built-in `enabled` option for conditional fetching |

### Supporting
No additional libraries needed - this is a configuration change, not new functionality.

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Modify `usePeople` hook | Inline `useQuery` in modals | Inline approach duplicates API call logic, hook modification is DRYer |
| `enabled` option | `skipToken` (TS only) | `skipToken` disables `refetch()`, `enabled` allows manual refetch |

**Installation:**
```bash
# No new packages needed
```

## Architecture Patterns

### Recommended Approach: Hook-Level Enabled Option

**What:** Modify `usePeople` hook to accept an `enabled` option, defaulting to `true` for backward compatibility.

**Why:**
- Follows existing codebase patterns (`useDisciplineCases`, `usePersonDisciplineCases`)
- DRY - hook logic stays centralized
- Minimal changes to modal components
- Backward compatible - existing usages continue to work

### Pattern: Conditional Data Fetching via `enabled`

From TanStack Query documentation:

```javascript
// Hook with enabled option
export function usePeople(params = {}, options = {}) {
  const { enabled = true } = options;

  return useQuery({
    queryKey: peopleKeys.list(params),
    queryFn: async () => {
      // ... existing fetch logic
    },
    enabled, // Only fetch when enabled is true
  });
}

// Modal component usage
function MyModal({ isOpen, onClose }) {
  // Data only fetched when modal is open
  const { data: people, isLoading } = usePeople({}, { enabled: isOpen });

  if (!isOpen) return null;
  // ...
}
```

### Existing Codebase Examples

The codebase already uses this pattern in multiple places:

1. **CommissieEditModal** (lines 40, 50):
```javascript
const { data: allCommissies = [] } = useQuery({
  queryKey: ['commissies', 'all'],
  queryFn: async () => { /* ... */ },
  enabled: isOpen,
});
```

2. **TeamEditModal** (lines 40, 50):
```javascript
const { data: allTeams = [] } = useQuery({
  queryKey: ['teams', 'all'],
  queryFn: async () => { /* ... */ },
  enabled: isOpen,
});
```

3. **useDisciplineCases** (line 39):
```javascript
export function useDisciplineCases({ seizoen = null, enabled = true } = {}) {
  return useQuery({
    queryKey: disciplineCaseKeys.list({ seizoen }),
    queryFn: async () => { /* ... */ },
    enabled,
  });
}
```

### Anti-Patterns to Avoid

- **Fetching in useEffect:** Don't manually fetch data in useEffect when modal opens - let TanStack Query handle it
- **Caching isOpen state:** Don't store a local copy of isOpen - use the prop directly
- **Early return before hook:** The `if (!isOpen) return null;` pattern must come AFTER the hook call, not before (React hooks rules)

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Conditional fetching | Manual fetch with useEffect | TanStack Query `enabled` option | Built-in, handles cache, loading states, deduplication |
| Data caching | localStorage or state | TanStack Query cache | Already configured with 5-minute staleTime |
| Loading states | Manual isLoading state | TanStack Query `isLoading` | Automatic, handles edge cases |

**Key insight:** TanStack Query's `enabled` option is designed exactly for this use case - it handles all the complexity of conditional fetching while maintaining cache coherence.

## Common Pitfalls

### Pitfall 1: Hook Call Order
**What goes wrong:** Putting `if (!isOpen) return null;` before the `usePeople` hook call
**Why it happens:** Developers think they're optimizing by short-circuiting early
**How to avoid:** Always call hooks unconditionally at the top of the component, use `enabled` to control fetching
**Warning signs:** React hooks error about conditional hook calls

### Pitfall 2: Forgetting Default Value for `enabled`
**What goes wrong:** Breaking existing usages that don't pass `enabled`
**Why it happens:** Changing hook signature without backward compatibility
**How to avoid:** Default `enabled` to `true` so existing calls continue to work
**Warning signs:** Other components that use `usePeople` stop fetching data

### Pitfall 3: Loading State Flash
**What goes wrong:** Modal briefly shows loading spinner even when data is cached
**Why it happens:** Not accounting for TanStack Query's cache
**How to avoid:** Check `isLoading` rather than `!data` - cached data is immediately available
**Warning signs:** Unnecessary spinners on modal re-open when data hasn't changed

### Pitfall 4: isLoading vs isPending
**What goes wrong:** Using `isPending` instead of `isLoading` for disabled queries
**Why it happens:** Confusion about TanStack Query v5 terminology
**How to avoid:** Use `isLoading` which is `false` when query is disabled with no data in cache
**Warning signs:** Showing loading state when query is disabled

## Code Examples

### Example 1: Modified usePeople Hook

```javascript
// src/hooks/usePeople.js

/**
 * Hook for fetching all people
 * @param {Object} params - Query parameters (per_page, etc.)
 * @param {Object} options - Hook options
 * @param {boolean} options.enabled - Whether to enable the query (default: true)
 */
export function usePeople(params = {}, options = {}) {
  const { enabled = true } = options;

  return useQuery({
    queryKey: peopleKeys.list(params),
    queryFn: async () => {
      const allPeople = [];
      let page = 1;
      const perPage = 100;

      while (true) {
        const response = await wpApi.getPeople({
          per_page: perPage,
          page,
          _embed: true,
          ...params,
        });

        const people = response.data.map(transformPerson);
        allPeople.push(...people);

        if (people.length < perPage) break;

        const totalPages = parseInt(
          response.headers['x-wp-totalpages'] ||
          response.headers['X-WP-TotalPages'] || '0',
          10
        );
        if (totalPages > 0 && page >= totalPages) break;

        page++;
      }

      return allPeople;
    },
    enabled,
  });
}
```

### Example 2: Updated QuickActivityModal

```javascript
// src/components/Timeline/QuickActivityModal.jsx

export default function QuickActivityModal({
  isOpen,
  onClose,
  onSubmit,
  isLoading,
  personId,
  initialData = null,
  activity = null
}) {
  // ... existing state declarations ...

  // Only fetch people when modal is open
  const { data: allPeople } = usePeople({}, { enabled: isOpen });

  // ... rest of component ...

  // Early return AFTER hook calls (React rules of hooks)
  if (!isOpen) return null;

  // ... render logic ...
}
```

### Example 3: Updated TodoModal

```javascript
// src/components/Timeline/TodoModal.jsx

export default function TodoModal({ isOpen, onClose, onSubmit, isLoading, todo = null }) {
  // ... existing state declarations ...

  // Only fetch people when modal is open
  const { data: people = [], isLoading: isPeopleLoading } = usePeople({}, { enabled: isOpen });

  // ... rest of component ...

  if (!isOpen) return null;

  // ... render logic ...
}
```

### Example 4: Updated GlobalTodoModal

```javascript
// src/components/Timeline/GlobalTodoModal.jsx

export default function GlobalTodoModal({ isOpen, onClose }) {
  // ... existing state declarations ...

  // Only fetch people when modal is open
  const { data: people = [], isLoading: isPeopleLoading } = usePeople({}, { enabled: isOpen });

  // ... rest of component ...

  if (!isOpen) return null;

  // ... render logic ...
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Fetch all data on component mount | Use `enabled` for conditional fetching | TanStack Query v3+ | Significant performance improvement for modals |
| Manual `isLoading` tracking with disabled queries | Use `isLoading` (not `isPending`) | TanStack Query v5 | Clearer loading state semantics |

**Current in this codebase:**
- TanStack Query v5.17.0 is being used
- `refetchOnWindowFocus: false` already set globally (Phase 135)
- `refetchOnMount: false` already set globally (Phase 135)
- Pattern is already used in `CommissieEditModal` and `TeamEditModal`

## Open Questions

None - this is a well-established pattern with clear implementation path.

## Sources

### Primary (HIGH confidence)
- TanStack Query v5 documentation on [Disabling/Pausing Queries](https://tanstack.com/query/v5/docs/framework/react/guides/disabling-queries)
- Codebase analysis: `src/components/CommissieEditModal.jsx` (lines 40, 50)
- Codebase analysis: `src/components/TeamEditModal.jsx` (lines 40, 50)
- Codebase analysis: `src/hooks/useDisciplineCases.js` (line 39)

### Secondary (MEDIUM confidence)
- TanStack Query v5 documentation on loading states and `isLoading` vs `isPending`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - using existing library already in codebase
- Architecture: HIGH - pattern already implemented in other modals
- Pitfalls: HIGH - well-documented in TanStack Query docs

**Research date:** 2026-02-04
**Valid until:** 2026-03-04 (30 days - stable pattern, unlikely to change)
