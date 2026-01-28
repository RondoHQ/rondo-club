# Phase 108: Offline Support - Research

**Researched:** 2026-01-28
**Domain:** PWA Offline Mode - React + TanStack Query + Service Worker
**Confidence:** HIGH

## Summary

This phase adds comprehensive offline support to the Stadion PWA, building on Phase 107's service worker foundation. The research covers online/offline detection patterns in React, TanStack Query's built-in offline mode, Workbox offline fallback strategies, and UI patterns for graceful offline degradation.

The standard approach combines browser navigator.onLine API with custom React hooks for state management, TanStack Query's networkMode configuration to respect online/offline state, Workbox's offlineFallback recipe for uncached routes, and prominent but unobtrusive offline indicators (bottom banner pattern). The key insight is that TanStack Query already caches data client-side, so the service worker should use NetworkFirst strategy to avoid double-caching, and the UI should simply disable writes while showing cached reads normally.

**Primary recommendation:** Create useOnlineStatus hook wrapping navigator.onLine events, integrate with TanStack Query's onlineManager, configure Workbox offlineFallback for uncached routes, and display persistent bottom banner when offline that doesn't interfere with content but clearly indicates network state.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| navigator.onLine | Browser API | Network connectivity detection | Native browser API, real-time online/offline events |
| TanStack Query onlineManager | Built-in | Query library offline integration | Official offline mode, prevents failed requests when offline |
| Workbox offlineFallback | v7.0.0 | Service worker fallback page | Official Workbox recipe, handles uncached navigation requests |
| React useState/useEffect | React 18.2 | Custom hook state management | Standard React patterns for event listeners |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| lucide-react | ^0.309.0 | WifiOff icon | Already in project, consistent icon system |
| vite-plugin-pwa workbox | ^1.2.0 | SW offline config | Already configured in Phase 107 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| navigator.onLine | API ping polling | Polling more reliable but costs bandwidth/battery; onLine is instant and free |
| Bottom banner | Toast/snackbar | Toast auto-dismisses; offline is persistent state needing persistent indicator |
| TanStack Query networkMode | Custom request blocking | Query library handles this robustly; reinventing wastes effort |
| Workbox offlineFallback | Custom SW navigation handler | Recipe handles edge cases; custom code misses Android/iOS quirks |

**Installation:**
```bash
# No new dependencies needed - all already installed
# lucide-react: ^0.309.0 (already in package.json)
# vite-plugin-pwa: ^1.2.0 (already in package.json)
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── hooks/
│   └── useOnlineStatus.js        # Custom hook for online/offline detection
├── components/
│   ├── OfflineBanner.jsx         # Bottom banner indicator
│   └── OfflineFallback.jsx       # Static fallback page (in public/)
├── App.jsx                       # Mount OfflineBanner globally
└── main.jsx                      # Configure onlineManager

public/
└── offline.html                  # Static HTML fallback (no React)

vite.config.js                    # Configure workbox offlineFallback
```

### Pattern 1: useOnlineStatus Hook
**What:** Custom React hook for detecting online/offline state
**When to use:** Always - provides centralized state management for network status

**Example:**
```javascript
// src/hooks/useOnlineStatus.js
// Combines navigator.onLine API with React state management
import { useState, useEffect } from 'react';

export function useOnlineStatus() {
  const [isOnline, setIsOnline] = useState(() => {
    // Initialize from navigator.onLine (browser API)
    return typeof navigator !== 'undefined' ? navigator.onLine : true;
  });

  useEffect(() => {
    // Handle online event
    const handleOnline = () => setIsOnline(true);

    // Handle offline event
    const handleOffline = () => setIsOnline(false);

    // Add event listeners
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Cleanup
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  return isOnline;
}
```

