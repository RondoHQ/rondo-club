# Roadmap: Stadion v8.0 PWA Enhancement

## Milestones

- ✅ **v1.0 Tech Debt Cleanup** - Shipped 2026-01-13
- ✅ **v2.0 Multi-User** - Phases 1-10 (shipped 2026-01-13)
- ✅ **v3.0 Testing Infrastructure** - Phases 11-19 (shipped 2026-01-14)
- ✅ **v4.0 Calendar Integration** - Phases 20-45 (shipped 2026-01-17)
- ✅ **v5.0 Google Contacts Sync** - Phases 46-60 (shipped 2026-01-18)
- ✅ **v6.0 Custom Fields** - Phases 61-80 (shipped 2026-01-21)
- ✅ **v7.0 Dutch Localization** - Phases 99-106 (shipped 2026-01-25)
- ✅ **v8.0 PWA Enhancement** - Phases 107-110 (shipped 2026-01-28)

## Phases

<details>
<summary>✅ v1.0 through v7.0 (Phases 1-106) - SHIPPED</summary>

See PROJECT.md for complete milestone history.

</details>

### ✅ v8.0 PWA Enhancement (Shipped)

**Milestone Goal:** Transform Stadion into an installable Progressive Web App with native-like UX on iOS and Android

- [x] **Phase 107: PWA Foundation** - Manifest, service worker, iOS compatibility ✓
- [x] **Phase 108: Offline Support** - Caching strategy, offline indicator, cached data ✓
- [x] **Phase 109: Mobile UX** - Pull-to-refresh, overscroll prevention ✓
- [x] **Phase 110: Install & Polish** - Smart install prompt, update notifications, device testing ✓

## Phase Details

### Phase 107: PWA Foundation
**Goal**: Make Stadion installable on iOS and Android with proper platform-specific support
**Depends on**: Nothing (first phase of milestone)
**Requirements**: PWA-01, PWA-02, PWA-03, PWA-04, PWA-05
**Success Criteria** (what must be TRUE):
  1. User can install Stadion to home screen on Android (automatic prompt)
  2. User can Add to Home Screen on iOS with proper icons and splash screen
  3. App launches in standalone mode (no browser chrome) after installation
  4. Content displays correctly on iPhone X+ (not hidden behind notch)
  5. App theme color matches user's configured accent color setting
**Plans**: 4 plans

Plans:
- [x] 107-01-PLAN.md — Install vite-plugin-pwa and generate PWA icons
- [x] 107-02-PLAN.md — iOS meta tags and safe area CSS
- [x] 107-03-PLAN.md — ReloadPrompt component and dynamic theme color
- [x] 107-04-PLAN.md — Deploy and verify on devices

### Phase 108: Offline Support
**Goal**: Enable basic offline functionality showing cached data when network unavailable
**Depends on**: Phase 107 (service worker must be registered)
**Requirements**: OFF-01, OFF-02, OFF-03, OFF-04
**Success Criteria** (what must be TRUE):
  1. User can view previously loaded contacts/teams/dates when offline
  2. App displays clear offline indicator when network disconnected
  3. Static assets (JS, CSS, fonts) load from cache when offline
  4. User sees helpful offline fallback page when navigating to uncached route
**Plans**: 4 plans

Plans:
- [x] 108-01-PLAN.md — Core infrastructure (useOnlineStatus hook, TanStack Query integration, offline.html)
- [x] 108-02-PLAN.md — OfflineBanner component and App.jsx mounting
- [x] 108-03-PLAN.md — Form disabling in edit modals when offline
- [x] 108-04-PLAN.md — Deploy and verify offline functionality

### Phase 109: Mobile UX
**Goal**: Provide native-like mobile gestures and prevent iOS-specific UX issues
**Depends on**: Phase 108 (offline support should work before adding gestures)
**Requirements**: UX-01, UX-02
**Success Criteria** (what must be TRUE):
  1. User can pull-to-refresh on mobile to reload current view
  2. iOS standalone mode does not accidentally reload page from overscroll bounce
  3. Pull-to-refresh gesture feels native and responsive
**Plans**: 3 plans

Plans:
- [x] 109-01-PLAN.md — Install react-simple-pull-to-refresh and create PullToRefreshWrapper component, add overscroll CSS
- [x] 109-02-PLAN.md — Integrate pull-to-refresh into all list views, detail views, and Dashboard
- [x] 109-03-PLAN.md — Deploy and verify on devices

### Phase 110: Install & Polish
**Goal**: Optimize install experience and handle app updates gracefully
**Depends on**: Phase 109 (core PWA functionality complete)
**Requirements**: INST-01, INST-02, INST-03
**Success Criteria** (what must be TRUE):
  1. Android users see smart install prompt after viewing 2 people or adding 1 note
  2. iOS users can access manual install instructions explaining Add to Home Screen
  3. Users see update notification when new version available with refresh button
  4. App passes Lighthouse PWA audit with score above 90
  5. App works correctly in standalone mode on real iOS and Android devices
**Plans**: 4 plans

Plans:
- [ ] 110-01-PLAN.md — Install prompt infrastructure (hooks and utilities)
- [ ] 110-02-PLAN.md — Install UI components (Android banner, iOS modal)
- [ ] 110-03-PLAN.md — Update notification enhancement and Dutch localization
- [ ] 110-04-PLAN.md — Deploy, Lighthouse audit, and device verification

## Progress

**Execution Order:**
Phases execute in numeric order: 107 → 108 → 109 → 110

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 107. PWA Foundation | v8.0 | 4/4 | ✓ Complete | 2026-01-28 |
| 108. Offline Support | v8.0 | 4/4 | ✓ Complete | 2026-01-28 |
| 109. Mobile UX | v8.0 | 3/3 | ✓ Complete | 2026-01-28 |
| 110. Install & Polish | v8.0 | 4/4 | ✓ Complete | 2026-01-28 |
