# Phase 110: Install & Polish - Research

**Researched:** 2026-01-28
**Domain:** PWA Installation UX & Production Optimization
**Confidence:** HIGH

## Summary

Phase 110 focuses on optimizing the installation experience for Progressive Web Apps on both Android (smart prompts) and iOS (manual instructions), handling app updates gracefully, and ensuring production-quality PWA compliance. The research covers `beforeinstallprompt` API integration for Android, iOS install instruction modals, update notification strategies, Lighthouse PWA auditing requirements, and production testing on real devices.

The standard approach for Android uses the `beforeinstallprompt` event (Chromium-only) with localStorage-based dismissal tracking and engagement heuristics (e.g., show after 2 page views or 1 interaction). For iOS, custom modals with visual instructions are required since Safari doesn't support automatic prompts. Update handling builds on vite-plugin-pwa's `useRegisterSW` hook (already implemented in ReloadPrompt.jsx) with optional periodic checking. Lighthouse PWA audits require passing installability, performance (TTI < 10s on 4G), and offline functionality checks.

**Primary recommendation:** Build custom React hooks for Android install prompts with localStorage dismissal tracking, create an iOS-specific instruction modal component, enhance existing ReloadPrompt with periodic update checks, and establish device testing procedures for standalone mode verification.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| vite-plugin-pwa | ^1.2.0 (already installed) | PWA manifest & service worker generation | Already used in Phase 107, handles update notifications via useRegisterSW |
| localStorage API | Native | Track install prompt dismissals & preferences | Browser standard, persists across sessions, widely supported |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| react-ios-pwa-prompt | ^2.0.x | Pre-built iOS install instructions component | Alternative to custom modal; customizable, handles detection |
| @dotmind/react-use-pwa | ^1.x | React hooks for PWA install state | Alternative to custom hooks; provides isInstalled, canInstall, installPrompt |
| Lighthouse CLI | Latest | PWA audit scoring | CI/CD integration, automated quality checks |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom hooks | react-ios-pwa-prompt | Pre-built library vs full control; library is iOS-only, we need Android too |
| localStorage tracking | Cookie-based tracking | localStorage persists better, simpler API, no server round-trip |
| Custom iOS modal | react-ios-pwa-prompt library | Library is battle-tested but less customizable; custom modal fits existing design system |
| Manual testing | BrowserStack automated testing | Automated testing is faster but manual testing on real devices catches UX issues |

**Installation:**
```bash
# No new dependencies required for basic implementation
# Optional libraries if needed:
npm install -D lighthouse  # For CI/CD auditing
# npm install react-ios-pwa-prompt  # If using pre-built iOS component
```

## Architecture Patterns

### Recommended Project Structure
```
src/
├── hooks/
│   ├── useInstallPrompt.js      # Android beforeinstallprompt hook
│   └── useVersionCheck.js        # Already exists - enhance with periodic checks
├── components/
│   ├── ReloadPrompt.jsx          # Already exists - update notifications
│   ├── InstallPrompt.jsx         # Android install prompt UI
│   └── IOSInstallModal.jsx       # iOS install instructions modal
├── utils/
│   └── installTracking.js        # localStorage helpers for dismissal tracking
└── App.jsx                       # Integrate install prompts
```

