# PWA Architecture Integration

**Domain:** React SPA + WordPress Theme
**Researched:** 2026-01-28
**Confidence:** HIGH (verified with official Vite PWA plugin docs and WordPress PWA patterns)

## Executive Summary

PWA features integrate into the existing Stadion architecture through the **vite-plugin-pwa**, which generates manifest and service worker at build time. The plugin extends the Vite build pipeline to produce PWA assets that WordPress then serves alongside the existing React SPA. Critical integration points include: manifest injection into `index.php`, service worker registration in `main.jsx`, and scope configuration to exclude WordPress admin areas.

**Key architectural decision:** Service worker must be served from theme root (not `/dist/`) to control the entire frontend scope while excluding `/wp-admin/`.

## Integration Points

### 1. Manifest Location and Serving

**Where it lives:**
- Generated: `dist/manifest.webmanifest` (by vite-plugin-pwa during build)
- Served from: Theme root via WordPress `wp_head` hook
- Referenced in: `index.php` via `<link rel="manifest">` tag

**How it's served:**
```php
// In functions.php
function stadion_add_pwa_manifest() {
    $manifest_url = STADION_THEME_URL . '/dist/manifest.webmanifest';
    echo '<link rel="manifest" href="' . esc_url($manifest_url) . '">';
}
add_action('wp_head', 'stadion_add_pwa_manifest', 2);
```

**Configuration location:** `vite.config.js`
```javascript
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'prompt', // or 'autoUpdate'
      manifest: {
        name: 'Stadion',
        short_name: 'Stadion',
        start_url: '/',
        display: 'standalone',
        background_color: '#fffbeb',
        theme_color: '#f59e0b',
        icons: [
          // Icon configurations
        ]
      }
    })
  ]
})
```

**Why this location:**
- Vite PWA plugin automatically injects manifest link into build output
- WordPress `wp_head` ensures proper HTML head ordering
- Theme URL constant ensures correct absolute paths

### 2. Service Worker Location and Scope

**Critical architectural requirement:** Service worker MUST be served from theme root to control frontend scope.

**Where it lives:**
- Generated: `dist/sw.js` (by vite-plugin-pwa)
- **Must be copied to:** `sw.js` (theme root)
- Registered from: `src/main.jsx`

**Why theme root:**
Service worker scope is determined by its location. A service worker at `/wp-content/themes/stadion/sw.js` can only control URLs under `/wp-content/themes/stadion/`, which is too restrictive. The service worker needs to control `/` to intercept frontend routes like `/people`, `/teams`, etc.

**Service Worker-Allowed header alternative:**
If copying to root is not possible, WordPress can serve the service worker from `/dist/sw.js` with a `Service-Worker-Allowed: /` HTTP header to broaden scope. However, this requires server configuration and is less portable.

**Recommended approach:** Post-build copy step
```json
// package.json
{
  "scripts": {
    "build": "vite build && npm run copy-sw",
    "copy-sw": "cp dist/sw.js sw.js"
  }
}
```

**Scope configuration:**
```javascript
// vite.config.js
VitePWA({
  scope: '/',
  workbox: {
    navigateFallback: null, // WordPress handles routing
    runtimeCaching: [
      {
        urlPattern: /^https:\/\/.*\/wp-json\/.*/,
        handler: 'NetworkFirst',
        options: {
          cacheName: 'api-cache',
          networkTimeoutSeconds: 10,
          expiration: {
            maxEntries: 50,
            maxAgeSeconds: 5 * 60 // 5 minutes
          }
        }
      }
    ]
  }
})
```

### 3. Service Worker Registration

**Where:** `src/main.jsx` (after React root render)

**Strategy:** Use vite-plugin-pwa virtual modules for lifecycle control

```javascript
// src/main.jsx
import { registerSW } from 'virtual:pwa-register'

// After ReactDOM.createRoot(...).render(...)

const updateSW = registerSW({
  onNeedRefresh() {
    // Show update prompt to user
    // Store updateSW callback for user-triggered reload
  },
  onOfflineReady() {
    // Notify user app is ready for offline use
  },
  onRegistered(registration) {
    // Service worker registered successfully
    console.log('SW registered:', registration)
  },
  onRegisterError(error) {
    console.error('SW registration error:', error)
  }
})
```

