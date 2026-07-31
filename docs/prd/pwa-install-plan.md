# Making Rondo easy to save as an app icon / bookmark

**Status:** ✅ implemented in **33.81.0**, 2026-07-30. Audit below kept as the record of what was
wrong and why each fix looks the way it does. **Not yet verified on production** — see §7.
**Theme version audited:** 33.80.2
**Question:** what does it take for a member to end up with a proper Rondo icon on their phone home screen or desktop?

## What shipped

| Item | Where |
|---|---|
| Icons regenerated from the AWC crest (`awc-logo.svg`), opaque, on `#CCE1D7` | [scripts/generate-pwa-icons.js](../../scripts/generate-pwa-icons.js), `public/icons/club-crest.svg` |
| Manifest served from PHP, name from the site title, `start_url: '/'`, `id`, correct MIME type | [functions.php](../../functions.php) `rondo_render_manifest()` / `rondo_build_manifest()` |
| Static manifest removed from the build | [vite.config.js](../../vite.config.js) `manifest: false` |
| Service worker actually registered | [src/App.jsx](../../src/App.jsx) mounts `<ReloadPrompt />` |
| Service worker scope widened to `/` | [.htaccess](../../.htaccess) + `scope: '/'` in vite config |
| **Navigation route rewritten** so the offline page can no longer replace the app | [vite.config.js](../../vite.config.js) runtimeCaching |
| Permanent "App installeren" button + per-platform instructions | [InstallAppButton.jsx](../../src/components/InstallAppButton.jsx), [InstallInstructions.jsx](../../src/components/InstallInstructions.jsx) |
| Engagement gate counts route changes, not document loads | [useEngagementTracking.js](../../src/hooks/useEngagementTracking.js) |
| App name read from the site title everywhere | [src/constants/app.js](../../src/constants/app.js) `getAppName()` |
| 9 regression tests | [tests/Wpunit/PwaManifestTest.php](../../tests/Wpunit/PwaManifestTest.php) |

Two things found **during** implementation that the audit had missed, both of which would have
broken the site the moment the service worker got scope `/`:

- **`navigateFallback` bound a NavigationRoute to the precached `offline.html`.** Workbox answers
  *every* navigation from that route, online or not. Giving the worker real scope would have shown
  every member "je bent offline" instead of Rondo. Replaced with a `NetworkOnly` navigation route
  whose `precacheFallback` only fires when the network actually fails.
- **vite-plugin-pwa defaults `navigateFallback` to `index.html`**, which does not exist in this
  build. `createHandlerBoundToURL` throws on a non-precached URL at worker startup, so the worker
  would have failed to install at all. Explicitly set to `undefined`.

Deferred deliberately: manifest `screenshots` (needs real screenshots captured from demo, §5 W5),
and `useVersionCheck.reload()` still tears down all workers and caches on update — correct, just
wasteful now that the precache is real. Left alone rather than risk regressing the update path that
was specifically fixed for PWA installs.

## TL;DR

Rondo already has a near-complete PWA setup — manifest, icons, iOS meta tags, an Android install
banner, an iOS instruction modal, engagement gating, offline page, Workbox config. Most of the work
was done and then quietly stopped working.

Three things are actually wrong:

1. **The service worker is never registered.** The only call site sits in a component that is not
   mounted. So Chrome never fires `beforeinstallprompt` → the Android install banner and the desktop
   omnibox install icon never appear. The install UI that exists is dead code today.
2. **Even if it were registered, it could not control the app.** It is served from the theme's
   `dist/` directory, so its scope can never reach `/`.
3. **The icons have transparent corners and are upscaled from a 145 px source.** iOS composites a
   transparent `apple-touch-icon` on **black**, so every iPhone that adds Rondo today gets a small
   blurry hexagon on a black square.

Plus a fourth, which is what prompted this revision:

4. **The name and icon are hardcoded product branding, not club branding.** The manifest says
   "Rondo Club" with the Rondo hexagon on every deployment. WordPress already knows better: the
   production site title is **"AWC Rondo"** and demo's is **"Rondo Demo"** — the manifest just never
   reads it. And a configured club crest already exists in the database
   (`rondo_finance_club_logo_id`, used on invoices and wallet passes) and is never used for the app
   icon. See §3.

Item 3+4 together are the cheapest fix with the most visible payoff and need no service worker at
all. Items 1 + 2 are one afternoon and unlock the native install prompts on Android and desktop.