### Pattern 1: Android Install Prompt with Engagement Heuristics
**What:** Listen for `beforeinstallprompt`, track user engagement, show custom UI after meaningful interaction
**When to use:** Always on Android/Chromium browsers
**Example:**
```javascript
// src/hooks/useInstallPrompt.js
// Source: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Trigger_install_prompt
import { useState, useEffect } from 'react';

export function useInstallPrompt() {
  const [installPrompt, setInstallPrompt] = useState(null);
  const [canInstall, setCanInstall] = useState(false);

  useEffect(() => {
    const handleBeforeInstall = (e) => {
      e.preventDefault(); // Prevent automatic browser prompt
      setInstallPrompt(e);

      // Check if user previously dismissed
      const lastDismissed = localStorage.getItem('pwa-install-dismissed');
      const dismissedCount = parseInt(localStorage.getItem('pwa-install-dismiss-count') || '0', 10);

      // Show if never dismissed or dismissed < 3 times and > 7 days ago
      const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
      if (!lastDismissed || (dismissedCount < 3 && parseInt(lastDismissed, 10) < sevenDaysAgo)) {
        setCanInstall(true);
      }
    };

    const handleAppInstalled = () => {
      setInstallPrompt(null);
      setCanInstall(false);
      localStorage.setItem('pwa-installed', 'true');
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstall);
    window.addEventListener('appinstalled', handleAppInstalled);

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstall);
      window.removeEventListener('appinstalled', handleAppInstalled);
    };
  }, []);

  const promptInstall = async () => {
    if (!installPrompt) return { outcome: 'unavailable' };

    const result = await installPrompt.prompt();
    const outcome = await result.userChoice;

    if (outcome === 'dismissed') {
      const dismissCount = parseInt(localStorage.getItem('pwa-install-dismiss-count') || '0', 10);
      localStorage.setItem('pwa-install-dismissed', Date.now().toString());
      localStorage.setItem('pwa-install-dismiss-count', (dismissCount + 1).toString());
    }

    setInstallPrompt(null);
    setCanInstall(false);

    return outcome;
  };

  const hidePrompt = () => {
    const dismissCount = parseInt(localStorage.getItem('pwa-install-dismiss-count') || '0', 10);
    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
    localStorage.setItem('pwa-install-dismiss-count', (dismissCount + 1).toString());
    setCanInstall(false);
  };

  return { canInstall, promptInstall, hidePrompt };
}
```

### Pattern 2: Engagement-Based Install Prompt Trigger
**What:** Show install prompt after user demonstrates engagement (page views, interactions)
**When to use:** To avoid annoying users with immediate prompts
**Example:**
```javascript
// Track engagement in App.jsx or custom hook
import { useState, useEffect } from 'react';

export function useEngagementTracking() {
  const [showPrompt, setShowPrompt] = useState(false);

  useEffect(() => {
    // Track page views
    const pageViews = parseInt(sessionStorage.getItem('pwa-page-views') || '0', 10);
    sessionStorage.setItem('pwa-page-views', (pageViews + 1).toString());

    // Show after 2 page views OR 1 note added (tracked elsewhere)
    const notesAdded = parseInt(sessionStorage.getItem('pwa-notes-added') || '0', 10);

    if (pageViews >= 2 || notesAdded >= 1) {
      setShowPrompt(true);
    }
  }, []);

  return showPrompt;
}
```