**registerType options:**
- `'prompt'` (recommended): User controls when to reload for updates. Better UX for data-heavy forms.
- `'autoUpdate'`: Silent background updates. Simpler but can interrupt user work.

**Why main.jsx:** Ensures service worker registers only after React mounts, avoiding race conditions with initial app state.

### 4. Interaction with TanStack Query Cache

**Cache layer coordination:**

PWA introduces two cache layers:
1. **Service Worker cache** (via Workbox) - network/HTTP level
2. **TanStack Query cache** - application/React level

**Recommended strategy: NetworkFirst for API, CacheFirst for assets**

```javascript
// Service Worker caching (Workbox config)
runtimeCaching: [
  {
    // API requests: Network first, fallback to cache
    urlPattern: /^https:\/\/.*\/wp-json\/.*/,
    handler: 'NetworkFirst',
    options: {
      cacheName: 'api-cache',
      networkTimeoutSeconds: 10,
    }
  },
  {
    // Static assets: Cache first
    urlPattern: /\.(?:js|css|woff2?|png|jpg|jpeg|svg)$/,
    handler: 'CacheFirst',
    options: {
      cacheName: 'assets-cache',
      expiration: {
        maxEntries: 60,
        maxAgeSeconds: 30 * 24 * 60 * 60 // 30 days
      }
    }
  }
]
```

```javascript
// TanStack Query config (src/main.jsx)
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      cacheTime: 10 * 60 * 1000, // 10 minutes in memory
      retry: 1,
      networkMode: 'offlineFirst' // Use SW cache before failing
    }
  }
})
```

**Why NetworkFirst for API:**
- Ensures fresh data when online
- Falls back to cached data when offline
- Respects TanStack Query's staleTime (5 min)
- Network timeout (10s) prevents long waits on poor connections

**Why offlineFirst networkMode:**
TanStack Query's `offlineFirst` mode works with service workers: queries run even when offline, using cached responses from the service worker. This prevents query errors during offline periods.

**Cache coordination:**
```
User action → TanStack Query
               ↓
           axios request → Service Worker
                            ↓
                        Network attempt (with timeout)
                            ↓
                     [success] or [timeout/offline]
                            ↓
                    [return fresh] or [return cached]
                            ↓
                        TanStack Query
                            ↓
                        Update UI
```

### 5. WordPress Admin Area Exclusion

**Critical requirement:** Service worker MUST NOT interfere with `/wp-admin/`

**Implementation: Scope-based exclusion**

The service worker scope (`/`) naturally includes admin URLs. Explicit exclusion is required in the service worker fetch handler:

```javascript
// vite.config.js
VitePWA({
  workbox: {
    // Exclude admin and WordPress core from precaching
    globIgnores: ['**/wp-admin/**', '**/wp-includes/**'],

    // Navigation requests exclusion
    navigateFallbackDenylist: [
      /^\/wp-admin/,
      /^\/wp-login/,
      /^\/?wp-json/,
    ],

    // Runtime caching exclusions handled per route
  }
})
```

**Why this approach:**
- WordPress admin uses different authentication (cookies vs REST nonce)
- Admin requests should never be cached (always fresh)
- Prevents service worker from breaking admin features
- Preview functionality (`?preview=true`) remains unaffected

**Additional safeguard in custom service worker (if needed):**
```javascript
// src/sw-custom.js (optional)
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)

  // Don't intercept admin, login, or API requests
  if (
    url.pathname.startsWith('/wp-admin') ||
    url.pathname.startsWith('/wp-login') ||
    url.pathname.includes('wp-json')
  ) {
    return // Let WordPress handle it
  }

  // Handle other requests with Workbox strategies
})
```

## Service Worker Strategy

### Registration Strategy: Prompt for Update

**Recommended:** `registerType: 'prompt'`

**Rationale:**
- Users control when to reload (preserves form state)
- Clear communication about updates
- Better for data-heavy CRM app
- Prevents mid-workflow interruptions

**UX Flow:**
1. Service worker detects new version
2. `onNeedRefresh()` callback triggers
3. App shows update notification banner
4. User clicks "Update" when ready
5. `updateSW()` called → reload with new version

**Alternative: autoUpdate**
Use if seamless updates are more important than preserving user state. Suitable for read-heavy apps, not ideal for form-heavy CRM.

### Caching Strategy

