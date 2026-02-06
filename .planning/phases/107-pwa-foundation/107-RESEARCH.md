# Phase 107: PWA Foundation - Research

**Researched:** 2026-01-28
**Domain:** Progressive Web App (PWA) - Vite + React + WordPress
**Confidence:** HIGH

## Summary

This phase makes Stadion installable on iOS and Android devices with proper platform-specific support. The research covers vite-plugin-pwa integration with the existing Vite 5.0 build system, service worker configuration, manifest generation, iOS-specific requirements (splash screens, safe areas, status bar), and dynamic theme color handling.

The standard approach is vite-plugin-pwa with generateSW strategy, which automatically generates service workers from configuration. For iOS splash screens, pwa-asset-generator is the established tool that scrapes Apple Human Interface Guidelines for all device sizes. The WordPress theme (PHP) requires manual meta tag injection since vite-plugin-pwa's HTML injection targets index.html files, not PHP templates.

**Primary recommendation:** Use vite-plugin-pwa with generateSW strategy, pwa-asset-generator for iOS splash screens, and manual PHP meta tag injection in index.php for PWA-specific tags that vite-plugin-pwa cannot inject.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| vite-plugin-pwa | ^0.20.x | PWA plugin for Vite | Official Vite PWA solution, 600k+ weekly downloads, maintained by vite-pwa org |
| workbox-window | ^7.0.0 | Service worker window API | Required peer dependency for React hook |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @vite-pwa/assets-generator | ^0.2.x | PWA icon generation | Generate icons from SVG source |
| pwa-asset-generator | ^6.x | iOS splash screens | Generate all iOS splash screen sizes |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| generateSW | injectManifest | injectManifest gives full SW control but requires writing custom SW code; generateSW simpler for our needs |
| pwa-asset-generator | @vite-pwa/assets-generator | @vite-pwa handles icons but NOT iOS splash screens; pwa-asset-generator handles both |
| Manual manifest | vite-plugin-pwa manifest | Plugin auto-generates; we use it but inject additional iOS meta tags via PHP |

**Installation:**
```bash
npm install -D vite-plugin-pwa
npm install -D pwa-asset-generator
```

## Architecture Patterns

### Recommended Project Structure
```
rondo-club/
├── public/                     # Static assets served from root
│   ├── icons/                  # PWA icons (all sizes)
│   │   ├── icon-64x64.png
│   │   ├── icon-192x192.png
│   │   ├── icon-512x512.png
│   │   ├── icon-512x512-maskable.png
│   │   └── apple-touch-icon-180x180.png
│   ├── splash/                 # iOS splash screens
│   │   ├── apple-splash-*.png  # All device sizes
│   │   └── apple-splash-dark-*.png  # Dark mode variants
│   ├── favicon.ico             # ICO for legacy browsers
│   └── favicon.svg             # SVG favicon (accent-colored)
├── src/
│   ├── components/
│   │   └── ReloadPrompt.jsx    # Update prompt component
│   └── sw-registration.js      # Service worker registration
├── vite.config.js              # PWA plugin configuration
├── pwa-assets.config.js        # Asset generator config
└── index.php                   # PWA meta tags injected here
```

### Pattern 1: vite-plugin-pwa Configuration
**What:** Configure PWA plugin for WordPress/Vite SPA
**When to use:** Always - this is the core PWA configuration

**Example:**
```javascript
// vite.config.js
// Source: https://vite-pwa-org.netlify.app/guide/
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'prompt',  // User controls updates (per CONTEXT.md)
      injectRegister: 'auto',

      manifest: {
        name: 'Stadion',
        short_name: 'Stadion',
        description: 'Club data management',
        theme_color: '#f97316',  // Default orange, overridden by meta tag at runtime
        background_color: '#ffffff',
        display: 'standalone',
        orientation: 'any',
        start_url: '/dashboard',
        scope: '/',
        categories: ['sports'],
        icons: [
          { src: '/icons/icon-64x64.png', sizes: '64x64', type: 'image/png' },
          { src: '/icons/icon-192x192.png', sizes: '192x192', type: 'image/png' },
          { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png' },
          { src: '/icons/icon-512x512-maskable.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' }
        ]
      },

      workbox: {
        // Precache all static build assets
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],

        // NetworkFirst for API to avoid double-caching with TanStack Query
        runtimeCaching: [
          {
            urlPattern: /^https:\/\/.*\/wp-json\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: {
                maxEntries: 100,
                maxAgeSeconds: 60 * 60  // 1 hour
              },
              cacheableResponse: {
                statuses: [0, 200]
              }
            }
          }
        ],

        // Clean up old caches
        cleanupOutdatedCaches: true
      }
    })
  ]
})
```