### Pattern 3: iOS Install Instructions Modal
**What:** Modal with visual instructions for iOS "Add to Home Screen" process
**When to use:** iOS/Safari users who can install PWA
**Example:**
```jsx
// src/components/IOSInstallModal.jsx
import { useState, useEffect } from 'react';
import { Share, Plus, X } from 'lucide-react';

export function IOSInstallModal() {
  const [show, setShow] = useState(false);

  useEffect(() => {
    // Detect iOS Safari (not already installed)
    const isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);
    const isStandalone = window.navigator.standalone === true;
    const isSafari = /Safari/.test(navigator.userAgent) && !/CriOS|FxiOS|EdgiOS/.test(navigator.userAgent);

    if (isIOS && !isStandalone && isSafari) {
      // Check if dismissed recently
      const dismissed = localStorage.getItem('ios-install-dismissed');
      const weekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);

      if (!dismissed || parseInt(dismissed, 10) < weekAgo) {
        // Show after 3 page views
        const views = parseInt(sessionStorage.getItem('pwa-page-views') || '0', 10);
        if (views >= 3) {
          setShow(true);
        }
      }
    }
  }, []);

  const handleDismiss = () => {
    localStorage.setItem('ios-install-dismissed', Date.now().toString());
    setShow(false);
  };

  if (!show) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50">
      <div className="bg-white dark:bg-gray-800 rounded-t-2xl sm:rounded-2xl p-6 max-w-sm w-full mx-4 sm:mx-0">
        <div className="flex justify-between items-start mb-4">
          <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Installeer Stadion
          </h3>
          <button onClick={handleDismiss} className="p-1 text-gray-400 hover:text-gray-500">
            <X className="w-5 h-5" />
          </button>
        </div>

        <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
          Voor snellere toegang kun je Stadion installeren op je beginscherm:
        </p>

        <ol className="space-y-4">
          <li className="flex items-start gap-3">
            <div className="flex-shrink-0 w-6 h-6 rounded-full bg-accent-100 dark:bg-accent-900 text-accent-600 dark:text-accent-400 flex items-center justify-center text-sm font-medium">
              1
            </div>
            <div className="flex-1 text-sm">
              <p className="text-gray-900 dark:text-gray-100">Tik op het Deel-icoon</p>
              <Share className="w-5 h-5 mt-2 text-accent-600" />
            </div>
          </li>

          <li className="flex items-start gap-3">
            <div className="flex-shrink-0 w-6 h-6 rounded-full bg-accent-100 dark:bg-accent-900 text-accent-600 dark:text-accent-400 flex items-center justify-center text-sm font-medium">
              2
            </div>
            <div className="flex-1 text-sm">
              <p className="text-gray-900 dark:text-gray-100">Scroll omlaag en kies "Zet op beginscherm"</p>
              <div className="mt-2 flex items-center gap-2 text-accent-600">
                <Plus className="w-4 h-4" />
                <span>Zet op beginscherm</span>
              </div>
            </div>
          </li>

          <li className="flex items-start gap-3">
            <div className="flex-shrink-0 w-6 h-6 rounded-full bg-accent-100 dark:bg-accent-900 text-accent-600 dark:text-accent-400 flex items-center justify-center text-sm font-medium">
              3
            </div>
            <div className="flex-1 text-sm">
              <p className="text-gray-900 dark:text-gray-100">Tik op "Voeg toe"</p>
            </div>
          </li>
        </ol>

        <button
          onClick={handleDismiss}
          className="mt-6 w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600"
        >
          Misschien later
        </button>
      </div>
    </div>
  );
}
```

### Pattern 4: Periodic Service Worker Update Checking
**What:** Enhance existing ReloadPrompt with periodic update checks
**When to use:** To notify users of new versions without requiring manual refresh
**Example:**
```javascript
// Enhance src/components/ReloadPrompt.jsx
// Source: https://vite-pwa-org.netlify.app/guide/periodic-sw-updates
import { useRegisterSW } from 'virtual:pwa-register/react';

export function ReloadPrompt() {
  const intervalMS = 60 * 60 * 1000; // Check every hour

  const {
    offlineReady: [offlineReady, setOfflineReady],
    needRefresh: [needRefresh, setNeedRefresh],
    updateServiceWorker
  } = useRegisterSW({
    onRegisteredSW(swUrl, registration) {
      console.log('SW Registered:', registration);

      // Set up periodic update check
      if (registration) {
        setInterval(async () => {
          // Check network before update
          if (!(!navigator.onLine)) {
            try {
              const resp = await fetch(swUrl, {
                cache: 'no-store',
                headers: {
                  'cache': 'no-store',
                  'cache-control': 'no-cache',
                },
              });

              if (resp?.status === 200) {
                await registration.update();
              }
            } catch (error) {
              console.debug('SW update check failed:', error);
            }
          }
        }, intervalMS);
      }
    },
    onRegisterError(error) {
      console.error('SW registration error:', error);
    }
  });

  // ... rest of component (already exists)
}
```