**Three-tier caching:**

| Resource Type | Strategy | Cache Name | Rationale |
|---------------|----------|------------|-----------|
| HTML shell (`index.php` output) | NetworkFirst (timeout 5s) | `shell-cache` | Always try for fresh, fast fallback |
| API requests (`/wp-json/*`) | NetworkFirst (timeout 10s) | `api-cache` | Fresh data priority, offline fallback |
| Static assets (JS/CSS/images) | CacheFirst | `assets-cache` | Immutable, versioned by Vite |
| WordPress uploads | CacheFirst | `media-cache` | Immutable, user-uploaded content |

**Cache expiration:**
```javascript
workbox: {
  runtimeCaching: [
    {
      urlPattern: /^https:\/\/.*\/wp-json\/.*/,
      handler: 'NetworkFirst',
      options: {
        cacheName: 'api-cache',
        expiration: {
          maxEntries: 50,
          maxAgeSeconds: 5 * 60 // Match TanStack Query staleTime
        }
      }
    },
    {
      urlPattern: /\/wp-content\/uploads\/.*/,
      handler: 'CacheFirst',
      options: {
        cacheName: 'media-cache',
        expiration: {
          maxEntries: 100,
          maxAgeSeconds: 30 * 24 * 60 * 60 // 30 days
        }
      }
    }
  ]
}
```

### Precaching Strategy

**What to precache:**
- App shell (HTML)
- Core JS bundle (vendor chunk)
- Core CSS
- Critical icons/assets

**What NOT to precache:**
- API responses (runtime cached instead)
- User uploads (too large, runtime cached)
- Dynamic route data

**Configuration:**
```javascript
VitePWA({
  includeAssets: ['favicon.svg'], // Static assets to precache
  workbox: {
    globPatterns: ['**/*.{js,css,html,svg,woff2}'],
    maximumFileSizeToCacheInBytes: 3 * 1024 * 1024, // 3MB limit
  }
})
```

## Build Order and Dependencies

### Phase 1: Foundation Setup (No code changes)
**Goal:** Add vite-plugin-pwa and configure basic manifest

**Tasks:**
1. Install dependencies: `npm install -D vite-plugin-pwa workbox-window`
2. Configure `vite.config.js` with VitePWA plugin
3. Configure manifest (name, icons, theme colors)
4. Test build produces `dist/manifest.webmanifest`

**Verification:**
- `npm run build` succeeds
- `dist/manifest.webmanifest` exists
- Manifest contains correct app metadata

**Dependencies:** None (standalone)

### Phase 2: Manifest Integration
**Goal:** Serve manifest from WordPress, add PWA meta tags

**Tasks:**
1. Add `stadion_add_pwa_manifest()` function to `functions.php`
2. Add theme color meta tags to `index.php`
3. Add apple-touch-icon links
4. Test manifest loads in browser

**Verification:**
- Manifest appears in DevTools → Application → Manifest
- No console errors about manifest
- Theme colors applied correctly

**Dependencies:** Phase 1 (requires manifest file)

### Phase 3: Service Worker Generation
**Goal:** Generate service worker, configure caching strategies

**Tasks:**
1. Configure Workbox runtime caching in `vite.config.js`
2. Add API caching strategy (NetworkFirst)
3. Add static asset caching (CacheFirst)
4. Exclude `/wp-admin/` and `/wp-login/`
5. Test build produces `dist/sw.js`

**Verification:**
- `npm run build` produces `dist/sw.js`
- Service worker includes cache strategies
- Admin URLs excluded from caching

**Dependencies:** Phase 1 (requires plugin setup)

### Phase 4: Service Worker Deployment
**Goal:** Copy service worker to theme root, make it accessible

**Tasks:**
1. Add post-build script to `package.json`: `"copy-sw": "cp dist/sw.js sw.js"`
2. Update `npm run build` to run copy-sw
3. Test service worker accessible at theme root
4. Update `.gitignore` to track `sw.js`

**Verification:**
- `sw.js` exists in theme root after build
- Service worker accessible via browser
- File tracked in git (intentional)

**Dependencies:** Phase 3 (requires sw.js to exist)

**Why copy step:** Service worker needs root scope to control frontend routes. Serving from `/dist/sw.js` would limit scope to `/dist/` only.

