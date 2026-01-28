# Technology Stack - PWA Features

**Project:** Stadion (Personal CRM)
**Researched:** 2026-01-28
**Confidence:** HIGH

## Executive Summary

Adding PWA capabilities to the existing Vite + React + WordPress stack requires minimal additional dependencies. The recommended approach uses `vite-plugin-pwa` with Workbox's `generateSW` strategy for automatic service worker generation, custom React hooks for install prompts, and `react-simple-pull-to-refresh` for mobile pull-to-refresh functionality.

## Recommended Stack

### Core PWA Plugin

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `vite-plugin-pwa` | ^0.20+ | PWA generation | Zero-config integration with Vite, handles manifest generation and service worker creation. Requires Vite 5+ (already satisfied). Actively maintained by Vite ecosystem. |
| `workbox-window` | ^7.3+ | Service worker registration | Runtime library for service worker lifecycle management. Auto-installed as peer dependency of vite-plugin-pwa. |

**Rationale:** `vite-plugin-pwa` is the de facto standard for Vite-based PWAs. It wraps Workbox's `generateSW` and `injectManifest` methods, providing seamless integration with Vite's build pipeline. Version 0.17+ requires Vite 5, which this project already uses.

### Pull-to-Refresh

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `react-simple-pull-to-refresh` | ^1.3.3 | Mobile pull-to-refresh gesture | Zero dependencies, 40K+ weekly downloads, works on both mobile and desktop. Simple async/await API integrates cleanly with TanStack Query's refetch methods. |

**Rationale:** Among React pull-to-refresh libraries, `react-simple-pull-to-refresh` has the highest adoption (40K weekly downloads vs 667 for alternatives) and zero dependencies. While last updated 3 years ago, it's feature-complete and stable. Alternative considered: building custom hook using touch events, but this library provides cross-browser compatibility and resistance customization out of the box.

### Install Prompt (No External Library)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Custom React Hook | N/A | Capture beforeinstallprompt | Native browser API, no library needed. Chrome/Edge/Samsung Internet support only. iOS Safari does not support programmatic install prompts. |

**Rationale:** The `beforeinstallprompt` event is a browser-native API. Adding a library like `react-pwa-install` adds unnecessary dependencies for functionality that can be implemented in ~20 lines of custom hook code. iOS Safari requires manual "Add to Home Screen" from share menu regardless of library choice.

### PWA Icon Generation (Build-time Tool)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `@vite-pwa/assets-generator` | ^0.2+ | Generate PWA icons | Official tool from vite-pwa ecosystem. Generates all required icon sizes (192x192, 512x512) and maskable variants from single source image. |

**Rationale:** Manually creating 8+ icon variants is error-prone. This CLI tool generates all required sizes and formats (including Apple touch icons) from a single source image. Used during development setup, not a runtime dependency.

## Integration Architecture

### Web App Manifest vs Vite Build Manifest

**Critical:** Vite's `build.manifest: true` generates a `manifest.json` for asset mapping. Web App Manifest also uses `manifest.json`. To avoid conflicts:

- **Vite build manifest:** Remains at `dist/.vite/manifest.json` (Vite 5+ default behavior)
- **Web App Manifest:** Use `manifest.webmanifest` filename (W3C recommended standard)
- `vite-plugin-pwa` automatically handles this separation

Current `vite.config.js` has `manifest: true` for asset mapping. This remains unchanged. `vite-plugin-pwa` generates separate `manifest.webmanifest` for PWA functionality.

### Service Worker Strategy: generateSW vs injectManifest

**Recommendation:** Use `generateSW` (default) strategy.

| Strategy | When to Use | Complexity |
|----------|-------------|------------|
| `generateSW` | Standard offline caching, precaching build assets | Low - Workbox generates SW code |
| `injectManifest` | Custom SW logic, advanced features (background sync, push notifications) | High - Write custom SW code |

**Rationale for generateSW:**
- Milestone scope is basic PWA features (install, offline, pull-to-refresh)
- No background sync or push notifications required yet
- `generateSW` provides runtime caching for WordPress REST API via configuration
- Can migrate to `injectManifest` later if advanced features needed

### Runtime Caching Strategy

For WordPress REST API endpoints, configure Workbox runtime caching:

```javascript
workbox: {
  runtimeCaching: [
    {
      urlPattern: /^https:\/\/.*\.stadion\..*\/wp-json\/.*/i,
      handler: 'NetworkFirst',
      options: {
        cacheName: 'api-cache',
        expiration: {
          maxEntries: 50,
          maxAgeSeconds: 5 * 60, // 5 minutes
        },
        networkTimeoutSeconds: 10,
      },
    },
  ],
}
```

**Strategy rationale:**
- **NetworkFirst** for `/wp-json/` endpoints: Tries network first, falls back to cache on failure. Ensures fresh data when online while providing offline fallback.
- **CacheFirst** for static assets (CSS, JS, images): vite-plugin-pwa handles this automatically via precaching.
- **StaleWhileRevalidate** not recommended for WordPress REST: Would show stale data immediately, causing confusing UX when data has changed server-side.

