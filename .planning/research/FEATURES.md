# PWA Feature Landscape for Personal CRM

**Domain:** Personal CRM (Contact Management System)
**Researched:** 2026-01-28
**Confidence:** MEDIUM (verified with multiple sources, some iOS-specific behaviors may require testing)

## Executive Summary

PWAs for personal CRM applications in 2026 require a specific set of table stakes features to feel "installable" and "app-like," with clear platform differences between Android and iOS. For a mobile-first CRM where users primarily access on phones to check contacts and log activities, offline capability and home screen installation are critical differentiators from web-only alternatives.

**Key insight:** iOS PWA limitations significantly impact feature availability compared to Android. Storage persistence, lack of automatic install prompts, and 7-day cache eviction require careful architecture decisions.

## Table Stakes Features

Features users expect when a PWA is marketed as "installable" or "mobile-ready." Missing these = product feels incomplete or broken.

| Feature | Why Expected | Complexity | Platform Notes |
|---------|--------------|------------|----------------|
| **Web App Manifest** | Required for installability on all platforms | Low | Android: auto-prompt available. iOS: requires manual "Add to Home Screen" |
| **HTTPS serving** | Security requirement for service workers | Low | Vite dev server supports HTTPS in dev mode |
| **Service Worker registration** | Core PWA requirement for offline/caching | Medium | Use vite-plugin-pwa for zero-config setup |
| **App icons (192x192, 512x512)** | Visual identity on home screen | Low | iOS also needs Apple Touch icons (180x180) |
| **Splash screen** | Native-like launch experience | Low | Auto-generated from manifest on Android; requires static images on iOS |
| **Standalone display mode** | App runs without browser chrome | Low | Set in manifest.json: "display": "standalone" |
| **Basic offline fallback** | Show something when offline, not blank screen | Medium | Cache app shell + show "offline" message for failed requests |
| **Theme color** | Browser UI matches app branding | Low | Set in manifest + meta tag for iOS |

## Differentiators

Features that set this PWA apart from basic web apps. Not expected by default, but add significant value for mobile CRM usage.

| Feature | Value Proposition | Complexity | Dependencies |
|---------|-------------------|------------|--------------|
| **Smart install prompt** | Increases install rate by prompting at right moment | Medium | Android only (beforeinstallprompt API). Show custom UI after user performs 2-3 valuable actions |
| **Pull-to-refresh gesture** | Native-like interaction for refreshing contact list | Medium | Requires disabling default browser pull behavior (overscroll-behavior-y: contain). iOS support is buggy |
| **Offline data access** | View cached contacts/meetings without network | High | IndexedDB + TanStack Query offlineFirst mode. iOS: data evicts after 7 days of non-use |
| **Background sync for mutations** | Queue activity logs when offline, sync when back online | High | Background Sync API (Android Chrome only). iOS requires alternative approach |
| **App shortcuts** | Quick access to "Add Contact" or "Today's Meetings" from home screen icon | Medium | Android only. Defined in manifest.json |
| **Update notification** | Prompt user when new version available | Low | Built into vite-plugin-pwa with React hook (useRegisterSW) |
| **Share target** | Share contacts from other apps into CRM | Medium | Android only. Web Share Target API |

## Anti-Features

Features to explicitly NOT build in this milestone. Common mistakes or premature optimizations.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **Push notifications** | Requires backend infrastructure (FCM/APNS), user permission prompt fatigue, iOS requires v16.4+ and installed PWA | Use existing reminder email system. Revisit post-MVP if user demand exists |
| **Full offline editing** | Complex conflict resolution, requires queueing system, IndexedDB transaction complexity | Show cached data read-only when offline. Allow viewing but not editing |
| **Native app features** (contacts API, calendar integration) | Poor browser support, iOS doesn't support, adds complexity | Stick to web-standard PWA features. Users can manually add to contacts/calendar |
| **Precaching all contacts** | Storage limits (iOS: 50MB cache), wastes bandwidth, slow initial install | Cache app shell only. Fetch contacts on-demand with TanStack Query caching |
| **Custom service worker logic** | Maintenance burden, hard to debug, vite-plugin-pwa covers 90% of use cases | Use vite-plugin-pwa's Workbox strategies. Only customize if specific need arises |
| **iOS-specific web app meta tags overload** | Marginal benefit, maintenance cost, most are deprecated | Stick to Apple Touch icon + viewport meta. Skip status bar styling and obsolete tags |

## Feature Dependencies & Relationships

### Core Installation Flow
```
HTTPS + Manifest + Service Worker
    ↓
Installability criteria met
    ↓
Android: beforeinstallprompt fires → Custom install button → User installs
iOS: User manually adds via Safari Share menu → App installed
```