---

## 1. Current state

### What is already right

| Thing | Where | Status |
|---|---|---|
| `vite-plugin-pwa` wired into the build | [vite.config.js:11](../../vite.config.js) | ✅ |
| Manifest generated and served (HTTP 200 on production) | `dist/manifest.webmanifest` | ✅ |
| `<link rel="manifest">` in the head | [functions.php:794](../../functions.php) | ✅ |
| `name`, `short_name`, `display: standalone`, `scope`, `theme_color`, `background_color` | manifest | ✅ present, ⚠️ name hardcoded "Rondo Club" — see B7 |
| Icons 192, 512 and a 512 `maskable` | `public/icons/` | ✅ present, ⚠️ quality — see below |
| `<link rel="apple-touch-icon">` | [functions.php:791](../../functions.php) | ✅ |
| `apple-mobile-web-app-capable` / `-status-bar-style` / `-title` | [functions.php:786-788](../../functions.php) | ✅ |
| `mobile-web-app-capable` (the non-deprecated one) | [functions.php:785](../../functions.php) | ✅ |
| `theme-color` split light/dark | [functions.php:797-798](../../functions.php) | ✅ |
| Favicon (PNG 192 + SVG) | [functions.php:806-811](../../functions.php) | ✅ |
| Android install banner UI | [src/components/InstallPrompt.jsx](../../src/components/InstallPrompt.jsx) | ✅ written, ❌ never triggers |
| `beforeinstallprompt` capture hook | [src/hooks/useInstallPrompt.js](../../src/hooks/useInstallPrompt.js) | ✅ written, ❌ event never fires |
| iOS "share → add to home screen" modal | [src/components/IOSInstallModal.jsx](../../src/components/IOSInstallModal.jsx) | ✅ written, ⚠️ rarely triggers |
| Dismissal backoff (7 days, max 3 dismissals) | [src/utils/installTracking.js](../../src/utils/installTracking.js) | ✅ |
| Offline page + Workbox runtime caching config | [vite.config.js:44-93](../../vite.config.js) | ✅ configured, ❌ never applies |
| The head tags render for logged-out visitors too | verified on production `/` | ✅ |

No third-party PWA plugin (Super PWA, PWA for WP & AMP, …) is installed, and none is needed. The
standards-based setup here is already better than what those plugins produce for an SPA.

### What is broken

#### B1 — The service worker is never registered ❌ blocking

`useRegisterSW` is called in exactly one place, [src/components/ReloadPrompt.jsx:1](../../src/components/ReloadPrompt.jsx),
and `ReloadPrompt` is not rendered anywhere. [src/App.jsx](../../src/App.jsx) mounts `UpdateBanner`,
`OfflineBanner`, `InstallPrompt` and `IOSInstallModal` — not `ReloadPrompt`. It was dropped in
`8bd1e496 refactor(135-02): simplify App.jsx to root layout component`.

The plugin does not register it either: [vite.config.js:13](../../vite.config.js) sets
`injectRegister: null` with the comment "We'll inject meta tags via PHP" — but only the *meta tags*
were moved to PHP, the *registration* was never re-added anywhere.

