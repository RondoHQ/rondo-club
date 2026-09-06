# Rondo Capacitor login spike

Development experiment, version **0.7.0**. This is not the first app release and is not ready
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
  arbitrary WordPress endpoints. Separately consented sessions can use their own shift signup/cancel and profile routes. No global cookie/nonce or OIDC changes.
- Start, Passen with QR detail and server-provided pass choices, Vrijwillig with month navigation
  and counts of eligible duties, My duties, duty detail, My details and More. A compact passive
  header shows the configured club logo beside Rondo; no separate club-name row or switcher.
- Query cache and route history are scoped to a single in-memory login. Android back uses that
  route history. Returning from the system browser refreshes the current club's data.
- Own contact details and household address can be edited natively after profile consent.
  Wallet uses the club generators directly. Remaining household/contribution actions open the fixed
  `/mijn-gegevens` club page without app credentials in the URL.
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
| `POST /wp-json/rondo-mobile-spike/v1/shift` | Consented current member signup/cancel; fixed routes and no person selection |
| `POST /wp-json/rondo-mobile-spike/v1/revoke` | Revoke a device family using access or refresh token, idempotently |

Client ID: `rondo-mobile-spike`; scope: `rondo:spike:read` with optional `rondo:spike:volunteer` and `rondo:spike:profile`; callback:
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
4. Complete the member workflows: remaining household actions and
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

## Member signup and cancellation (0.5.0)

Version 0.5.0 introduced `rondo:spike:read rondo:spike:volunteer`; the browser consent explicitly explains
self-service signup/cancellation. Old read-only families stay read-only, including after refresh.
The scope is bound to the authorization code and device family, returned with each token pair,
and retained in pending authorization. Legacy pending records resume with the original read scope.

The duty write adapter is `POST /shift`, JSON `{shift_id, action, force_overlap}`. `action` is
`signup` or `cancel`; `force_overlap` is a boolean and can only be true for signup. Other keys,
person selection and arbitrary paths are rejected. It dispatches to the existing current member
routes, retaining eligibility, certificates, pool, capacity locks, signup windows, deadlines,
confirmation-mail scheduling and permission callbacks. It restores the original caller afterwards.

The app confirms the chosen duty before writing. Overlap requires a separate explicit choice;
late signup displays the existing 30-minute correction rule. Only `can_signup`/`can_cancel` allow
actions. One write runs at a time and POSTs are never retried automatically. A missing response
requires checking the current signup before retry. Successful writes invalidate member caches.
This shift milestone added no profile/admin/payment/Wallet writes; profile operations follow below. Tests and simulators use synthetic local
records and captured mail; the plugin still refuses staging/production.

## Native own-profile editing (0.6.0)

New logins request read, volunteer and `rondo:spike:profile` permission. Browser consent explains
own contact changes and household address effects. Old read-only and volunteer device families
keep their original permissions after refresh and must reconnect to grant profile access.

`GET /read?resource=profile` returns the own person from the existing filtered household response,
`can_edit`, `readonly_reason` and token-free `pending_email` metadata. Former-member and deceased
restrictions come from `MemberProfileService::linked_person_id()` on both read and every write.

`POST /profile` accepts exactly `action` and `values`. Fixed actions delegate to existing routes:

| Action | Required values | Original route |
|---|---|---|
| `phones` | All four phone slots | POST `/user/profile-phones` |
| `address` | street_name, house_number, house_number_addition, postal_code, city, state, country, country_code | POST `/user/household-address` |
| `email_request` | slot, email | POST `/user/profile-email/request` |
| `email_cancel` | Empty object | DELETE `/user/profile-email/pending` |
| `email_remove` | Empty object | DELETE `/user/profile-email/secondary` |

All original paths are under `/rondo/v1`. The adapter rejects unknown/missing fields, non-string
values, long values and caller-selected person IDs. The logged-in linked person is authoritative,
including when the token belongs to an administrator. Phone groups must be complete because the
existing service replaces all four slots. Original validation, phone normalization, household
propagation, secondary-email promotion, audit logs and sync markers are preserved. No sync is run
locally. Email verification uses the existing public verification page; app activation or the
explicit refresh button reads the actual result. Browser return alone is never proof of verification.

