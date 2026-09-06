# Rondo Capacitor login spike

Development experiment, version **0.4.1**. This is not the first app release and is not ready
for TestFlight, Google Play, or production installation. The agreed screen design remains in
`docs/prd/mobile-app-first-release.md`.

## What this milestone implements

- Separate, locally packaged React build with Capacitor 8.5.1 iOS and Android projects.
- Build-time allowlisted HTTPS clubs, search and confirmation, passive club heading.
- Browser login with a public client, S256 PKCE, state, fixed callback and one-use code.
- Five-minute access tokens in memory; native secure storage and rotating refresh tokens restore
  the active club after a process restart for up to 30 days from login.
- Read-only adapter dispatching to existing profile, household, personal pass, own-duty and member-calendar REST routes.
  Their permission callbacks and data filters remain authoritative. Tokens cannot authenticate
  arbitrary WordPress endpoints or writes. No global cookie/nonce or OIDC changes.
- Start, Passen with QR detail and server-provided pass choices, Vrijwillig with month navigation
  and counts of eligible duties, My duties, duty detail, My details and More. A compact passive
  header shows the configured club logo beside Rondo; no separate club-name row or switcher.
- Query cache and route history are scoped to a single in-memory login. Android back uses that
  route history. Returning from the system browser refreshes the current club's data.
- External write/Wallet actions open fixed `/vrijwillig` or `/mijn-gegevens` club pages, with no
  app credentials in the URL. Native signup, cancellation, editing and Wallet delivery are later work.
- Explicit logout/revocation and club switching under **Meer → Mijn clubs**.
- Tests for wrong club/state/verifier, replay, expiry, revocation, password/access changes,
  household filtering, caller restoration and stale responses after logout.

The spike uses a separately installed WordPress plugin. Nothing in the theme loads it. It
requires both `RONDO_MOBILE_SPIKE === true` and environment `local` or `development`; even
with that constant set it must remain unavailable in `staging` and `production`.

## Build

From the repository root, install normal project dependencies for shared lint tooling, then:

```sh
npm ci --prefix mobile
npm test --prefix mobile
npm run sync --prefix mobile
npm run ios --prefix mobile
npm run android --prefix mobile
```

Capacitor reads `mobile/capacitor.config.json`. There is no `server.url`, service worker,
remote HTML, embedded client secret, or browser credential-storage fallback. Generated platform source
is tracked; web assets are copied by `sync` and ignored in Git. Native IDs are deliberately
`club.rondo.spike`, separate from the future released app. Use a separate signing profile for
this experiment. Never commit signing keys, provisioning credentials or local SDK paths.

Configure approved test clubs in ignored `mobile/.env.local`, then rebuild and sync:

```dotenv
VITE_SPIKE_CLUBS='[{"id":"test-alpha","name":"Testclub Alpha","url":"https://alpha.example.test"},{"id":"test-beta","name":"Testclub Beta","url":"https://beta.example.test"}]'
```

These are documentation placeholders. Use two actual, isolated development sites with trusted
HTTPS certificates and synthetic data. HTTP, credentials in URLs, duplicate IDs, subdirectory
installations and arbitrary callback-selected endpoints are deliberately unsupported. The default
build contains **no clubs**, so it cannot accidentally contact a live club. A browser preview can
check club selection but does not implement a substitute browser-only login flow.

## WordPress setup and protocol

Install `spike-plugin/rondo-mobile-spike.php` as a plugin on each isolated development site.
Set `WP_ENVIRONMENT_TYPE` to `local` or `development`, explicitly define
`RONDO_MOBILE_SPIKE` as `true`, and activate the plugin. Keep outgoing email captured locally.
Use the real Rondo theme/API. Do not copy production people, credentials or Wallet settings.

The directory entry origin must equal the site's canonical `home_url`. The native HTTP adapter
has redirect following disabled, sends no app-added cookies or REST nonce, and never learns an
API origin from a callback. All token payloads are stored server-side under hashed token keys;
an explicit canonical-club audience and expiry are checked when loading a code or session.
This prototype does not implement a durable installation UUID or a signed club registry.

