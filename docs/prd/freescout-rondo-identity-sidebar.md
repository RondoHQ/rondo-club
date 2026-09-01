# FreeScout sidebar, Rondo identity and mailbox provisioning

**Status:** draft PRD, updated 2026-09-01<br>
**Scope:** Rondo Club and one custom Rondo Integration FreeScout module<br>
**Milestone type:** planning only; this document does not authorize implementation or production changes

## Outcome

FreeScout agents sign in through Rondo, receive FreeScout mailbox access from their effective Rondo
capabilities, and see live Rondo person context beside a conversation without copying that context
into FreeScout.

The first managed access rule is:

```text
Rondo capability ledenadministratie -> FreeScout mailbox Ledenadministratie
```

Current membership of the Ledenadministratie commissie can already grant the
`rondo_ledenadministratie` role through `CommissieCapabilityMap`. That role carries the
`ledenadministratie` capability. FreeScout provisioning therefore consumes the **effective Rondo
capability**, not raw committee rows. This preserves the existing capability sync, administrator
guard and manual grant/revoke behavior.

## Decisions already taken

1. Build one custom **Rondo Integration** FreeScout module for OIDC login, the sidebar and managed
   mailbox provisioning. Use the MIT-licensed `fulldecent/freescout-sidebar-webhook` implementation
   and relevant forks as attributed references, not as a maintained fork or separate installed
   module.
2. The sidebar varies by FreeScout mailbox. Ledenadministratie receives a more extensive view than
   a general mailbox.
3. Rondo becomes an OpenID Connect provider for FreeScout agents. The Rondo Integration module is
   the relying party and implements authorization code flow with PKCE S256, `state`, `nonce`, ID-token
   validation and UserInfo subject matching.
4. Read-only sidebar authorization uses the signed current FreeScout agent mapped to a Rondo user.
5. Actions that mutate or expose a complete record open Rondo and require its normal WordPress
   session.
6. FreeScout mailbox access is provisioned from Rondo capabilities. The first mapping is
   `ledenadministratie` to the Ledenadministratie mailbox.
7. Access managed by this integration is removed automatically when the capability disappears.
   Mailbox access assigned manually outside the managed mapping is never changed.
8. Failure of Rondo or the sidebar endpoint must never block normal FreeScout conversation work.
9. New FreeScout installations receive a known fixed Rondo Integration module version and then use
   FreeScout's built-in third-party module updater to reach the latest approved release.
10. The Rondo Integration module binds each Rondo `sub` to exactly one FreeScout user before that
    identity can be trusted for later logins; email is used only for the controlled first link.
11. The Rondo installation URL is environment-specific configuration. No Rondo or FreeScout
    hostname is embedded in module code.
12. FreeScout runs with `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true`. Its default `false` value lets a
    non-administrator with zero mailboxes open customer profile and edit routes by ID even though
    the related conversation is denied.
13. The paid OAuth Login add-on is not a production dependency. Its compatibility spike is retained
    as rejection evidence; the custom module owns the complete login and recovery flow.
14. A failed custom OIDC callback returns once to `/login?rondo_oauth=0`, preserves a safe visible
    error and never automatically starts a second authorization request. Production forcing remains
    disabled until the released module passes this proof.
15. The unsupported FreeScout Design module has been disabled. Rondo Integration replaces only the
    required appearance controls: semantic accent colors and responsive conversation-sidebar width.
    It does not become a general FreeScout theme or accept arbitrary CSS.

## Why this replaces copied customer context

The current Rondo Sync FreeScout pipeline copies customer fields from Rondo into FreeScout:

- names, email addresses and phone numbers;
- profile photo and Rondo/Sportlink links;
- current teams, KNVB ID and membership dates;
- relation-end data;
- current contribution balance and status.

The proposed sidebar reads current Rondo data when a conversation opens. It removes the need to
keep most of these secondary FreeScout fields synchronized and prevents stale information from
being treated as authoritative.

It does **not** automatically replace the separate FreeScout-conversations-to-Rondo activity
pipeline. That pipeline remains enabled during the initial rollout until its historical value and
customer-matching dependency have been reviewed separately.

## Goals

- One Rondo identity for Rondo and FreeScout.
- No FreeScout access for an unapproved or ineligible Rondo user.
- Automatic grant and revocation of managed mailbox access.
- Live, mailbox-specific Rondo context in the FreeScout conversation sidebar.
- Rondo permissions remain authoritative for every field returned.
- Exact, auditable customer matching with safe zero-match and multiple-match states.
- No shared secret in a request body, browser code, URL or log.
- A slow or unavailable endpoint cannot exhaust FreeScout PHP workers.
- A sidebar response cannot execute arbitrary code in the FreeScout page.
- Existing manually managed FreeScout access remains untouched.
- Administrators can align FreeScout's blue accent surfaces with the club identity without editing
  FreeScout core files.
- The desktop customer sidebar can be wider while preserving usable conversation space and
  FreeScout's narrow-screen layout.

## Non-goals

- Replacing FreeScout's own conversation, assignment, Team or mailbox-permission model.
- Mapping all Rondo roles to FreeScout in the first release.
- Editing Rondo person fields directly inside the sidebar.
- Showing the complete Rondo person record.
- Automatically deactivating every FreeScout account that loses one managed mailbox.
- Replacing the FreeScout conversation activity sync in the first release.
- Making Rondo a public, general-purpose identity provider for third parties.
- Supporting implicit OAuth flows or password grants.
- Replacing every feature of the retired Design module or offering unrestricted CSS injection.
- Recoloring semantic success, warning, destructive or availability states as branding.

## Current systems and constraints

### Rondo identity and capabilities

- A provisioned WordPress user links to one person through `rondo_linked_person_id` user meta.
- `CapabilitySync` derives current commissie IDs from the linked person's `work_history` field.
- `CommissieCapabilityMap` maps commissie IDs to Rondo roles.
- `rondo_ledenadministratie` carries the `ledenadministratie` capability.
- Capability sync respects manual grants and revocations and never alters administrators.
- `AccessControl` already treats FreeScout/onboarding fields as a separate support-sensitive group
  readable by ledenadministratie and administrators.
- Some shared-email accounts use a synthetic `@members.rondo.invalid` WordPress email. Such an
  address cannot become an external FreeScout agent identity.

### Rejected FreeScout OAuth Login add-on

The paid OAuth Login add-on was evaluated because it accepts a custom provider with:

- Authorization URL;
- Token URL;
- User Info URL;
- client ID and client secret;
- claim-to-user-field mapping.

It can force OAuth login and optionally create new FreeScout users. Version `1.0.28` validates
`state`, but sends neither PKCE nor an OpenID Connect `nonce`, consumes User Info, ignores `sub` and
`email_verified` during user matching, and looks up an existing user only by email. Automatic user
creation is enabled by default. Its force-login error path also loops after provider denial.

The product decision is to remove this add-on and build the login client inside Rondo Integration.
No paid-module hook, route, setting, license key or runtime code is required in production.

Reference: <https://freescout.net/module/oauth-login/>

### Sidebar Webhook reference implementation

Upstream version `3.2.2` sends customer, conversation and mailbox context to a configured endpoint
and injects the returned HTML into the conversation page.

Its current operational implementation is small, generic and MIT licensed. The Rondo Integration
module may reuse compatible portions with the original copyright and license notice, but it owns
its own name, release lifecycle, security model and configuration. The upstream module is not
installed alongside it.

Relevant reference work:

- `8c88174489686536431640395ed7b1b8c30fad2d` moves the returned content higher in the FreeScout
  sidebar DOM so it can contain multiple panels and accordions. Reproduce the layout intent in the
  custom module; do not blindly copy the old commit.
- `w-paheg` commit `ecfd64dc59e17e16a078d3a8814161fb9ac074b9` adds a 5-second connection
  timeout and 10-second total timeout after an unreachable endpoint exhausted FreeScout workers.
  Reimplement the protection in the custom module and tune the final values during the
  compatibility spike.

References:

- <https://github.com/fulldecent/freescout-sidebar-webhook/commit/8c88174489686536431640395ed7b1b8c30fad2d>
- <https://github.com/fulldecent/freescout-sidebar-webhook/compare/main...w-paheg:freescout-sidebar-webhook:main>

## Product experience

### First FreeScout login

1. The agent chooses **Login with Rondo**.
2. FreeScout redirects to Rondo's authorization endpoint.
3. Rondo requires a normal authenticated, approved WordPress account.
4. Rondo checks that the user is eligible for the FreeScout OAuth client.
5. Rondo displays a concise consent/continuation screen naming FreeScout and the identity claims
   being shared.
6. Rondo returns a short-lived authorization code to the exact registered FreeScout redirect URI.
7. FreeScout exchanges the code and reads the dedicated Rondo identity resource.
8. FreeScout matches an existing agent by verified email. Automatic creation remains off during
   the pilot.
9. The Rondo Integration login listener asks Rondo for the agent's desired managed mailbox keys.
10. The agent enters FreeScout with only the mailboxes permitted by Rondo plus any unrelated manual
    access already present.

### Later logins

- Rondo re-evaluates eligibility on every authorization request.
- The Rondo Integration module reconciles managed mailbox access after every successful FreeScout
  login.
- An agent who no longer has an eligible Rondo account cannot start a new OAuth session.

### Conversation sidebar

1. A logged-in agent opens a conversation they may view.
2. The Rondo Integration module validates the conversation and mailbox before sending anything to
   Rondo.
3. FreeScout signs customer, conversation, mailbox and current-agent context.
4. Rondo maps the signed agent to the approved Rondo user.
5. Rondo intersects three boundaries:
   - which person the agent may view;
   - which fields the agent's Rondo capabilities permit;
   - which fields the current FreeScout mailbox permits.
6. Rondo returns a script-free sidebar document.
7. The module displays the document in an isolated sidebar surface.

### Sidebar actions

- **Open in Rondo** and record-specific actions open a new Rondo page.
- Rondo performs its normal browser-session authorization again.
- The sidebar itself is read-only in version one.

### Safe non-success states

The sidebar shows a small, non-sensitive state for:

- no Rondo match;
- multiple possible Rondo matches;
- agent not mapped to an eligible Rondo account;
- mailbox not configured;
- invalid or expired signature;
- Rondo timeout or temporary error.

Normal FreeScout conversation controls remain usable in every state.

## Architecture

### Component 1: Rondo OpenID Connect provider

Rondo exposes a narrowly scoped provider for registered first-party clients:

```text
GET  /oauth/authorize
POST /oauth/token
GET  /oauth/userinfo
GET  /oauth/jwks
GET  /.well-known/openid-configuration
GET  /.well-known/oauth-authorization-server
```

The initial client is FreeScout. Client registration is administrator-only and stores:

- opaque client ID;
- hashed client secret;
- exact redirect URI allowlist;
- allowed scopes;
- enabled/disabled status;
- client label shown to users;
- environment-specific FreeScout base URL for provisioning-event delivery;
- secret-created and last-rotated timestamps.

Rondo supports the authorization-code flow. For the FreeScout client it requires PKCE S256,
`state`, `nonce`, exact redirect matching and confidential client authentication through HTTP
Basic. The token response contains a signed ID token and a short-lived access token.

Only these initial scopes are supported:

```text
openid email profile
```

The UserInfo response is limited to:

```json
{
  "sub": "opaque-stable-rondo-subject",
  "email": "agent@example.nl",
  "email_verified": true,
  "name": "Agent Name",
  "picture": "https://rondo.example.nl/path/to/avatar"
}
```

No Rondo role, capability, committee, KNVB ID or person ID is exposed as an identity claim.
FreeScout access is obtained separately from the signed access service.

Rondo may assert `email_verified: true` only when the address has passed the approved Rondo account
verification or administrator-provisioning policy. If current account approval does not provide
sufficient assurance of control over the address, automatic email linking remains disabled until
an explicit email-verification step exists.

#### Provider storage

Rondo follows the repository's WordPress-native storage rule:

- client configuration in an option;
- one opaque subject identifier in user meta;
- short-lived authorization codes in transients, stored hashed;
- short-lived access-token records in transients, stored hashed;
- consent/audit metadata in user meta or a native audit post/comment model if required;
- no custom database tables.

Authorization codes are single-use and expire within two minutes. Access tokens expire within five
minutes, are audience-bound to the FreeScout client and are scoped only to UserInfo. Refresh tokens
are not issued.

The ID token is signed with an asymmetric key and contains `iss`, `sub`, `aud`, `iat`, `exp`,
`nonce` and `auth_time`; it contains `at_hash` when Rondo's selected signing algorithm supports the
standard calculation. Rondo publishes public keys through JWKS and supports controlled signing-key
rotation with an overlap window.