### Phase 5: Service Worker Registration
**Goal:** Register service worker in React app, wire up update UI

**Tasks:**
1. Import `registerSW` from `virtual:pwa-register` in `main.jsx`
2. Implement `onNeedRefresh()` callback (show update banner)
3. Implement `onOfflineReady()` callback (show offline indicator)
4. Create UpdateBanner component (user-triggered reload)
5. Store `updateSW` callback in state/context

**Verification:**
- Service worker registers on app load
- Update banner appears when new version detected
- Clicking "Update" reloads with new version
- Offline indicator appears when service worker ready

**Dependencies:** Phase 4 (requires accessible service worker)

### Phase 6: TanStack Query Integration
**Goal:** Configure TanStack Query for offline-first mode

**Tasks:**
1. Update QueryClient config: add `networkMode: 'offlineFirst'`
2. Test API requests work offline (using cached data)
3. Test online/offline transitions
4. Add error boundaries for offline failures

**Verification:**
- Queries work offline (use cached SW responses)
- No query errors during offline periods
- UI updates when transitioning online/offline
- Error messages clear when online

**Dependencies:** Phase 5 (requires registered service worker)

### Phase 7: Testing and Optimization
**Goal:** Verify PWA features work correctly, optimize performance

**Tasks:**
1. Test install prompt on mobile/desktop
2. Test offline functionality (airplane mode)
3. Test update flow (deploy new version, trigger update)
4. Run Lighthouse PWA audit
5. Optimize cache sizes and expiration

**Verification:**
- Lighthouse PWA score > 90
- Install prompt appears on supported browsers
- App works offline (cached routes accessible)
- Updates apply cleanly without data loss

**Dependencies:** Phase 6 (requires full integration)

### Dependency Graph

```
Phase 1: Foundation Setup
    ↓
Phase 2: Manifest Integration (requires manifest file)
    ↓
Phase 3: Service Worker Generation (parallel with Phase 2)
    ↓
Phase 4: Service Worker Deployment (requires sw.js)
    ↓
Phase 5: Service Worker Registration (requires accessible sw.js)
    ↓
Phase 6: TanStack Query Integration (requires registered SW)
    ↓
Phase 7: Testing and Optimization (requires full stack)
```

**Critical path:** 1 → 3 → 4 → 5 → 6 → 7
**Parallel work:** Phase 2 can be done alongside Phase 3

## WordPress Considerations

### Admin Area Protection

**Requirement:** Service worker MUST NOT interfere with WordPress admin.

**Implementation:**
1. **Scope-based exclusion:** Service worker at `/` but with fetch handler checks
2. **Navigation exclusion:** `navigateFallbackDenylist` prevents admin route interception
3. **Cache exclusion:** `globIgnores` prevents admin asset caching

**Testing checklist:**
- [ ] `/wp-admin/` loads without service worker interception
- [ ] Login redirects work correctly
- [ ] Admin AJAX requests not cached
- [ ] Plugin/theme updates work
- [ ] Media uploads work

### Authentication Considerations

**WordPress uses two auth mechanisms:**
1. **Session cookies** (admin, traditional pages)
2. **REST nonce** (REST API, React app)

**Service worker implications:**
- API requests include `X-WP-Nonce` header (short-lived, 24h)
- Cached API responses may have stale nonces
- Nonce refresh required after cache expiration

**Solution:** NetworkFirst strategy with timeout
- Always attempts fresh request (gets fresh nonce)
- Falls back to cache only when truly offline
- TanStack Query handles 401 errors (nonce expired)

**Nonce refresh flow:**
```javascript
// In axios interceptor (already exists in src/api/client.js)
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      // Nonce expired, redirect to login
      window.location.href = window.stadionConfig.loginUrl
    }
    return Promise.reject(error)
  }
)
```

### Asset Versioning

**Current system:** Vite generates hashed filenames (`main-abc123.js`)

**PWA integration:** Service worker precache automatically includes hashed assets

**Update flow:**
1. Deploy new build (new hashes)
2. Service worker detects manifest change
3. Triggers `onNeedRefresh()` callback
4. User clicks update
5. New assets downloaded and cached
6. Page reloads with new version

**No changes required** - existing Vite versioning works seamlessly with PWA.

### Development vs Production