### Pattern 2: React Update Prompt Component
**What:** UI component for prompting users about app updates
**When to use:** When registerType is 'prompt'

**Example:**
```jsx
// src/components/ReloadPrompt.jsx
// Source: https://vite-pwa-org.netlify.app/frameworks/react.html
import { useRegisterSW } from 'virtual:pwa-register/react'

export function ReloadPrompt() {
  const {
    offlineReady: [offlineReady, setOfflineReady],
    needRefresh: [needRefresh, setNeedRefresh],
    updateServiceWorker
  } = useRegisterSW({
    onRegistered(r) {
      console.log('SW Registered:', r)
    },
    onRegisterError(error) {
      console.error('SW registration error:', error)
    }
  })

  const close = () => {
    setOfflineReady(false)
    setNeedRefresh(false)
  }

  if (!offlineReady && !needRefresh) return null

  return (
    <div className="fixed bottom-4 right-4 z-50 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg border">
      {offlineReady && (
        <div className="flex items-center gap-3">
          <span>App ready for offline use</span>
          <button onClick={close} className="text-gray-500">
            Close
          </button>
        </div>
      )}
      {needRefresh && (
        <div className="flex items-center gap-3">
          <span>New version available</span>
          <button
            onClick={() => updateServiceWorker(true)}
            className="px-3 py-1 bg-accent-600 text-white rounded"
          >
            Reload
          </button>
          <button onClick={close} className="text-gray-500">
            Later
          </button>
        </div>
      )}
    </div>
  )
}
```

### Pattern 3: PHP Meta Tag Injection for iOS
**What:** WordPress-specific PWA meta tags that vite-plugin-pwa cannot inject
**When to use:** Always - required for iOS PWA support

**Example:**
```php
// In functions.php or via wp_head action
// Source: https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html
function stadion_pwa_meta_tags() {
    ?>
    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Stadion">

    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="<?php echo RONDO_THEME_URL; ?>/public/icons/apple-touch-icon-180x180.png">

    <!-- Manifest -->
    <link rel="manifest" href="<?php echo RONDO_THEME_URL; ?>/dist/manifest.webmanifest">

    <!-- Theme Color (will be updated dynamically by React) -->
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f97316">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#ea580c">
    <?php
}
add_action('wp_head', 'stadion_pwa_meta_tags', 1);
```

### Pattern 4: iOS Safe Area CSS
**What:** Handle notch/Dynamic Island with CSS env() variables
**When to use:** Always for iOS standalone mode

**Example:**
```css
/* In index.css or Tailwind config */
/* Source: https://developer.mozilla.org/en-US/docs/Web/CSS/env */

/* Viewport must include viewport-fit=cover for env() to work */
/* This goes in index.php: <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"> */

html {
  /* Extend background behind safe areas */
  min-height: calc(100% + env(safe-area-inset-top));

  /* Never let content go under notch */
  padding-top: env(safe-area-inset-top);
  padding-right: env(safe-area-inset-right);
  padding-bottom: env(safe-area-inset-bottom);
  padding-left: env(safe-area-inset-left);
}

/* Or use Tailwind utilities */
.safe-top { padding-top: env(safe-area-inset-top); }
.safe-bottom { padding-bottom: env(safe-area-inset-bottom); }
.safe-left { padding-left: env(safe-area-inset-left); }
.safe-right { padding-right: env(safe-area-inset-right); }
```

### Pattern 5: Dynamic Theme Color
**What:** Update theme-color meta tag when user changes accent color
**When to use:** When supporting user-configurable accent colors

**Example:**
```javascript
// Enhancement to existing useTheme.js hook
// Source: https://developer.mozilla.org/en-US/docs/Web/Manifest/theme_color

function updateThemeColorMeta(hexColor, effectiveColorScheme) {
  // Light mode meta tag
  let lightMeta = document.querySelector('meta[name="theme-color"][media*="light"]');
  if (lightMeta) {
    lightMeta.content = hexColor;
  }

  // Dark mode meta tag (slightly darker variant)
  let darkMeta = document.querySelector('meta[name="theme-color"][media*="dark"]');
  if (darkMeta) {
    darkMeta.content = darkenColor(hexColor, 10);
  }

  // Fallback meta (no media query) for browsers that don't support media
  let fallbackMeta = document.querySelector('meta[name="theme-color"]:not([media])');
  if (fallbackMeta) {
    fallbackMeta.content = effectiveColorScheme === 'dark' ? darkenColor(hexColor, 10) : hexColor;
  }
}
```