#### Eligible Rondo identity

A user may authorize the FreeScout client only when all are true:

- the WordPress user exists and is not blocked/deleted;
- the account is approved under the normal Rondo account policy;
- the user has at least one capability mapped to a FreeScout mailbox, or is an administrator;
- the user has an acceptable unique external email identity;
- the linked person, when required by the mapping, still exists.

Synthetic `@members.rondo.invalid` addresses and ambiguous shared addresses fail closed with a
message directing the agent to an administrator. The provider never silently substitutes another
person's contact email.

### Component 2: custom Rondo Integration FreeScout module

One custom FreeScout module owns all Rondo-specific behavior on the FreeScout side:

- OIDC login initiation, callback validation, local session creation and recovery;
- conversation-sidebar placement and loading;
- current-agent and conversation authorization;
- signed server-to-server sidebar requests;
- isolated response rendering and failure handling;
- controlled FreeScout accent and conversation-layout settings;
- managed mailbox mapping, grants and revocations;
- provisioning-event receipt, reconciliation and audit settings.

The module records the audited Sidebar Webhook commit used as a reference. Any copied or
substantially derived code retains the upstream MIT copyright and license notice. There is no
runtime dependency on the Sidebar Webhook module and no second custom provisioning module.

#### Rondo connection configuration

The module requires one **Rondo base URL** setting, for example `https://rondo.example.nl`. It is
stored as environment-specific FreeScout configuration and may also be supplied during automated
provisioning through `RONDO_BASE_URL`; the environment value takes precedence over the UI value.
There is no compiled default and the integration remains disabled until a value is configured and
verified.

The value is normalized once when saved. It may contain a deployment path prefix, but never user
info, a query string or a fragment. HTTPS is mandatory outside an explicitly marked local/test
environment. A trailing slash does not change the resulting URL.

All FreeScout-to-Rondo requests derive their destinations from this base URL plus module-owned,
versioned paths:

```text
/wp-json/rondo/v1/integrations/freescout/sidebar
/wp-json/rondo/v1/integrations/freescout/access
```

The OIDC client discovers its provider endpoints from the configured Rondo base URL. Setup
documentation and the Rondo Integration settings screen show the derived values, but no endpoint
hostname is duplicated in code:

```text
/.well-known/openid-configuration
/oauth/authorize
/oauth/token
/oauth/userinfo
/oauth/jwks
```

Changing the base URL disables Rondo requests until an administrator re-verifies the destination
and signing configuration. Outbound requests may use only the configured origin and path prefix;
redirects to another origin are never followed.

The inverse Rondo-to-FreeScout provisioning target uses a separate environment-specific
**FreeScout base URL** in Rondo's client configuration. It is not inferred from an OAuth redirect
URI, and Rondo derives the provisioning-event path from it.

#### OIDC login and subject binding

The module owns two routes:

```text
GET /rondo/oidc/login
GET /rondo/oidc/callback
```

Starting login
creates fresh cryptographically random `state`, `nonce` and PKCE verifier values in the server-side
session, derives an S256 challenge and redirects only to the discovered Rondo authorization
endpoint.

The callback:

1. validates and consumes the session-bound `state`;
2. exchanges the code server-to-server with the PKCE verifier and `client_secret_basic`;
3. validates the ID-token signature against the discovered JWKS;
4. validates exact `iss`, expected `aud`, `azp` when present, `exp`, acceptable `iat`, and the
   session-bound `nonce`;
5. validates `at_hash` when present;
6. calls UserInfo over verified HTTPS with the access token;
7. requires the UserInfo `sub` to exactly equal the validated ID-token `sub`;
8. requires boolean `email_verified: true`, an acceptable non-empty email and current Rondo
   eligibility;
9. resolves and commits the one-to-one subject binding before creating the FreeScout session;
10. reconciles managed mailboxes before redirecting the browser to its intended FreeScout page.

Every terminal success or failure clears the transaction's state, nonce, verifier, code and token
material from the session. Tokens, codes and claims are never written to normal logs.

The module stores a one-to-one binding between the configured Rondo issuer plus `sub` and the
FreeScout user ID. Both sides of the binding are unique.

For the first login only, an unbound subject may select one existing, unbound FreeScout user by its
unique verified email. The module commits the binding and creates the authenticated FreeScout
session as one controlled callback operation. Later logins resolve the bound user by ID. A changed
email or another subject presenting the old email can never silently move the binding.

Automatic user creation remains disabled for the pilot. If it is enabled later, the new user must
be bound to the initiating subject in the same successful login flow before mailbox provisioning
runs. Any unlink or rebind requires an explicit, audited administrator action; normal OIDC login
never replaces an existing binding.

#### Login failure and break glass

Callback denial, provider errors, validation failures and transport failures redirect once to
`/login?rondo_oauth=0` with a safe human-readable error and a redacted correlation ID. The bypass
parameter suppresses only automatic Rondo login for that request; it never bypasses authentication.

Optional forced login is controlled by `RONDO_FORCE_OAUTH_LOGIN`, defaults off and is applied only
to unauthenticated login-page requests without `rondo_oauth=0`. Clearing the server-side setting
and restarting FreeScout restores local login without Rondo. One local administrator account is
maintained and tested as break glass. Version one does not implement provider-initiated logout.

#### Controlled appearance settings

The disabled Design module is not restored as a dependency. Rondo Integration loads one
module-owned stylesheet after FreeScout core and exposes a small allowlisted appearance contract.
It never modifies core files and does not accept custom CSS, selectors or uploaded stylesheets.

The existing FreeScout header-color setting remains authoritative for the top navigation. Rondo
Integration adds two validated hexadecimal color settings:

- **Interface accent:** links, actionable icons, active mailbox text and focus indicators;
- **Interface accent surface:** the light-tint backgrounds used for the active mailbox row,
  conversation toolbar and comparable selected or highlighted surfaces.

The settings screen previews both colors together and rejects combinations that do not meet WCAG
AA contrast for their actual text/icon use. The module maps them only to an audited selector
allowlist for the supported FreeScout version. Neutral text, borders and surfaces retain FreeScout
defaults. Success, warning, destructive, unread and availability colors retain their semantic
meaning. The isolated Rondo sidebar document receives the same two semantic colors without gaining
access to parent-page CSS.

