# PWA Implementation Pitfalls

**Domain:** Progressive Web App Implementation for React SPA + WordPress Backend
**Researched:** 2026-01-28
**Confidence:** HIGH (based on official documentation, Vite PWA docs, and verified community reports)

## Critical Pitfalls

Mistakes that cause rewrites, major UX issues, or require significant refactoring.

---

### Pitfall 1: iOS PWAs Lose All Data After 7 Days of Non-Use

**What goes wrong:** Users open the PWA after not using it for 7+ days and all their cached data is gone. Service worker cache, IndexedDB, localStorage - everything wiped clean.

**Why it happens:** Starting with iOS 13.4, Apple implemented a 7-day cap on all script-writable storage for PWAs. If the PWA isn't opened within 7 days, iOS purges all stored data to "conserve storage."

**Consequences:**
- Users forced to re-fetch all data from network
- Offline mode completely broken for inactive users
- Poor experience for casual users who don't open app daily
- In Stadion's case: contact list, activity history, dashboard widgets all need re-loading

**Prevention:**
- **Accept this limitation** - there is no workaround for iOS PWA data persistence
- Design for graceful degradation: treat all offline data as transient cache, not storage
- Implement fast re-hydration: optimize initial data fetch on cold start
- Use TanStack Query's existing cache as primary mechanism (it already handles stale data well)
- Add loading states that handle cold start gracefully
- Consider keeping truly critical settings in backend user preferences
- Document this limitation for users (set expectations)

**Detection:**
- User complaints about "having to reload everything"
- Analytics showing high initial load times for returning users
- Support tickets from users who open app infrequently

**Phase to address:** Phase 1 (PWA Setup) - Document limitation, Phase 2 (Cache Strategy) - Design around it