### Pattern 5: Detect Display Mode (Standalone vs Browser)
**What:** Adjust UI based on whether app is installed/running in standalone mode
**When to use:** Hide install prompts when already installed
**Example:**
```javascript
// src/utils/displayMode.js
export function getDisplayMode() {
  const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
  const isIOSStandalone = window.navigator.standalone === true;

  if (isStandalone || isIOSStandalone) {
    return 'standalone';
  }

  return 'browser';
}

export function useIsInstalled() {
  const [isInstalled, setIsInstalled] = useState(false);

  useEffect(() => {
    setIsInstalled(getDisplayMode() === 'standalone');
  }, []);

  return isInstalled;
}
```

### Anti-Patterns to Avoid
- **Showing install prompt immediately on first load:** Users need to see value before installing; use engagement heuristics
- **Not respecting dismissals:** Track localStorage and honor user's "no" choice with appropriate cooldown periods
- **Blocking UI with install prompts:** Use non-intrusive banners/toasts, not blocking modals for Android
- **Showing iOS prompts on other browsers:** Detect iOS Safari specifically; instructions don't apply to Chrome iOS
- **Not testing on real devices:** Simulators/emulators don't accurately reflect PWA install behavior

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| iOS detection logic | Custom user agent parsing | `react-ios-pwa-prompt` or standard patterns | User agent detection is complex; library handles edge cases (iOS Chrome, iPad OS, etc.) |
| Install prompt UI | Completely custom components | Enhance existing patterns with Stadion design system | Established patterns prevent UX mistakes (timing, dismissal tracking) |
| Lighthouse scoring | Manual checklist verification | Lighthouse CLI in CI/CD | Automated scoring catches regressions, provides reproducible metrics |
| Device testing matrix | Ad-hoc manual testing | Documented test procedure + real device testing | Ensures consistent verification across iOS/Android versions |
| Dismissal tracking logic | Ad-hoc localStorage code | Centralized utility with documented schema | Prevents bugs from inconsistent tracking across components |

**Key insight:** Install prompt UX patterns are well-established but easy to implement poorly (wrong timing, no respect for dismissals, platform-specific bugs). Use proven patterns and test extensively on real devices.

## Common Pitfalls

### Pitfall 1: beforeinstallprompt Only Fires Once Per Session
**What goes wrong:** Prompt doesn't re-appear after dismissal, even if you want to show it again
**Why it happens:** Browser fires event once per page load; calling `prompt()` consumes the event
**How to avoid:**
- Store the event object immediately when received
- Track dismissals in localStorage
- Only show custom UI when both: (1) event fired, (2) not recently dismissed
- Reset on new session/page load
**Warning signs:** Install button appears but doesn't do anything when clicked

### Pitfall 2: Showing Install Prompts to Already-Installed Users
**What goes wrong:** Users see "Install Stadion" even though it's already on their home screen
**Why it happens:** Not checking display-mode or navigator.standalone
**How to avoid:**
```javascript
const isInstalled = window.matchMedia('(display-mode: standalone)').matches
  || window.navigator.standalone === true;
if (!isInstalled) {
  // Show install UI
}
```
**Warning signs:** User reports seeing install prompts inside installed app

### Pitfall 3: iOS Instructions Don't Work on Chrome iOS
**What goes wrong:** Instructions say "tap Share icon" but Chrome iOS has different UI
**Why it happens:** Chrome iOS doesn't support PWA installation; must use Safari
**How to avoid:** Detect Safari specifically, show different message for other iOS browsers
**Warning signs:** iOS users report "I don't see the Share button"

### Pitfall 4: Engagement Tracking Across Sessions
**What goes wrong:** Page view counter resets, so users see prompt every session
**Why it happens:** Using sessionStorage for engagement that should persist
**How to avoid:**
- Use sessionStorage for per-session engagement (page views this session)
- Use localStorage for persistent state (total dismissals, last dismissed date)
- Combine both: "show after 2 page views OR 1 week since dismissal"
**Warning signs:** Users report seeing prompt too frequently