The screenshots captured after disabling the Design module establish the visual baseline: a
club-colored top header with FreeScout's default blue active navigation, toolbar, links and icons.
No member names, addresses or other screenshot content is stored as PRD evidence.

#### Responsive conversation-sidebar width

FreeScout core gives `#conv-layout-customer` a fixed `280px` desktop width and reserves the same
space through `padding-right` on `#conv-layout-header` and `#conv-layout-main`. Rondo Integration
must update all three values together; changing only the customer element would overlap the
conversation.

The setting is labelled **Maximum conversation sidebar width**, accepts `280` through `420` pixels
and defaults to `360`. Above FreeScout's `1100px` desktop breakpoint, the actual width is:

```css
clamp(280px, 25vw, var(--rondo-sidebar-max-width))
```

At `1100px` and below, the module removes its geometry override and FreeScout's existing full-width
stacked customer layout remains authoritative. The stylesheet is scoped to conversation pages and
an enabled Rondo appearance class. Disabling the appearance feature or the module restores core
layout without a file rollback.

#### Distribution, provisioning and updates

FreeScout core supports updates for installed third-party modules through these `module.json`
fields:

```json
{
  "alias": "rondointegration",
  "version": "1.0.0",
  "requiredAppVersion": "1.8.238",
  "authorUrl": "https://rondo.club",
  "latestVersionUrl": "https://github.com/RondoHQ/freescout-rondo-integration/releases/latest/download/module.json",
  "latestVersionZipUrl": "https://github.com/RondoHQ/freescout-rondo-integration/releases/latest/download/rondo-integration.zip"
}
```

The exact repository and URLs are confirmed when the FreeScout module repository is created. The
contract is:

1. Provision a fresh FreeScout installation with a known compatible, fixed-version ZIP.
2. Extract it as `Modules/RondoIntegration` and run FreeScout's module installation/activation
   command for alias `rondointegration`.
3. Run the module-owned `php artisan rondo:integration-update` provisioning command.
4. That command reads and validates `latestVersionUrl`, compares the semantic version and invokes
   FreeScout's core updater only for alias `rondointegration`.
5. FreeScout downloads `latestVersionZipUrl`, installs migrations/assets and rebuilds its module
   configuration.
6. Record the resulting module version and fail provisioning if it differs from the approved
   latest release.

Current FreeScout `1.8.238` detects `module.json` version responses and performs alias-specific
updates correctly through its Modules UI. Its generic `freescout:module-update` command does not
apply the same JSON parsing and alias filtering consistently for third-party modules. Automated
provisioning therefore uses the Rondo-owned wrapper around `App\Module::updateModule()` and verifies
the alias before and after the update. The generic command is not used for unattended provisioning.

The fixed bootstrap version guarantees that a new installation always has the updater contract,
even when provisioning code has not yet been changed for a later module release. Routine updates
then use FreeScout's normal Modules UI or the Rondo-owned targeted Artisan command.

FreeScout's third-party updater does not verify an artifact checksum or signature before
extraction. Therefore:

- release ZIPs and `module.json` are published together as immutable GitHub Release assets;
- `latestVersionUrl` and `latestVersionZipUrl` resolve to assets from the same release;
- only protected CI release workflows may publish or replace update artifacts;
- provisioning records the downloaded release version and artifact SHA-256 independently;
- unattended updates are not scheduled in version one;
- a database and module-directory backup is taken before an operator-approved update;
- module updates are first installed and tested on the non-production FreeScout instance.

#### FreeScout request authorization

- Add `auth` middleware to the AJAX route; upstream currently applies only `web`.
- Read the current agent with `auth()->user()`.
- Authorize the agent against the conversation using FreeScout's normal conversation policy.
- Verify `conversation.mailbox_id` equals the supplied mailbox ID.
- Fetch the customer through the already-authorized conversation.
- Never accept agent, customer, conversation or mailbox identity from browser-supplied data without
  reloading and authorizing it server-side.

#### Signed server request

The request body contains:

```json
{
  "version": 1,
  "mailboxId": 12,
  "conversationId": 3456,
  "conversationNumber": 789,
  "customerId": 123,
  "customerEmail": "member@example.nl",
  "customerEmails": ["member@example.nl"],
  "customerPhones": [],
  "agent": {
    "id": 44,
    "email": "agent@example.nl"
  }
}
```

Headers contain:

```text
X-Rondo-Timestamp: Unix timestamp
X-Rondo-Nonce: cryptographically random one-time value
X-Rondo-Signature: v1=<hex HMAC-SHA256>
```

The signature covers the timestamp, nonce and exact raw body. The shared signing key is stored only
in server configuration. It is never included in the body, URL, response, JavaScript or logs.

#### Availability limits

- HTTPS endpoint only.
- Redirects disabled.
- Explicit connection and total timeouts.
- Initial target: 2-second connection timeout and 5-second total timeout; confirm with production
  latency during the spike.
- Maximum accepted response size: 256 KiB.
- Expected content type enforced.
- No automatic retry in version one; the user may trigger one controlled refresh.
- Failure hides the data surface and leaves a compact retry control.

#### Response isolation

Do not insert remote HTML directly into the FreeScout document.

The preferred implementation creates a sandboxed `iframe` and assigns the response through the
DOM `srcdoc` property. The sandbox does not permit scripts or same-origin access. It may permit
user-initiated popups that escape the sandbox solely so server-generated, allowlisted Rondo links
can open in a new tab. Those links use `target="_blank"` and `rel="noopener noreferrer"`; forms,
scripts, same-origin access and parent navigation remain disabled. The response also carries a
restrictive content-security policy. This gives Rondo control over the presentation while
preventing its CSS and markup from affecting FreeScout.

If the compatibility spike rejects `srcdoc` because of FreeScout layout constraints, the fallback
is a fixed local renderer that receives a versioned JSON view model and escapes every value. Raw
`.html(response.html)` injection is not accepted for production.

### Component 3: Rondo sidebar endpoint

Route appended to the configured Rondo base URL:

```text
POST /wp-json/rondo/v1/integrations/freescout/sidebar
```

The endpoint is publicly routable but accepts only valid signed requests. Its permission path:

1. Enforce the request method, content type, required headers and raw-body size limit.
2. Validate timestamp within a narrow clock-skew window.
3. Validate the HMAC over the still-unparsed raw body with constant-time comparison.
4. Reject and then record a reused nonce using a short-lived transient.
5. Parse and validate the protocol version and body schema.
6. Validate the mailbox against configured mailbox mappings.
7. Resolve the FreeScout agent to one approved Rondo user.
8. Recheck the user's current effective capabilities.
9. Resolve the customer using the matching policy.
10. Apply person visibility and field-level access through existing Rondo services.
11. Apply the mailbox field allowlist.
12. Render escaped, script-free output.