| Request | Purpose |
|---|---|
| `GET /wp-json/rondo-mobile-spike/v1/config` | Protocol and canonical club origin |
| `GET /wp-admin/admin-post.php?action=rondo_mobile_spike_authorize&…` | Validate client/redirect/PKCE, then existing WordPress login and consent |
| `POST` to the same authorization action | Cookie-authenticated consent with WordPress nonce; two-minute one-use code |
| `POST /wp-json/rondo-mobile-spike/v1/token` | Code/PKCE exchange or refresh rotation; five-minute access, absolute 30-day device session |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=me` | Existing current-user response |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=household` | Existing permission-filtered household response |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=my-shifts` | Original current member's duties |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=calendar&month=YYYY-MM` | One month, forced member/signup view |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=pass&person_id=…&role=…` | Original QR route, restricted to personal household passes even for admins |
| `POST /wp-json/rondo-mobile-spike/v1/revoke` | Revoke a device family using access or refresh token, idempotently |

Client ID: `rondo-mobile-spike`; scope: `rondo:spike:read`; callback:
`club.rondo.spike://oauth/callback`. This is a private-use callback for the experiment, **not**
the planned verified Universal Link/App Link. The OS registrations are in the iOS plist and
Android manifest. Interception cannot redeem a code without its verifier, but verified HTTPS
links remain a release gate. This adapter is not advertised as a complete OAuth/OIDC provider.

A unique WordPress option atomically claims each consumed code, with a scheduled cleanup.
Sessions are transients and expire even if cleanup is delayed. Revocation or a changed password
invalidates a session; removing the `read` capability also blocks it. Closing a browser login
session alone does not revoke the app token. Other role changes take effect through the original
REST callbacks. Refresh families and durable offline revocation are described below.

## Persistent device sessions (0.3.0)

`DeviceSession` serializes vault writes and coalesces refresh requests. Startup validates the saved
club against the compiled directory, rotates its refresh token and saves the replacement before
publishing access. Five-minute access tokens, personal responses and QR codes stay in memory. A network error retains the encrypted login for retry; invalid grants
require a new login. There is no offline personal-data mode.

The local `RondoSessionVault` bridge supports only read/write/clear for one bounded record. iOS
uses a nonsynchronizing Keychain item with `WhenUnlockedThisDeviceOnly` and a reinstall marker.
Android uses AES-256-GCM with a nonexportable Keystore key and an AtomicFile in `noBackupFilesDir`.
Neither implementation falls back to browser storage. Capacitor bridge logging is disabled even
in debug builds, keeping plugin arguments and results out of logs.

WordPress stores hashed refresh-token keys and an opaque device-session family in options with
autoload disabled. Atomic claims prevent replay; reusing a consumed refresh token revokes the
whole family, including later access tokens. Password changes, removed read access, club audience
mismatch and absolute expiry invalidate access. Families expire 30 days from login, without sliding
extension. Consumed hashes and claims remain until expiry for reuse detection and cron cleanup.
Production scaling, rate limits and account-facing device management still need review.

Logout invalidates in-flight reads immediately and durably removes the active login before network
revocation. Offline revocations stay encrypted for the next startup; the server family may remain
valid until that retry or its absolute expiry. Storage errors are reported as incomplete logout.
A lost refresh response requires fresh login after retry rejection; there is no replay grace period.

Simulator Keychain access requires local signing (no developer account needed):

```sh
xcodebuild -project mobile/ios/App/App.xcodeproj -scheme App \
  -destination 'generic/platform=iOS Simulator' -configuration Debug \
  -derivedDataPath /private/tmp/rondo-spike-simulator \
  CODE_SIGNING_ALLOWED=YES CODE_SIGN_IDENTITY=- build
```

`Simulator.entitlements` applies only to simulator SDK builds. Physical builds need real
team-prefixed entitlements from Apple provisioning. An unsigned simulator build compiles but
cannot access Keychain (`-34018`); do not replace secure storage to bypass this error.

## Email login and unfinished authorization (0.4.0)

The existing club login remains responsible for account authentication. When Magic Login is
installed, the development plugin preserves a strictly validated mobile authorization destination
in an existing account's email link and its final redirect. Provider token creation, nonce,
throttling and email/account eligibility remain in the existing provider and Rondo activation flow.
Other destinations are unchanged. No new mail sender or production authentication hook is added.