### Anti-Patterns to Avoid
- **Double-caching API responses:** Don't use CacheFirst for API endpoints when TanStack Query already caches; use NetworkFirst or no caching
- **Static iOS splash screens in manifest:** iOS ignores manifest splash screens; must use apple-touch-startup-image link tags
- **Single theme-color meta tag:** Use media queries for light/dark support
- **Forgetting viewport-fit=cover:** env() safe area values are 0 without this

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Service worker generation | Custom SW from scratch | vite-plugin-pwa generateSW | Complex precaching logic, versioning, update handling |
| PWA icon generation | Manual resize scripts | @vite-pwa/assets-generator | Handles all required sizes, formats, manifest entries |
| iOS splash screens | Manual Photoshop exports | pwa-asset-generator | 20+ device sizes, scrapes latest Apple specs, handles dark mode |
| Workbox configuration | Raw Workbox APIs | vite-plugin-pwa workbox option | Plugin handles Vite integration, build pipeline |
| Update prompts | Custom polling logic | useRegisterSW hook | Built-in state management, proper SW lifecycle handling |

**Key insight:** PWA involves many edge cases (iOS quirks, caching strategies, versioning) that established tools handle. Custom solutions inevitably miss cases that cause user-facing bugs.

## Common Pitfalls

### Pitfall 1: WordPress PHP Template vs Vite HTML Injection
**What goes wrong:** vite-plugin-pwa injects manifest link and meta tags into index.html, but Stadion uses index.php
**Why it happens:** Plugin designed for SPA index.html files, not PHP templates
**How to avoid:**
1. Set `injectManifest: false` for meta tags you'll inject via PHP
2. Add PWA meta tags manually in `wp_head` action
3. Keep manifest file in dist/, reference it manually from PHP
**Warning signs:** Manifest link missing from page source, PWA not installable

### Pitfall 2: iOS Status Bar Color Mismatch
**What goes wrong:** Status bar text color conflicts with app background (white text on white)
**Why it happens:** iOS `apple-mobile-web-app-status-bar-style` has limited options: default, black, black-translucent
**How to avoid:**
- Use `default` for light backgrounds (black text)
- Use `black-translucent` for dark backgrounds (white text)
- Dynamic switching requires page reload - consider sticking with `default`
**Warning signs:** Unreadable status bar after switching themes

### Pitfall 3: Service Worker Scope Mismatch
**What goes wrong:** SW doesn't control pages, API calls not cached
**Why it happens:** Service worker scope is the directory it's served from
**How to avoid:**
- Serve SW from root (/) not /dist/
- Configure scope explicitly in vite-plugin-pwa
- WordPress rewrite rules must not interfere with SW registration
**Warning signs:** Console shows SW scope warnings, offline mode doesn't work

### Pitfall 4: Missing viewport-fit=cover
**What goes wrong:** env(safe-area-inset-*) returns 0, content hidden behind notch
**Why it happens:** Browser defaults to safe viewport, env() needs explicit opt-in
**How to avoid:** Update index.php viewport meta: `viewport-fit=cover`
**Warning signs:** Content appears under notch on iPhone X+

### Pitfall 5: iOS Splash Screen Size Mismatch
**What goes wrong:** No splash screen shown, or wrong size causing white flash
**Why it happens:** iOS requires exact pixel match for device resolution + media query
**How to avoid:** Use pwa-asset-generator which scrapes current Apple specs
**Warning signs:** White screen on app launch instead of splash

### Pitfall 6: TanStack Query + Service Worker Double Caching
**What goes wrong:** Stale data persists even after server updates
**Why it happens:** Both TanStack Query AND service worker cache the same API response
**How to avoid:** Use NetworkFirst for API routes (already in CONTEXT.md decisions)
**Warning signs:** Data appears stale after clearing TanStack Query cache

## Code Examples

### iOS Splash Screen Generation
```bash
# Generate all iOS splash screens from SVG source
# Source: https://github.com/elegantapp/pwa-asset-generator
npx pwa-asset-generator logo.svg public/splash \
  --splash-only \
  --background "#f97316" \
  --padding "20%" \
  --type png \
  --quality 90

# Generate dark mode variants
npx pwa-asset-generator logo.svg public/splash \
  --splash-only \
  --dark-mode \
  --background "#1f2937" \
  --padding "20%" \
  --type png \
  --quality 90
```

### iOS Splash Screen HTML Tags (Generated)
```html
<!-- Example output from pwa-asset-generator -->
<!-- Source: https://github.com/elegantapp/pwa-asset-generator -->
<link rel="apple-touch-startup-image"
      href="/splash/apple-splash-2048-2732.png"
      media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
<link rel="apple-touch-startup-image"
      href="/splash/apple-splash-1125-2436.png"
      media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
<!-- ... many more for all device sizes -->
```