The endpoint must not use an unrestricted FreeScout service identity to read person data and then
filter it manually. It establishes the mapped Rondo user as the effective user for the request and
reuses the existing access-control predicates.

### Component 4: managed mailbox provisioning

The custom OIDC flow authenticates users but does not itself assign mailboxes. This provisioning
component lives inside the same custom Rondo Integration module described in Component 2; it is not
a separate FreeScout module. It owns only Rondo-managed mailbox relationships.

#### Managed mapping

Rondo stores the capability-to-stable-key mapping:

```json
{
  "ledenadministratie": {
    "freescout_mailbox_key": "ledenadministratie",
    "enabled": true
  }
}
```

The Rondo Integration module separately stores the environment-specific FreeScout mapping:

```json
{
  "ledenadministratie": {
    "mailbox_id": 12,
    "enabled": true
  }
}
```

Administrators configure and verify both sides before enabling automation.

The access service returns desired **managed mailbox keys**, not arbitrary FreeScout IDs. The
FreeScout module translates keys to its local IDs. Rondo therefore cannot attach a user to an
unconfigured mailbox.

#### Rondo access service

Route appended to the configured Rondo base URL:

```text
POST /wp-json/rondo/v1/integrations/freescout/access
```

The Rondo Integration module sends a signed FreeScout user identity. Rondo resolves the user and
returns:

```json
{
  "subject": "opaque-stable-rondo-subject",
  "active": true,
  "managed_mailboxes": ["ledenadministratie"],
  "evaluated_at": "2026-08-31T20:00:00Z"
}
```

No person or committee data is returned.

#### Reconciliation algorithm

For every configured managed mailbox:

- desired and absent: attach;
- desired and present: leave unchanged;
- not desired and previously managed: detach;
- unrelated mailbox: never touch;
- FreeScout administrator: never downgrade or detach administrator access.

The provisioning component must not call `sync()` over all of a user's mailboxes because that
would remove manual assignments. It manages only the configured mailbox IDs and records enough
module-owned state to distinguish a managed attachment from an unrelated manual attachment.

#### Sync triggers

1. After every successful Rondo OIDC login.
2. After a Rondo capability change, through a signed Rondo-to-FreeScout provisioning event.
3. Hourly reconciliation as repair when an event was missed.
4. Nightly full audit with counts and errors but no personal data in logs.

The push event is an optimization; reconciliation remains authoritative and idempotent.

### Component 5: Rondo-to-FreeScout provisioning events

When `CapabilitySync` grants or revokes a capability participating in a mailbox mapping, Rondo
queues a signed event for the Rondo Integration module. The event contains only the stable Rondo
subject and the fact that access must be re-evaluated; it does not assert the final mailbox list.

FreeScout then calls the Rondo access service to obtain current desired state. This avoids accepting
stale capability data from a delayed event.

Retries use WordPress cron/transients or another existing native Rondo queue pattern. No custom
database table is introduced. Repeated delivery is safe.

## Authorization model

### Why capability, not raw commissie membership

The requested behavior is “members of the Ledenadministratie commissie get access to the
Ledenadministratie mailbox.” Rondo already has a more complete authorization path:

```text
current work_history commissie membership
  -> CommissieCapabilityMap
  -> rondo_ledenadministratie role
  -> ledenadministratie capability
  -> FreeScout managed mailbox
```

Using the effective capability:

- respects current/end-dated work history;
- respects the configured commissie-to-role map;
- includes explicitly authorized board/administrator users;
- respects manual capability grants and revocations;
- avoids a second interpretation of “current commissie member.”

### Mailbox field policy

The mailbox policy can only narrow the Rondo user's permissions. It can never grant a field the
user cannot read in Rondo.

Initial Ledenadministratie sidebar allowlist:

- identity: name, active/former-member status and exact match state;
- membership: type, KNVB ID, birthdate/age, member-since date and relation-end date;
- sport: current teams, spelactiviteit and transfer-pending state;
- contact: primary/secondary email, phone and address;
- household: directly related people with relationship label and current team summary;
- process: onboarding email state, digital membership-pass state and last source-sync timestamp;
- work: count and summary of open Rondo tasks relevant to ledenadministratie;
- links: person, relevant task and Sportlink record.

Explicitly excluded unless a future mailbox policy and Rondo capability both allow them:

- contribution balance, invoices and installment details;
- financial block and Nikki fields;
- VOG details;
- sponsor-management fields;
- private notes and unrestricted timeline content;
- discipline cases;
- arbitrary custom fields.

## Customer matching

Version one matches in this order:

1. Normalize all FreeScout customer emails.
2. Search Rondo for exact normalized matches in canonical email fields.
3. Deduplicate matches by person ID.
4. Render a record only when exactly one accessible person remains.

Rules:

- Case differences are ignored.
- An exact secondary email is valid.
- Phone numbers never select a person automatically.
- A shared email returning multiple people produces an ambiguous state.
- An inaccessible record is indistinguishable from no match.
- The agent may open Rondo search, but the sidebar never asks them to pick from inaccessible people.
- No match state contains no inferred membership information.

A future stable customer-to-person mapping may be added after an explicit, audited linking action.
Email matching remains the rollout baseline because the goal is to retire dependence on copied
FreeScout IDs.

## OAuth and account lifecycle

### Pilot

- Automatic FreeScout user creation is disabled.
- Existing FreeScout agents are matched to Rondo by a unique verified email.
- Rondo OIDC login is optional until every pilot agent succeeds.
- One documented local FreeScout administrator remains available as break glass.
- Forced Rondo login remains disabled until failed callbacks and recovery through both
  `/login?rondo_oauth=0` and `RONDO_FORCE_OAUTH_LOGIN` have been rehearsed against the released
  custom module.

### Later automatic creation

Automatic creation may be enabled only when:

- Rondo denies authorization for users without a mapped FreeScout capability;
- unique-email and synthetic-email guards are proven;
- the Rondo Integration module assigns zero or more managed mailboxes immediately after creation;
- a newly created user with no mailbox cannot see customer data;
- duplicate and renamed-email behavior is tested.

### Revocation

