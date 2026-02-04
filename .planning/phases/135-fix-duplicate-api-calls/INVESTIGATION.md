# Investigation: Duplicate API Calls on Page Load

## Problem Statement
Every page load makes 2x API calls for all endpoints. This was originally thought to be a React 18 StrictMode development issue, but testing confirms it happens in **production** too.

## Key Findings

### 1. The duplicates are real, not StrictMode
- Removed `<React.StrictMode>` wrapper - duplicates still occur
- Cleared service worker and storage - duplicates still occur
- Tested on multiple pages (Dashboard, People) - same pattern everywhere

### 2. Timing analysis
- Two batches of requests ~270-300ms apart
- First batch: user/me, VOG-filtered, dashboard, user/me (again), todos, settings, meetings
- Second batch: VOG-filtered, dashboard, todos, settings, meetings (same endpoints minus one user/me)
- Console logs confirm `queryFn` is being called twice for same queryKey

### 3. What we tried (none worked)
- Removed `React.StrictMode`
- Removed `DomErrorBoundary` wrapper
- Added `refetchOnWindowFocus: false` to QueryClient defaults
- Added `refetchOnMount: false` to QueryClient defaults
- Added `refetchOnReconnect: false` to QueryClient defaults
- Added explicit `staleTime` and `refetchOnMount` to individual queries
- Disabled `ReloadPrompt` (PWA service worker registration)
- Removed lazy loading for Dashboard component

### 4. Evidence the entire component tree mounts twice
- "SW Registered" console log appears twice
- `[useDashboard] Fetching` console log appears twice with ~270ms gap
- ALL queries are duplicated, not just specific ones

### 5. Current component hierarchy
```
main.jsx
  └── QueryClientProvider
      └── BrowserRouter
          └── App
              ├── UpdateBanner (useVersionCheck)
              ├── OfflineBanner
              ├── InstallPrompt
              ├── IOSInstallModal
              └── Routes
                  └── Route path="/*"
                      └── ProtectedRoute
                          └── ApprovalCheck (useQuery['current-user'], shows loading then children)
                              └── Layout (useDashboard, useVOGCount)
                                  ├── Sidebar (useQuery['current-user'])
                                  ├── UserMenu (useQuery['current-user'])
                                  └── Suspense
                                      └── Routes (nested)
                                          └── Dashboard (useDashboard, useTodos, etc.)
```

### 6. Suspicious patterns identified
- **ApprovalCheck conditional rendering**: Shows loading spinner while `isLoading`, then renders `children`. This state transition causes Layout/Dashboard to mount AFTER ApprovalCheck's query completes.
- **Nested Routes**: Outer `<Routes>` with `path="/*"` contains inner `<Routes>` inside Suspense
- **Multiple components calling same queries**: Layout and Dashboard both call `useDashboard()`, Sidebar/UserMenu/ApprovalCheck all call `['current-user']`

## Hypotheses to explore

### Hypothesis A: ApprovalCheck waterfall causing remount
The ApprovalCheck component's loading state might be causing a specific React reconciliation issue where the child tree is mounted, unmounted briefly, then remounted.

**Test**: Bypass ApprovalCheck temporarily and see if duplicates disappear.

### Hypothesis B: Nested Routes causing double render
The nested `<Routes>` pattern inside a `<Route path="/*">` might be triggering React Router to process the route twice.

**Test**: Flatten the route structure to use a single `<Routes>` component.

### Hypothesis C: React Query context boundary issue
Something about how QueryClientProvider interacts with the conditional rendering might cause queries to be treated as "new" on the second render.

**Test**: Move QueryClientProvider inside App component or restructure providers.

### Hypothesis D: BrowserRouter initial navigation
BrowserRouter might be firing an initial navigation event that causes a re-render of the route tree.

**Test**: Try using `createBrowserRouter` from react-router-dom v6 instead.

## Files modified during investigation
- `src/main.jsx` - Removed StrictMode, DomErrorBoundary, added QueryClient options
- `src/hooks/useDashboard.js` - Added debug logging, explicit options
- `src/App.jsx` - Disabled ReloadPrompt, made Dashboard non-lazy

## Recommended next steps
1. Revert investigation changes to clean state
2. Focus on Hypothesis A first (simplest to test)
3. If that fails, try Hypothesis B (flatten routes)
4. Consider that some duplicate fetching might be acceptable if it doesn't hurt UX

## QueryClient current config
```javascript
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      refetchOnWindowFocus: false,
      refetchOnMount: false,
      refetchOnReconnect: false,
      retry: 1,
    },
  },
});
```

---
*Investigation date: 2026-02-04*