### Offline Data Access Flow
```
Service Worker (caching strategy)
    +
IndexedDB storage (TanStack Query persistence)
    +
TanStack Query offlineFirst mode
    ↓
Cached data available offline (up to 7 days on iOS)
```

### Update Flow
```
Service Worker detects new version
    ↓
vite-plugin-pwa React hook fires
    ↓
Custom UI shows "Update available" prompt
    ↓
User clicks "Update" → skipWaiting → Page reloads
```

## Platform-Specific Considerations

### Android (Chrome, Edge, Samsung Internet)

**Strengths:**
- Automatic install prompt after engagement criteria met
- beforeinstallprompt API for custom install UI
- Background Sync API for offline mutations
- App shortcuts in manifest
- Web Share Target API
- Reliable IndexedDB persistence
- No arbitrary cache eviction

**Limitations:**
- None significant for this use case

**Recommended approach:** Full feature implementation

### iOS (Safari only)

**Strengths:**
- Push notifications support (iOS 16.4+, requires installed PWA)
- Improved IndexedDB stability in recent versions
- Shares Service Worker + CacheStorage with Safari (iOS 14+)

**Limitations:**
- No automatic install prompt (manual only via Share > Add to Home Screen)
- No beforeinstallprompt API
- Cache storage limit: ~50MB (service worker cache)
- IndexedDB limit: ~500MB (but can be cleared)
- **7-day cache eviction:** If PWA not used for 7 days, all cached data cleared
- No Background Sync API
- No App Shortcuts support
- No Web Share Target API
- Pull-to-refresh buggy in Safari
- Chrome/Edge on iOS don't support PWA installation at all (must use Safari)

**Critical impact:** The 7-day eviction policy means offline data is NOT reliable for infrequent users. Mitigation: Accept this limitation and document clearly.

**Recommended approach:**
- Treat iOS as second-class PWA experience
- Focus on installability + basic offline (app shell caching)
- Don't invest in iOS-exclusive features this milestone
- Provide clear messaging: "For best experience, open app at least weekly"

## MVP Feature Prioritization

For this milestone (adding PWA to existing React SPA), prioritize:

### Phase 1: Basic Installability (1-2 days)
1. Web app manifest with icons
2. Service worker registration (vite-plugin-pwa)
3. HTTPS in production
4. Cache app shell (CSS, JS, fonts)
5. Offline fallback page

**Outcome:** Users can install to home screen (Android auto-prompt, iOS manual). App loads when offline but shows "reconnect" message for data.

### Phase 2: Smart Installation & Updates (1 day)
1. Custom install button (Android only, hidden on iOS)
2. beforeinstallprompt handling
3. Update notification UI using useRegisterSW hook
4. Prompt install after 2-3 interactions (view person, add note)

**Outcome:** Higher install rate on Android. Users get notified when updates available.

### Phase 3: Offline Data Access (2-3 days)
1. TanStack Query persistence with IndexedDB
2. Configure networkMode: 'offlineFirst'
3. Cache recently viewed contacts/meetings
4. Show cached data when offline with "Last synced: X" timestamp
5. Read-only mode when offline (no editing)

**Outcome:** Users can view recently accessed data without network. Clear sync status.

### Defer to Post-MVP

**Pull-to-refresh:** Medium complexity, buggy on iOS, low priority for CRM use case (users rarely need to force refresh)

**Background sync:** High complexity, Android-only, requires queueing mutations. Not critical for MVP.

**Push notifications:** Requires backend work (FCM), user permission fatigue, iOS limitations. Use existing email reminders instead.

**App shortcuts:** Android-only, nice-to-have. Easy to add later if user demand exists.

## Technical Implementation Notes

### Vite Plugin PWA Configuration

Use vite-plugin-pwa with these recommended settings:

```javascript
VitePWA({
  registerType: 'prompt', // Don't auto-reload, prompt user
  includeAssets: ['favicon.ico', 'robots.txt', 'apple-touch-icon.png'],
  manifest: {
    name: 'Stadion CRM',
    short_name: 'Stadion',
    description: 'Personal CRM for managing contacts and teams',
    theme_color: '#1e40af', // Match brand color
    icons: [
      { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
      { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
    ],
    display: 'standalone',
    start_url: '/',
    scope: '/',
  },
  workbox: {
    runtimeCaching: [
      {
        urlPattern: /^https:\/\/api\.stadion\.svawc\.nl\/.*/i, // API calls
        handler: 'NetworkFirst',
        options: {
          cacheName: 'api-cache',
          expiration: { maxEntries: 100, maxAgeSeconds: 86400 }, // 24 hours
        },
      },
    ],
  },
})
```