Removing `ledenadministratie` causes the Rondo Integration module to remove the managed mailbox. It
does not automatically terminate an existing FreeScout session or delete the FreeScout account.

This is acceptable for version one because the user loses the mailbox and Rondo sidebar data. A
future account-deactivation policy may be considered only after proving that the user has no manual
mailboxes and no other managed capability.

## Migration from the current FreeScout sync

### Keep initially

- Existing FreeScout users and mailbox assignments.
- Existing customer records and historical custom fields.
- FreeScout conversation-to-Rondo activity sync.
- Existing FreeScout customer IDs and SQLite mappings as rollback/reference data.

### Dual-run validation

For a pilot group, compare sidebar output to existing FreeScout customer fields:

- person identity;
- current team;
- member-since date;
- contact details;
- contribution values where the validating agent has finance access, without adding those values to
  the Ledenadministratie sidebar.

Differences are treated as evidence about stale copied data or matching defects; the sidebar does
not silently overwrite FreeScout.

### Disable only after acceptance

After the sidebar has passed pilot acceptance:

1. Stop creating/updating copied FreeScout customer custom fields.
2. Preserve existing FreeScout customers; do not bulk-delete them.
3. Keep the old pipeline available behind an explicit rollback switch for one release window.
4. Review whether FreeScout customer ID reverse sync to Rondo/Sportlink is still needed.
5. Review and redesign the conversation activity pipeline's person mapping before removing its
   dependency on the existing FreeScout SQLite map.

No local Rondo Sync pipeline is run as part of planning or migration analysis; production SQLite
mappings remain authoritative.

## Security requirements

- Exact OAuth redirect URI matching.
- Authorization codes single-use and short-lived.
- PKCE S256 required for every authorization request and token exchange.
- Fresh `state` and `nonce` values are required and validated by the Rondo Integration module.
- The Rondo Integration module is an OpenID Connect relying party and validates both the ID token
  and UserInfo response.
- Identity data is accepted only from the configured Rondo issuer; ID-token and UserInfo subjects
  must match and UserInfo must contain boolean `email_verified: true`.
- Rondo subject and FreeScout user bindings are one-to-one, persistent and never changed from an
  ordinary login attempt.
- Email is a first-link locator only; a later login resolves the already-bound FreeScout user.
- Rondo and FreeScout base URLs are explicit environment configuration with no compiled hostname.
- Integration URLs reject credentials, query strings and fragments; production requires HTTPS.
- Outbound integration requests stay within the configured origin and path prefix and never follow
  cross-origin redirects.
- Client secrets stored hashed where retrieval is unnecessary and otherwise encrypted using the
  existing Rondo secret-storage boundary.
- Signing keys rotatable with an overlap window for one previous key.
- Rondo Integration update URLs use HTTPS and one controlled release origin.
- Release artifacts are immutable, produced by protected CI and accompanied by a recorded SHA-256.
- An updater release never advertises a version before both its manifest and ZIP are available.
- Production module updates require a backup and explicit operator initiation in version one.
- HMAC validation before parsing or querying personal data.
- Replay prevention through timestamp and one-time nonce.
- Constant-time signature comparison.
- Authenticated FreeScout AJAX route.
- Conversation policy authorization and mailbox/conversation consistency check.
- Agent identity reloaded server-side; never trusted from browser parameters.
- Mailbox allowlist intersects Rondo access; it never widens it.
- FreeScout customer visibility is limited to customers with conversations in mailboxes the agent
  may view; `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true` is a required deployment invariant.
- Script-free isolated sidebar response.
- No credentials, raw tokens, signatures, personal payloads or returned HTML in normal logs.
- Rate limiting per FreeScout instance, agent and source IP where reliable.
- Response cache keys include mailbox policy and effective authorization class; no cross-user PII
  cache leakage.
- All integration endpoints fail closed for data and fail open for normal FreeScout operation.

## Privacy requirements

- Document FreeScout as a recipient/display surface for live Rondo data in the processing record.
- Send only customer identifiers needed for exact matching.
- Send only agent ID/email needed for identity mapping.
- Do not persist sidebar person payloads in FreeScout.
- Avoid browser analytics, third-party fonts, external images and remote scripts inside the sidebar.
- Keep audit logs event-oriented: success/failure, IDs and reason codes, without full person payloads.
- Set an explicit retention period for OAuth and provisioning audit records.
- Review the expanded Ledenadministratie field allowlist with the club before production.

## Observability

Rondo records aggregate and audit-safe events for:

- OAuth authorizations allowed/denied by reason;
- token exchange failures;
- sidebar signature/replay failures;
- match outcomes: exact, none, ambiguous and inaccessible;
- sidebar latency and timeout rate;
- mailbox access grants/revocations;
- reconciliation drift and repair;
- signing-key and OAuth-client-secret rotation.

FreeScout records:

- endpoint availability and timeout class;
- invalid response type/size;
- managed-mailbox reconciliation counts;
- last successful reconciliation timestamp.

Neither side logs OAuth codes, access tokens, HMAC signatures, client secrets or complete request
bodies.

## Performance and availability targets

- Sidebar endpoint p95 server time below 500 ms after warm-up.
- Visible sidebar result or quiet unavailable state within 5 seconds.
- FreeScout conversation page remains interactive while the sidebar loads.
- Rondo outage causes no FreeScout login outage for an existing FreeScout session.
- Rondo outage during a new OIDC login returns a clear login failure without changing access.
- Managed mailbox grant after successful OIDC login: immediate in the same login flow.
- Managed mailbox revocation after successful Rondo event: within 5 minutes.
- Missed-event repair: within one hourly reconciliation cycle.

## Compatibility spike

The paid OAuth Login add-on was evaluated with FreeScout `1.8.238` in a non-production environment
and rejected as the production login client. Version `1.0.28` validates `state`, but uses
`client_secret_post`, consumes UserInfo without an ID token, sends neither PKCE nor `nonce`, and
accepts a different `sub` with `email_verified: false` when the email matches an existing user.

Those findings are retained as rejection evidence and reusable test cases. The remaining spike now
proves that the custom Rondo Integration module implements the required OIDC controls and that its
sidebar, provisioning and failure-containment behavior works with the current FreeScout release.

Required observations:

1. Exact authorization request parameters and scopes.
2. Whether `state`, PKCE and OpenID Connect `nonce` are sent and validated.
3. Token endpoint client-authentication method.
4. Whether the module requires an ID token or relies only on User Info.
5. User matching and duplicate-email behavior.
6. Whether current Rondo account approval provides sufficient assurance to assert
   `email_verified: true`.
