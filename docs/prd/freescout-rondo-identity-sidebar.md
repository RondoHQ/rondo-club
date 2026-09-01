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
    required appearance controls: per-club semantic accent colors and responsive
    conversation-sidebar width. It does not become a general FreeScout theme or accept arbitrary
    CSS.
16. Sidebar content renders through an opaque-origin sandboxed `iframe.srcdoc`. The Rondo response
    remains script-free; one nonce-authorized, module-owned resize script may send height-only
    messages to the parent. A separate JSON renderer is not planned.
17. Subject bindings use module-owned FreeScout tables with database-enforced uniqueness. First
    link, disablement and replacement are transactional and audited; replacement uses a short-lived
    administrator-authorized Rondo recovery flow instead of accepting a typed subject or email.
18. Existing Rondo account status is not email-verification evidence. Rondo issues
    `email_verified: true` only for the exact current external address recorded by a completed
    mailbox-possession flow; existing users without durable evidence verify once before FreeScout
    authorization can continue.
19. Rondo Integration supports guarded just-in-time creation of ordinary FreeScout agent accounts.
    A new account, subject binding, OIDC-only marker, initial managed mailbox access and audit must
    commit together before a session exists. The feature defaults off until its local mailbox
    mapping and production invariants have been verified.
20. Rondo revocation changes authorization, not every FreeScout session indiscriminately. Removed
    mailbox access is enforced on the next server request after reconciliation; sessions are
    invalidated when no mailbox remains or when the identity binding is disabled/replaced. Manual
    mailbox access and manually created account status remain under FreeScout administration.
21. The first Ledenadministratie sidebar release uses the fixed field contract below. It shows
    current membership, contact, household, onboarding, membership-pass and agent-visible task
    context, but no contribution, VOG, sponsor, private-note or unrestricted custom-field data.
22. Keep FreeScout conversation activities in Rondo long-term. They remain a lightweight pointer
    from a person timeline to FreeScout, not a message archive: one idempotent activity containing
    the conversation subject, creation time and server-generated link, with no message body,
    attachment, recipient or reply content.
23. Conversation activities use the same normalized exact-email matcher as the sidebar, but under
    integration service scope rather than the viewing agent's scope. All current FreeScout customer
    emails are compared with Rondo `email_1` and `email_2`; exactly one person may match. No phone,
    name, FreeScout ID, KNVB ID, SQLite mapping or automatic persistent customer binding may select
    a person.
24. The production managed-mailbox key is `ledenadministratie` and its configured FreeScout
    mailbox ID is `18`. A read-only production API query on 2026-09-01 returned the unique mailbox
    name `Ledenadministratie` and address `ledenadministratie@svawc.nl`. The numeric ID remains
    environment configuration, not a source-code constant, and the module must verify its local
    existence and enabled state before provisioning is switched on.
25. Mailbox mappings are configured in the Rondo Integration administrator screen. Administrators
    select a Rondo-supported stable key and a local FreeScout mailbox from lists; they never type a
    capability, stable key or numeric mailbox ID. Activation, repointing and revoking a mapping use
    verification, aggregate impact preview, recent local-password confirmation and an audit event.
26. The first release advertises only `ledenadministratie`. The only pre-approved later mapping
    candidates are `fairplay` for the FairPlay mailbox and `contributie` for the Contributie
    mailbox. Contributie requires the effective `financieel` capability because mailbox access can
    send replies; `financieel_read` is explicitly insufficient. Every other current capability and
    mailbox remains unavailable until it has an exact dedicated capability, sidebar policy and
    separate product and privacy approval.

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

The reverse FreeScout-conversation-to-Rondo activity feature stays because it solves a different
problem: a Rondo user can see that support correspondence exists and open the authoritative
conversation in FreeScout. Its current daily batch remains during rollout, then delivery moves to
the Rondo Integration module after its replacement person-matching path is proven.

## Goals

- One Rondo identity for Rondo and FreeScout.
- No FreeScout access for a blocked or ineligible Rondo user.
- Automatic grant and revocation of managed mailbox access.
- Live, mailbox-specific Rondo context in the FreeScout conversation sidebar.
- A lightweight FreeScout conversation pointer in the Rondo person timeline without copying the
  conversation itself.
- Rondo permissions remain authoritative for every field returned.
- Exact, auditable customer matching with safe zero-match and multiple-match states.
- No shared secret in a request body, browser code, URL or log.
- A slow or unavailable endpoint cannot exhaust FreeScout PHP workers.
- A sidebar response cannot execute arbitrary code in the FreeScout page.
- Existing manually managed FreeScout access remains untouched.
- Every reply remains attributable to a durable FreeScout user account and its bound Rondo subject.
- Administrators can align FreeScout's blue accent surfaces with the club identity without editing
  FreeScout core files.
- The desktop customer sidebar can be wider while preserving usable conversation space and
  FreeScout's narrow-screen layout.

## Non-goals

- Replacing FreeScout's own conversation, assignment, Team or mailbox-permission model.
- Mapping all Rondo roles to FreeScout in the first release.
- Editing Rondo person fields directly inside the sidebar.
- Showing the complete Rondo person record.
- Automatically deactivating a manually created FreeScout account merely because it loses one
  managed mailbox.
- Mirroring FreeScout messages, replies, recipients, attachments or conversation bodies in Rondo.
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
3. Rondo requires a normal authenticated WordPress account that passes the FreeScout-client
   eligibility policy.
4. Rondo checks that the user is eligible for the FreeScout OAuth client.
5. Rondo displays a concise consent/continuation screen naming FreeScout and the identity claims
   being shared.
6. Rondo returns a short-lived authorization code to the exact registered FreeScout redirect URI.
7. FreeScout exchanges the code and reads the dedicated Rondo identity resource.
8. FreeScout resolves an existing subject binding or one unique, unbound agent by verified email.
9. When neither exists and guarded creation is enabled, the module asks Rondo for the subject's
   current managed mailbox keys before creating anything.
10. The module atomically creates an ordinary OIDC-only FreeScout agent, subject binding, initial
    managed mailbox assignments and audit record.
11. The agent enters FreeScout only after that commit; every later reply is attributed to the same
    FreeScout user ID. Existing users retain unrelated manual access.

### Later logins

- Rondo re-evaluates eligibility on every authorization request.
- The Rondo Integration module reconciles managed mailbox access after every successful FreeScout
  login.
- An agent who no longer has an eligible Rondo account cannot start a new OAuth session.
- The module retains no ID token, access token or refresh token after creating the FreeScout
  session; the normal configured FreeScout session lifetime remains authoritative.

### Conversation sidebar

1. A logged-in agent opens a conversation they may view.
2. The Rondo Integration module validates the conversation and mailbox before sending anything to
   Rondo.