**Development (WP_DEBUG = true):**
- Vite dev server at `localhost:5173` (HMR enabled)
- Service worker disabled (interferes with HMR)
- Manifest served but not functional

**Production (WP_DEBUG = false):**
- Assets from `dist/` folder
- Service worker active
- Full PWA functionality

**Configuration:**
```javascript
// vite.config.js
export default defineConfig(({ mode }) => ({
  plugins: [
    react(),
    VitePWA({
      // Disable in dev mode to avoid HMR conflicts
      disable: mode === 'development',
      // ... other config
    })
  ]
}))
```

**Why disable in dev:**
- Service worker caching conflicts with HMR hot reload
- Development workflow prioritizes speed over offline capability
- Manifest still served for testing installation

### Deployment Process

**Current:** `bin/deploy.sh` syncs files to production

**PWA additions:**
1. Ensure `sw.js` copied to theme root before deploy
2. Deploy syncs `sw.js` along with other theme files
3. Service worker updates trigger automatically on client

**Updated deployment checklist:**
- [ ] Run `npm run build` (generates dist/ and copies sw.js)
- [ ] Verify `sw.js` exists in theme root
- [ ] Run `bin/deploy.sh`
- [ ] Test service worker updates on production
- [ ] Verify update prompt appears for existing users

**No script changes required** - `sw.js` at theme root deploys like any other PHP/JS file.

## Anti-Patterns to Avoid

### 1. Service Worker in /dist/
**Why bad:** Scope limited to `/dist/`, can't control frontend routes
**Instead:** Copy `sw.js` to theme root, scope to `/`

### 2. Caching /wp-admin/ URLs
**Why bad:** Breaks admin functionality, caches sensitive data
**Instead:** Explicitly exclude admin paths in service worker config

### 3. CacheFirst for API requests
**Why bad:** Serves stale data indefinitely, nonces expire
**Instead:** NetworkFirst with timeout, cache as fallback

### 4. Precaching all API responses
**Why bad:** Bloats cache, stale data, nonce issues
**Instead:** Runtime caching with expiration, NetworkFirst strategy

### 5. autoUpdate without user notification
**Why bad:** Interrupts user workflows, loses form data
**Instead:** Use `prompt` strategy, let users control updates

### 6. Ignoring offline error states
**Why bad:** Users see broken UI when offline
**Instead:** Detect online/offline, show appropriate UI, use error boundaries

### 7. Not testing offline transitions
**Why bad:** Edge cases (going offline mid-request) break app
**Instead:** Test airplane mode, throttle network, test partial offline states

### 8. Mixing WordPress session auth with cached responses
**Why bad:** Session cookies cached, auth state inconsistent
**Instead:** Exclude session-based URLs, use NetworkFirst for authenticated requests

## Scalability Considerations

### Cache Size Management

| User Load | Cache Strategy | Considerations |
|-----------|---------------|----------------|
| 1-10 users | Default (50 API entries, 60 assets) | Minimal overhead, ~5-10MB per user |
| 10-100 users | Same as default | No server impact, browser handles caching |
| 100+ users | Same as default | No backend changes needed, scales infinitely |

**Why PWA scales:** Caching is client-side. Server load actually *decreases* as more users cache assets.

### API Request Optimization

**Current:** TanStack Query with 5-minute staleTime
**With PWA:** NetworkFirst adds 10s timeout fallback

**Impact on API load:**
- Online users: No change (network first)
- Intermittently offline: Reduced API calls (fallback to cache during timeouts)
- Fully offline: Zero API calls

**Recommendation:** Monitor API response times. If consistently > 10s, increase `networkTimeoutSeconds` to reduce cache fallbacks.

### Storage Quotas

**Browser storage limits:**
- Chrome/Edge: ~60% of disk space (temporary storage)
- Safari: ~1GB on desktop, ~500MB on iOS
- Firefox: ~50% of disk space

**Stadion PWA cache estimate:**
- Precached assets: ~2-3MB (JS/CSS bundles)
- API cache (50 entries): ~1-2MB (JSON responses)
- Media cache (100 entries): ~10-20MB (profile photos)
- **Total:** ~15-25MB per user

**Quota exceeded handling:**
```javascript
// Service worker automatically evicts oldest entries
// No manual handling required with Workbox
workbox: {
  runtimeCaching: [
    {
      // ... config
      options: {
        expiration: {
          maxEntries: 50, // Automatic LRU eviction
          maxAgeSeconds: 5 * 60
        }
      }
    }
  ]
}
```