Before opening the system browser, the app saves the reviewed club ID/origin, PKCE verifier,
state and creation time in the same native vault. This pending attempt expires after ten minutes.
Startup restores it before processing a native launch URL; the app can also reopen the same
inlog window or cancel. Cancellation and valid denials erase it durably. Wrong-state callbacks
cannot cancel it. Successful callbacks consume it before the code exchange; duplicate native
notifications share that exchange. A lost exchange response requires a fresh login.

Only an existing linked account is covered. New-account activation, household-selection journeys,
physical devices and a real Mail/Gmail application return still require separate verification.
The callback remains the experimental private scheme, not a verified Universal Link/App Link.

## Website branding

The compact header uses the website's actual `rondo-wordmark.svg`, with the club logo on its left.
Figtree 600/700/800 headings and the navy `#001B60`, teal `#00908B`, purple `#993399`, surface and
border palette match the Rondo website. The native launcher and splash images are rendered from
its unchanged `rondo-logo.svg`, centered with padding for platform masks. Fonts are bundled with
their OFL license, without remote requests.

## Verification

See `docs/prd/mobile-app-spike-results.md` for the actual evidence and outstanding gates.
The PHP tests require the normal WordPress/MySQL test setup, using a disposable database:

```sh
WP_ENVIRONMENT_TYPE=local vendor/bin/codecept run Wpunit MobileSpikeTest
node_modules/.bin/eslint mobile/src --max-warnings 0
vendor/bin/phpcs mobile/spike-plugin/rondo-mobile-spike.php tests/Wpunit/MobileSpikeTest.php
```

CI runs the JavaScript contract tests and native asset sync, plus the PHP integration tests in
an explicitly local environment. Sync is not native compilation or device testing.

For Android builds on this Mac, use Java 21 explicitly (the current Android Studio bundles Java 25):

```sh
cd mobile/android
JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home ./gradlew assembleDebug
```

Local HTTPS fixtures use a disposable CA trusted only by test simulators. A temporary Android
`src/debug` network-security override is local test instrumentation, not part of the repository
or a release build. Never add its key, certificate or trust override to a production package.

## Required follow-up before choosing a production auth implementation

1. Native simulator builds and local password login now work on iOS 26.2 and Android API 36;
   see `docs/prd/mobile-app-spike-results.md` for evidence. Run on real iPhone and Android
   with two independent HTTPS test clubs. Test browser cancellation, warm/cold callback, real email
   return, timeout, airplane mode and switching clubs during requests.
2. Independently review the native vaults and refresh protocol on physical devices, including
   uninstall/reinstall, locked device, backup/restore and concurrent requests. See the results document
   for simulator and contract evidence; this is still development-only authentication.
3. Replace the experimental adapter with reviewed production native authorization, verified
   HTTPS callbacks, stable installation identity and the mobile config/API adapter. Retain all
   existing web and FreeScout contracts.
4. Complete the member workflows: native write actions, direct Wallet delivery, full profile and
   contribution controls, guest passes and configurable capability navigation. The read screens
   reuse server contracts; browser and app share `src/hooks/usePassQr.js`.
5. Add remaining release work: background snapshot privacy, Wallet/payment
   handoffs, push, accessibility/device verification, store metadata, reviewer access and accounts.

Official references: [environment setup](https://capacitorjs.com/docs/getting-started/environment-setup),
[Browser](https://capacitorjs.com/docs/apis/browser), [App callbacks](https://capacitorjs.com/docs/apis/app),
[native HTTP](https://capacitorjs.com/docs/apis/http), [native OAuth guidance](https://www.rfc-editor.org/rfc/rfc8252).

Club logos have no frame, background or inner padding. A reviewed directory entry may provide
`logoUrl` with an explicit public HTTPS image URL, including a separate official club website.
AWC uses `https://www.svawc.nl/wp-content/uploads/2024/02/awc-logo.svg`; the local Alpha fixture
uses that same configured image. Without an override, the API's same-origin logo remains the fallback.
The reviewed logo is restored from the build directory, not from persisted session metadata.

Membership pass cards use `pass.background_color` from the existing QR response and share the
web pass's color fallback and pass-type presentation helpers. Sponsor cards use dark text on
the light server background; businessclub passes retain their specific `pass.logo_url` rather
than the general club logo. Normal passes prefer the reviewed club logo, then a same-origin
server logo. Logos render without a frame or padding and disappear on image failure. General
Rondo branding remains on the surrounding app, not as a forced gradient on the membership card.