### TanStack Query Persistence

```javascript
import { PersistQueryClientProvider } from '@tanstack/react-query-persist-client'
import { createSyncStoragePersister } from '@tanstack/query-sync-storage-persister'

const persister = createSyncStoragePersister({
  storage: window.localStorage, // Use IndexedDB for larger datasets
})

// In app setup
<PersistQueryClientProvider
  client={queryClient}
  persistOptions={{ persister }}
>
  <App />
</PersistQueryClientProvider>
```

Configure queries with offline-first mode:

```javascript
useQuery({
  queryKey: ['person', id],
  queryFn: fetchPerson,
  networkMode: 'offlineFirst', // Critical for PWA
  staleTime: 1000 * 60 * 5, // 5 minutes
})
```

## Success Metrics

How to measure if PWA features are successful:

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Install rate (Android) | 15-20% of weekly active users | Track beforeinstallprompt → actual install |
| Install rate (iOS) | 5-8% (lower due to manual flow) | Track standalone display mode visits |
| Offline access usage | 10%+ of sessions start offline | Track service worker cache hits vs network |
| Update acceptance rate | 80%+ click "Update" when prompted | Track update prompt → reload action |
| Return visit rate (installed) | 2x higher than web-only | Compare installed users vs browser users |

## Confidence Assessment

| Area | Level | Reason |
|------|-------|--------|
| Android features | HIGH | Well-documented, stable APIs, multiple verified sources |
| iOS limitations | MEDIUM | Documented in multiple sources, but specific behaviors (7-day eviction) may vary by iOS version |
| vite-plugin-pwa | HIGH | Official documentation, active maintenance, proven in production |
| TanStack Query offline | MEDIUM | Official docs + community examples, but integration patterns require testing |
| Offline sync complexity | MEDIUM | Multiple implementation patterns exist; need to choose based on actual usage |

## Open Questions for Implementation

1. **Storage strategy:** Use localStorage (simple) or IndexedDB (more storage) for TanStack Query persistence?
   - Recommendation: Start with localStorage for MVP, migrate to IndexedDB if hitting limits

2. **Cache duration:** How long should API responses be cached?
   - Recommendation: 24 hours for contacts, 1 hour for meetings (more time-sensitive)

3. **Install prompt timing:** When to show install button?
   - Recommendation: After viewing 2 people OR adding 1 note (engaged user signal)

4. **iOS install instructions:** Show modal with screenshots?
   - Recommendation: Yes, but only show once per device, use subtle hint after

5. **Offline editing:** Allow or block?
   - Recommendation: Block for MVP (read-only offline), add queuing in future milestone if demanded

## Sources

### PWA Installation & Manifest
- [Making PWAs installable - MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable)
- [Installation prompt - web.dev](https://web.dev/learn/pwa/installation-prompt)
- [iOS PWA install - Brainhub](https://brainhub.eu/library/pwa-on-ios)
- [Android manifest requirements - MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable)

### Offline Support & Service Workers
- [PWA offline strategies - MDN](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Offline_and_background_operation)
- [Service worker caching strategies - MagicBell](https://www.magicbell.com/blog/offline-first-pwas-service-worker-caching-strategies)
- [PWA capabilities 2026 - Progressier](https://progressier.com/pwa-capabilities)

### iOS Limitations
- [PWA iOS limitations - MagicBell](https://www.magicbell.com/blog/pwa-ios-limitations-safari-support-complete-guide)
- [Safari PWA storage persistence - Vinova](https://vinova.sg/navigating-safari-ios-pwa-limitations/)
- [iOS PWA status 2025 - Brainhub](https://brainhub.eu/library/pwa-on-ios)

### React & Vite Implementation
- [vite-plugin-pwa React docs](https://vite-pwa-org.netlify.app/frameworks/react)
- [TanStack Query network modes](https://tanstack.com/query/v4/docs/framework/react/guides/network-mode)
- [TanStack Query offline - TkDodo](https://tkdodo.eu/blog/offline-react-query)

### Pull-to-Refresh
- [Pull to refresh implementation - DEV](https://dev.to/chicio/implement-a-pull-to-refresh-component-for-you-web-application-1pcg)
- [iOS PWA pull to refresh issues - Discourse](https://meta.discourse.org/t/ios-pwa-app-pull-to-refresh/343262)

### PWA Market Trends
- [Best PWA frameworks 2026 - WebOsmotic](https://webosmotic.com/blog/pwa-frameworks/)
- [PWA vs Native 2026 - Progressier](https://progressier.com/pwa-vs-native-app-comparison-table)