7. Automatic user creation defaults and role assigned.
8. Events and extension points needed after custom OIDC login and user creation.
9. Behavior when Rondo denies authorization.
10. Force-login recovery procedure.
11. FreeScout mobile-app behavior.
12. Sidebar `srcdoc`/sandbox layout at realistic sidebar widths.
13. Timeout behavior with DNS failure, connection refusal, TLS error and slow response.

The spike produces captured request shapes with secrets redacted, a compatibility matrix and a
go/no-go decision. It does not use production member data.

Execution checklist:
[FreeScout and Rondo compatibility spike checklist](freescout-rondo-compatibility-spike-checklist.md).

## Implementation phases

### Phase 0: compatibility and threat-model spike

- Retain the completed paid-add-on evaluation as a recorded `NO-GO` decision.
- Build a minimal custom Rondo Integration OIDC client outside production.
- Prove authorization code flow with PKCE S256, `state`, `nonce`, ID-token validation and UserInfo
  subject matching.
- Prove first-link, persistent subject binding and explicit administrator recovery.
- Prove the sandboxed response design and current-agent hook.
- Confirm timeout values and review the custom flow's threat model.

**Gate:** no product implementation proceeds without a successful custom-client end-to-end test
login, complete token validation and a confirmed current-agent hook.

### Phase 1: Rondo OpenID Connect provider

- Add first-party client registration and secret rotation.
- Implement OIDC discovery, authorization-server metadata, authorize, token, UserInfo and JWKS
  endpoints.
- Add opaque subjects and external-email eligibility checks.
- Add tests for redirects, codes, tokens, claims, scopes, PKCE, nonce and denials.
- Document `/login?rondo_oauth=0` and server-side `RONDO_FORCE_OAUTH_LOGIN` disablement as separate
  break-glass paths.

### Phase 2: Rondo Integration module foundation and sidebar

- Create the custom FreeScout module and record the audited Sidebar Webhook reference commit.
- Retain the upstream MIT notice for reused or substantially derived code.
- Add third-party updater metadata to `module.json`.
- Add `rondo:integration-update` as an alias-restricted wrapper around FreeScout's core updater.
- Publish matching `module.json` and ZIP assets through a protected release workflow.
- Add a fixed-version bootstrap artifact and targeted update command to FreeScout provisioning.
- Add configurable Rondo base URL storage, `RONDO_BASE_URL` provisioning support and derived
  endpoint display.
- Add the complete OIDC relying-party flow, local session creation and one-to-one subject binding.
- Add authentication and conversation authorization.
- Add current agent to the signed payload.
- Replace body secret with versioned HMAC headers.
- Add strict timeouts, redirect prevention and response limits.
- Replace raw HTML injection with the accepted isolated renderer.
- Preserve a small visible refresh action and graceful failure state.
- Add allowlisted accent settings and a live preview without arbitrary CSS support.
- Add the responsive customer-sidebar maximum-width setting and coordinated conversation spacing.

### Phase 3: Rondo sidebar service

- Add signature/replay validation.
- Add agent mapping and effective-user authorization.
- Add exact customer matching.
- Add mailbox policies and the Ledenadministratie view.
- Add no-match, ambiguous, unauthorized and unavailable states.
- Link all mutations to authenticated Rondo pages.

### Phase 4: automatic provisioning in the Rondo Integration module

- Extend the same custom module with the FreeScout login listener.
- Add signed Rondo access service.
- Add managed capability-to-mailbox configuration.
- Reconcile only managed mailbox relationships.
- Add Rondo capability-change events and FreeScout receiver.
- Add hourly repair and nightly audit.

### Phase 5: pilot and sync cutover

- Pilot with a small Ledenadministratie group.
- Compare live sidebar values with copied FreeScout fields.
- Validate grant and revocation with real non-admin agents.
- Enable Rondo OIDC login without forcing it.
- Rehearse break-glass recovery.
- Disable customer enrichment only after acceptance.
- Keep conversation activity sync until separately approved.

## Test matrix

### Identity provider

- Approved eligible user succeeds.
- User without a mapped capability is denied.
- Disabled/deleted user is denied.
- Synthetic/shared/ambiguous email is denied.
- Redirect URI mismatch is denied.
- Expired or reused authorization code is denied.
- Invalid client secret is denied.
- PKCE verifier mismatch is denied when PKCE was initiated.
- Unsupported scope is denied.
- An ID token with a bad signature, issuer, audience, nonce, expiry or issued-at time is denied.
- A UserInfo response with a subject different from the ID-token subject is denied.
- Missing `sub` or `email_verified` other than boolean `true` is denied before FreeScout login.
- A first login binds one verified Rondo subject to one unbound FreeScout user.
- The same subject can log in again after an email change without changing the binding.
- A different subject presenting a bound user's email is denied.
- A subject already bound to another FreeScout user is denied.
- An ordinary login cannot unlink, replace or transfer a subject binding.

### Connection configuration

- No source or built artifact contains a club-specific Rondo or FreeScout hostname.
- The same module build connects to two different Rondo test installations by changing only the
  base URL setting.
- UI and `RONDO_BASE_URL` provisioning values produce the same normalized endpoint URLs.
- A deployment path prefix and optional trailing slash are normalized without losing the prefix.
- Missing, malformed, credential-bearing, query-bearing or fragment-bearing URLs are rejected.
- Plain HTTP is rejected outside an explicitly marked local/test environment.
- Changing the base URL disables requests until destination and signing configuration are
  re-verified.
- A redirect to another origin is rejected.

### Sidebar request

- Authenticated agent with conversation access succeeds.
- Unauthenticated AJAX request is denied.
- Agent without conversation access is denied before webhook dispatch.
- Conversation/mailbox mismatch is denied.
- Tampered body, timestamp or nonce is denied.
- Replayed request is denied.
- Slow endpoint times out without blocking the conversation UI.
- Oversized or wrong-content-type response is rejected.
- Returned script/event-handler markup cannot execute in FreeScout.

### Appearance and conversation layout