The member navigates through My details → Gegevens wijzigen. Address forms explain the effect on
minor children; email forms explain verification and matching child-address propagation. Pending
requests can be cancelled, and secondary email removal requires confirmation. Form drafts remain
in component memory only. Writes share one session guard with volunteer actions; they are never
queued or retried automatically. After an uncertain response, controls require a fresh profile
read before allowing another submission. Logout rejects stale write results.

Contribution and separate child/other-parent editing remain on the club site. The adapter
still requires local/development opt-in, and all test email is captured locally.

Validation for 0.6.0: 35 mobile JavaScript tests and 21 focused WordPress/MySQL tests (208 assertions)
pass. Web/mobile/native builds, mobile lint and PHP coding standards pass. Both simulators exercised
profile consent upgrade, phone save plus cold restart, and a pending secondary-email request.
iPhone exercised address save and pending-email cancellation. Android opened the captured verification
link in Chrome and showed the confirmed address after a cold app restart. Independent WordPress
reads verified persisted phone/address values and pending/verified email states. Android interruption
before delivery exercised the readback-only error state and recovery; actual loss of a response after
storage is covered by the client unit test. No physical device, real mailbox or local Sportlink sync
was used. Shared-service household propagation and former-member rejection are covered in PHP.

Version 0.6.1 separates the household action links with a wrapping gap and removes the province input. Existing `state` data remains in the complete address payload so saving another address field does not clear it.

The address form uses a single Dutch country dropdown from pinned `i18n-iso-countries` data. Selecting a country sets its name and two-letter ISO code together. Existing Dutch/English names and three-letter codes are resolved on opening; unknown values require an explicit selection. Neither province nor country code has a separate input.


## Direct Wallet handoff (0.7.0)

The pass detail shows only the device's provider: Apple on iOS, Google on Android. An unconfigured
provider has a short explanation; QR access remains available. Apple's `canAddPasses()` is checked
before offering its button. A browser preview cannot export a native Wallet pass.

`POST /wallet` accepts exactly `person_id`, `role` and `provider` (`apple` or `google`). The existing
read scope permits exporting an already accessible pass. The adapter checks the current token,
personal household (including for admins), visible-person access and the existing selected-pass
resolver, then calls the original Apple/Google generator. Entitlement, role, branding, pass version
and QR rules stay in those services. The route is development-only and never authenticates arbitrary
REST routes. Provider diagnostics are replaced by bounded, generic errors.

Apple responses contain base64 pass bytes (maximum 4 MiB decoded), kept only in process memory.
The narrow `RondoWallet` plugin uses `PKPass(data:)` and `PKAddPassesViewController`. No signing key,
pass file, clipboard content or additional Keychain record is created on the phone. Closing Apple's
sheet is not reported as a successful save. Google responses contain only an exact
`https://pay.google.com/gp/v/save/<signed JWT>` URL, validated on server and client before opening
Capacitor Browser. Rondo bearer/refresh tokens are never included in that link. Google completes
the add flow; returning to Rondo does not assert that the pass was saved.

Export is an explicit, serialized POST with no automatic retry or offline queue, since the Google
generator may create/update its Wallet object. Logout or leaving the pass screen discards a late
result. Responses use `Cache-Control: no-store`; exports are excluded from the query cache and vault.
Wallet passes already saved by a member remain in their Wallet after Rondo logout; existing server
entitlement/version checks still govern scans.

The local fixture contains no real Apple signing certificate or Google issuer credentials. Tests
cover access denial, invalid input, missing configuration, safe handoff data, provider failures and
late/logout responses. Successfully adding a signed pass to a real Wallet still requires an approved
test issuer/certificate and device validation. Do not copy production keys into this fixture.

References: [Apple PassKit](https://developer.apple.com/documentation/passkit/pkaddpassesviewcontroller)
and [Google save links](https://developers.google.com/wallet/generic/web).

Native validation: both simulators exercised the matching button and invalid-provider error. iOS also rejected an intentionally corrupt pass through the actual PassKit bridge. All temporary invalid Wallet configuration was removed afterwards.


A subsequent signed-pass test succeeded on 6 September: the existing AWC server signed one clearly
marked synthetic pass, keeping its private key on that server. A local fixture delivered only that
pass after the normal adapter checks. The real native add sheet, cancellation, addition and the
saved pass in Apple's Wallet app were verified. This does not establish production mobile login or
live member-pass issuance. See [signed-pass test evidence](../docs/prd/mobile-wallet-test.md).