3. FreeScout signs customer, conversation, mailbox and current-agent context.
4. Rondo maps the signed agent to the eligible, verified Rondo user.
5. Rondo intersects three boundaries:
   - which person the agent may view;
   - which fields the agent's Rondo capabilities permit;
   - which fields the current FreeScout mailbox permits.
6. Rondo returns sanitized, script-free sidebar markup.
7. The module places that markup inside its own sandboxed document shell and adjusts the iframe
   height through the module-owned resize bridge.

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
  "given_name": "Agent",
  "family_name": "Name",
  "picture": "https://rondo.example.nl/path/to/avatar"
}
```

No Rondo role, capability, committee, KNVB ID or person ID is exposed as an identity claim.
FreeScout access is obtained separately from the signed access service.

Rondo may assert `email_verified: true` only under the explicit policy below. Account existence,
roles, capabilities, a linked person, administrator provisioning and password authentication are
not evidence that the current user controls the claimed address.

#### Provider storage

Rondo follows the repository's WordPress-native storage rule:

- client configuration in an option;
- one opaque subject identifier in user meta;
- the normalized verified email, verification timestamp and verification method in user meta;
- short-lived authorization codes in transients, stored hashed;
- short-lived access-token records in transients, stored hashed;
- short-lived email-verification and authorization-resume tokens in transients, stored hashed;
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
- the user has at least one capability mapped to a FreeScout mailbox, or is an administrator;
- the user has an acceptable, uniquely assigned and explicitly verified external email identity;
- the linked person, when required by the mapping, still exists.

Synthetic `@members.rondo.invalid` addresses and ambiguous shared addresses fail closed with a
message directing the agent to an administrator. The provider never silently substitutes another
person's contact email.

#### Email verification policy

The current `is_user_approved()` compatibility method proves only that a WordPress user ID exists.
Rondo's activation and Magic Login flows can prove mailbox possession when their emailed links are
consumed, and member-profile email changes already require an emailed token, but no durable,
account-wide verification marker currently survives those flows. Existing account status is
therefore insufficient for an OIDC `email_verified: true` claim.

The OIDC identity-email resolver uses `rondo_contact_email` when it is a valid external address and
otherwise falls back to a valid, non-synthetic WordPress `user_email`. It normalizes case for email
comparison and rejects the address when it is synthetic, shared by another FreeScout-eligible Rondo
user or inconsistent with the linked person's permitted contact identity.

Successful verification writes these WordPress-native user-meta values:

```text
rondo_oidc_verified_email
rondo_oidc_verified_email_at
rondo_oidc_verified_email_method
```

The claim is true only when `rondo_oidc_verified_email` exactly matches the resolver's current
normalized address at issuance time. A different, missing or ambiguous address fails closed even if
stale verification meta remains. There is no time-based expiry in version one; changing the
resolved address invalidates the proof immediately and requires verification of the new address.

Only consumption of an emailed, single-use proof may set the marker:

- a Rondo account-activation link;
- a Magic Login link for that exact user and address;
- the existing verified member-profile email-change flow;
- a dedicated verification link started while authorizing the FreeScout client.

Sending an email, administrator provisioning, a password login or an administrator editing the
address never sets the marker. Existing accounts are not backfilled from historical assumptions;
each intended FreeScout agent without a durable marker verifies once.

When authorization encounters missing or mismatched evidence, Rondo pauses the request and offers
to send a rate-limited, single-use verification link to the resolved address. Rondo stores only a
hash of the token for at most two hours. The email contains no OAuth parameters; it references a
server-side continuation record bound to the user, client and exact original authorization request.
After token consumption, Rondo rechecks the current address, uniqueness, account eligibility and
authorization parameters before setting the marker and resuming consent. Failure returns a safe
error and never issues an ID token or UserInfo response with `email_verified: true`.

### Component 2: custom Rondo Integration FreeScout module

One custom FreeScout module owns all Rondo-specific behavior on the FreeScout side:

- OIDC login initiation, callback validation, local session creation and recovery;
- guarded creation and lifecycle of ordinary OIDC-only FreeScout agents;
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
/wp-json/rondo/v1/integrations/freescout/configuration
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
9. resolves an existing binding or unique unbound FreeScout user;
10. for every first link, obtains and validates the subject's current desired managed mailboxes;
11. atomically binds the existing user or safely creates and provisions a new ordinary agent;
12. creates the FreeScout session only after commit and redirects to the intended page.

Every terminal success or failure clears the transaction's state, nonce, verifier, code and token
material from the session. Tokens, codes and claims are never written to normal logs.

##### Binding persistence and concurrency

Subject binding uses three migration-managed FreeScout tables; Rondo itself gains no database
table:

- `rondo_oidc_bindings` stores current and retired identities: nullable active FreeScout user ID,
  last FreeScout user ID, normalized issuer, case-sensitive subject, a binary SHA-256 identity
  fingerprint, status, linked/disabled/retired timestamps and normal timestamps;
- `rondo_oidc_binding_recoveries` stores only hashed single-use recovery tokens, target and actor
  user IDs, expiry, consumption state and the administrator's reason;
- `rondo_oidc_binding_audit` is append-only and stores event type, target and actor user IDs,
  old/new identity fingerprints, reason, redacted correlation ID and timestamp.

`rondo_oidc_bindings.active_user_id` and `identity_fingerprint` each have a unique database
constraint. Active, disabled and recovery-pending rows retain `active_user_id`; retired rows set it
to null but retain `last_user_id` and the unique identity fingerprint. The fingerprint is SHA-256
over the exact normalized issuer and case-sensitive subject with an unambiguous separator. The
stored issuer and subject are compared exactly after a fingerprint lookup and are available only to
module services. The administrator UI, normal logs and audit output show a short fingerprint,
never the raw subject. A user deletion retires the binding before the native deletion completes so
the identity is not silently recycled.

For the first login only, an unbound subject may select one existing, active, unbound FreeScout user
by its unique verified email. After all remote OIDC and UserInfo validation has completed, the
module starts a local database transaction, locks the identity, candidate user, candidate binding
and relevant mapped mailboxes in a deterministic order, rechecks every condition, inserts the
binding, applies initial managed mailbox state, writes its audit event and commits. A uniqueness,
mailbox or audit failure rolls back and fails closed. No HTTP request or FreeScout session creation
occurs inside this transaction. The authenticated FreeScout session is created only after the
binding commit succeeds.

Later logins resolve the active bound user by issuer and subject, not by email. A changed email or
another subject presenting the old email can never silently move the binding. Disabled or
recovery-pending bindings fail before session creation. Concurrent callbacks may succeed only for
the same final subject/user pair; all competing pairings are denied and audited without a partial
binding.

Any unlink or rebind requires an explicit, audited administrator action; normal OIDC login never
replaces an existing binding.

##### Guarded FreeScout account creation

Automatic creation is implemented by Rondo Integration, not by the rejected paid add-on. It is
disabled by default and cannot be enabled until `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true`, the
Rondo connection, at least one capability-to-mailbox mapping and the local break-glass
administrator have all been verified. An environment setting may force it off; the UI cannot
override that restriction.

Creation is considered only after full OIDC validation and durable email verification, when no
subject binding and no unique existing FreeScout email match exists. Before starting a database
transaction, the module calls the Rondo access service with the validated subject. Creation fails
without mutation when the response is unavailable, inactive, empty, contains an unknown mailbox
key or maps to no enabled local mailbox. A Rondo administrator claim never creates or promotes a
FreeScout administrator; new users always receive FreeScout's ordinary agent role.

The module validates the standard `name`, `given_name` and `family_name` claims against the current
FreeScout user schema. It creates the user with the exact verified email, an unrecoverable random
local password and no welcome or password email. A module-owned `rondo_managed_users` row stores a
unique FreeScout user ID, OIDC-only status, creation time, deactivation time and conversion audit
reference. Password login, password reset and remember-token creation are blocked for that account
unless a FreeScout administrator later converts it to a local account through a separate locally
re-authenticated, audited action.

One local database transaction creates the FreeScout user, active subject binding, OIDC-only
marker, initial managed mailbox relationships and audit events. The transaction locks the relevant
email, identity and mailbox mapping state and relies on the native unique email plus module binding
constraints. Model side effects that send email or perform external work are suppressed or queued
after commit. Any validation, insert, pivot or audit failure rolls back the whole unit and creates
no session. A concurrent identical callback may reuse only the exact committed user/binding;
competing identity or email pairings fail closed.

The module never deletes a Rondo-created FreeScout user because conversation and reply attribution
must remain intact. If reconciliation removes the last managed mailbox and the user has no manual
mailbox, the module deactivates the account and invalidates its sessions. Regaining mapped access
reactivates that same bound user. A manual mailbox prevents automatic deactivation but does not
silently enable local-password login.

##### Administrator binding recovery

Only a FreeScout administrator who has recently confirmed a local password may change a binding.
The administrator sees the issuer, status, linked date and a shortened subject fingerprint, not the
raw subject. Every action requires a reason.

- **Disable Rondo sign-in** marks the binding disabled and invalidates the target user's active
  FreeScout sessions and remember tokens. It does not delete the row or permit a new email-based
  first link.
- **Replace Rondo identity** marks the existing binding recovery-pending, invalidates the target's
  sessions and creates a single-use recovery URL valid for 10 minutes. The administrator gives that
  URL to the intended user; the administrator never types a subject or asserts an email mapping.
- Opening the recovery URL starts the complete Rondo authorization flow with fresh `state`, `nonce`
  and PKCE values. Its callback accepts only an eligible, verified and currently unbound Rondo
  subject.
- The callback locks the recovery, target binding and proposed identity, retires the old row,
  inserts the new active row, consumes the recovery and writes the audit event in one database
  transaction. The new FreeScout session is created only after commit.
- Cancellation, expiry, validation failure or a competing binding leaves the target disabled. A
  new recovery or explicit restoration of the previous binding requires another locally
  authenticated, audited administrator action.

The normal administrator UI never physically deletes a binding. Database maintenance or user
deletion retains a retired identity fingerprint so a former subject cannot be reassigned silently.

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

These are site-wide settings for each club's FreeScout installation. The release artifact contains
no AWC-specific green or other club-specific color. An installation with no configured values uses
FreeScout's native blue accents. The same module build must support different club color pairs
through configuration alone.

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

The module creates a complete sandboxed `iframe.srcdoc` document from a module-owned shell,
sanitized Rondo markup and one module-owned resize script. The sandbox permits scripts and
user-initiated popups only; it does not permit same-origin access, forms, downloads, top-level
navigation or any other capability. Its exact capability set is
`sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox"`. The Rondo response itself
remains script-free. Before assembly, the module removes scripts, event-handler attributes, forms,
embeds, base elements, refresh directives, nonce attributes and unknown URLs. Raw
`.html(response.html)` injection into the FreeScout document is never permitted.