## Installation

```bash
# Core PWA dependencies
npm install -D vite-plugin-pwa

# Pull-to-refresh
npm install react-simple-pull-to-refresh

# Icon generation (one-time setup)
npm install -D @vite-pwa/assets-generator
```

## Configuration Changes Required

### 1. Update vite.config.js

```javascript
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      manifest: {
        name: 'Stadion Personal CRM',
        short_name: 'Stadion',
        description: 'Personal relationship management system',
        theme_color: '#ffffff',
        icons: [
          {
            src: 'pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
          {
            src: 'pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        runtimeCaching: [
          {
            urlPattern: /^https:\/\/.*\.stadion\..*\/wp-json\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 50,
                maxAgeSeconds: 300,
              },
              networkTimeoutSeconds: 10,
            },
          },
        ],
      },
    }),
  ],
  // Existing config remains unchanged
});
```

### 2. Add PWA Icons to public/

Generate icons using assets-generator, place in `public/` directory:
- `pwa-192x192.png`
- `pwa-512x512.png`
- `apple-touch-icon.png` (180x180 for iOS)

### 3. Custom React Hook for Install Prompt

No external library needed. Implement custom hook:

```javascript
// src/hooks/usePWAInstall.js
import { useState, useEffect } from 'react';

export function usePWAInstall() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isInstallable, setIsInstallable] = useState(false);

  useEffect(() => {
    const handler = (e) => {
      e.preventDefault();
      setDeferredPrompt(e);
      setIsInstallable(true);
    };

    window.addEventListener('beforeinstallprompt', handler);

    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  const promptInstall = async () => {
    if (!deferredPrompt) return;

    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;

    setDeferredPrompt(null);
    setIsInstallable(false);

    return outcome === 'accepted';
  };

  return { isInstallable, promptInstall };
}
```

## Alternatives Considered

### Service Worker Management

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| vite-plugin-pwa | Custom service worker | Requires manual Workbox setup, no Vite integration, increases maintenance burden |
| vite-plugin-pwa | Parcel/Webpack plugins | Not compatible with Vite |

### Pull-to-Refresh Libraries

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| react-simple-pull-to-refresh | react-pull-to-refresh | Lower adoption (3K vs 40K weekly downloads), similar API |
| react-simple-pull-to-refresh | Custom touch event implementation | Requires handling touch events, resistance physics, scroll detection—all already solved by library |
| react-simple-pull-to-refresh | Native CSS overscroll-behavior | Only works on Chromium browsers, no iOS Safari support |

### Install Prompt Libraries

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| Custom hook | react-pwa-install | Adds 50KB dependency for ~20 lines of code. Library handles iOS Safari detection but can't bypass Apple's restrictions anyway. |
| Custom hook | pwa-install | Similar issue—unnecessary dependency for simple event handler |

## iOS Safari Limitations (Critical)

**SERVICE WORKER:** Supported but with severe restrictions.
- Background execution extremely limited (battery conservation)
- Cache storage limited to 50MB
- Service worker may be killed aggressively when app in background

**INSTALL PROMPT:** NOT supported.
- No `beforeinstallprompt` event fires on iOS Safari
- Must use manual "Add to Home Screen" from share menu
- No programmatic prompt possible

**PUSH NOTIFICATIONS:** Supported only on iOS 16.4+ (outside EU).
- In EU, Apple removed standalone PWA support in iOS 17.4
- PWAs open in Safari tabs instead of standalone windows
- Push notifications don't work in EU

**RECOMMENDATION:** Design UI to gracefully handle absence of install prompt on iOS. Show instruction modal with screenshot of share menu for iOS users.

## Platform Support Matrix

| Feature | Chrome/Edge Desktop | Chrome Android | iOS Safari | Samsung Internet |
|---------|---------------------|----------------|------------|------------------|
| Service Worker | Full | Full | Limited | Full |
| Install Prompt | Yes | Yes | No | Yes |
| Pull-to-Refresh | Yes (via library) | Yes (native + library) | Yes (via library) | Yes |
| Offline Caching | Yes | Yes | Yes (50MB limit) | Yes |
| Standalone Mode | Yes | Yes | Yes (No in EU) | Yes |

## Build Pipeline Integration

### Development Mode
- `npm run dev` - Vite dev server on port 5173
- Service worker NOT active in dev (prevents caching issues)
- Enable via `devOptions: { enabled: true }` if testing SW locally

### Production Build
- `npm run build` - Generates:
  - `dist/.vite/manifest.json` (Vite asset manifest)
  - `dist/manifest.webmanifest` (Web App Manifest)
  - `dist/sw.js` (Service worker)
  - `dist/workbox-*.js` (Workbox runtime)
  - All existing assets (CSS, JS, images)

### Deployment
- Existing `bin/deploy.sh` script works unchanged
- Syncs entire `dist/` folder including new SW files
- Service worker registers automatically on first page load