**Sources:**
- [PWA on iOS - Current Status & Limitations](https://brainhub.eu/library/pwa-on-ios)
- [iOS PWA Limitations and Safari Support](https://www.magicbell.com/blog/pwa-ios-limitations-safari-support-complete-guide)

---

### Pitfall 2: Service Worker + TanStack Query Double-Caching Conflict

**What goes wrong:** Users see stale data even after TanStack Query claims to have fetched fresh data. React Query refetches, but service worker serves cached API responses, making refetch pointless.

**Why it happens:** Two cache layers (service worker + TanStack Query) with no coordination. When using StaleWhileRevalidate strategy in service worker, it serves cached data first, updates cache in background. By the time TanStack Query refetches, service worker background update may not be complete yet.

**Consequences:**
- Users see outdated contact information
- Dashboard shows stale counts/data
- "Refresh" button appears broken
- Data inconsistencies between views
- TanStack Query's smart invalidation becomes useless

**Prevention:**
- **Option 1 (Recommended): Network-first for API routes**
  - Configure service worker to use Network First strategy for `/wp-json/` and `/wp/v2/` paths
  - Only cache API responses as fallback for offline mode
  - Let TanStack Query handle all caching for online mode

- **Option 2: Set networkMode in TanStack Query**
  - Use `networkMode: 'offlineFirst'` in default query options
  - This makes React Query aware of the service worker cache layer
  - First request hits cache = success state, cache miss = retries

- **Option 3: Don't cache API responses in service worker at all**
  - Only precache static assets (JS, CSS, fonts, images)
  - Let WordPress REST API responses bypass service worker
  - TanStack Query provides sufficient caching for online mode

- **Recommended for Stadion:** Use Option 1 or 3 - WordPress REST API responses should not be cached by service worker. TanStack Query already provides optimal caching with invalidation.

**Detection:**
- Users report "refresh doesn't work"
- Data appears stale despite network requests showing 200 OK
- Manual hard refresh (Cmd+Shift+R) fixes the issue
- Developers see cache hits in service worker logs but data is old

**Phase to address:** Phase 2 (Cache Strategy Configuration) - CRITICAL

**Sources:**
- [React Query's Integration with Service Workers - GitHub Issue #7897](https://github.com/TanStack/query/issues/7897)
- [React Query's Integration with Service Workers - Discussion #8034](https://github.com/TanStack/query/discussions/8034)
- [Offline React Query - TkDodo's blog](https://tkdodo.eu/blog/offline-react-query)

---

### Pitfall 3: skipWaiting() Causes Version Mismatch and Broken Pages

**What goes wrong:** New service worker activates while user is actively using the app. Page has old JS bundle loaded, new service worker serves new assets for subsequent requests. Result: mismatched versions, broken functionality, React errors.

**Why it happens:** Using `skipWaiting()` makes new service worker activate immediately, even if user has tabs open with old version. Those tabs were loaded with old JS/CSS but are now served by new service worker that expects new asset names/structure.

**Consequences:**
- Page suddenly stops working mid-session
- React hydration errors
- Missing chunks or 404s for old bundle files
- User forced to hard refresh (but doesn't know to do this)
- Support tickets like "app just stopped working"

**Prevention:**
- **Default: Don't use skipWaiting()**
  - Let new service worker wait until all tabs are closed
  - User gets update naturally on next visit
  - No mid-session disruption

- **If updates are critical (security fixes):**
  - Show a non-intrusive notification: "Update available. Refresh to get the latest version."
  - Let user control when to refresh
  - On user acceptance, call `skipWaiting()` and then `window.location.reload()`
  - Never force update without user consent

- **For Vite PWA Plugin:**
  - Use `registerType: 'prompt'` instead of `registerType: 'autoUpdate'`
  - Implement custom UI for update prompts
  - Test the update flow thoroughly

**Detection:**
- Random crashes in production
- Error logs showing "ChunkLoadError" or "Failed to fetch dynamically imported module"
- Sentry errors spiking after deployment
- Support tickets immediately after deploy

**Phase to address:** Phase 1 (Service Worker Setup) - CRITICAL decision point

**Sources:**
- [Handling Service Worker Updates](https://whatwebcando.today/articles/handling-service-worker-updates/)
- [Workbox - Handling Service Worker Updates](https://developer.chrome.com/docs/workbox/handling-service-worker-updates)
- [skipWaiting with StaleWhileRevalidate the right way](https://allanchain.github.io/blog/post/pwa-skipwaiting/)
- [Rich Harris - Stuff I wish I'd known about service workers](https://gist.github.com/Rich-Harris/fd6c3c73e6e707e312d7c5d7d0f3b2f9)

---

### Pitfall 4: iOS Pull-to-Refresh Conflicts with App Scrolling

**What goes wrong:** On iOS, dragging down at the top of the page triggers browser pull-to-refresh, reloading the entire PWA and losing app state. Extremely frustrating for users who accidentally trigger it while scrolling.

**Why it happens:** iOS PWAs in standalone mode still have pull-to-refresh gesture enabled by default. Unlike native apps where you control this behavior, PWAs inherit browser behavior.

**Consequences:**
- Accidental page reloads lose unsaved form data
- User scrolling up triggers unexpected refresh
- Different behavior than Android (which has bottom swipe for back navigation issues)
- Users think app is buggy or unresponsive

**Prevention:**
- **CSS solution (most reliable):**
  ```css
  body {
    overscroll-behavior-y: none;
  }
  ```
  This disables overscroll and prevents pull-to-refresh gesture.

- **Alternative approaches:**
  - Set `overflow: hidden` on body, use scrollable container with `overflow-y: scroll`
  - Add a header element that pins to top and absorbs the gesture

- **Android consideration:**
  - On Android Chrome PWA, default pull-to-refresh can also be annoying
  - Same CSS solution works across platforms

- **Trade-off awareness:**
  - Disabling pull-to-refresh means you need alternative refresh mechanism
  - Add a visible refresh button in UI (Stadion likely has this already)
  - Or implement custom pull-to-refresh within your scrollable containers

**Detection:**
- User complaints about "app keeps refreshing by itself"
- Support tickets about lost form data
- QA testing on iOS devices

**Phase to address:** Phase 1 (PWA Manifest + CSS Setup)

**Sources:**
- [PWA Pull to Refresh Issues](https://www.heltweg.org/posts/checklist-issues-progressive-web-apps-how-to-fix/)
- [iOS PWA Pull to Refresh - Discourse Meta](https://meta.discourse.org/t/ios-pwa-app-pull-to-refresh/343262)
- [How to Force a PWA to Refresh its Content](https://plainenglish.io/blog/how-to-force-a-pwa-to-refresh-its-content)

---

## Platform-Specific Issues

iOS Safari has significant PWA limitations compared to Android Chrome.

---

### Pitfall 5: iOS PWA Has No System Back Button

**What goes wrong:** Users install PWA on iOS, navigate deep into the app, and can't figure out how to go back. The gesture they learned in native apps doesn't work consistently, and there's no visible back button.

**Why it happens:** iOS PWAs in standalone mode lack browser chrome (no address bar, no back button). While iOS 12.2+ added back gesture support, it's unreliable and not discoverable.

**Consequences:**
- Users get stuck in detail views
- Poor UX compared to native apps
- Increased bounce rate from confused users
- Users uninstall PWA and go back to browser

**Prevention:**
- **Add visible in-app navigation:**
  - Prominent back button in header for all detail views
  - Breadcrumb navigation for deeper hierarchies
  - Bottom tab bar for main sections (Stadion likely has this)

- **Test on actual iOS device in standalone mode:**
  - Simulator doesn't accurately reflect PWA experience
  - Test every navigation flow
  - Ensure no "dead ends" where user can't go back

- **React Router considerations:**
  - Use `<Link>` and programmatic navigation (`navigate()`)
  - Ensure browser history works properly
  - Test back button in multiple contexts

**Detection:**
- iOS-specific user complaints about navigation
- High exit rates from detail pages
- Users reopening app from home screen instead of navigating back

**Phase to address:** Phase 1 (UI Review for iOS PWA) - Audit existing navigation patterns

**Sources:**
- [Back button in iOS PWA - Discourse Meta](https://meta.discourse.org/t/back-button-in-ios-pwa/93909)
- [iOS PWA needs reload and back buttons - Invision Community](https://invisioncommunity.com/forums/topic/473218-ios-pwa-needs-reload-and-back-buttons-on-every-page/)
- [Navigation in iOS PWA - GitHub Issue #769](https://github.com/thepracticaldev/dev.to/issues/769)

---

### Pitfall 6: iOS Safe Area Insets Not Handled

**What goes wrong:** On iPhone with notch/Dynamic Island, PWA content gets hidden behind the status bar or home indicator. Fixed headers overlap with notch, bottom buttons get cut off by home indicator.

**Why it happens:** iOS PWAs don't automatically handle safe area insets like native apps do. Developers must explicitly opt-in and add CSS handling.

**Consequences:**
- Content unreadable at top/bottom of screen
- Buttons hidden behind home indicator
- Unprofessional appearance
- Poor UX on modern iPhones

**Prevention:**
- **Required viewport meta tag:**
  ```html
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  ```
  The `viewport-fit=cover` is critical for safe area support.

- **Required CSS:**
  ```css
  body {
    padding-top: env(safe-area-inset-top);
    padding-bottom: env(safe-area-inset-bottom);
    padding-left: env(safe-area-inset-left);
    padding-right: env(safe-area-inset-right);
  }

  /* Or for specific elements: */
  header {
    padding-top: calc(1rem + env(safe-area-inset-top));
  }
  ```

- **Status bar style meta tag:**
  ```html
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  ```
  Options: `default` (white), `black`, or `black-translucent` (transparent with white text)

- **Test on device:**
  - Simulator works, but real device is better
  - Test with standalone mode (added to home screen)
  - Rotate device to test landscape safe areas

**Detection:**
- Visual inspection on iPhone X or newer
- Content appearing behind notch in screenshots
- Users reporting "can't see top of app"

**Phase to address:** Phase 1 (PWA Manifest + HTML Meta Tags)

**Sources:**
- [PWA viewport, status bar, safe area problems](https://community.flutterflow.io/ask-the-community/post/pwa-viewport-status-bar-safe-area-fullscreen-problems-Y2bqgN9HizO7FGg)
- [Make Your PWAs Look Handsome on iOS](https://dev.to/karmasakshi/make-your-pwas-look-handsome-on-ios-1o08)
- [How to create a blurry status bar for PWAs on iOS](https://danielpietzsch.com/articles/how-to-create-a-blurry-status-bar-for-pwas-on-ios)

---

### Pitfall 7: iOS Ignores Web Manifest Icons, Requires Apple-Specific Tags

**What goes wrong:** iOS PWA shows generic gray icon or screenshot-based icon instead of your carefully crafted app icon.

**Why it happens:** Safari on iOS completely ignores the web manifest's `icons` array. Instead, it requires legacy `<link rel="apple-touch-icon">` meta tags.

**Consequences:**
- Unprofessional appearance on iOS home screen
- Icon looks different than intended
- Users may not recognize the app
- Brand inconsistency

**Prevention:**
- **Provide both web manifest icons AND apple-touch-icon:**
  ```html
  <!-- For iOS -->
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

  <!-- For Android/others via manifest.json -->
  {
    "icons": [
      { "src": "/icon-192.png", "sizes": "192x192", "type": "image/png" },
      { "src": "/icon-512.png", "sizes": "512x512", "type": "image/png" }
    ]
  }
  ```

- **Icon requirements:**
  - iOS: 180x180px minimum, non-transparent (iOS fills transparent areas with color)
  - Android: 192x192 and 512x512 recommended
  - Use square icons with ~80% safe area (Android applies masks)
  - Don't use "any maskable" combined purpose - pick one or the other

- **Maskable icons for Android:**
  - Create separate maskable icon for Android adaptive icons
  - Use purpose: "maskable" in manifest
  - Test with [Maskable.app](https://maskable.app/)

**Detection:**
- Visual inspection after installing PWA
- Icon looks wrong or is a screenshot
- Different icons on iOS vs Android

**Phase to address:** Phase 1 (PWA Icons and Manifest)

**Sources:**
- [PWA manifest icons common mistakes](https://coywolf.com/guides/how-to-create-pwa-icons-that-look-correct-on-all-platforms-and-devices)
- [Why PWA icons shouldn't use 'any maskable'](https://dev.to/progressier/why-a-pwa-app-icon-shouldnt-have-a-purpose-set-to-any-maskable-4c78)
- [PWA Icon Requirements and Safe Areas](https://logofoundry.app/blog/pwa-icon-requirements-safe-areas)

---

### Pitfall 8: EU Users Can't Install PWA (iOS 17.4+)

**What goes wrong:** Users in EU countries try to install PWA on iOS and it just opens in Safari tab. No standalone mode, no home screen icon that launches as app.

**Why it happens:** Apple removed standalone PWA support in EU due to regulatory compliance (Digital Markets Act). iOS 17.4+ in EU regions disables PWA standalone mode entirely.

**Consequences:**
- EU users can't use PWA features
- No offline mode for EU users on iOS
- Different experience based on geography
- Support burden explaining why it doesn't work

**Prevention:**
- **Detection and messaging:**
  - Detect EU users (IP geolocation or user agent signals)
  - Show different messaging: "Add to bookmarks for quick access"
  - Don't promise features that won't work

- **Workarounds (limited):**
  - Encourage Android users or desktop
  - Provide bookmark as alternative
  - Consider building native iOS app if EU market is critical

- **Stay informed:**
  - Apple may reverse this decision
  - Monitor iOS release notes for changes

**Detection:**
- EU user complaints about install not working
- Analytics showing iOS users in EU with no PWA installs

**Phase to address:** Phase 3 (Post-launch monitoring) - Not blocking, but document limitation

**Sources:**
- [PWA iOS Limitations and Safari Support](https://www.magicbell.com/blog/pwa-ios-limitations-safari-support-complete-guide)
- [PWA on iOS - Current Status & Limitations](https://brainhub.eu/library/pwa-on-ios)

---

## Service Worker Gotchas

Common mistakes when implementing service workers with Vite.

---

### Pitfall 9: generateSW Strategy Limits Customization

**What goes wrong:** Team starts with Vite PWA's `generateSW` strategy, then realizes they need custom service worker logic. But `generateSW` doesn't allow custom code, requiring a full rewrite to `injectManifest`.

**Why it happens:** Developers pick the "easy" option without understanding limitations. generateSW is great for basic precaching, but any custom logic requires `injectManifest` strategy.

**Consequences:**
- Late-stage refactoring of service worker approach
- Lost time on generateSW configuration
- More complex migration mid-project

**Prevention:**
- **Choose strategy upfront based on needs:**
  - `generateSW`: Simple precaching only, no custom logic
  - `injectManifest`: Any custom service worker code (runtime caching, background sync, etc.)

- **For Stadion (recommendation):**
  - Start with `injectManifest` if you need:
    - Custom runtime caching rules for WordPress REST API
    - Network-first strategy for API routes
    - Any custom fetch handling
    - Background sync for offline actions
  - Use `generateSW` only if truly just precaching static assets

- **Migration is painful:**
  - Different configuration structure
  - Need to write custom service worker file
  - Workbox API changes

**Detection:**
- Realizing generateSW can't do what you need
- GitHub issues asking "how to add custom code to generateSW"
- Searching for workarounds instead of switching strategies

**Phase to address:** Phase 1 (PWA Plugin Configuration) - Decide strategy BEFORE implementing

**Sources:**
- [Vite PWA Plugin Guide](https://vite-pwa-org.netlify.app/guide/)
- [Service Worker Strategies and Behaviors](https://vite-pwa-org.netlify.app/guide/service-worker-strategies-and-behaviors)
- [How to add custom service worker with generateSW - Discussion #756](https://github.com/vite-pwa/vite-plugin-pwa/discussions/756)

---

### Pitfall 10: Stale Service Worker Due to HTTP Caching

**What goes wrong:** Deploy new version with updated service worker, but users keep getting old service worker file. Browser caches `sw.js` for 24 hours, preventing updates.

**Why it happens:** Server sends cache headers for service worker file, allowing browser to cache it. Service worker update check never happens because browser uses cached file.

**Consequences:**
- Users stuck on old version indefinitely
- Bug fixes don't reach users
- Force-refresh required (but users don't know to do this)

**Prevention:**
- **Server configuration (CRITICAL):**
  - Set `Cache-Control: max-age=0, no-cache` for service worker file
  - Apply to both `/sw.js` and `/workbox-*.js` files
  - WordPress .htaccess rule:
    ```apache
    <FilesMatch "sw\.js$">
      Header set Cache-Control "max-age=0, no-cache, no-store, must-revalidate"
    </FilesMatch>
    ```

- **Verification:**
  - Check response headers in DevTools Network tab
  - Deploy and verify sw.js isn't cached
  - Test update flow on real devices

- **Hosting considerations:**
  - SiteGround (Stadion's host) may have default caching rules
  - Verify cache rules don't override .htaccess
  - May need to configure in SiteGround dashboard

**Detection:**
- Service worker never updates after deploy
- Version number stuck on old build
- Users report not seeing new features

**Phase to address:** Phase 1 (Server Configuration) - Before deploying to production

**Sources:**
- [Cache Validation Issues in React](https://medium.com/@osmancalisir/what-causes-cache-validation-issues-in-react-and-how-can-they-be-resolved-1a9069055c15)
- [Service Worker Updates and Error Handling with React](https://medium.com/@FezVrasta/service-worker-updates-and-error-handling-with-react-1a3730800e6a)

---

### Pitfall 11: precacheAndRoute Caches Wrong Files

**What goes wrong:** Service worker precaches files that shouldn't be cached (API responses, admin pages) or misses files that should be cached (fonts, images).

**Why it happens:** Default `globPatterns` only includes `['**/*.{js,css,html}']`. Other assets need explicit configuration.

**Consequences:**
- Offline mode broken because critical assets missing
- Service worker cache bloated with unnecessary files
- Long initial install time (downloading everything)

**Prevention:**
- **Configure globPatterns explicitly:**
  ```javascript
  VitePWA({
    workbox: {
      globPatterns: [
        '**/*.{js,css,html,ico,png,svg,woff2}' // Add asset types you need
      ],
      globIgnores: [
        '**/node_modules/**',
        '**/test/**',
        '**/*.map'
      ]
    }
  })
  ```

- **Be selective:**
  - Don't precache large images unnecessarily
  - Don't precache admin-only resources
  - Focus on critical path assets

- **Runtime caching for on-demand assets:**
  - Use runtime caching for images loaded dynamically
  - Cache-first for fonts, images
  - Network-first for API calls

**Detection:**
- Offline mode not working
- Console errors about missing assets when offline
- Service worker cache size unexpectedly large

**Phase to address:** Phase 2 (Cache Strategy Refinement)

**Sources:**
- [Vite PWA - Service Worker Precache](https://vite-pwa-org.netlify.app/guide/service-worker-precache)
- [Vite PWA - Service Worker Strategies and Behaviors](https://vite-pwa-org.netlify.app/guide/service-worker-strategies-and-behaviors)

---

### Pitfall 12: Service Worker Scope Issues

**What goes wrong:** Service worker registers but doesn't intercept fetch requests. App behaves like service worker doesn't exist.

**Why it happens:** Service worker scope is limited to its own directory and below. If `sw.js` is in `/dist/`, it can only control `/dist/*` URLs, not root-level URLs.

**Consequences:**
- Service worker installed but not functional
- No caching, no offline mode
- Confusing because registration succeeds

**Prevention:**
- **Vite PWA default scope:**
  - Plugin places sw.js at root by default
  - Should work without configuration

- **If issues occur:**
  - Check `scope` option in service worker registration
  - Verify sw.js is served from correct path
  - WordPress might need rewrite rule for root-level sw.js

- **WordPress consideration:**
  - Theme is in `/wp-content/themes/stadion/`
  - Ensure sw.js accessible at `/sw.js` not `/wp-content/themes/stadion/sw.js`
  - May require WordPress rewrite rule or copy sw.js to root

**Detection:**
- Service worker registers in DevTools but fetch events don't fire
- Cache storage empty despite precache configuration
- Offline mode doesn't work

**Phase to address:** Phase 1 (Service Worker Registration)

**Sources:**
- [Vite PWA - Register Service Worker](https://vite-pwa-org.netlify.app/guide/register-service-worker)
- [Service Worker Scope Issues - Discussion](https://github.com/vite-pwa/vite-plugin-pwa/discussions/756)

---

## Installation and Manifest Issues

Problems preventing PWA installation or causing poor install UX.

---

### Pitfall 13: Missing Manifest Fields Prevent Installation

**What goes wrong:** Users can't install PWA, no install prompt appears, or installation seems to work but PWA doesn't launch properly.

**Why it happens:** Browser requires specific manifest fields to enable installation. Missing any required field silently disables install prompt.

**Consequences:**
- No install prompt on Android
- Can't add to home screen on iOS (different criteria)
- PWA not recognized as installable
- Poor discoverability

**Prevention:**
- **Required manifest fields:**
  ```json
  {
    "name": "Stadion CRM",
    "short_name": "Stadion",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#3b82f6",
    "icons": [
      {
        "src": "/icon-192.png",
        "sizes": "192x192",
        "type": "image/png",
        "purpose": "any"
      },
      {
        "src": "/icon-512.png",
        "sizes": "512x512",
        "type": "image/png",
        "purpose": "any"
      },
      {
        "src": "/icon-maskable-512.png",
        "sizes": "512x512",
        "type": "image/png",
        "purpose": "maskable"
      }
    ]
  }
  ```

- **Additional requirements:**
  - HTTPS (required except localhost)
  - Service worker registered
  - Manifest linked from HTML: `<link rel="manifest" href="/manifest.json">`

- **Testing:**
  - Chrome DevTools > Application > Manifest (shows errors)
  - Lighthouse PWA audit
  - Test install prompt on Android device

**Detection:**
- No install prompt appearing
- Lighthouse failing PWA checks
- DevTools showing manifest errors

**Phase to address:** Phase 1 (PWA Manifest Creation)

**Sources:**
- [Web App Manifest - PWA](https://web.dev/learn/pwa/web-app-manifest)
- [PWA Minimal Requirements - Vite PWA](https://vite-pwa-org.netlify.app/guide/pwa-minimal-requirements)
- [Add to Home Screen - MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Add_to_home_screen)

---

### Pitfall 14: start_url Outside Scope Breaks PWA

**What goes wrong:** PWA installs successfully but clicking icon opens browser tab instead of standalone app, or shows 404 error.

**Why it happens:** `start_url` in manifest points to URL outside service worker scope, or to a URL that doesn't exist/requires authentication.

**Consequences:**
- PWA feels broken after install
- Users uninstall immediately
- Poor first impression

**Prevention:**
- **start_url best practices:**
  - Use relative URL: `"start_url": "/"`
  - Ensure it's within service worker scope
  - Test that URL loads without errors
  - Consider authentication state (don't send to login page)

- **WordPress consideration:**
  - Stadion is single-page app, so `"/"` is correct
  - Ensure WordPress serves React app at root
  - Test with and without trailing slash
  - Verify WordPress rewrite rules work for PWA

- **Testing:**
  - Install PWA and launch from home screen
  - Verify it opens in standalone mode
  - Check URL in address bar (standalone mode hides it)

**Detection:**
- PWA opens in browser instead of standalone
- 404 error when launching PWA
- Users report "install doesn't work"

**Phase to address:** Phase 1 (PWA Manifest Configuration)

**Sources:**
- [Web App Manifest - PWA](https://web.dev/learn/pwa/web-app-manifest)
- [PWA Minimal Requirements - Vite PWA](https://vite-pwa-org.netlify.app/guide/pwa-minimal-requirements)

---

## Update and Versioning Issues

Strategies for handling PWA updates without breaking user experience.

---

### Pitfall 15: No Update Notification, Users Stuck on Old Version

**What goes wrong:** Deploy new version, but users never see it. They continue using cached old version indefinitely until they manually hard refresh (which they don't know to do).

**Why it happens:** Service worker detects update but doesn't notify user or force refresh. Users have no idea a new version exists.

**Consequences:**
- Users miss bug fixes and new features
- Support burden from users on different versions
- Difficult to reproduce issues (which version are they on?)
- Security fixes don't reach users

**Prevention:**
- **Implement update notification:**
  - Use Vite PWA's `registerType: 'prompt'`
  - Show custom UI when update available
  - Let user decide when to update

- **UI patterns:**
  ```javascript
  // Vite PWA provides this
  const { needRefresh, offlineReady, updateServiceWorker } = useRegisterSW();

  // Show banner/toast
  if (needRefresh) {
    // "Update available. Refresh to get the latest version."
    // [Refresh Now] [Later]
  }
  ```

- **Auto-update considerations:**
  - Don't auto-update without warning (breaks user flow)
  - Consider auto-update on app startup (not mid-session)
  - Show loading indicator during update

- **Version tracking:**
  - Use `__BUILD_TIME__` or version number to track deployed version
  - Log version to console for debugging
  - Show version in settings/about page

**Detection:**
- Support tickets from users seeing old bugs you've fixed
- Users reporting missing features you've deployed
- Version number in DevTools doesn't match production

**Phase to address:** Phase 2 (Update UX Implementation)

**Sources:**
- [Handling Service Worker Updates](https://whatwebcando.today/articles/handling-service-worker-updates/)
- [Workbox - Handling Service Worker Updates](https://developer.chrome.com/docs/workbox/handling-service-worker-updates)
- [Vite PWA - Register Service Worker](https://vite-pwa-org.netlify.app/guide/register-service-worker)

---

### Pitfall 16: Hash-based Filenames Cause Orphaned Cache Entries

**What goes wrong:** Every deploy creates new `main.abc123.js` files. Old cache entries accumulate, bloating storage. Storage quota exceeded error eventually appears.

**Why it happens:** Vite generates hashed filenames for cache busting. Service worker precaches new versions but doesn't clean up old ones.

**Consequences:**
- Progressive storage bloat
- QuotaExceededError on devices with limited storage
- Slower app startup (searching through bloated cache)
- iOS 50MB storage limit hit faster

**Prevention:**
- **Workbox cleanupOutdatedCaches:**
  ```javascript
  VitePWA({
    workbox: {
      cleanupOutdatedCaches: true // Removes old precache entries
    }
  })
  ```
  This is usually enabled by default, but verify.

- **Cache versioning:**
  - Service worker install event should clean old caches
  - Use cache version in cache name: `stadion-v1`
  - On version bump, delete old caches

- **Monitoring:**
  - Log cache storage usage
  - Alert if approaching quota (especially for iOS)
  - Implement cache pruning strategy

**Detection:**
- Cache storage growing unbounded
- QuotaExceededError in production logs
- Multiple cache versions visible in DevTools

**Phase to address:** Phase 2 (Cache Management)

**Sources:**
- [Cache Busting a React App](https://dev.to/flexdinesh/cache-busting-a-react-app-22lk)
- [Service Worker Cache Validation Issues](https://medium.com/@osmancalisir/what-causes-cache-validation-issues-in-react-and-how-can-they-be-resolved-1a9069055c15)

---

## Testing and Debugging Pitfalls

Common mistakes that make PWA bugs hard to reproduce and fix.

---

### Pitfall 17: Testing in Browser Instead of Standalone Mode

**What goes wrong:** PWA works perfectly in Chrome/Safari browser, but breaks when installed and launched from home screen.

**Why it happens:** Standalone mode has different behavior:
- Different user agent string
- No browser chrome (no back button, address bar)
- Different safe area insets
- Different behavior for external links

**Consequences:**
- Bugs only appear in production/standalone mode
- Can't reproduce issues during development
- Late discovery of critical issues

**Prevention:**
- **Test in standalone mode during development:**
  - Install PWA locally on test device
  - Use Chrome DevTools device simulation with PWA mode
  - Test all critical flows in standalone mode before deploy

- **iOS-specific testing:**
  - Must test on actual device (simulator is insufficient)
  - Add to home screen and launch from icon
  - Test in airplane mode for offline behavior

- **Automated testing:**
  - Playwright/Puppeteer can test PWA mode
  - Include PWA-specific tests in CI/CD

**Detection:**
- User reports that only happen in installed PWA
- Different behavior between browser and PWA
- iOS-specific issues not caught in development

**Phase to address:** Phase 3 (Testing and QA)

**Sources:**
- [PWA Checklist](https://www.heltweg.org/posts/checklist-issues-progressive-web-apps-how-to-fix/)
- [Navigating Safari/iOS PWA Limitations](https://vinova.sg/navigating-safari-ios-pwa-limitations/)

---

### Pitfall 18: Service Worker Cache Hides Bugs in Development

**What goes wrong:** Fix a bug, reload page, bug still appears. Or can't reproduce bug because service worker is serving cached working version.

**Why it happens:** Service worker aggressively caches everything. Even with HMR, some requests go to service worker cache instead of dev server.

**Consequences:**
- Frustrating development experience
- False positives/negatives during debugging
- Wasted time troubleshooting phantom issues

**Prevention:**
- **Disable service worker in development:**
  - Vite PWA has dev mode that doesn't register SW by default
  - If testing SW in dev, use DevTools "Update on reload" checkbox
  - Clear cache frequently during SW development

- **Chrome DevTools tips:**
  - Application > Service Workers > "Update on reload"
  - Application > Service Workers > "Bypass for network"
  - Right-click refresh > "Empty cache and hard reload"

- **Unregister during active development:**
  ```javascript
  if (import.meta.env.DEV && 'serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistrations().then(registrations => {
      registrations.forEach(r => r.unregister());
    });
  }
  ```

**Detection:**
- Changes not appearing after reload
- HMR not working as expected
- Inconsistent behavior between machines

**Phase to address:** Phase 1 (Development Workflow Setup)

**Sources:**
- [Vite PWA - Service Worker Development](https://vite-pwa-org.netlify.app/guide/)
- [Rich Harris - Service Worker Gotchas](https://gist.github.com/Rich-Harris/fd6c3c73e6e707e312d7c5d7d0f3b2f9)

---

## WordPress-Specific Pitfalls

Issues unique to PWA + WordPress combination.

---

### Pitfall 19: WordPress Nonce Expiration Breaks Offline Actions

**What goes wrong:** User goes offline, performs actions (mark todo complete, add note), comes back online, and all actions fail with 403 Forbidden. WordPress nonce has expired.

**Why it happens:** WordPress nonces expire after 12-24 hours. If user is offline longer than that, or device clock drifts, nonce becomes invalid.

**Consequences:**
- Offline actions appear to succeed but fail when syncing
- User loses data/work
- Confusing error messages
- Poor offline experience

**Prevention:**
- **Detect nonce expiration:**
  - Check for 403 responses on API calls
  - Detect `rest_cookie_invalid_nonce` error
  - Prompt user to refresh to get new nonce

- **Nonce refresh strategy:**
  - Fetch new nonce on app startup if online
  - Before replaying offline queue, verify nonce is valid
  - Store nonce expiration time (12 hours from issue)

- **Background Sync API (future):**
  - Queue failed requests for retry
  - Browser handles retry when connection restored
  - Limited browser support (especially iOS)

- **For Stadion:**
  - TanStack Query mutation queue can help
  - Add nonce validation before mutation execution
  - Show clear error if nonce expired: "Please refresh to continue"

**Detection:**
- 403 errors after offline period
- Mutations failing with nonce errors
- User complaints about lost offline actions

**Phase to address:** Phase 3 (Offline Action Handling)

**Sources:**
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [WordPress Nonces](https://developer.wordpress.org/plugins/security/nonces/)

---

### Pitfall 20: WordPress Cache Plugins Conflict with Service Worker

**What goes wrong:** WordPress cache plugin (WP Super Cache, W3 Total Cache) caches service worker file, preventing updates. Or caches API responses, breaking TanStack Query invalidation.

**Why it happens:** WordPress cache plugins aggressively cache everything, including files that shouldn't be cached.

**Consequences:**
- Service worker never updates
- API responses stale despite invalidation
- Different users see different versions
- Deployment doesn't reach all users

**Prevention:**
- **Exclude from WordPress caching:**
  - Add `/sw.js` to cache exclusion list
  - Exclude `/wp-json/*` from HTML caching
  - Exclude `/manifest.json` from caching

- **SiteGround specific:**
  - SiteGround has built-in caching (not plugin-based)
  - Configure exclusions in SiteGround dashboard
  - Check for CDN caching of service worker

- **Deployment script consideration:**
  - Stadion's deploy script clears caches: `bin/deploy.sh` runs cache flush
  - Verify this includes SG Optimizer cache
  - May need to clear browser cache separately

- **Testing:**
  - Deploy, clear all caches, verify sw.js served with no-cache header
  - Check response headers in production
  - Test from multiple devices/networks

**Detection:**
- Service worker cached with long max-age
- API responses cached when they shouldn't be
- Deployment doesn't reach users immediately

**Phase to address:** Phase 1 (Server Configuration)

**Sources:**
- [WordPress Caching Best Practices](https://developer.wordpress.org/advanced-administration/performance/cache/)
- [SiteGround Optimizer Documentation](https://www.siteground.com/kb/siteground-optimizer/)

---

## Phase-Specific Implementation Checklist

### Phase 1: PWA Foundation

**MUST HANDLE:**
- [ ] Choose service worker strategy (injectManifest vs generateSW)
- [ ] Configure server to NOT cache sw.js (Cache-Control headers)
- [ ] Create manifest.json with all required fields
- [ ] Add apple-touch-icon for iOS
- [ ] Add viewport meta with viewport-fit=cover
- [ ] Add apple-mobile-web-app-status-bar-style
- [ ] Add CSS safe area insets for iOS
- [ ] Add overscroll-behavior-y: none to prevent pull-to-refresh
- [ ] Exclude sw.js and manifest from WordPress cache
- [ ] Test installation on iOS and Android devices
- [ ] Verify back navigation works in standalone mode

**DEFER:**
- Background sync (Phase 3)
- Advanced offline features (Phase 3)
- Push notifications (separate milestone)

---

### Phase 2: Cache Strategy

**MUST HANDLE:**
- [ ] Decide: Network-first for all API routes (recommended)
- [ ] Configure TanStack Query networkMode if caching APIs
- [ ] Configure precache globPatterns for needed assets only
- [ ] Enable cleanupOutdatedCaches
- [ ] Implement update notification UI (registerType: 'prompt')
- [ ] Add version display in UI for debugging
- [ ] Test cache invalidation with TanStack Query

**AVOID:**
- Caching WordPress REST API in service worker (conflicts with TanStack Query)
- Using skipWaiting() without user prompt
- Overly aggressive precaching (bloats cache)

---

### Phase 3: Offline and Polish

**MUST HANDLE:**
- [ ] Offline fallback page
- [ ] Queue failed mutations for retry
- [ ] Nonce expiration detection and handling
- [ ] Test in standalone mode on real devices
- [ ] Test iOS safe area on devices with notch
- [ ] Document 7-day iOS storage limitation for users
- [ ] Test update flow end-to-end

**TESTING:**
- [ ] Install on iPhone (iOS 16+) and Android device
- [ ] Test in airplane mode
- [ ] Deploy update and verify update notification appears
- [ ] Test navigation patterns in standalone mode
- [ ] Verify no content behind notch/home indicator
- [ ] Confirm pull-to-refresh disabled

---

## Prevention Strategies Summary

### High-Impact, Low-Effort
1. Don't cache WordPress REST API responses in service worker (Network-first)
2. Set Cache-Control: no-cache for sw.js
3. Use injectManifest if you need any custom logic
4. Add overscroll-behavior-y: none to prevent pull-to-refresh
5. Include apple-touch-icon for iOS

### Critical Decisions (Make Early)
1. Service worker strategy: generateSW vs injectManifest
2. Update policy: prompt user vs wait for next visit (never auto skipWaiting)
3. What to precache: be selective, focus on critical path
4. iOS limitations: document 7-day storage cap for users

### iOS-Specific Checklist
- [ ] viewport-fit=cover in meta tag
- [ ] CSS safe area insets
- [ ] apple-touch-icon link tag
- [ ] Test in standalone mode on real device
- [ ] Verify back navigation works
- [ ] Check content not behind notch

### Testing Requirements
- [ ] Test on real iOS device in standalone mode
- [ ] Test on Android device with install prompt
- [ ] Test offline mode (airplane mode)
- [ ] Test update flow from old to new version
- [ ] Test after 7+ days of non-use (iOS storage wipe)

---

## Sources

### Official Documentation
- [Web.dev - Progressive Web Apps](https://web.dev/learn/pwa/)
- [MDN - Progressive Web Apps](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Vite PWA Plugin Guide](https://vite-pwa-org.netlify.app/guide/)
- [Workbox Documentation](https://developer.chrome.com/docs/workbox/)

### iOS-Specific Resources
- [PWA iOS Limitations and Safari Support - MagicBell](https://www.magicbell.com/blog/pwa-ios-limitations-safari-support-complete-guide)
- [Navigating Safari/iOS PWA Limitations - Vinova](https://vinova.sg/navigating-safari-ios-pwa-limitations/)
- [PWA on iOS - Current Status & Limitations - Brainhub](https://brainhub.eu/library/pwa-on-ios)
- [Make Your PWAs Look Handsome on iOS - DEV Community](https://dev.to/karmasakshi/make-your-pwas-look-handsome-on-ios-1o08)
- [iOS PWA Compatibility - firt.dev](https://firt.dev/notes/pwa-ios/)

### Service Worker Resources
- [Handling Service Worker Updates - WhatWebCanDo](https://whatwebcando.today/articles/handling-service-worker-updates/)
- [Rich Harris - Stuff I wish I'd known about service workers](https://gist.github.com/Rich-Harris/fd6c3c73e6e707e312d7c5d7d0f3b2f9)
- [Workbox - Handling Service Worker Updates](https://developer.chrome.com/docs/workbox/handling-service-worker-updates)
- [skipWaiting with StaleWhileRevalidate](https://allanchain.github.io/blog/post/pwa-skipwaiting/)

### React + Service Worker Integration
- [React Query + Service Workers - GitHub Issue #7897](https://github.com/TanStack/query/issues/7897)
- [React Query + Service Workers - Discussion #8034](https://github.com/TanStack/query/discussions/8034)
- [Offline React Query - TkDodo's blog](https://tkdodo.eu/blog/offline-react-query)

### Troubleshooting and Patterns
- [PWA Checklist - Philip Heltweg](https://www.heltweg.org/posts/checklist-issues-progressive-web-apps-how-to-fix/)
- [Cache Busting a React App](https://dev.to/flexdinesh/cache-busting-a-react-app-22lk)
- [Service Worker Cache Validation Issues](https://medium.com/@osmancalisir/what-causes-cache-validation-issues-in-react-and-how-can-they-be-resolved-1a9069055c15)

### WordPress Resources
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [WordPress Nonces](https://developer.wordpress.org/plugins/security/nonces/)
- [WordPress Caching Best Practices](https://developer.wordpress.org/advanced-administration/performance/cache/)