### Pitfall 5: Update Prompt and Install Prompt Conflict
**What goes wrong:** Both ReloadPrompt (updates) and InstallPrompt show simultaneously
**Why it happens:** Independent state management for each prompt
**How to avoid:**
- Prioritize update prompts over install prompts (updates are more critical)
- Check `needRefresh` before showing install UI
- Position prompts to avoid overlap (top-right for updates, bottom for install)
**Warning signs:** Overlapping notification banners

### Pitfall 6: Lighthouse PWA Score Fails on Offline Functionality
**What goes wrong:** Lighthouse reports PWA score < 90 due to offline failures
**Why it happens:** Service worker doesn't properly cache all required assets, or navigateFallback misconfigured
**How to avoid:**
- Test offline mode manually before Lighthouse audit
- Verify workbox.globPatterns includes all critical assets
- Ensure navigateFallback path is correct for WordPress theme structure
- Check that offline.html is included in build
**Warning signs:** Lighthouse reports "Does not respond with a 200 when offline"

### Pitfall 7: Periodic Update Check Performance Impact
**What goes wrong:** Frequent update checks drain battery or cause network congestion
**Why it happens:** Too aggressive interval (e.g., every 1 minute)
**How to avoid:**
- Use reasonable intervals: 1 hour minimum for production
- Check navigator.onLine before attempting fetch
- Use cache-busting headers to avoid false positives
- Only check when app is active (not in background)
**Warning signs:** High network usage in installed app, battery drain reports

## Code Examples

### Android Install Banner Component
```jsx
// src/components/InstallPrompt.jsx
import { useInstallPrompt } from '@/hooks/useInstallPrompt';
import { useEngagementTracking } from '@/hooks/useEngagementTracking';
import { Download, X } from 'lucide-react';

export function InstallPrompt() {
  const { canInstall, promptInstall, hidePrompt } = useInstallPrompt();
  const showBasedOnEngagement = useEngagementTracking();

  if (!canInstall || !showBasedOnEngagement) return null;

  const handleInstall = async () => {
    const outcome = await promptInstall();
    if (outcome === 'accepted') {
      // Track successful install (analytics)
      console.log('PWA installed successfully');
    }
  };

  return (
    <div className="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-4 sm:max-w-sm z-40">
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-4">
        <div className="flex items-start gap-3">
          <div className="flex-shrink-0">
            <Download className="w-5 h-5 text-accent-600" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
              Installeer Stadion
            </p>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Voor snellere toegang en offline gebruik
            </p>
          </div>
          <button
            onClick={hidePrompt}
            className="flex-shrink-0 p-1 text-gray-400 hover:text-gray-500"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
        <div className="mt-3 flex gap-2 justify-end">
          <button
            onClick={hidePrompt}
            className="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800"
          >
            Niet nu
          </button>
          <button
            onClick={handleInstall}
            className="px-3 py-1.5 text-sm bg-accent-600 text-white rounded-md hover:bg-accent-700"
          >
            Installeer
          </button>
        </div>
      </div>
    </div>
  );
}
```