- Disabling appearance overrides reproduces the unmodified FreeScout UI.
- Header color remains controlled by FreeScout's existing setting.
- Accent and accent-surface settings affect only the documented blue interface roles.
- Success, warning, destructive, unread and availability colors do not change with branding.
- Invalid hexadecimal values and insufficient-contrast pairs are rejected.
- No setting or request can inject CSS, selectors, HTML or external stylesheets.
- The isolated sidebar uses the same semantic colors without reading parent-page styles.
- The customer sidebar, conversation header and conversation body reserve exactly the same width.
- Maximum values `280`, `360` and `420` produce no overlap at desktop widths.
- Viewports `1100px` and below retain FreeScout's full-width stacked customer layout.
- The layout remains usable at `1101`, `1280`, `1440` and `1920` pixels and at 200% zoom.
- Disabling Rondo appearance overrides restores core width and colors without stale inline styles.

### Module distribution and updates

- A clean FreeScout installation can provision the fixed bootstrap module version.
- The targeted third-party update command detects and installs a newer semantic version.
- The command parses both a plain version response and the version in `module.json`.
- Updating `rondointegration` never updates another installed third-party module.
- A current version reports that no update is available and remains unchanged.
- An unavailable version endpoint leaves the installed module unchanged.
- A failed or invalid ZIP does not produce an active partial module.
- The manifest and ZIP endpoints resolve to assets from the same release.
- The resulting module version and independently calculated artifact SHA-256 are recorded.
- Migrations and assets are installed once and repeated updates are idempotent.
- A backup and restore rehearsal returns the previous module and database state.

### Person matching and fields

- One exact primary-email match renders.
- One exact secondary-email match renders.
- Zero matches shows no-match state.
- Two accessible matches show ambiguous state without details.
- An inaccessible match is treated as no match.
- Phone-only match never auto-selects.
- Ledenadministratie sees its allowlist.
- Ledenadministratie does not see finance, VOG, sponsor or private-note fields.
- A future finance mailbox cannot expose finance to an agent lacking Rondo finance access.

### Mailbox provisioning

- Current mapped commissie member receives the Rondo role/capability and FreeScout mailbox.
- End-dated commissie membership revokes the managed mailbox.
- Manual Rondo revoke prevents the mailbox grant.
- Manual Rondo grant permits the mailbox without direct committee membership.
- Unrelated manual FreeScout mailbox access survives every reconciliation.
- A manually attached instance of the managed mailbox is not detached unless recorded as managed.
- FreeScout administrators are not downgraded.
- Repeated login/event/reconciliation is idempotent.
- A zero-mailbox user is denied on direct customer, customer-edit and conversation URLs with
  `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true`.
- Missed event is repaired hourly.
- Rondo outage leaves existing FreeScout access unchanged and records a retryable error.

## Acceptance criteria

The milestone is complete only when:

- a real pilot agent signs into FreeScout through Rondo;
- that agent's verified Rondo subject is bound one-to-one to the expected FreeScout user;
- another subject presenting the same email cannot authenticate as that FreeScout user;
- the deployed module contains no hardcoded Rondo hostname and uses the verified configured base
  URL for every Rondo request;
- FreeScout identifies the current agent and signs the sidebar request;
- Rondo maps that agent to the expected approved WordPress user;
- a current `ledenadministratie` capability grants the correct FreeScout mailbox;
- revoking that capability removes only integration-managed access;
- the Ledenadministratie sidebar renders the approved live field set;
- zero-match and multiple-match cases disclose no person data;
- an agent lacking Rondo access cannot retrieve the sidebar record by changing IDs;
- an unreachable Rondo endpoint does not freeze FreeScout or exhaust workers;
- returned sidebar content cannot execute scripts in the FreeScout parent page;
- the configured interface accent replaces the audited default-blue roles without changing
  semantic status colors or failing contrast requirements;
- a configured maximum customer-sidebar width of `360px` widens the sidebar and reserves the same
  space in the conversation header and body without overlap;
- disabling appearance overrides restores FreeScout's native colors and `280px` desktop sidebar;
- existing manual mailbox assignments remain unchanged;
- `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true` is verified in the deployed FreeScout runtime and a
  zero-mailbox user cannot open customer, customer-edit or conversation routes by ID;
- a fresh FreeScout installation can provision a fixed Rondo Integration version and update it to
  the latest approved release through FreeScout's targeted module updater;
- the installed version and artifact SHA-256 match the approved release record;
- a break-glass FreeScout administrator login is proven;
- a denied or failed forced OIDC callback shows the local error page and does not start another
  authorization request;
- the current customer-enrichment sync remains available until explicit cutover approval;
- production OIDC forcing and sync disablement each receive separate approval.

## Rollback

- Disable the Rondo OIDC client.
- Open `/login?rondo_oauth=0` for immediate local break-glass login.
- If browser recovery is unavailable, clear `RONDO_FORCE_OAUTH_LOGIN` in FreeScout's
  server-side configuration and restart FreeScout.
- Disable managed provisioning in the Rondo Integration module while leaving existing mailbox
  relations unchanged.
- Disable the sidebar feature or Rondo Integration module.
- Disable Rondo appearance overrides to restore FreeScout's native accent colors and layout.
- Restore the previous module directory and database backup if a module update fails.
- Re-enable the existing Rondo Sync FreeScout customer pipeline if it was disabled.
- Preserve FreeScout users, conversations, customer records and historical custom fields.
- Rotate the OAuth client secret and HMAC signing key if compromise is suspected.

## Open decisions before implementation

1. Exact production FreeScout mailbox ID and stable key for Ledenadministratie.
2. Whether `srcdoc` with a scriptless sandbox fits the final FreeScout sidebar height and refresh
   behavior; otherwise use the escaped JSON renderer.
3. The module-owned persistence model and transactional boundary for one-to-one subject bindings.
4. Whether current Rondo account approval is sufficient email verification for automatic
   FreeScout account matching.
5. Whether automatic FreeScout user creation is ever enabled after the pilot.
6. The acceptable FreeScout session lifetime after Rondo account revocation.
7. The approved final Ledenadministratie sidebar field allowlist.
8. Whether the FreeScout conversation activity sync remains a long-term feature.
9. How its person matching works after customer enrichment and FreeScout ID reverse sync are
   retired.
10. Which additional Rondo capabilities may map to FreeScout mailboxes in later releases.
11. Audit retention period and operational owners for failed provisioning events.
12. Final module repository, protected release workflow and update-asset URLs.
13. Initial production values for interface accent and interface accent surface.
14. Whether the maximum customer-sidebar width remains `360px` after realistic conversation and
    200%-zoom testing.