## Version Compatibility

| Dependency | Minimum Version | Current Project | Compatible |
|------------|-----------------|-----------------|------------|
| Node.js | 18+ | 18+ | Yes |
| Vite | 5.0+ | 5.0+ | Yes |
| React | 16.8+ (hooks) | 18+ | Yes |
| PHP | 8.0+ | 8.0+ | Yes |

## Migration Path to Advanced Features

If future milestones require advanced PWA features:

### Background Sync (for offline actions)
- Switch to `injectManifest` strategy
- Add custom SW code with Workbox BackgroundSync
- Complexity: Medium

### Push Notifications
- Switch to `injectManifest` strategy
- Add backend support for Web Push API (PHP library)
- Add notification permission UI
- Complexity: High
- Note: Won't work on iOS in EU

### Periodic Background Sync
- Switch to `injectManifest` strategy
- Limited browser support (Chrome 80+, not iOS)
- Complexity: Medium

**Current recommendation:** Stay with `generateSW` until these features are explicitly required.

## Security Considerations

### Service Worker Scope
- Service worker at `/wp-content/themes/stadion/dist/sw.js` has scope of `/wp-content/themes/stadion/dist/`
- **Issue:** Won't intercept requests to WordPress root or other paths
- **Solution:** Configure `scope: '/'` in VitePWA options and serve SW from root via WordPress routing

### HTTPS Requirement
- Service workers require HTTPS (or localhost for dev)
- Production environment must serve over HTTPS
- Current deployment: Verify SSL certificate is active

### Cache Poisoning
- NetworkFirst strategy prevents serving indefinitely stale data
- 5-minute cache expiration for API responses
- Workbox automatically versions precached assets

## Performance Impact

### Bundle Size
- `vite-plugin-pwa`: 0 KB (build-time only)
- `workbox-window`: ~4 KB gzipped (runtime)
- `react-simple-pull-to-refresh`: ~3 KB gzipped
- **Total runtime impact:** ~7 KB additional bundle size

### Service Worker Overhead
- Initial SW registration: ~50ms
- Precache size: ~500 KB (estimated for current React app)
- API cache: 50 entries max, 5-minute TTL, minimal storage impact

### Perceived Performance
- **First visit:** Slightly slower (SW registration + precache download)
- **Repeat visits:** Significantly faster (instant load from cache)
- **Offline:** Functional instead of broken

## Testing Requirements

### Browser Testing
- Chrome/Edge: Full PWA features
- Firefox: Service worker only (no install prompt)
- Safari macOS: Service worker only (no install prompt)
- Safari iOS: Service worker (limited), no install prompt
- Chrome Android: Full PWA features

### Testing Checklist
- [ ] Install prompt appears on Chrome/Edge desktop and Android
- [ ] App installs successfully
- [ ] Offline mode works (disconnect network, app still loads)
- [ ] Pull-to-refresh triggers data refetch on mobile
- [ ] iOS Safari shows app in standalone mode (when added to home screen)
- [ ] Service worker updates automatically on new deployments

## Sources

### Official Documentation (HIGH confidence)
- [Vite Plugin PWA Guide](https://vite-pwa-org.netlify.app/guide/)
- [Workbox Strategies - Chrome for Developers](https://developer.chrome.com/docs/workbox/modules/workbox-strategies)
- [MDN: Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
- [MDN: Progressive Web Apps](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [BeforeInstallPromptEvent - MDN](https://developer.mozilla.org/en-US/docs/Web/API/BeforeInstallPromptEvent)

### Library Sources (MEDIUM confidence)
- [vite-plugin-pwa GitHub](https://github.com/vite-pwa/vite-plugin-pwa)
- [react-simple-pull-to-refresh npm](https://www.npmjs.com/package/react-simple-pull-to-refresh)
- [react-simple-pull-to-refresh vs alternatives - npm trends](https://npmtrends.com/react-pullable-vs-react-simple-pull-to-refresh)

### Platform Limitations (MEDIUM confidence - rapidly evolving)
- [PWA on iOS - Current Status & Limitations [2025]](https://brainhub.eu/library/pwa-on-ios)
- [PWA iOS Limitations and Safari Support Guide](https://www.magicbell.com/blog/pwa-ios-limitations-safari-support-complete-guide)
- [Do PWAs Work on iPhone?](https://www.mobiloud.com/blog/progressive-web-apps-ios)

### Technical Implementation (MEDIUM confidence)
- [Advanced Caching Strategies with Workbox](https://medium.com/animall-engineering/advanced-caching-strategies-with-workbox-beyond-stalewhilerevalidate-d000f1d27d0a)
- [Vite manifest.json vs Web App Manifest - GitHub Issue #9636](https://github.com/vitejs/vite/issues/9636)
- [Progressive Web App (PWA) with Vite Development Guide](https://dev.to/hamdankhan364/simplifying-progressive-web-app-pwa-development-with-vite-a-beginners-guide-38cf)