The generated document carries a restrictive content-security policy: `default-src 'none'`, a
fresh nonce for the module-owned script, inline styles for the isolated presentation, data-only
images, and no connections, forms, objects, frames or base URI. Server-generated Rondo links are
restricted to the configured origin and approved paths, use `target="_blank"` and
`rel="noopener noreferrer"`, and may escape the sandbox only after a user action.

The module-owned script observes the document height with `ResizeObserver` and sends only a
versioned message containing `type`, a fresh per-render channel and `height`. It sends to the exact
FreeScout parent origin. Because an iframe without `allow-same-origin` has an opaque origin, the
parent accepts a message only when its source is the expected iframe window and its type and
channel match the current render. The height must be a finite integer, is debounced, and is clamped
between `160px` and `1600px`. No markup, person data, URL or action is accepted through this bridge.
If a valid message is absent, the iframe keeps a safe `480px` default height and scrolls internally.

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
7. Resolve the FreeScout agent to one eligible, verified Rondo user.
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
    "mailbox_id": 18,
    "verified_name": "Ledenadministratie",
    "verified_email": "ledenadministratie@svawc.nl",
    "enabled": true
  }
}
```

These are the approved production values. Other installations configure their own numeric ID for
the same stable key; neither `18` nor the AWC name/address is compiled into the module.

Mapping verification rules:

- the module loads mailbox ID `18` through FreeScout's local model during production setup and
  confirms it exists and is enabled before the mapping can be enabled;
- the numeric local ID is authoritative after verification; name and email are a human-readable
  snapshot and are never used as a fallback lookup;
- a later name or address change raises mapping drift and requires administrator re-verification,
  but never silently searches for or switches to another mailbox;
- a missing, deleted, disabled or replaced ID blocks new managed grants and records an operator
  error; it never guesses by name/address or touches unrelated manual mailbox access;
- changing the configured ID requires an authenticated FreeScout administrator, explicit selection
  from local mailboxes, confirmation of the displayed name/address and an audit event;
- Rondo only knows the stable key `ledenadministratie`; it never sends production mailbox ID `18`.

The production API list used for planning exposes ID, name and address but not the active flag, so
the module-local enabled-state check remains a release gate rather than an assumed API fact.
Evidence: [FreeScout production mailbox mapping](evidence/freescout-mailbox-mapping-2026-09-01.md).

#### Mailbox mapping settings screen

Rondo Integration adds a **Mailbox mappings** section to its FreeScout administrator settings. It
is visible only to FreeScout administrators and uses normal FreeScout CSRF protection. The screen
does not accept free-form capability names, stable keys or mailbox IDs.

The screen starts with a prerequisite banner showing:

- Rondo connection and signing configuration: Verified or Action required;
- Rondo access-service availability and last successful check;
- `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true`: Verified or Blocking;
- local break-glass administrator: Verified or Blocking;
- last successful mapping reconciliation and any current drift.

Each supported Rondo mapping appears as one row:

| Column | Behavior |
|---|---|
| Rondo access | Read-only human label, stable key and required effective capability returned by the verified Rondo configuration service |
| FreeScout mailbox | Local selector showing name, address and numeric ID; only `Mailbox::isActive()` mailboxes can be activated |
| Sidebar policy | Read-only policy label/version associated with the stable key; FreeScout cannot widen its Rondo allowlist |
| Managed access | Aggregate count of module-managed user-mailbox relations; no user names or emails in the overview |
| State | Draft, Verified, Active, Paused, Drifted, Disabling or Disabled |
| Health | Last local verification, last reconciliation and a short redacted error when applicable |
| Actions | Verify, Activate, Pause, Resume, Change mailbox, Disable and revoke, or View audit, according to state |

Version one shows only stable keys advertised by the verified Rondo installation and supports each
key once. The same local FreeScout mailbox cannot be active for two Rondo keys. The initial list
contains only `ledenadministratie`; adding future keys requires a Rondo release and its approved
mailbox/sidebar policy, not a FreeScout text entry.

The mapping catalog is deliberately closed:

| Stable key | Required effective capability | Production mailbox candidate | Status | Release gate |
|---|---|---|---|---|
| `ledenadministratie` | `ledenadministratie` | Ledenadministratie, locally configured as ID `18` | First release | Current `ledenadministratie.v1` policy and all compatibility gates |
| `fairplay` | `fairplay` | FairPlay, currently discovered as ID `17` | Pre-approved candidate | Define and approve `fairplay.v1` fields, privacy rules and pilot before Rondo advertises the key |
| `contributie` | `financieel` | Contributie, currently discovered as ID `9` | Pre-approved candidate | Define and approve `contributie.v1` fields, finance-specific privacy rules and pilot before Rondo advertises the key |

Candidate IDs are production discovery snapshots only. They remain environment configuration and
receive the same local existence, active-state and drift verification as Ledenadministratie.
Neither candidate appears in the signed configuration response until its release gates have passed.

No other existing capability may be mapped implicitly:

- `financieel_read` cannot grant Contributie because a FreeScout agent can send or alter
  correspondence; read-only Rondo finance access is not equivalent to mailbox participation;
- `toegangscontrole`, `manage_clothing`, `sponsorbeheer`, `narrowcasting`,
  `accommodatiebeheer`, `vrijwilligers`, `rondo_iva_approve`, `kaderlijst` and `vog` do not
  semantically match a current dedicated production mailbox;
- Communicatie, Grote Clubactie, Info, Jeugdkamp, Junioren, Pupillen, Sjors Sportief, Toernooien,
  Wedstrijdzaken and age-team mailboxes remain manually administered in FreeScout until Rondo has
  an exact, narrowly scoped capability and approved sidebar policy for that work;
- Rondo roles, committee membership, team membership, age-group visibility, pool roles,
  administrator status and arbitrary WordPress capabilities are never accepted as alternate
  mapping inputs. Authorization uses only the catalog's named effective capability;
- one user may receive multiple managed mailboxes only when the signed Rondo access response
  independently returns each active catalog key.

Adding a catalog entry is a Rondo product release: amend this PRD, add the dedicated capability if
none exists, version and test its sidebar field policy, then advertise the key from the signed
configuration endpoint. Updating FreeScout settings alone cannot make an unsupported key available.

The module obtains that allowlist through a signed server-to-server request:

```text
POST /wp-json/rondo/v1/integrations/freescout/configuration
```

The response contains no users or person data:

```json
{
  "version": 1,
  "mappings": [
    {
      "key": "ledenadministratie",
      "label": "Ledenadministratie",
      "required_capability": "ledenadministratie",
      "sidebar_policy": "ledenadministratie.v1",
      "enabled": true
    }
  ],
  "evaluated_at": "2026-09-01T12:00:00Z"
}
```

The response is schema-validated and cached only for short-lived settings-page use; previously
verified rows remain visible but enter Action required when Rondo is unavailable. An unavailable
configuration service cannot be used to add, activate or repoint a mapping.

Mapping workflow:

1. **Add mapping** opens a two-step form: choose an available Rondo key, then choose an active local
   FreeScout mailbox. The selector displays `Name <address> · ID n`.
2. **Verify** checks the key against the configured Rondo issuer, reloads the mailbox locally,
   confirms `Mailbox::isActive()`, uniqueness, connection prerequisites and the field-policy
   version, then stores the name/address snapshot. Verification performs no grants or revocations.
3. **Activate** shows an aggregate dry run: users to grant, existing managed relations unchanged,
   manual relations preserved, and invalid/unresolved users. It requires recent local-password
   confirmation and an explicit **Activate and reconcile** action.
4. Activation commits the verified mapping and an audit event before reconciliation starts. The UI
   reports progress and final aggregate counts; individual failures remain retryable.
5. **Change mailbox** verifies the new mailbox and previews grants to the new ID plus managed
   revocations from the old ID. It requires the same password confirmation and explicit
   **Change and reconcile** action. The old ID remains recorded in the audit/reconciliation state
   until its managed relations reach zero.
6. **Disable and revoke** previews the managed relations to remove and the manual relations that
   remain. After password confirmation it enters Disabling, reconciles only module-managed
   relations, and becomes Disabled only when their count reaches zero.

Operational states are deliberately distinct:

- **Pause** stops grants and revocations and preserves the last confirmed access state; use it for
  maintenance or a Rondo outage;
- **Disable and revoke** is an intentional access-policy change and removes managed relations;
- **Drifted** means the selected ID is missing/inactive or its name/address differs from the
  verification snapshot. Automatic reconciliation pauses, existing access is preserved, and an
  administrator must re-verify or deliberately change/disable the mapping;
- an environment-provisioned mapping may be displayed and verified in the UI, but its locked fields
  identify their configuration source and cannot be overridden there.

Automated provisioning may supply local IDs as a JSON object in
`RONDO_MANAGED_MAILBOX_MAPPINGS`, for example `{"ledenadministratie":18}`. Environment values take
precedence and lock the key/ID pair; name, address, `Mailbox::isActive()` and Rondo-policy snapshots
are still resolved and verified locally before activation. Invalid JSON, unknown keys and
non-positive or duplicate IDs block activation without changing the last confirmed mapping.

Every mutating action records administrator ID, action, stable key, old/new mailbox IDs, aggregate
impact, time and result. Activation, change and disable require a local-password confirmation made
within the preceding 10 minutes. The audit never records capability payloads, user lists or email
addresses in its normal view. No workflow calls `sync()` on a user's entire mailbox collection.

The access service returns desired **managed mailbox keys**, not arbitrary FreeScout IDs. The
FreeScout module translates keys to its local IDs. Rondo therefore cannot attach a user to an
unconfigured mailbox.

#### Rondo access service

Route appended to the configured Rondo base URL:

```text
POST /wp-json/rondo/v1/integrations/freescout/access
```

The Rondo Integration module sends the validated Rondo subject plus the FreeScout user identity
when one already exists. Rondo resolves the subject and returns:

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

The first Ledenadministratie release has this fixed, versioned display contract:

| Group | Values | Canonical source and derivation |
|---|---|---|
| Summary | Full name; Active, Former member, Membership ended and Transfer pending badges; member type; KNVB ID; current teams; game activity | Name from `first_name`, `infix`, `last_name`; status from `former_member` and `lid_tot`; transfer from `wacht_op_overschrijving`; membership from `type_lid`; identity from `knvb_id`; sport from current `work_history` team rows and `spelactiviteit` |
| Membership | Date of birth and derived age; age group; member since; member until | `birthdate`, `leeftijdsgroep`, `lid_sinds`, `lid_tot`; age is calculated on the server for the response date and is not stored separately |
| Contact | Primary and secondary email; both mobile and telephone fields; populated labelled addresses | `email_1`, `email_2`, `mobile_1`, `mobile_2`, `telephone_1`, `telephone_2`, `addresses`; Home is shown first, followed by other populated labelled addresses |
| Household | Directly related person's name, relationship label, active/former status and current team summary | One non-recursive level from `relationships`; the related person is independently visibility-checked and its current teams are derived from current `work_history` rows |
| Process | Member-onboarding email state; whether a Rondo account is linked and its welcome-email time; eligible digital membership-pass type and available wallet platforms | `onboarding_email_lid_sent`, boolean presence of `linked_user_id`, `welcome_email_sent_at`, and the client-safe `membership_pass` summary; user IDs, wallet action URLs and role-selection details are not returned |
| Open tasks | Count plus at most three open or awaiting tasks visible to the current agent, with title, status, due date and overdue state | `rondo_todo` records directly related through `related_persons`, after the normal created-by-or-assigned-to visibility filter; notes, email-event data and assignee details are omitted |
| Links | Open person in Rondo; open the visible task list or task; open the Sportlink member record | Server-generated allowlisted links only; the Sportlink URL uses the canonical `knvb_id`; absent identifiers produce no link |

Presentation and missing-data rules:

- the summary stays visible; Membership, Contact, Household, Process and Open tasks are compact
  collapsible groups suitable for the configured `360px` default maximum width;
- empty values and empty groups are omitted rather than rendered as dashes or unknown facts;
- an unavailable current-team relation is omitted; historical or raw `work_history` rows are never
  returned;
- at most six household members render, followed by an Open in Rondo link when more exist;
- `is_deceased` may produce a discreet Do not contact state and disables clickable communication
  links, but `datum_overlijden` is not returned;
- the response includes its generation time labelled Live from Rondo; it does not claim a
  Sportlink synchronization time because no canonical source-sync timestamp exists;
- the FreeScout customer avatar remains the only portrait in this surface, so the Rondo payload
  contains no image URL or image bytes;
- the sidebar is read-only. Mail, telephone, Rondo, task and Sportlink links are explicit
  navigation; no person, task, pass or onboarding mutation occurs in the iframe.

Explicitly excluded unless a future mailbox policy and Rondo capability both allow them:

- contribution balance, invoices and installment details;
- financial block and Nikki fields;
- VOG details;
- sponsor-management fields;
- private notes and unrestricted timeline content;
- todo notes, Lettermint event/recipient data and assignee details;
- `freescout_id`, raw user IDs, wallet action URLs and full `work_history` rows;
- gender, pronouns, nickname, photo gallery and date of death;
- discipline cases;
- arbitrary custom fields.

## Customer matching

The shared matching core works in this order:

1. Normalize all FreeScout customer emails.
2. Search Rondo for exact normalized matches in canonical email fields.
3. Deduplicate matches by person ID.
4. Accept a person only when exactly one candidate remains in the caller's permitted scope.

The two callers apply different scopes without changing the identity rule:

- the sidebar intersects candidates with the current agent's Rondo visibility and renders only one
  accessible match;
- conversation activity delivery uses the integration service scope across active and former
  people, then relies on normal Rondo person/timeline authorization when humans read the pointer.

Rules:

- Case differences are ignored.
- An exact secondary email is valid.
- Synthetic `@members.rondo.invalid`, missing and malformed addresses are discarded before search.
- Phone numbers never select a person automatically.
- Names, FreeScout customer IDs, `freescout_id`, KNVB IDs and old SQLite mappings never break a tie
  or select a person.
- A shared email returning multiple people produces an ambiguous state.
- An inaccessible record is indistinguishable from no match.
- The agent may open Rondo search, but the sidebar never asks them to pick from inaccessible people.
- No match state contains no inferred membership information.

Version one creates no persistent customer-to-person binding from an automatic match. A future
explicit, audited linking action may add one, but it is not required to retire copied FreeScout IDs.

## OAuth and account lifecycle

### Pilot

- Existing FreeScout agents are matched to Rondo by a unique verified email.
- Guarded creation starts disabled, is proven with one synthetic user, and is then enabled for a
  bounded new-agent pilot only after all creation gates pass.
- Rondo OIDC login is optional until every pilot agent succeeds.
- One documented local FreeScout administrator remains available as break glass.
- Forced Rondo login remains disabled until failed callbacks and recovery through both
  `/login?rondo_oauth=0` and `RONDO_FORCE_OAUTH_LOGIN` have been rehearsed against the released
  custom module.

### Guarded automatic creation

Production automatic creation may be enabled only when:

- Rondo denies authorization for users without a mapped FreeScout capability;
- unique-email, durable-verification and synthetic/shared-email guards are proven;
- every returned mailbox key maps to a verified enabled local mailbox;
- account, binding, OIDC-only state, one or more managed mailboxes and audit commit atomically;
- access-service failure or empty desired access creates nothing;
- local password login/reset is blocked for module-created users;
- duplicate, concurrent, revocation, deactivation and reactivation behavior is tested.

### Revocation

Removing `ledenadministratie` causes the Rondo Integration module to remove the managed mailbox. It
never deletes the FreeScout account or its historical reply attribution. FreeScout's normal
authorization must read current mailbox relationships on every request; the module must not cache a
revoked mailbox in its session. Once reconciliation commits, the next request to that mailbox or
its customers/conversations is denied even if the browser still has a valid session cookie.

Session and account handling depends on the remaining FreeScout authority:

- if another managed or manual mailbox remains, the session and account remain active and only the
  revoked mailbox disappears;
- if no mailbox remains, all sessions and remember tokens are invalidated; a Rondo-created account
  is also deactivated, while a manually created account keeps its administrator-controlled status;
- disabling or replacing a subject binding invalidates all sessions regardless of remaining
  mailboxes because the identity itself is in question;
- regaining mapped access reactivates the same Rondo-created user before a new session is issued;
- a manual mailbox on an OIDC-only account is preserved, but the administration screen warns that
  future access requires continuing Rondo eligibility or an explicit audited conversion to local
  login.

The normal target remains managed-access removal within five minutes of a signed Rondo capability
event. The hourly reconciliation is the maximum normal repair window when an event is missed. If
Rondo or FreeScout cannot exchange current state, the integration preserves the last confirmed
mailbox state and records drift; it does not guess a revocation or block unrelated FreeScout work.
Once connectivity returns, reconciliation runs immediately. This availability exception is
explicit: there is no claimed hard revocation deadline during a two-sided integration outage.

Session invalidation runs after the mailbox transaction commits. If invalidation itself fails, the
removed mailbox remains inaccessible through normal authorization, the failure is audited and the
module retries invalidation; it never restores the mailbox relation to make logout appear atomic.

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
5. Keep the conversation activity feature enabled and redesign its person mapping before removing
   its dependency on the existing FreeScout SQLite map.

No local Rondo Sync pipeline is run as part of planning or migration analysis; production SQLite
mappings remain authoritative.

### Long-term conversation activity delivery

The current daily Rondo Sync job scans conversations for every customer tracked in the customer
enrichment SQLite database, resolves FreeScout customer ID to KNVB ID there, resolves KNVB ID to a
Rondo person ID in a second local database, and creates a `rondo_activity` comment containing the
subject and FreeScout link. It does not copy individual replies, and once created it does not keep
conversation status in sync.

The long-term version preserves that deliberately small product surface while removing the
customer-enrichment dependency. Current FreeScout core exposes both inbound
`CustomerCreatedConversation` and agent-created `UserCreatedConversation` events, the
`ConversationCustomerChanged` event and all emails through the customer relation; the
compatibility spike must still prove the module listener timing and repair behavior:

- Rondo Integration listens for both inbound and agent-created conversations; the existing daily
  job remains the fallback until both paths, customer reassignment and missed-event repair are
  accepted;
- the signed event contains only the configured FreeScout instance, numeric conversation and
  customer identifiers, mailbox key, plain-text subject, creation time and the customer's current
  normalized email set;
- Rondo resolves the person through the shared exact-email matcher; a FreeScout-supplied Rondo
  person ID, KNVB ID, `freescout_id`, name, phone or sidebar match result is never trusted as
  authority;
- Rondo stores the pointer as its existing native `rondo_activity` comment plus comment meta for
  the FreeScout instance and conversation ID; no custom Rondo table is introduced;
- the instance-and-conversation pair is the idempotency key, so retry, event replay and repair
  cannot create duplicate timeline rows;
- the subject is escaped as plain text and the conversation URL is constructed server-side from
  the configured FreeScout base URL and validated numeric conversation ID;
- no message body or preview, reply text, recipient address, attachment, customer profile payload
  or agent identity is stored in Rondo;
- existing historical activity comments stay in place during cutover and rollback.

Match and repair outcomes are explicit:

- one person: create or confirm the approved activity on that person;
- zero or multiple people: create no visible activity; Rondo returns only `no_match` or
  `ambiguous`, and the module-owned delivery queue records IDs, attempts and reason code without
  storing another copy of the email addresses;
- hourly repair reloads the customer's current emails from FreeScout and retries pending delivery;
- a later FreeScout customer-email edit or Rondo email change may resolve a pending delivery, but
  never moves an already matched historical pointer by itself;
- an explicit FreeScout conversation customer change is authoritative for association: re-run the
  matcher, move the existing activity when the new customer uniquely matches another person, or
  hide it as pending when the new customer has no unique match;
- moving uses the WordPress comment API to change `comment_post_ID`; hiding retains the same comment
  with non-approved status and match-state comment meta, so repair can restore it without deletion
  or a second activity;
- every move, hide and restore is idempotent and audited by instance/conversation and old/new Rondo
  IDs without email addresses in the log.

The event-driven path cannot replace the current batch until creation from both directions,
customer reassignment and missed-event repair pass the compatibility checklist.

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
- Binding uniqueness is enforced by database constraints and binding/audit writes share one local
  transaction; no remote call or session creation occurs inside it.
- Recovery requires a locally re-authenticated administrator, a reason and a single-use 10-minute
  Rondo flow; subjects are never typed or reassigned by email.
- Just-in-time creation requires current non-empty mapped access and atomically commits the ordinary
  user, OIDC-only state, binding, initial mailboxes and audit before session creation.
- Rondo-created accounts cannot use password login/reset and are never promoted to administrator
  automatically.
- Sessions contain no durable copy of managed mailbox authorization; current FreeScout mailbox
  relationships are checked for each protected request.
- Zero-mailbox and identity-binding revocations invalidate all sessions and remember tokens;
  unrelated manual mailbox access never gets removed to force a logout.
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
- Script-free Rondo markup inside an opaque-origin sandbox; only the nonce-authorized module resize
  script runs.
- The iframe sandbox excludes same-origin access, forms, downloads and top-level navigation.
- Resize messages are height-only, source/channel/type validated, debounced and clamped before the
  parent changes the iframe height.
- No credentials, raw tokens, signatures, personal payloads or returned HTML in normal logs.
- Conversation activity events and logs never contain message bodies, previews, recipients,
  attachments or agent identity; Rondo escapes the subject and constructs the FreeScout link.
- Customer emails are transient matching inputs for the signed activity request; neither Rondo
  activity meta nor integration logs retain another copy of them.
- Rate limiting per FreeScout instance, agent and source IP where reliable.
- Response cache keys include mailbox policy and effective authorization class; no cross-user PII
  cache leakage.
- All integration endpoints fail closed for data and fail open for normal FreeScout operation.

## Privacy requirements

- Document FreeScout as a recipient/display surface for live Rondo data in the processing record.
- Send only customer identifiers needed for exact matching.
- For activity delivery, send only the customer's current normalized emails and discard them after
  matching; pending repair reloads them from FreeScout instead of storing a queue copy.
- Send only agent ID/email needed for identity mapping.
- Do not persist sidebar person payloads in FreeScout.
- Avoid browser analytics, third-party fonts, external images and remote scripts inside the sidebar.
- Keep audit logs event-oriented: success/failure, IDs and reason codes, without full person payloads.
- Keep FreeScout activity pointers to one subject, creation time and server-generated link per
  conversation; FreeScout remains the system of record for all message content.
- Set an explicit retention period for OAuth and provisioning audit records.
- Treat the approved Ledenadministratie field contract as a privacy boundary; every later field or
  group requires a separate club review before production.

## Observability

Rondo records aggregate and audit-safe events for:

- OAuth authorizations allowed/denied by reason;
- token exchange failures;
- sidebar signature/replay failures;
- match outcomes: exact, none, ambiguous and inaccessible;
- sidebar latency and timeout rate;
- mailbox access grants/revocations;
- mailbox mapping verification and name/address/availability drift;
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
- Revoked mailbox authorization: denied on the next server request after reconciliation commits,
  independent of the remaining FreeScout session cookie.
- No hard revocation claim is made while current desired state cannot be exchanged; reconciliation
  runs immediately after connectivity returns.

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
6. Current and changed-address email-verification behavior and durable proof.
7. Automatic user creation defaults and role assigned.
8. Events and extension points needed after custom OIDC login and user creation.
9. Behavior when Rondo denies authorization.
10. Force-login recovery procedure.
11. FreeScout mobile-app behavior.
12. Sandboxed `srcdoc` layout and height-bridge behavior at realistic sidebar widths.
13. Timeout behavior with DNS failure, connection refusal, TLS error and slow response.
14. Inbound, agent-created and customer-reassignment conversation events plus missed-event repair.

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
- Prove the opaque-origin sandbox, module-owned height bridge and current-agent hook.
- Confirm timeout values and review the custom flow's threat model.

**Gate:** no product implementation proceeds without a successful custom-client end-to-end test
login, complete token validation and a confirmed current-agent hook.

### Phase 1: Rondo OpenID Connect provider

- Add first-party client registration and secret rotation.
- Implement OIDC discovery, authorization-server metadata, authorize, token, UserInfo and JWKS
  endpoints.
- Add opaque subjects and external-email eligibility checks.
- Add durable exact-address verification meta, hooks for successful emailed-link consumption and
  the resumable FreeScout authorization verification flow.
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
- Add module migrations, row-locking transactions, immutable binding audit and the administrator
  disable/replace recovery UI.
- Add guarded ordinary-user creation, the OIDC-only account boundary and atomic initial mailbox
  provisioning.
- Add the administrator Mailbox mappings screen, verification state machine, aggregate dry run and
  protected activate/change/pause/disable workflows.
- Add authentication and conversation authorization.
- Add current agent to the signed payload.
- Replace body secret with versioned HMAC headers.
- Add strict timeouts, redirect prevention and response limits.
- Render sanitized Rondo markup in the opaque-origin `srcdoc` sandbox with the nonce-authorized,
  height-only resize bridge.
- Preserve a small visible refresh action and graceful failure state.
- Add allowlisted accent settings and a live preview without arbitrary CSS support.
- Add the responsive customer-sidebar maximum-width setting and coordinated conversation spacing.
- Add the inbound/agent-created/customer-change activity listeners and module-owned pending-delivery
  queue without storing email copies.

### Phase 3: Rondo sidebar service

- Add signature/replay validation.
- Add agent mapping and effective-user authorization.
- Add exact customer matching.
- Reuse that matcher under integration scope for the signed conversation-activity endpoint.
- Create idempotent native activity comments and implement pending, move, hide and restore behavior.
- Add mailbox policies and the Ledenadministratie view.
- Add no-match, ambiguous, unauthorized and unavailable states.
- Link all mutations to authenticated Rondo pages.

### Phase 4: automatic provisioning in the Rondo Integration module

- Extend the same custom module with the FreeScout login listener.
- Add signed Rondo access service.
- Add managed capability-to-mailbox configuration.
- Reconcile only managed mailbox relationships.
- Add conditional session invalidation plus Rondo-created account deactivation/reactivation.
- Add Rondo capability-change events and FreeScout receiver.
- Add hourly repair and nightly audit.

### Phase 5: pilot and sync cutover

- Pilot with a small Ledenadministratie group.
- Compare live sidebar values with copied FreeScout fields.
- Validate grant and revocation with real non-admin agents.
- Enable Rondo OIDC login without forcing it.
- Rehearse break-glass recovery.
- Disable customer enrichment only after acceptance.
- Keep the conversation-activity product feature; disable its daily batch only after the module
  event, exact-email matching, reassignment and repair path is accepted.

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
- An existing account, linked person, capability, password login or administrator provisioning
  alone never produces `email_verified: true`.
- A future activation, Magic Login, verified profile change or dedicated OIDC verification records
  the exact normalized email, time and approved method.
- Existing accounts without that durable marker complete one emailed verification before consent.
- A changed, synthetic or ambiguous current address invalidates the marker at claim time.
- An emailed continuation token is hashed, single-use, rate-limited, expires within two hours and
  contains no OAuth parameters.
- A first login binds one verified Rondo subject to one unbound FreeScout user.
- The same subject can log in again after an email change without changing the binding.
- A different subject presenting a bound user's email is denied.
- A subject already bound to another FreeScout user is denied.
- An ordinary login cannot unlink, replace or transfer a subject binding.
- Concurrent callbacks cannot bind one subject to two users or two subjects to one user.
- A failed binding or audit write creates no FreeScout session and leaves no partial row.
- Disabling a binding prevents email-based first link and invalidates the target's sessions.
- A replacement requires local administrator re-authentication, a reason and a single-use
  10-minute recovery flow through Rondo.
- A successful replacement retires the old identity, binds the new identity and consumes the
  recovery atomically; failure or expiry leaves the target disabled.
- An unknown verified subject with current non-empty mapped access creates one ordinary OIDC-only
  FreeScout user and at least one managed mailbox before the first session exists.
- Empty, unavailable, invalid or unmapped desired access creates no user, binding or session.
- A creation failure at every transaction step leaves no partial user, binding, mailbox or audit.
- A repeated or concurrent login resolves the same FreeScout user and never creates a duplicate.
- Password login/reset is denied for a Rondo-created user; administrators are never auto-created or
  auto-promoted.
- Replies sent by the created agent retain that FreeScout user as author after later deactivation.
- Losing the last managed mailbox deactivates a Rondo-created user with no manual mailbox and
  invalidates sessions; restored access reactivates the same account.
- Losing one mailbox while another remains keeps the session but denies every subsequent request to
  the removed mailbox.
- A manually created zero-mailbox user is logged out without the integration changing its account
  status; a manual mailbox is never detached to force logout.
- Binding disablement or replacement invalidates sessions even when a mailbox remains.
- An invalidation failure leaves the mailbox revoked, records the error and retries logout.

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
- The same module build supports different club color pairs without source or asset changes.
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
- Ledenadministratie sees exactly the fixed, versioned field contract.
- Empty values and groups are absent; they never become guessed or stale fallback values.
- Current teams contain only current derived team summaries, never raw or historical work history.
- Household output is one level deep, independently visibility-checked and capped at six people.
- A related person outside the agent's Rondo scope is absent without revealing that it exists.
- Only tasks created by or assigned to the current agent appear; completed tasks, notes, Lettermint
  metadata and assignee details never appear.
- The pass summary exposes eligibility/type and wallet-platform availability without wallet action
  URLs or role-selection details.
- A deceased person shows Do not contact without returning the date of death or clickable
  communication links.
- The generation time is labelled as Rondo freshness and never as Sportlink sync time.
- Ledenadministratie does not see finance, VOG, sponsor or private-note fields.
- A future finance mailbox cannot expose finance to an agent lacking Rondo finance access.

### Conversation activity pointer

- One new matched conversation creates exactly one `rondo_activity` pointer.
- Replaying the same instance-and-conversation event creates no duplicate.
- Both inbound and agent-created conversations use the same matching and idempotency rules.
- All current customer emails are normalized and compared only with Rondo `email_1` and `email_2`;
  exactly one integration-scope person may remain.
- Former members remain eligible for activity matching; human readers still need normal Rondo
  permission for that person and timeline.
- Name, phone, FreeScout/KNVB ID and SQLite mapping inputs cannot select or disambiguate a person.
- The activity contains only escaped subject, creation date/time and a server-generated FreeScout
  link; message, reply, recipient, attachment, customer-profile and agent data are absent.
- A malformed instance, conversation ID, timestamp or signature creates no activity.
- A FreeScout-supplied Rondo or KNVB ID is ignored.
- An unmatched or ambiguous customer creates no person activity and remains eligible for repair
  after the matching state changes.
- Email edits may resolve pending delivery but do not silently move an approved historical
  pointer.
- An explicit conversation customer change moves the pointer only after a new unique match; zero or
  multiple matches hide it pending repair rather than leave it on the previous person.
- Existing historical pointers remain readable after event delivery replaces the daily batch.
- Disabling event delivery can restore the previous batch during its rollback window without
  duplicating activities already keyed to the same conversation.

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
- An administrator cannot type a stable key, capability or mailbox ID; mappings use verified Rondo
  keys and local active-mailbox selectors.
- Verification stores the local ID/name/address snapshot but changes no access.
- Activation dry-run counts match the resulting grants, unchanged relations, preserved manual
  relations and failures.
- Pause preserves current managed relations and performs no grants or revocations.
- Disable and revoke removes only managed relations and reaches Disabled only when their count is
  zero.
- Change mailbox moves only managed relations from the old ID to the verified new ID and preserves
  manual relations on both.
- A missing/inactive or renamed/address-changed mailbox enters Drifted, pauses reconciliation and
  never falls back to name/address lookup.
- One stable key and one local mailbox cannot each be active in more than one mapping.
- Environment-locked values are visible with their source and cannot be overridden in the UI.
- Activate, change and disable require administrator authorization, recent local-password
  confirmation, CSRF validation, explicit confirmation and an audit event.

## Acceptance criteria

The milestone is complete only when:

- a real pilot agent signs into FreeScout through Rondo;
- that agent's verified Rondo subject is bound one-to-one to the expected FreeScout user;
- another subject presenting the same email cannot authenticate as that FreeScout user;
- Rondo issued `email_verified: true` only after durable proof for the exact current, unique
  external address, and a changed address requires verification again;
- simultaneous competing callbacks cannot create a conflicting or partial subject binding;
- an administrator can disable or replace a binding through the audited recovery flow, and the
  replaced subject can no longer authenticate;
- an eligible new agent receives exactly one ordinary OIDC-only FreeScout account and current
  managed mailbox before login, while any failed access or transaction creates nothing;
- replies remain attributed to that durable FreeScout user after access is revoked or the account
  is deactivated;
- a revoked managed mailbox is inaccessible on the next server request after reconciliation;
- zero-mailbox and binding-recovery cases invalidate sessions without deleting the user, while a
  remaining manual mailbox and manually controlled account status are preserved;
- the deployed module contains no hardcoded Rondo hostname and uses the verified configured base
  URL for every Rondo request;
- FreeScout identifies the current agent and signs the sidebar request;
- Rondo maps that agent to the expected eligible WordPress user with current email proof;
- a current `ledenadministratie` capability grants the correct FreeScout mailbox;
- production key `ledenadministratie` resolves only to locally verified mailbox ID `18`, displayed
  as `Ledenadministratie <ledenadministratie@svawc.nl>`;
- the signed first-release catalog advertises only `ledenadministratie`; `fairplay`, `contributie`,
  `financieel_read`, role names and arbitrary capabilities cannot be activated through FreeScout;
- the mapping settings screen can verify, preview, activate, pause, change and intentionally revoke
  the mapping without accepting arbitrary identifiers or modifying manual mailbox relations;
- revoking that capability removes only integration-managed access;
- the Ledenadministratie sidebar renders exactly the approved live field contract, omits empty
  values, applies related-person and task visibility independently and exposes no prohibited
  payload keys;
- each matched FreeScout conversation produces one minimal idempotent Rondo activity pointer while
  FreeScout remains the sole store for message content;
- activity matching uses only the unique normalized intersection of current FreeScout customer
  emails and Rondo `email_1`/`email_2`, with deterministic pending and reassignment behavior;
- zero-match and multiple-match cases disclose no person data;
- an agent lacking Rondo access cannot retrieve the sidebar record by changing IDs;
- an unreachable Rondo endpoint does not freeze FreeScout or exhaust workers;
- returned sidebar content cannot execute scripts in the FreeScout parent page;
- expanding or collapsing sidebar content resizes the iframe through a validated height-only
  message without exposing FreeScout DOM, cookies or storage;
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
- Disable further automatic creation without deleting or unlinking accounts already created.
- Open `/login?rondo_oauth=0` for immediate local break-glass login.
- If browser recovery is unavailable, clear `RONDO_FORCE_OAUTH_LOGIN` in FreeScout's
  server-side configuration and restart FreeScout.
- Disable managed provisioning in the Rondo Integration module while leaving existing mailbox
  relations unchanged.
- Disable the sidebar feature or Rondo Integration module.
- Disable Rondo appearance overrides to restore FreeScout's native accent colors and layout.
- Restore the previous module directory and database backup if a module update fails.
- Re-enable the existing Rondo Sync FreeScout customer pipeline if it was disabled.
- Disable conversation events and re-enable the existing conversation-activity batch during its
  rollback window; preserve all existing activity comments.
- Preserve FreeScout users, conversations, customer records and historical custom fields.
- Rotate the OAuth client secret and HMAC signing key if compromise is suspected.

## Open decisions before implementation

1. Audit retention period and operational owners for failed provisioning events.
2. Final module repository, protected release workflow and update-asset URLs.
3. Initial production values for interface accent and interface accent surface.
4. Whether the maximum customer-sidebar width remains `360px` after realistic conversation and
    200%-zoom testing.
