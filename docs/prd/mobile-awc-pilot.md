# AWC pilot on physical devices

Status: implementation prepared in the development PR; **not deployed, signed, uploaded or invited**.
The five requested testers have existing accounts linked to published AWC people. Their account
IDs and person IDs were checked on production read-only. The actual tester configuration is kept
outside Git, disabled. Private email addresses supplied for TestFlight do not replace the existing
AWC account email/login. No invitations or login email have been sent.

## First pilot scope

One Rondo app for clubs remains the product direction. This restricted build lists only AWC and
has its own `club.rondo.pilot` app ID, secure storage identity and server protocol. Members can
view their own household, calendar and existing duties, show available passes and add their real
passes to Apple/Google Wallet. The app has no profile-edit or signup/cancel actions; the server
rejects both write routes even with a valid pilot token. Browser links to editing and contribution
controls are omitted. Wallet generation uses the existing club services with the same household,
role, eligibility and visible-person checks; its credentials stay on the server.

## Authentication and account boundaries

The shared authorization runtime retains S256 PKCE, an exact callback, state, short-lived one-use
codes and atomic replay claims. It is shared with the development experiment so fixes do not drift.
The pilot is an explicitly installed plugin, never theme-autoloaded. Its namespace, code/access/
refresh/family/claim keys and cleanup hook differ from the experiment. A spike token, client or
callback is not a pilot credential. The spike's local/development environment restriction remains.

Pilot authorization requires the exact AWC origin, `RONDO_MOBILE_PILOT === true`, an enabled
`rondo_mobile_pilot` option with a future deadline, an epoch of at least 32 characters and 1–20
explicit tester pairs. Both the account ID and its linked published person ID must match on
consent, code exchange, refresh and every data request. Access is never inferred from a shared
email address, administrator role or household relation. The normal underlying REST permissions
remain authoritative. Changing the policy invalidates issued credentials; changing a password,
relinking/removing a tester or reaching the deadline blocks them immediately. To permanently
revoke all sessions when disabling/re-enabling, rotate the epoch as well as toggling enabled.

The only scope is `rondo:pilot:read`, including Wallet export. Access lasts at most five minutes;
a refresh family lasts at most seven days from login and the pilot deadline is checked on every
request. Refresh reuse revokes the family. Logout removes the encrypted local session before
network revocation; an offline revocation is retained for retry. No personal data is cached to disk.
A fixed 60/minute per-source-IP quota applies to token and Wallet endpoints using atomic WordPress
option claims. Forwarded IP headers are not trusted. Shared NATs may reach this limit together.

## Build and signing

Run `npm run prepare:pilot --prefix mobile`. The command creates a new temporary project and
plugin bundle, builds assets with Vite's `pilot` mode and syncs native projects. It never changes
the current simulator install or copies `.env`, local SDK settings or temporary debug CA overrides.
Release builds use standard system TLS trust. The generated iOS app covers its background snapshot;
Android uses `FLAG_SECURE` to prevent screenshots and recents previews of live member data.

The pilot callback is `https://rondo.svawc.nl/rondo-app/callback`. The iOS Associated Domains
entitlement and Android verified HTTPS intent filter target this exact host/path, with no custom
scheme fallback. The server fallback contains no pass, code, analytics or external assets.
Production access logging must redact callback query strings before activating login. A code that
falls back to the web still expires after two minutes and cannot be redeemed without PKCE.

Supply real, public signing identifiers through `RONDO_APPLE_TEAM_ID` (10 characters) and
`RONDO_ANDROID_CERT_SHA256` (colon-separated uppercase SHA256) to generate matching association
files. Never use placeholders or the simulator's ad-hoc identity for live association. The generated
`READINESS.json` distinguishes prepared files from signing/upload/deployment, which it does not do.
For TestFlight, create/select the `club.rondo.pilot` App ID with Associated Domains, configure the
real development team, archive and distribute using Apple signing. No signing identity is currently
available on this Mac. The generated iPhone Release build was compiled unsigned only, which does
not demonstrate installation, Keychain behavior or verified links on a physical device.

## Controlled activation and TestFlight handoff

1. Review and merge the code through the normal main workflow; never deploy from the worktree.
   The theme release alone does not install or enable either app plugin.
2. Configure Apple signing and the App Store Connect record. Confirm whether the five testers
   are external testers and complete TestFlight's required beta review and app declarations.
   Upload only the reviewed pilot artifact; send invitations only as an explicitly authorized step.
3. Publish the real association files at the AWC origin's `/.well-known/` paths without redirects,
   with JSON content type. Verify the signing identity matches the exact delivered app. Configure
   callback query redaction at the web server. Do not change global TLS or web authentication.
4. Install the prepared `rondo-awc-pilot` plugin on AWC after the code/security review. Recheck
   the five account/person pairs, set a bounded pilot deadline and fresh epoch, and explicitly
   enable it. No account creation, role changes, member updates or copied production data needed.
5. On an actual installed build, verify Universal/App Link ownership, browser and mail login,
   cancellation, cold/warm return, offline behavior, logout, lost/replayed refresh and app reinstall.
   Confirm own-household and denied outsider access before expanding from the first tester.
6. Verify one real eligible pass per available provider, including role choice, cancellation,
   successful addition and its normal scanner behavior. Then invite the remaining testers.

Current evidence: 45 JavaScript tests, 7 pilot PHP tests (119 assertions), and 22 existing spike
PHP tests (228 assertions) pass locally. iPhone and Android Release compilation passed without
signing or device installation. These checks do not constitute production or TestFlight verification.

References: [Apple associated domains](https://developer.apple.com/documentation/xcode/supporting-associated-domains),
[Android website associations](https://developer.android.com/training/app-links/configure-assetlinks),
[native OAuth guidance](https://www.rfc-editor.org/rfc/rfc8252).

The iPhone pilot uses `ASWebAuthenticationSession` with the exact HTTPS callback (iOS 17.4+),
including the `webcredentials` association alongside Universal Links. It does not rely on
a normal in-browser same-origin redirect to open the app. Mail callbacks close the pending native
authentication session after successful exchange. This still requires signed physical-device verification.

The generated iPhone target requires iOS 17.4 and includes an app-level privacy manifest for the
app-private UserDefaults reinstall marker (CA92.1). App Store privacy/export declarations and the
final archive privacy report still require review; this manifest does not assert a data-collection policy.