**Monitoring:** Browser DevTools → Application → Storage → Cache Storage

## Performance Impact

### Initial Load

**Before PWA:**
- First visit: Load HTML → JS → CSS → API requests
- Return visit: Browser cache (if not expired)

**After PWA:**
- First visit: Same as before + service worker installation (~200ms overhead)
- Return visit: Service worker active, instant load from cache

**Metrics:**
- Time to Interactive (TTI): +200ms on first visit, -1000ms on repeat visits
- Largest Contentful Paint (LCP): No change first visit, -500ms repeat visits

### API Request Performance

**Online, good connection:**
- Before PWA: Direct API request
- After PWA: +20ms overhead (service worker interception, still makes network request)

**Online, poor connection:**
- Before PWA: 2-10s wait, possible timeout
- After PWA: 10s timeout → cache fallback (~50ms)

**Offline:**
- Before PWA: Request fails, UI breaks
- After PWA: Instant cache response (~50ms)

**Recommendation:** NetworkFirst strategy optimizes for online performance while providing offline fallback.

### Build Performance

**Build time impact:**
- Before PWA: ~10-15s (Vite build)
- After PWA: +2-3s (Workbox service worker generation)
- Total: ~12-18s

**Disk usage:**
- Additional files: `sw.js` (~50KB), `manifest.webmanifest` (~1KB)
- Negligible impact on deployment size

## Sources

### High Confidence (Official Documentation)

- [Vite Plugin PWA Guide](https://vite-pwa-org.netlify.app/guide/) - Official Vite PWA plugin documentation
- [TanStack Query Network Mode](https://tanstack.com/query/v4/docs/framework/react/guides/network-mode) - Official TanStack Query docs on network modes
- [MDN Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API/Using_Service_Workers) - Authoritative web standards reference
- [web.dev PWA Service Workers](https://web.dev/learn/pwa/service-workers) - Google's official PWA learning resource

### Medium Confidence (Community Best Practices)

- [Making totally offline-available PWAs with Vite and React](https://adueck.github.io/blog/caching-everything-for-totally-offline-pwa-vite-react/) - Real-world implementation guide
- [How to turn your Vite React App into a PWA with Ease](https://www.timsanteford.com/posts/transform-your-vite-react-app-into-a-pwa-with-ease/) - Tutorial on Vite PWA integration
- [Offline React Query](https://tkdodo.eu/blog/offline-react-query) - TanStack Query offline strategies by TkDodo (TanStack maintainer)
- [Supporting Offline Mode in TanStack Query](https://lucas-barake.github.io/persisting-tantsack-query-data-locally/) - Offline mode implementation patterns

### Low Confidence (Requires Verification)

- [WordPress PWA Service Worker Integration](https://github.com/GoogleChromeLabs/pwa-wp/wiki/Service-Worker) - GoogleChromeLabs WordPress PWA plugin (archived project, patterns still relevant)
- [Implementing A Service Worker For Single-Page App WordPress Sites](https://www.smashingmagazine.com/2017/10/service-worker-single-page-application-wordpress-sites/) - 2017 article, patterns outdated but architectural concepts valid

## Confidence Assessment

| Area | Level | Rationale |
|------|-------|-----------|
| Vite PWA Plugin Integration | HIGH | Official documentation, well-established plugin |
| Service Worker Scope | HIGH | Web standards (MDN), verified with multiple sources |
| TanStack Query Offline Mode | MEDIUM | Official docs limited, community patterns validated |
| WordPress Admin Exclusion | MEDIUM | Community patterns, no official WordPress PWA guidance |
| Cache Strategies | HIGH | Workbox official patterns, standard web performance practice |

## Open Questions (for Phase-Specific Research)

1. **Background sync:** Should form submissions queue when offline? (Defer to Phase 7)
2. **Push notifications:** Are reminders better as push vs email? (Defer to separate milestone)
3. **Install prompt timing:** When should install banner appear? (Defer to Phase 7, UX testing)
4. **iOS install instructions:** How to guide iOS users through "Add to Home Screen"? (Defer to Phase 2, UX content)
5. **Cache invalidation:** Should admin actions invalidate frontend caches? (Defer to Phase 6, test first)