### localStorage Tracking Utilities
```javascript
// src/utils/installTracking.js
const KEYS = {
  DISMISSED: 'pwa-install-dismissed',
  DISMISS_COUNT: 'pwa-install-dismiss-count',
  INSTALLED: 'pwa-installed',
  IOS_DISMISSED: 'ios-install-dismissed',
};

export const installTracking = {
  // Check if install prompt should be shown
  shouldShowPrompt() {
    const lastDismissed = localStorage.getItem(KEYS.DISMISSED);
    const dismissCount = this.getDismissCount();
    const isInstalled = localStorage.getItem(KEYS.INSTALLED) === 'true';

    if (isInstalled) return false;

    // Don't show if dismissed 3+ times
    if (dismissCount >= 3) return false;

    // Show if never dismissed
    if (!lastDismissed) return true;

    // Show if dismissed > 7 days ago
    const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
    return parseInt(lastDismissed, 10) < sevenDaysAgo;
  },

  // Track dismissal
  trackDismissal() {
    const count = this.getDismissCount();
    localStorage.setItem(KEYS.DISMISSED, Date.now().toString());
    localStorage.setItem(KEYS.DISMISS_COUNT, (count + 1).toString());
  },

  // Track successful install
  trackInstall() {
    localStorage.setItem(KEYS.INSTALLED, 'true');
    localStorage.removeItem(KEYS.DISMISSED);
    localStorage.removeItem(KEYS.DISMISS_COUNT);
  },

  // Get dismiss count
  getDismissCount() {
    return parseInt(localStorage.getItem(KEYS.DISMISS_COUNT) || '0', 10);
  },

  // iOS-specific tracking
  shouldShowIOSPrompt() {
    const dismissed = localStorage.getItem(KEYS.IOS_DISMISSED);
    if (!dismissed) return true;

    const weekAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
    return parseInt(dismissed, 10) < weekAgo;
  },

  trackIOSDismissal() {
    localStorage.setItem(KEYS.IOS_DISMISSED, Date.now().toString());
  },
};
```

### Engagement Tracking Hook
```javascript
// src/hooks/useEngagementTracking.js
import { useState, useEffect } from 'react';

export function useEngagementTracking({
  minPageViews = 2,
  minNotes = 1
} = {}) {
  const [isEngaged, setIsEngaged] = useState(false);

  useEffect(() => {
    // Track page view
    const pageViews = parseInt(sessionStorage.getItem('pwa-page-views') || '0', 10);
    sessionStorage.setItem('pwa-page-views', (pageViews + 1).toString());

    const notesAdded = parseInt(sessionStorage.getItem('pwa-notes-added') || '0', 10);

    if (pageViews >= minPageViews || notesAdded >= minNotes) {
      setIsEngaged(true);
    }
  }, [minPageViews, minNotes]);

  return isEngaged;
}

// Call this when user adds a note/activity
export function trackNoteAdded() {
  const count = parseInt(sessionStorage.getItem('pwa-notes-added') || '0', 10);
  sessionStorage.setItem('pwa-notes-added', (count + 1).toString());
}
```

