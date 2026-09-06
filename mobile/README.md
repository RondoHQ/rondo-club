# Rondo Capacitor login spike

Development experiment, version **0.1.0**. This is not the first app release and is not ready
for TestFlight, Google Play, or production installation. The agreed screen design remains in
`docs/prd/mobile-app-first-release.md`.

## What this milestone implements

- Separate, locally packaged React build with Capacitor 8.5.1 iOS and Android projects.
- Build-time allowlisted HTTPS clubs, search and confirmation, passive club heading.
- Browser login with a public client, S256 PKCE, state, fixed callback and one-use code.
- A five-minute **memory-only** session; closing the app requires signing in again.
- Read-only adapter dispatching to the existing `user/me` and `people/household` REST routes.
  Their permission callbacks and data filters remain authoritative. Tokens cannot authenticate
  arbitrary WordPress endpoints or writes. No global cookie/nonce or OIDC changes.
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
remote HTML, embedded client secret, or persistent credential store. Generated platform source
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
| `POST /wp-json/rondo-mobile-spike/v1/token` | Exchange exact client, callback, code and verifier for five-minute token |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=me` | Existing current-user response |
| `GET /wp-json/rondo-mobile-spike/v1/read?resource=household` | Existing permission-filtered household response |
| `POST /wp-json/rondo-mobile-spike/v1/revoke` | Revoke the supplied bearer token, idempotently |

Client ID: `rondo-mobile-spike`; scope: `rondo:spike:read`; callback:
`club.rondo.spike://oauth/callback`. This is a private-use callback for the experiment, **not**
the planned verified Universal Link/App Link. The OS registrations are in the iOS plist and
Android manifest. Interception cannot redeem a code without its verifier, but verified HTTPS
links remain a release gate. This adapter is not advertised as a complete OAuth/OIDC provider.

A unique WordPress option atomically claims each consumed code, with a scheduled cleanup.
Sessions are transients and expire even if cleanup is delayed. Revocation or a changed password
invalidates a session; removing the `read` capability also blocks it. Closing a browser login
session alone does not revoke the app token. Other role changes take effect through the original
REST callbacks. Offline logout clears app memory; server revocation may fail and then the token
expires within five minutes. There is no durable retry queue in this milestone.

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

## Required follow-up before choosing a production auth implementation

1. Install iOS platform/simulator components and Android Studio/SDK/JDK; run on real iPhone and
   Android with two HTTPS test clubs. Test browser cancellation, warm/cold callback, real email
   return, timeout, airplane mode and switching clubs during requests.
2. Implement and test approved Keychain/Keystore storage, rotating per-device refresh tokens,
   reuse detection, concurrent refresh and revocation. This spike deliberately does not persist
   credentials. Its successful tests provide **no evidence** for those unimplemented guarantees.
3. Replace the experimental adapter with reviewed production native authorization, verified
   HTTPS callbacks, stable installation identity and the mobile config/API adapter. Retain all
   existing web and FreeScout contracts.
4. Reuse the agreed member UI, including passes and the full volunteer calendar. This milestone
   has only connection/login/profile proof screens; it does not duplicate those feature screens.
5. Add remaining release work: background snapshot privacy, Android back behavior, Wallet/payment
   handoffs, push, accessibility/device verification, store metadata, reviewer access and accounts.

Official references: [environment setup](https://capacitorjs.com/docs/getting-started/environment-setup),
[Browser](https://capacitorjs.com/docs/apis/browser), [App callbacks](https://capacitorjs.com/docs/apis/app),
[native HTTP](https://capacitorjs.com/docs/apis/http), [native OAuth guidance](https://www.rfc-editor.org/rfc/rfc8252).