Consequence, per [Chrome's installability criteria](https://developer.chrome.com/blog/update-install-criteria):
install from the three-dot menu still works (Chrome dropped the service-worker requirement for that
path in 108 mobile / 112 desktop), but **the omnibox install icon and the `beforeinstallprompt`
event still require a service worker with a `fetch` handler**. So `InstallPrompt.jsx` can never
show, and desktop Chrome/Edge never shows the install icon in the address bar. Users have to know
to dig into the browser menu.

#### B2 — Service worker scope cannot reach the app ❌ blocking

The worker is built to `/wp-content/themes/rondo-club/dist/sw.js`, and vite-plugin-pwa registers it
with `scope = base` (`node_modules/vite-plugin-pwa/dist/index.js:826`), i.e.
`/wp-content/themes/rondo-club/dist/`. A service worker's scope can never be broader than its own
directory unless the response carries a `Service-Worker-Allowed` header — and no such header is set
(checked the codebase and the live response).

So even after fixing B1, the worker would control nothing under `/`. `navigateFallback` to
`offline.html` and the `/wp-json/` `NetworkFirst` caching in
[vite.config.js:69-90](../../vite.config.js) would never fire, and Chrome would still not consider
the app installable via the prompt path.

#### B3 — Icons have transparent corners ⚠️ visible on every iPhone

Measured with sharp:

| File | Size | Top-left pixel |
|---|---|---|
| `apple-touch-icon-180x180.png` | 180×180 | `rgba(0,0,0,0)` — **fully transparent** |
| `icon-192x192.png` | 192×192 | `rgba(0,0,0,0)` — transparent |
| `icon-512x512.png` | 512×512 | `rgba(0,0,0,0)` — transparent |
| `icon-512x512-maskable.png` | 512×512 | `rgb(255,255,255)` opaque ✅ |

iOS does not support transparency in `apple-touch-icon` — it flattens onto **black**. Today an
iPhone home screen shows the Rondo hexagon on a black tile. The Android `purpose: any` icons have
the same issue on launchers that do not apply a mask.

The maskable icon is correctly built (opaque background, logo at 70% — inside the 80% safe zone),
but its background is white while the brand is navy `#001b60`.

#### B4 — Icons are upscaled from a 145 px source ⚠️

[scripts/generate-pwa-icons.js:20](../../scripts/generate-pwa-icons.js) reads
`public/icons/rondo-logo.png`, which is **145×148 px** — despite the file's own docblock saying
"Generate PWA icons from favicon.svg". `icon-512x512.png` is therefore a 3.5× upscale of a 145 px
bitmap, and non-square source into a square target. On a modern phone it reads as soft. The repo has
`favicon.svg` at the theme root — vector, correct source.

#### B5 — Manifest served without a `Content-Type` ⚠️

Verified live: `https://rondo.svawc.nl/wp-content/themes/rondo-club/dist/manifest.webmanifest`
returns 200 with `content-length`, `etag`, … and **no `content-type` header at all**. Chrome sniffs
and copes; Safari is stricter about manifests. Should be `application/manifest+json`.

#### B6 — `start_url` points at a route that does not exist ⚠️

The manifest sets `start_url: '/dashboard'` ([vite.config.js:22](../../vite.config.js)). There is no
`/dashboard` route — the dashboard is the index route at `/`, and `/dashboard` only resolves because
of the catch-all `{ path: '*', element: <Navigate to="/" replace /> }` at
[src/router.jsx:538](../../src/router.jsx). So every launch of the installed app costs a full
WordPress page render plus a client-side redirect. There is also no `id` field, so the app's identity
is derived from `start_url` — changing `start_url` later would register as a *different* app.

#### B7 — Manifest name and icon are hardcoded product branding ⚠️

The manifest is a **static Vite build artifact**, so `name`, `short_name` and the icon paths are
frozen at build time and identical on every deployment. Verified live:

| Site | WordPress site title | Manifest `name` |
|---|---|---|
| `rondo.svawc.nl` | **AWC Rondo** | Rondo Club |
| `demo.rondo.club` | **Rondo Demo** | Rondo Club |

So the home-screen label is wrong on both, and the same hardcoding appears in
[functions.php:788](../../functions.php) (`apple-mobile-web-app-title: "Rondo Club"`) and in the
Dutch install copy ("Installeer Rondo Club") in
[InstallPrompt.jsx](../../src/components/InstallPrompt.jsx) and
[IOSInstallModal.jsx](../../src/components/IOSInstallModal.jsx).

Same story for the icon: `FinanceConfig::OPTION_CLUB_LOGO_ID` (`rondo_finance_club_logo_id`) already
holds an uploaded club crest — set through Instellingen → Financieel, and used today for wallet
passes ([class-membership-pass-apple.php:180](../../includes/class-membership-pass-apple.php)) and
invoices. The PWA icons ignore it entirely and ship the Rondo hexagon.

Also: `lang: "en"` and `description: "Club data management"` on a Dutch-language app. No
`screenshots`, so Android shows the small install dialog instead of the richer one. No `shortcuts`
(long-press menu on the home-screen icon). No `display_override`.

#### B8 — No permanent "install" affordance ⚠️

Both install UIs are timed banners gated on engagement, and the gate is stricter than intended:
[useEngagementTracking](../../src/hooks/useEngagementTracking.js) reads the counter, compares the
**pre-increment** value, and only runs when `App` mounts — which in an SPA is once per full document
load, not per route change. So the Android banner needs 2 prior hard page loads in the same tab
session, and `IOSInstallModal` needs 3. In practice, almost nobody sees either. A member who wants
to install Rondo right now has no button to press.

#### B9 — Minor

- `sw.js` is served with `cache-control: max-age=31536000`. Browsers cap service-worker script
  caching at 24 h, so this is not fatal, but it should be `no-cache`.
- [useVersionCheck.reload()](../../src/hooks/useVersionCheck.js) unregisters **all** service workers
  and deletes **all** caches on every update. That is a deliberate workaround for
  `registerType: 'prompt'` leaving the new worker in `waiting`. Once the worker actually works, this
  throws away the precache on every deploy; `updateServiceWorker(true)` is the right call instead.

---

## 2. What actually matters, per platform

### iOS Safari — no service worker needed

"Add to Home Screen" is always available from the share sheet; nothing can make Safari offer it
automatically. What we control is (a) whether users *know* it exists and (b) whether the result looks
like an app.

- **Icon quality** — entirely `apple-touch-icon`. Fixing B3/B4/B7 turns a black-cornered blurry
  hexagon into a clean club crest. This is the single highest-visibility change in this document.
- **Standalone feel** — `apple-mobile-web-app-capable` is already set, so a launched icon has no
  Safari chrome. ✅
- **Home-screen label** — `apple-mobile-web-app-title` is set but hardcoded to "Rondo Club"
  ([functions.php:788](../../functions.php)); it should read the site title, "AWC Rondo". iOS
  truncates around 12 characters, so 9 is comfortable. ⚠️ B7
- **Discovery** — the iOS modal (B8) is the only education path, and it barely fires.

### Android Chrome — needs manifest + service worker

Manifest is fine (after B6/B7 polish). Without a controlling service worker there is no
`beforeinstallprompt`, no mini-infobar and no custom banner — only the buried "Add to Home
screen" / "Install app" menu item. Fixing B1 + B2 turns the existing `InstallPrompt.jsx` on.

### Desktop Chrome / Edge — same requirements

The install icon in the address bar needs the same manifest + service worker. Fixing B1 + B2 gets
it. This is also the answer to "save it as a desktop bookmark": an installed PWA gives a real dock /
Start-menu entry, which is strictly better than a bookmark.

---

## 3. Naming and icon design

### The name: read it from WordPress, don't hardcode it

`AWC Rondo` is already the WordPress site title on production, and it is already in
`window.rondoConfig.siteName`. Every place that currently says "Rondo Club" should read that value
instead. That gets the right answer on production **and** keeps "Rondo Demo" right on demo, with no
new setting and nothing club-specific baked into the build.

- `name` / `short_name`: `AWC Rondo` (9 characters — comfortably inside the ~12 iOS and Android
  truncate at).
- `apple-mobile-web-app-title`: same.
- Install-prompt copy: "Installeer AWC Rondo".

This requires the manifest to be served by PHP rather than emitted as a static file by Vite — see
W2. That single change also fixes the missing `Content-Type` (B5) for free.

### The icon: crest or hexagon or both?

Three options, judged at the size the icon is actually rendered — roughly 60×60 pt on iOS, 48 dp on
Android, 32 px in a desktop taskbar:

| | Option A: club crest | Option B: Rondo hexagon (today) | Option C: combination lockup |
|---|---|---|---|
| Recognition on a member's home screen | 🔥 "my club" | ⚠️ an unfamiliar product mark | ok |
| Legibility at 48 dp | good — crests are designed for it | good | ❌ two marks turn to mush |
| Survives Android's maskable crop | good if the crest sits inside the 80% safe zone | good | ❌ the secondary mark is exactly what gets clipped |
| Per-club correctness | 🔥 already configured per club | ❌ wrong for every club | ❌ needs a new asset per club |

**Recommendation: option A — the club crest, full-bleed on solid navy `#001b60`, no Rondo mark in
the icon.** Members are installing *their club's* app; the product name is already carried by the
label "AWC Rondo" underneath it. Rondo branding does not disappear — it stays on the splash screen
(`background_color` + `name` + the 512 icon), in the app itself, and the Rondo hexagon can remain
the browser-tab favicon, where product context is what you actually want.

If you'd still like a combination, the only version that survives at icon size is a crest-dominant
one: the crest centred on navy with a single Rondo-cyan (`#0891b2`) arc lifted from the hexagon mark
as a bottom accent or ring — one accent, not a lockup. That needs a designer pass and must be
checked at 48 dp under a circular mask before it ships. I would not do this in the first release.

**Source asset.** The crest is already in the database as `rondo_finance_club_logo_id` (Instellingen
→ Financieel), so no new upload is needed. I could not inspect it from here — it is behind auth —
so before implementing, check its aspect ratio and whether it has transparency, and prefer an SVG or
a ≥512 px master if one exists. If the stored crest is small or low quality, this is the moment to
replace it: the same asset drives invoices and wallet passes, so the upgrade pays off three times.

## 4. Ranked recommendations

Ordered by UX lift per unit of work.

| # | Change | Effort | Lift |
|---|---|---|---|
| **R1** | Regenerate icons from the **club crest**, opaque navy background, no transparency | ~2 h | 🔥 Members get their own club's icon instead of a black-cornered blur. No SW needed. |
| **R2** | Serve the manifest from PHP: name from the site title (**AWC Rondo**), `start_url: '/'`, `id`, `lang: 'nl'`, correct `Content-Type` | ~2 h | 🔥 Right name on every deployment, correct launch, stable identity, Safari-safe |
| **R3** | Permanent "Installeer app" entry in the sidebar/settings + a short per-platform help panel | ~2 h | 🔥 Gives every user a way to install *on purpose*, today, on all platforms |
| **R4** | Re-register the service worker **and** widen its scope to `/` | ~4 h | Unlocks Android install banner, desktop omnibox icon, offline |
| **R5** | Add `screenshots` to the manifest | ~1 h | Richer Android install dialog → measurably better conversion |
| **R6** | Fix the engagement gate so the existing banners actually fire | ~1 h | Makes R3's passive counterpart work |
| **R7** | Advanced tier (offline hardening, `shortcuts`, splash screens, install analytics) | ~1 d | Polish |

**Recommendation:** do R1 + R2 + R3 as one small release — no service-worker risk, and it covers iOS,
which is where most members are. Then R4 + R5 + R6 as a second release.

Do **not** install a PWA plugin. Super PWA / PWA for WP & AMP generate their own manifest and service
worker and would fight `vite-plugin-pwa` for the same registration and scope. Everything below is a
handful of lines in files we already own.

---

## 5. Concrete work items

### W1 — Regenerate icons from the club crest (R1)

Icon set needed — **all four opaque, no alpha channel**:

| File | Size | Purpose |
|---|---|---|
| `apple-touch-icon-180x180.png` | 180×180 | iOS home screen — **must** be opaque |
| `icon-192x192.png` | 192×192 | Android home screen, manifest `purpose: any` |
| `icon-512x512.png` | 512×512 | Android splash, install dialog |
| `icon-512x512-maskable.png` | 512×512 | Android adaptive icon, `purpose: maskable`, crest ≤ 80% |

Rewrite `scripts/generate-pwa-icons.js` to take the crest as its source and flatten onto navy
`#001b60`. Note `fit: 'contain'` plus `.flatten()` — the crest keeps its aspect ratio and the
letterboxing becomes solid navy rather than transparency:

```js
// scripts/generate-pwa-icons.js
const SOURCE = process.env.RONDO_ICON_SOURCE
  || join(projectRoot, 'public/icons/club-crest.png');   // ≥512px master, or an SVG
const NAVY = { r: 0, g: 27, b: 96, alpha: 1 };           // #001b60

// any-purpose + apple: crest at ~88% of the canvas, opaque navy behind it
for (const { name, size } of [
  { name: 'icon-192x192.png', size: 192 },
  { name: 'icon-512x512.png', size: 512 },
  { name: 'apple-touch-icon-180x180.png', size: 180 },
]) {
  const inner = Math.round(size * 0.88);
  await sharp(SOURCE, { density: 512 })
    .resize(inner, inner, { fit: 'contain', background: NAVY })
    .extend({                                             // pad back out to `size`
      top: Math.floor((size - inner) / 2), bottom: Math.ceil((size - inner) / 2),
      left: Math.floor((size - inner) / 2), right: Math.ceil((size - inner) / 2),
      background: NAVY,
    })
    .flatten({ background: NAVY })                        // <- kills the alpha channel
    .png()
    .toFile(join(iconsDir, name));
}

// maskable: same, but crest at 70% of the canvas (Android's safe zone is 80%)
```

Add an `npm run icons` script so this is reproducible, and keep the Rondo hexagon
(`favicon.svg`, `rondo-logo.png`) exactly where it is — it stays the browser-tab favicon in
[functions.php:806-811](../../functions.php).

**Getting the crest.** It already exists as the media attachment in
`FinanceConfig::OPTION_CLUB_LOGO_ID`. Two ways to use it, and they are a genuine fork:

- **A — commit it (recommended for this release).** Export the crest once, drop it in
  `public/icons/club-crest.png`, generate at build time. Simple, reviewable, cached like any static
  asset. Cost: demo.rondo.club would show the AWC crest too, which is wrong-ish for a demo — solvable
  by keeping the Rondo hexagon icons in the repo and selecting the source via an env var at build
  time, which the snippet above already allows.
- **B — generate server-side per club.** A WP-CLI command or a Settings button that reads
  `rondo_finance_club_logo_id`, runs it through `wp_get_image_editor()` (resize + flatten onto navy),
  writes the four PNGs into uploads, and stores their URLs for the manifest to reference. Correct for
  a multi-club future, but it is a new image pipeline in PHP for a benefit nobody needs while AWC is
  the only real deployment.

Do **A** now, and note **B** as the follow-up for when a second club onboards. If the stored crest
turns out to be small or low-quality, replace it in Instellingen → Financieel first — the same asset
drives invoices and wallet passes, so the upgrade pays off three times.

### W2 — Serve the manifest from PHP (R2)

The manifest has to stop being a static build artifact so `name` can follow the WordPress site
title. Drop `manifest` from the `VitePWA()` options and serve it from the theme instead:

```php
// functions.php — rewrite /manifest.webmanifest to a PHP handler
add_action( 'init', function () {
	add_rewrite_rule( '^manifest\.webmanifest$', 'index.php?rondo_manifest=1', 'top' );
} );
add_filter( 'query_vars', fn( $vars ) => array_merge( $vars, [ 'rondo_manifest' ] ) );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'rondo_manifest' ) ) {
		return;
	}

	$name  = get_bloginfo( 'name' );          // "AWC Rondo" on prod, "Rondo Demo" on demo
	$icons = RONDO_THEME_URL . '/public/icons';

	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );

	echo wp_json_encode( [
		'id'               => '/',            // stable identity, independent of start_url
		'name'             => $name,
		'short_name'       => $name,          // "AWC Rondo" = 9 chars, fits the label
		'description'      => sprintf( 'Ledenadministratie en vrijwilligersbeheer voor %s', \Rondo\Config\ClubConfig::get_club_name() ),
		'lang'             => 'nl',
		'dir'              => 'ltr',
		'start_url'        => '/',            // was '/dashboard' — not a real route
		'scope'            => '/',
		'display'          => 'standalone',
		'display_override' => [ 'standalone', 'minimal-ui' ],
		'orientation'      => 'any',
		'theme_color'      => '#0891b2',
		'background_color' => '#001b60',      // matches the icon, nicer splash than white
		'categories'       => [ 'sports', 'productivity' ],
		'icons'            => [
			[ 'src' => "$icons/icon-192x192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
			[ 'src' => "$icons/icon-512x512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
			[ 'src' => "$icons/icon-512x512-maskable.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
		],
	] );
	exit;
} );
```

Then point the head link at it — [functions.php:794](../../functions.php):

```php
<link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.webmanifest' ) ); ?>">
```

Flush rewrite rules on theme activation (the theme already has an activation hook for the Rondo User
role — add it there). Absolute icon URLs sidestep the fragile `'../public/icons/…'` relative paths
the static manifest uses today. Serving it from PHP also sets the `Content-Type` that is currently
missing entirely (B5), so no `.htaccess` `AddType` is needed.

While you are in `functions.php`, make the iOS title follow the same source —
[functions.php:788](../../functions.php):

```php
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
```

And in React, replace the two hardcoded "Installeer Rondo Club" strings in
[InstallPrompt.jsx](../../src/components/InstallPrompt.jsx) and
[IOSInstallModal.jsx](../../src/components/IOSInstallModal.jsx) with
`` `Installeer ${window.rondoConfig?.siteName ?? 'Rondo'}` `` — `siteName` is already in the config
object.

### W3 — Permanent install affordance (R3)

Add an item to `navigation` in [src/components/layout/Layout.jsx:50](../../src/components/layout/Layout.jsx)
(or under Instellingen), visible only when `!isInstalled`:

- If `canInstall` (Android/desktop, `beforeinstallprompt` captured) → call `promptInstall()`.
- Otherwise → open a small dialog with platform-detected instructions:
  - **iOS Safari:** Deel-knop → "Zet op beginscherm" → "Voeg toe"
  - **Android Chrome:** ⋮ → "App installeren"
  - **Desktop Chrome/Edge:** install icon in the address bar, or ⋮ → "Rondo installeren"

Reuse the copy already in [IOSInstallModal.jsx](../../src/components/IOSInstallModal.jsx). This is
the only item in this plan that works on every platform *today*, with no service worker.

### W4 — Re-register the service worker at root scope (R4)

Two independent fixes; both are required.

**(a) Register it.** Mount the existing component in [src/App.jsx](../../src/App.jsx):

```jsx
import { ReloadPrompt } from '@/components/ReloadPrompt';
// …
<ReloadPrompt />
```

**(b) Widen the scope.** Recommended — one header plus one config line:

```apache
# theme .htaccess
<Files "sw.js">
  Header set Service-Worker-Allowed "/"
  Header set Cache-Control "no-cache"
</Files>
```

```js
// vite.config.js
VitePWA({
  scope: '/',              // registration scope; requires the header above
  // …
})
```

*Fallback if SiteGround does not honour the header:* serve the worker from the site root through a
WordPress rewrite (`^sw\.js$` → a `template_redirect` handler that `readfile()`s the built worker
with `Content-Type: application/javascript`). If you take that route, also set
`workbox: { inlineWorkboxRuntime: true }` — the generated worker currently loads
`workbox-57649e2b.js` **relative to its own URL**, so a root-served `sw.js` would 404 on the runtime
chunk. Inlining removes the extra file.

**(c) Stop nuking the cache on update.** Replace the unregister-everything path in
[useVersionCheck.reload()](../../src/hooks/useVersionCheck.js) with `updateServiceWorker(true)` from
`useRegisterSW` (skip waiting + reload), so a deploy no longer discards the precache.

After this, verify in DevTools that the worker's scope reads `/` and that the page shows as
*controlled*.

### W5 — Screenshots (R5)

Two PNGs, referenced from the manifest, unlock Chrome's richer install dialog:

```js
screenshots: [
  { src: 'screenshots/mobile-dashboard.png', sizes: '1080x1920', type: 'image/png', form_factor: 'narrow' },
  { src: 'screenshots/desktop-dashboard.png', sizes: '1920x1080', type: 'image/png', form_factor: 'wide' },
],
```

Capture from the demo environment (`demo.rondo.club`) so no member data ends up in a public asset.

### W6 — Fix the engagement gate (R6)

In [useEngagementTracking.js](../../src/hooks/useEngagementTracking.js), compare *after* the
increment, and count route changes rather than document loads (subscribe to `useLocation()`), so an
SPA session actually accumulates page views. Same for the `pwa-page-views >= 3` read in
`IOSInstallModal`, which currently only samples the counter once at app mount.

---

## 6. Optional advanced tier

- **Real offline support.** Today `navigateFallback` never fires. Once the scope is fixed, decide
  what "offline" means for Rondo: a static offline page, or a read-only cached dashboard + people
  list from the existing `/wp-json/` `NetworkFirst` cache. The latter needs a call on caching
  personal data on shared devices — worth a separate decision.
- **`shortcuts`** in the manifest — long-press the icon for "Vrijwilligers", "Diensten", "Zoeken".
- **`apple-touch-startup-image`** splash screens for iOS (one PNG per device resolution; only worth
  it if the white flash on launch bothers people).
- **Install analytics** — `installTracking` already records dismissals locally; push
  `appinstalled` and dismissal counts to a REST endpoint so we can see whether any of this works.
- **Push notifications** — iOS supports web push only for installed PWAs (16.4+). If reminders ever
  need to reach phones, installability becomes a prerequisite, not a nicety.

---

## 7. Verification

### Done locally

- `npm run lint` clean, `npm run build` clean, `composer lint` clean.
- `composer test` — **398 tests, 0 failures** (was 389; the 9 new ones cover the manifest).
- Built worker inspected: no `NavigationRoute`/`createHandlerBoundToURL`, one `NetworkOnly` +
  `PrecacheFallbackPlugin` navigation route, registration compiled as
  `new Workbox("…/dist/sw.js", {scope: "/"})`, and `dist/manifest.webmanifest` no longer emitted.
- All four icons verified opaque (PNG colour type 2, not 4/6) and rendered at 48/60/192 px to check
  the crest survives the Android circular mask.

### The first thing to check after deploy

The service worker scope depends on an `.htaccess` header, and SiteGround may serve theme statics
through Nginx, which would ignore it:

```bash
curl -sI https://rondo.svawc.nl/wp-content/themes/rondo-club/dist/sw.js | grep -i 'service-worker-allowed\|cache-control'
```

If `Service-Worker-Allowed: /` is absent, registration fails with a `SecurityError` (visible in the
console as "SW registration error") and we are back to no worker — the icons and the manifest still
work, but the Chrome install prompt does not. Fallback in that case: serve the worker from the site
root via a WordPress rewrite and set `workbox: { inlineWorkboxRuntime: true }`, because the built
worker resolves its `workbox-*.js` chunk relative to its own URL.

Then confirm the manifest:

```bash
curl -sI https://rondo.svawc.nl/manifest.webmanifest | grep -i content-type
```

### Manifest and service worker (Chrome/Edge DevTools → Application)

- **Manifest** panel: no errors; **name reads "AWC Rondo"** (and "Rondo Demo" on demo — check both,
  this is the W2 regression test); icons all render and show the club crest; `start_url` correct;
  check the *Installability* line at the top — it names the exact missing criterion when the app is
  not installable.
- Confirm `curl -I https://rondo.svawc.nl/manifest.webmanifest` returns
  `Content-Type: application/manifest+json`.
- **Service Workers** panel: status `activated and is running`, **Scope = `/`** (this is the check
  for B2), "Update on reload" for iterating.
- **Cache Storage**: precache entries present after first load.
- Note: Lighthouse dropped its PWA category in v12, so the Application panel is the tool now.

### Desktop install

Load production in Chrome → an install icon should appear at the right of the address bar. Install,
confirm the app opens in its own window with the correct icon in the dock / taskbar, and that it
launches on `/` without a redirect flash.

### Android

Chrome on a real device (or remote-debug via `chrome://inspect`). Expect: `beforeinstallprompt`
fires → the Dutch install banner appears after the engagement threshold → install → check the home
screen icon is masked correctly (round launcher = no clipped logo, no white ring) and the splash
screen uses the manifest colours.

### iOS

Real device required — the Simulator's Safari does not do Add to Home Screen faithfully. Share sheet
→ "Zet op beginscherm" → confirm:
- the icon has **no black corners** (this is the B3 regression test),
- the icon shows the **club crest**, not the Rondo hexagon,
- the pre-filled label reads **"AWC Rondo"** and is not truncated on the home screen,
- launching it shows no Safari address bar,
- the status bar style looks right in both light and dark mode.

### Regression check for the icons

```bash
node -e "const s=require('sharp');(async()=>{for(const f of ['public/icons/apple-touch-icon-180x180.png','public/icons/icon-192x192.png','public/icons/icon-512x512.png']){const{data,info}=await s(f).raw().toBuffer({resolveWithObject:true});console.log(f,'alpha@0,0 =',data[3]);}})()"
```

Every value must be `255`. Worth adding as a CI check once W1 lands.

---

## 8. Open questions

1. **Crest quality** — is the crest currently in `rondo_finance_club_logo_id` a ≥512 px master or an
   SVG? I could not inspect it (behind auth). If it is small, replace it before W1; the same asset
   drives invoices and wallet passes.
2. **Icon background colour** — navy `#001b60` is taken from the Rondo hexagon. If the AWC crest has
   its own club colour, use that instead: the icon should read as the club's, not the product's.
3. **Demo branding** — keep the Rondo hexagon + "Rondo Demo" on demo.rondo.club (the env-var source
   switch in W1 handles it), or let demo show the AWC crest too? Recommendation: keep them distinct.
4. **Offline scope** — should Rondo cache member data for offline reading, given shared/family
   devices? Affects W4 and the advanced tier.
5. **Screenshots from demo or production** — demo is safer but its data looks fake in the install
   dialog.