### Lighthouse CI Configuration
```javascript
// lighthouserc.js
module.exports = {
  ci: {
    collect: {
      url: ['https://stadion.svawc.nl/dashboard'],
      numberOfRuns: 3,
      settings: {
        preset: 'desktop',
        throttlingMethod: 'simulate',
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'pwa'],
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['error', { minScore: 0.9 }],
        'categories:accessibility': ['error', { minScore: 0.9 }],
        'categories:best-practices': ['error', { minScore: 0.9 }],
        'categories:pwa': ['error', { minScore: 0.9 }],
      },
    },
  },
};
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Immediate install prompts | Engagement-based heuristics | 2018-2020 | Chrome changed default timing; best practice now waits for engagement |
| Browser-only install UI | Custom in-app prompts | 2019+ | beforeinstallprompt allows custom UI, better conversion rates |
| Single dismissal tracking | Multi-dismissal with cooldown | 2020+ | Respects user preference while allowing re-prompting after time |
| iOS "not supported" | Custom instruction modals | 2020+ | iOS Safari supports PWA but requires manual install instructions |
| Manual update checking | Service worker lifecycle hooks | Workbox 6+ | Automated update detection with prompt/reload pattern |
| Separate update systems | Unified vite-plugin-pwa | 2021+ | Single source of truth for SW, manifest, update notifications |

**Deprecated/outdated:**
- Automatic browser install banners without deferral: Chrome now suppresses aggressive prompts
- beforeinstallprompt.userChoice property: Still works but use await prompt() instead
- iOS "Add to Home Screen" prompt styling via CSS: Not supported; must use modal instructions

## Open Questions

1. **Coexistence of UpdateBanner and ReloadPrompt**
   - What we know: App.jsx has both UpdateBanner (useVersionCheck) and ReloadPrompt (useRegisterSW)
   - What's unclear: Do they serve different purposes or is there redundancy?
   - Recommendation: Review both components; UpdateBanner checks build timestamps via API, ReloadPrompt detects SW updates. Keep both but ensure they don't show simultaneously (prioritize SW updates).

2. **Install Prompt Timing on People Detail Page**
   - What we know: Requirement says "after viewing 2 people"
   - What's unclear: Should this be 2 people in one session, or 2 people total across sessions?
   - Recommendation: Use sessionStorage for "2 people this session" to avoid premature prompts for returning users who haven't engaged this session.

3. **Lighthouse Audit in CI/CD**
   - What we know: Requirement says "score above 90"
   - What's unclear: Should this be enforced in CI/CD or manual verification?
   - Recommendation: Start with manual verification during phase execution, add Lighthouse CI in future phase if needed.

4. **Testing on Specific Device Models**
   - What we know: Need to test on "real iOS and Android devices"
   - What's unclear: Which specific models/OS versions are minimum requirements?
   - Recommendation: Test on: iPhone 12+ (iOS 16+), iPhone 14+ (iOS 17+), Samsung/Pixel with Android 12+, plus any devices user currently owns.

## Sources

### Primary (HIGH confidence)
- [MDN: Trigger install prompt](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Trigger_install_prompt) - beforeinstallprompt API and best practices
- [MDN: beforeinstallprompt event](https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeinstallprompt_event) - Event specification and browser support
- [web.dev: Customize install](https://web.dev/articles/customize-install) - Custom install prompt patterns and timing
- [web.dev: Promote install](https://web.dev/articles/promote-install) - Installation promotion patterns and best practices
- [web.dev: PWA checklist](https://web.dev/articles/pwa-checklist) - Core and optimal PWA requirements
- [web.dev: Detection](https://web.dev/learn/pwa/detection) - Display mode detection and standalone checking
- [Vite PWA: Periodic SW updates](https://vite-pwa-org.netlify.app/guide/periodic-sw-updates) - Service worker update checking configuration

### Secondary (MEDIUM confidence)
- [MDN: BeforeInstallPromptEvent userChoice](https://developer.mozilla.org/en-US/docs/Web/API/BeforeInstallPromptEvent/userChoice) - Tracking user install choices
- [Medium: Installing PWA on iOS](https://medium.com/ngconf/installing-your-pwa-on-ios-d1c497968e62) - iOS-specific patterns verified with official Apple docs
- [GitHub: react-ios-pwa-prompt](https://github.com/chrisdancee/react-ios-pwa-prompt) - Popular iOS prompt library pattern reference
- [npm: react-pwa-install](https://www.npmjs.com/package/react-pwa-install) - Android install prompt library pattern reference
- [Lighthouse PWA audit](https://developer.chrome.com/docs/lighthouse/pwa/installable-manifest) - Installability requirements
- [web.dev: Tools and debug](https://web.dev/learn/pwa/tools-and-debug) - Testing strategies for PWAs
- [Brainhub: PWA on iOS](https://brainhub.eu/library/pwa-on-ios) - iOS limitations and best practices

### Tertiary (LOW confidence)
- Various blog posts on install prompt timing patterns - Common patterns but not official guidance
- Community discussions on localStorage tracking strategies - Practical but vary by use case

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - vite-plugin-pwa already in use, beforeinstallprompt well-documented by MDN/web.dev
- Architecture: HIGH - Established patterns from official sources, existing codebase provides context
- Pitfalls: HIGH - Well-documented in official guides and real-world implementations
- iOS specifics: MEDIUM - Apple documentation sparse, but community patterns well-established
- Engagement heuristics: MEDIUM - Chrome's implementation changes over time, best practices from web.dev
- Lighthouse requirements: HIGH - Official Chrome documentation, stable API

**Research date:** 2026-01-28
**Valid until:** 2026-03-28 (60 days - PWA ecosystem relatively stable, though Chrome may adjust engagement heuristics)