### Vite TypeScript Configuration for Virtual Modules
```typescript
// vite-env.d.ts or tsconfig.json
// Source: https://vite-pwa-org.netlify.app/frameworks/react.html
{
  "compilerOptions": {
    "types": ["vite-plugin-pwa/react"]
  }
}
```

### Service Worker Scope with WordPress
```javascript
// vite.config.js - handling WordPress URL structure
// Service worker must be at root for proper scope
export default defineConfig({
  plugins: [
    VitePWA({
      // SW file location
      filename: 'sw.js',

      // Scope should cover entire site
      scope: '/',

      // Base URL for assets
      base: '/wp-content/themes/rondo-club/dist/',

      // Manifest URL
      manifestFilename: 'manifest.webmanifest',
    })
  ]
})
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual service worker | Workbox/vite-plugin-pwa | 2020+ | Automated precaching, versioning |
| Separate manifest.json | Plugin-generated manifest | vite-plugin-pwa | Single source of truth in config |
| Static iOS splash images | pwa-asset-generator | 2021+ | Auto-generates all sizes from specs |
| Single theme-color | Media query theme-color | 2020 | Dark/light mode support |
| custom SW for offline | generateSW strategy | Workbox 6+ | Simpler, less error-prone |

**Deprecated/outdated:**
- `cache.addAll()` manual precaching: Use Workbox precaching instead
- AppCache (manifest.appcache): Removed from browsers, use Service Workers
- Old iOS splash screen sizes: Apple changes specs; use auto-generator

## Open Questions

1. **Service Worker File Location**
   - What we know: vite-plugin-pwa generates SW in dist/
   - What's unclear: WordPress serves from theme directory; does SW need to be at web root?
   - Recommendation: Test SW scope behavior; may need WordPress rewrite rule to serve sw.js from root

2. **Existing UpdateBanner Component**
   - What we know: App.jsx already has UpdateBanner for version checking
   - What's unclear: Should this integrate with SW updates or remain separate?
   - Recommendation: Enhance existing UpdateBanner to also trigger SW update, or replace with ReloadPrompt

3. **Dynamic Icons Based on Accent Color**
   - What we know: CONTEXT.md says "Icon color: Match user's configured accent color"
   - What's unclear: Manifest icons are static; changing them requires new manifest
   - Recommendation: Use neutral/black icon in manifest, dynamic favicon via useTheme (already implemented)

## Sources

### Primary (HIGH confidence)
- [vite-pwa-org.netlify.app/guide](https://vite-pwa-org.netlify.app/guide/) - Official vite-plugin-pwa documentation
- [vite-pwa-org.netlify.app/frameworks/react.html](https://vite-pwa-org.netlify.app/frameworks/react.html) - React integration guide
- [vite-pwa-org.netlify.app/workbox/generate-sw](https://vite-pwa-org.netlify.app/workbox/generate-sw) - Workbox generateSW configuration
- [vite-pwa-org.netlify.app/assets-generator](https://vite-pwa-org.netlify.app/assets-generator/) - PWA asset generation docs
- [developer.mozilla.org/en-US/docs/Web/CSS/env](https://developer.mozilla.org/en-US/docs/Web/CSS/env) - CSS env() for safe areas
- [developer.apple.com/library/archive/.../ConfiguringWebApplications](https://developer.apple.com/library/archive/documentation/AppleApplications/Reference/SafariWebContent/ConfiguringWebApplications/ConfiguringWebApplications.html) - Apple PWA meta tags

### Secondary (MEDIUM confidence)
- [github.com/elegantapp/pwa-asset-generator](https://github.com/elegantapp/pwa-asset-generator) - iOS splash screen generator
- [web.dev/learn/pwa/enhancements](https://web.dev/learn/pwa/enhancements) - PWA enhancement patterns
- [progressier.com/pwa-icons-and-ios-splash-screen-generator](https://progressier.com/pwa-icons-and-ios-splash-screen-generator) - Reference for iOS splash requirements

### Tertiary (LOW confidence)
- Community articles on iOS PWA status bar behavior (varies by iOS version)
- WordPress PWA plugin patterns (different architecture, used for reference only)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - vite-plugin-pwa is the de-facto standard, well-documented
- Architecture: HIGH - Official docs + existing Stadion patterns inform structure
- Pitfalls: HIGH - Well-documented in official guides and GitHub issues
- iOS specifics: MEDIUM - Apple documentation is authoritative but sparse; community fills gaps

**Research date:** 2026-01-28
**Valid until:** 2026-03-28 (60 days - PWA ecosystem relatively stable)