**Source:** [React offline detection best practices](https://dev.to/safal_bhandari/detecting-online-and-offline-status-in-react-1i6o)

### Pattern 2: TanStack Query onlineManager Integration
**What:** Integrate browser online/offline state with TanStack Query
**When to use:** Always - ensures queries respect network state

**Example:**
```javascript
// src/main.jsx
// Source: https://tanstack.com/query/latest/docs/reference/onlineManager
import { onlineManager } from '@tanstack/react-query';

// Set up custom event listener for online/offline
onlineManager.setEventListener((setOnline) => {
  const handleOnline = () => setOnline(true);
  const handleOffline = () => setOnline(false);

  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  // Return cleanup function
  return () => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
  };
});

// QueryClient config already exists - no changes needed
// networkMode defaults to 'online' which pauses queries when offline
```

**Key insight:** TanStack Query v5 changed from using navigator.onLine directly to event-based detection to avoid false negatives. Our integration follows this pattern.

**Source:** [TanStack Query Network Mode](https://tanstack.com/query/v4/docs/framework/react/guides/network-mode)

### Pattern 3: Offline Banner Component
**What:** Persistent bottom banner showing network status
**When to use:** Always - mounted in App.jsx, conditionally rendered

**Example:**
```jsx
// src/components/OfflineBanner.jsx
import { useState, useEffect } from 'react';
import { WifiOff, Wifi } from 'lucide-react';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

export function OfflineBanner() {
  const isOnline = useOnlineStatus();
  const [showBackOnline, setShowBackOnline] = useState(false);
  const [wasOffline, setWasOffline] = useState(false);

  // Track transitions from offline to online
  useEffect(() => {
    if (!isOnline) {
      setWasOffline(true);
      setShowBackOnline(false);
    } else if (wasOffline) {
      // Just came back online
      setShowBackOnline(true);
      setWasOffline(false);

      // Hide "back online" message after 2-3 seconds
      const timer = setTimeout(() => setShowBackOnline(false), 2500);
      return () => clearTimeout(timer);
    }
  }, [isOnline, wasOffline]);

  // Don't render if online and not showing "back online" message
  if (isOnline && !showBackOnline) return null;

  return (
    <div
      className={`fixed bottom-0 left-0 right-0 z-50 px-4 py-3 text-sm font-medium text-center transition-colors ${
        isOnline
          ? 'bg-green-500 text-white'
          : 'bg-gray-700 text-gray-100 dark:bg-gray-800'
      }`}
    >
      <div className="flex items-center justify-center gap-2">
        {isOnline ? (
          <>
            <Wifi className="w-4 h-4" />
            <span>You're back online</span>
          </>
        ) : (
          <>
            <WifiOff className="w-4 h-4" />
            <span>You're offline</span>
          </>
        )}
      </div>
    </div>
  );
}
```

**Design notes:**
- Fixed positioning at bottom (doesn't cover content)
- Persistent while offline (not auto-dismiss)
- Brief "back online" confirmation (2-3 seconds)
- Subtle gray styling (per CONTEXT.md: muted styling)
- Uses existing lucide-react icons

**Source:** [PWA Offline Indicator UI Patterns](https://www.netguru.com/blog/pwa-ux-techniques)

### Pattern 4: Workbox Offline Fallback Configuration
**What:** Configure service worker to serve offline.html for uncached navigation
**When to use:** Always - handles new routes user hasn't visited yet

**Example:**
```javascript
// vite.config.js
// Source: https://developer.chrome.com/docs/workbox/managing-fallback-responses
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    VitePWA({
      // ... existing config from Phase 107
      workbox: {
        // ... existing runtimeCaching config

        // Add offline fallback
        navigateFallback: null, // Don't use built-in navigate fallback

        // Use offlineFallback recipe via custom SW
        // (handled by vite-plugin-pwa's generateSW)
      },

      // Include offline.html in build
      includeAssets: ['offline.html', 'icons/**/*'],
    }),
  ],
});
```

**Note:** vite-plugin-pwa with generateSW strategy automatically includes offlineFallback pattern when offline.html exists in public/. No custom service worker code needed.

**Source:** [Workbox Managing Fallback Responses](https://developer.chrome.com/docs/workbox/managing-fallback-responses)

### Pattern 5: Static Offline Fallback Page
**What:** Standalone HTML page shown when navigating to uncached route while offline
**When to use:** Always - no React, plain HTML works offline

**Example:**
```html
<!-- public/offline.html -->
<!-- Source: Custom pattern for Stadion branding -->
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Offline - Stadion</title>
  <style>
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: #f9fafb;
      color: #111827;
    }
    .container {
      text-align: center;
      padding: 2rem;
      max-width: 400px;
    }
    .icon {
      width: 64px;
      height: 64px;
      margin: 0 auto 1.5rem;
      color: #6b7280;
    }
    h1 {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 0.5rem;
      color: #111827;
    }
    p {
      font-size: 1rem;
      color: #6b7280;
      margin: 0 0 2rem;
    }
    .buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
    }
    button {
      padding: 0.625rem 1.25rem;
      border: none;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-primary {
      background: #f97316;
      color: white;
    }
    .btn-primary:hover {
      background: #ea580c;
    }
    .btn-secondary {
      background: #e5e7eb;
      color: #374151;
    }
    .btn-secondary:hover {
      background: #d1d5db;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Wifi-off icon (SVG from lucide-react) -->
    <svg class="icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <line x1="2" x2="22" y1="2" y2="22"></line>
      <path d="M8.5 16.5a5 5 0 0 1 7 0"></path>
      <path d="M2 8.82a15 15 0 0 1 4.17-2.65"></path>
      <path d="M10.66 5c4.01-.36 8.14.9 11.34 3.76"></path>
      <path d="M16.85 11.25a10 10 0 0 1 2.22 1.68"></path>
      <path d="M5 13a10 10 0 0 1 5.24-2.76"></path>
      <circle cx="12" cy="20" r="1"></circle>
    </svg>

    <h1>Je bent offline</h1>
    <p>We kunnen deze pagina nu niet laden. Controleer je internetverbinding en probeer het opnieuw.</p>

    <div class="buttons">
      <button class="btn-primary" onclick="location.reload()">
        Opnieuw proberen
      </button>
      <button class="btn-secondary" onclick="history.back()">
        Ga terug
      </button>
    </div>
  </div>
</body>
</html>
```

**Design notes:**
- No external dependencies (works fully offline)
- Simple WiFi-off icon from lucide-react (inline SVG)
- Friendly Dutch copy per CONTEXT.md ("Je bent offline")
- Retry + Go back buttons
- Accent color matches Stadion theme

### Pattern 6: Disable Forms When Offline
**What:** Block all write operations (save, delete) when offline
**When to use:** In all forms, edit modals, and delete buttons

**Example:**
```jsx
// In any form/modal component
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

function PersonEditModal({ person, onSave, onDelete }) {
  const isOnline = useOnlineStatus();

  return (
    <form>
      {/* Form fields */}
      <input
        type="text"
        disabled={!isOnline}
        className={!isOnline ? 'opacity-50 cursor-not-allowed' : ''}
      />

      {/* Save button */}
      <button
        type="submit"
        disabled={!isOnline}
        className={!isOnline ? 'opacity-50 cursor-not-allowed' : ''}
      >
        Opslaan
      </button>

      {/* Delete button */}
      <button
        type="button"
        onClick={onDelete}
        disabled={!isOnline}
        className={!isOnline ? 'opacity-50 cursor-not-allowed' : ''}
      >
        Verwijderen
      </button>
    </form>
  );
}
```

**Design notes:**
- All inputs disabled (read-only)
- Buttons disabled with visual feedback (opacity-50)
- Banner provides context - no inline messages needed
- Consistent with CONTEXT.md decisions

### Anti-Patterns to Avoid
- **Relying only on TanStack Query paused state:** Query library pauses fetches but doesn't provide UI-facing online/offline state; need separate hook
- **Auto-dismissing offline indicator:** Offline is persistent state, not transient event; use fixed banner, not toast
- **Showing stale data indicator:** Per CONTEXT.md, data looks normal; only banner indicates offline state
- **Blocking navigation offline:** Let users browse cached content freely; show fallback for uncached routes
- **CacheFirst for API endpoints:** TanStack Query already caches; service worker should use NetworkFirst to avoid stale data

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Online/offline detection | Custom API ping polling | navigator.onLine + events | Instant, battery-friendly, browser handles reconnection |
| Query offline mode | Custom request interceptor | TanStack Query networkMode | Library handles edge cases, retry logic, state management |
| Offline fallback page | Custom SW navigation handler | Workbox offlineFallback recipe | Handles Android/iOS quirks, precaching, scope issues |
| Mutation queueing | Custom offline queue | (Not implemented in Phase 108) | Complex: conflict resolution, retry logic, storage - defer to future |

**Key insight:** Offline support has many edge cases (reconnection timing, cache invalidation, mutation conflicts). Standard tools handle these; custom solutions introduce bugs. Phase 108 focuses on read-only offline (cached data display), not write queueing.

## Common Pitfalls

### Pitfall 1: navigator.onLine False Positives
**What goes wrong:** navigator.onLine returns true even when connected to WiFi without internet access
**Why it happens:** Browser only detects network interface state, not actual internet connectivity
**How to avoid:**
- Accept false positives as tradeoff for instant detection
- TanStack Query will fail requests and pause retries automatically
- User sees "offline" banner after first failed request (network error triggers offline event)
- Don't poll API for connectivity checks (wastes bandwidth/battery)
**Warning signs:** User sees "online" banner but requests fail
**Mitigation:** TanStack Query's built-in error handling shows request failures in UI

**Source:** [TanStack Query Offline Discussion](https://github.com/TanStack/query/discussions/988)

### Pitfall 2: Double-Caching with Service Worker + TanStack Query
**What goes wrong:** Both service worker AND TanStack Query cache API responses, causing stale data
**Why it happens:** Service worker CacheFirst returns old response before TanStack Query can check server
**How to avoid:**
- Use NetworkFirst strategy for API routes (already configured in Phase 107)
- Service worker tries network first, falls back to cache only when offline
- TanStack Query controls cache freshness (staleTime, refetch)
- Service worker provides offline fallback, not primary caching
**Warning signs:** Data appears stale even after clearing TanStack Query cache
**Solution:** Verify workbox runtimeCaching uses NetworkFirst for /wp-json/

**Source:** [Phase 107 Research - Common Pitfall #6](https://github.com/vite-pwa/vite-plugin-pwa)

### Pitfall 3: Offline Banner Covering Bottom Content
**What goes wrong:** Fixed bottom banner covers important UI (save buttons, pagination)
**Why it happens:** Fixed positioning overlaps content at bottom of viewport
**How to avoid:**
- Use z-50 (high z-index) to ensure banner appears above content
- Add bottom padding to main content area when banner visible (via React state/CSS)
- Or: Position banner with `bottom: 0` and let content scroll underneath (acceptable)
**Warning signs:** Users can't click bottom buttons when offline
**Solution:** Test with banner visible on all pages, especially forms/modals

### Pitfall 4: Offline.html Not Precached
**What goes wrong:** offline.html itself isn't cached, so service worker can't serve it when offline
**Why it happens:** Forgot to include offline.html in precache manifest
**How to avoid:**
- Add offline.html to vite-plugin-pwa includeAssets config
- Verify dist/offline.html exists after build
- Test: go offline and navigate to uncached route
**Warning signs:** Browser shows default "No internet" page instead of custom offline.html
**Solution:** Check vite-plugin-pwa config includes offline.html in includeAssets

**Source:** [Chapimaster - Custom Offline Page in React PWA](https://www.chapimaster.com/programming/vite/create-custom-offline-page-react-pwa)

### Pitfall 5: Forgetting to Disable Delete Actions
**What goes wrong:** Delete buttons remain enabled offline, trigger errors
**Why it happens:** Focused on forms, forgot destructive actions
**How to avoid:**
- Audit all mutations: creates, updates, deletes
- Apply useOnlineStatus check to all mutation buttons
- Test: go offline, try to delete entity
**Warning signs:** Delete attempts show error toasts when offline
**Solution:** Wrap all mutation triggers with isOnline check

### Pitfall 6: Search/Filter Behavior Offline
**What goes wrong:** Search API calls fail offline even though data is cached
**Why it happens:** Search hits server endpoint, not cached client-side
**How to avoid:**
- **Option A (simple):** Disable search when offline (show message)
- **Option B (complex):** Implement client-side filtering of cached data
- Per CONTEXT.md: Claude's discretion - recommend Option A for Phase 108
**Warning signs:** Search box shows errors when offline
**Solution:** Conditionally render search based on isOnline, or disable with message

## Code Examples

### Complete useOnlineStatus Hook
```javascript
// src/hooks/useOnlineStatus.js
// Source: https://reacthustle.com/blog/react-check-online-status-tutorial
import { useState, useEffect } from 'react';

/**
 * Hook to detect online/offline network state
 *
 * Uses navigator.onLine API and window online/offline events.
 * Returns boolean indicating current network status.
 *
 * @returns {boolean} True if online, false if offline
 */
export function useOnlineStatus() {
  // Initialize from navigator.onLine
  const [isOnline, setIsOnline] = useState(() => {
    if (typeof navigator === 'undefined' || typeof window === 'undefined') {
      return true; // SSR fallback - assume online
    }
    return navigator.onLine;
  });

  useEffect(() => {
    // Skip if not in browser environment
    if (typeof window === 'undefined') return;

    // Event handlers
    const handleOnline = () => {
      console.log('Network: Online');
      setIsOnline(true);
    };

    const handleOffline = () => {
      console.log('Network: Offline');
      setIsOnline(false);
    };

    // Register event listeners
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Cleanup on unmount
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  return isOnline;
}
```

### TanStack Query onlineManager Setup
```javascript
// src/main.jsx (add to existing file)
// Source: https://tanstack.com/query/latest/docs/reference/onlineManager
import { onlineManager } from '@tanstack/react-query';

// Configure onlineManager to use browser events
// This must run before QueryClient initialization
onlineManager.setEventListener((setOnline) => {
  // Handler functions
  const handleOnline = () => setOnline(true);
  const handleOffline = () => setOnline(false);

  // Register listeners
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);

  // Return cleanup function
  return () => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
  };
});

// Existing QueryClient setup continues...
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000,
      retry: 1,
      networkMode: 'online', // Default - pauses when offline
    },
  },
});
```

### Workbox offlineFallback Configuration
```javascript
// vite.config.js (modify existing VitePWA config)
// Source: https://developer.chrome.com/docs/workbox/managing-fallback-responses
export default defineConfig({
  plugins: [
    VitePWA({
      registerType: 'prompt',
      injectRegister: null,

      manifest: {
        // ... existing manifest config from Phase 107
      },

      workbox: {
        // Precache static assets
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],

        // Runtime caching for API (NetworkFirst from Phase 107)
        runtimeCaching: [
          {
            urlPattern: /\/wp-json\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 60 * 60 * 24,
              },
              cacheableResponse: {
                statuses: [0, 200],
              },
            },
          },
        ],

        // Clean up old caches
        cleanupOutdatedCaches: true,

        // Offline fallback page
        navigateFallback: '/offline.html',
        navigateFallbackDenylist: [
          // Don't use offline page for API requests
          /^\/wp-json\//,
          // Don't use offline page for admin
          /^\/wp-admin\//,
        ],
      },

      // Include offline.html in build
      includeAssets: ['offline.html', 'icons/**/*'],
    }),
  ],
});
```

### Conditional Form Disabling Pattern
```jsx
// Example: PersonEditModal.jsx
import { useOnlineStatus } from '@/hooks/useOnlineStatus';

function PersonEditModal({ person, onSubmit, onDelete, isOpen }) {
  const isOnline = useOnlineStatus();

  // Disable all interactions when offline
  const formDisabled = !isOnline;

  return (
    <Modal isOpen={isOpen}>
      <form onSubmit={onSubmit}>
        {/* All inputs disabled when offline */}
        <input
          type="text"
          name="name"
          disabled={formDisabled}
          className={formDisabled ? 'opacity-50 cursor-not-allowed' : ''}
        />

        <input
          type="email"
          name="email"
          disabled={formDisabled}
          className={formDisabled ? 'opacity-50 cursor-not-allowed' : ''}
        />

        {/* Buttons disabled when offline */}
        <button
          type="submit"
          disabled={formDisabled}
          className={formDisabled ? 'opacity-50 cursor-not-allowed' : 'bg-accent-600'}
        >
          Opslaan
        </button>

        <button
          type="button"
          onClick={onDelete}
          disabled={formDisabled}
          className={formDisabled ? 'opacity-50 cursor-not-allowed' : 'bg-red-600'}
        >
          Verwijderen
        </button>
      </form>
    </Modal>
  );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| API ping polling | navigator.onLine events | 2020+ | Instant feedback, zero bandwidth cost |
| Custom offline queue | TanStack Query networkMode | TanStack Query v4+ | Library handles pausing, retry logic |
| Toast notifications | Persistent banner | PWA UX guidelines 2022+ | Matches persistent offline state |
| CacheFirst API strategy | NetworkFirst | Service Worker + React Query | Avoids double-caching, fresher data |
| Custom SW fallback handler | Workbox offlineFallback recipe | Workbox v6+ | Handles precaching, scope edge cases |

**Deprecated/outdated:**
- AppCache offline mode: Removed from browsers, use Service Workers
- localStorage API caching: Use TanStack Query + Service Worker cache
- Manual query pausing: Use TanStack Query networkMode instead
- Offline.js library: No longer maintained, use native APIs

## Open Questions

1. **Search/Filter Behavior Offline**
   - What we know: Search API endpoint won't work offline
   - What's unclear: Should we disable search or implement client-side filtering?
   - Recommendation: Disable search when offline (Option A) - simpler, fewer edge cases. Add message: "Search unavailable offline". Mark as Claude's discretion in CONTEXT.md.

2. **TanStack Query Persistence**
   - What we know: Query cache is in-memory only, lost on page refresh
   - What's unclear: Should we persist cache to IndexedDB for true offline-first?
   - Recommendation: Not in Phase 108 scope. Current behavior acceptable: user must load data while online, then can browse cached data until refresh. Future enhancement: PersistQueryClient with IndexedDB.

3. **Mutation Queue Complexity**
   - What we know: Requirements say "read-only" offline (OFF-03)
   - What's unclear: Should we implement optimistic UI for mutations?
   - Recommendation: No. Phase 108 blocks writes completely. Future requirements (OFF-A1, OFF-A2, OFF-A3) cover mutation queueing. Simpler, safer to block writes now.

4. **Offline Banner Position with Mobile Keyboard**
   - What we know: Fixed bottom banner may be covered by iOS keyboard in forms
   - What's unclear: Should banner move up when keyboard appears?
   - Recommendation: Test on iOS. If keyboard covers banner, position: absolute with bottom padding may work better. Mark for verification testing.

## Sources

### Primary (HIGH confidence)
- [TanStack Query Network Mode](https://tanstack.com/query/v4/docs/framework/react/guides/network-mode) - Official network mode documentation
- [TanStack Query onlineManager](https://tanstack.com/query/latest/docs/reference/onlineManager) - Official online/offline integration
- [Workbox Managing Fallback Responses](https://developer.chrome.com/docs/workbox/managing-fallback-responses) - Official Workbox offline fallback pattern
- [React offline detection patterns](https://dev.to/safal_bhandari/detecting-online-and-offline-status-in-react-1i6o) - Standard useOnlineStatus implementation
- [MDN Navigator.onLine API](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/onLine) - Browser API documentation

### Secondary (MEDIUM confidence)
- [PWA Offline UX Patterns](https://www.netguru.com/blog/pwa-ux-techniques) - Industry best practices for offline indicators
- [Chapimaster - Custom Offline Page](https://www.chapimaster.com/programming/vite/create-custom-offline-page-react-pwa) - vite-plugin-pwa offline page setup
- [LogRocket - Next.js PWA Offline Support](https://blog.logrocket.com/nextjs-16-pwa-offline-support) - Modern PWA patterns 2026
- [React Native Offline First with TanStack Query](https://dev.to/fedorish/react-native-offline-first-with-tanstack-query-1pe5) - Query offline patterns

### Tertiary (LOW confidence)
- [GitHub TanStack Query Offline Discussions](https://github.com/TanStack/query/discussions/988) - Community approaches to offline mode
- [Service Worker Caching Strategies](https://www.zeepalm.com/blog/service-worker-caching-5-offline-fallback-strategies) - Overview of caching patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - navigator.onLine, TanStack Query, Workbox are all established standards
- Architecture: HIGH - Patterns verified in official docs and production PWAs
- Pitfalls: HIGH - Well-documented in GitHub issues and Stack Overflow
- UI patterns: MEDIUM - Some subjective design decisions, verified with PWA guidelines

**Research date:** 2026-01-28
**Valid until:** 2026-03-28 (60 days - offline patterns are stable, but TanStack Query updates frequently)
