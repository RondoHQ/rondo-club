# FreeScout and Rondo compatibility spike checklist

**Status:** in progress
**Parent PRD:** [FreeScout sidebar, Rondo identity and mailbox provisioning](freescout-rondo-identity-sidebar.md)  
**Environment:** non-production only  
**Data:** synthetic users, customers and conversations only

## Purpose

Evaluate the licensed FreeScout OAuth Login add-on, record its rejection, and prove that the current
FreeScout release plus the custom Rondo Integration module can support the PRD before product
implementation starts.

**Paid add-on disposition:** `NO-GO`, approved 2026-09-01. It is evaluation evidence only and will
not be a production dependency. The remaining login tests target the custom module as an OpenID
Connect relying party.

## Completion record

Fill this section when executing the spike.

| Item | Value |
|---|---|
| Date | 2026-08-31 |
| Tester | Joost and Codex |
| FreeScout version/commit | 1.8.238 |
| OAuth Login module version | 1.0.28 |
| Sidebar Webhook reference version/commit | |
| PHP version | 8.5.9 |
| Browser versions | |
| FreeScout mobile app/version | |
| Result | In progress; paid OAuth Login add-on rejected, custom OIDC client not yet proven |
| Evidence location | [OAuth identity evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) |

## Production mailbox mapping record

This read-only discovery record fixes deployment configuration; spike execution still uses the
synthetic non-production mailbox.

| Stable key | Production ID | Verified name | Verified address | Discovery evidence | Remaining gate |
|---|---:|---|---|---|---|
| `ledenadministratie` | `18` | Ledenadministratie | `ledenadministratie@svawc.nl` | [Production mailbox mapping evidence](evidence/freescout-mailbox-mapping-2026-09-01.md) | Module-local existence and enabled-state verification before provisioning |

The same discovery found two pre-approved later candidates. These are not active mappings and must
not appear in the signed version-one catalog:

| Future stable key | Production ID snapshot | Verified mailbox | Required capability | Remaining gate |
|---|---:|---|---|---|
| `fairplay` | `17` | FairPlay | `fairplay` | Approve `fairplay.v1` field/privacy policy and pilot |
| `contributie` | `9` | Contributie | `financieel` | Approve `contributie.v1` field/privacy policy and pilot; never substitute `financieel_read` |

## Evidence rules

- Record every result as `PASS`, `FAIL`, `BLOCKED` or `NOT APPLICABLE`.
- Link each result to a screenshot, redacted trace, log excerpt or source reference.
- Redact client secrets, authorization codes, access tokens, signatures, cookies and personal data.
- Do not store complete request bodies containing customer or agent data in normal logs.
- Record exact versions and configuration; do not rely on “current” or “latest.”
- Document observed behavior separately from a proposed workaround.

## 1. Prepare the test environment

- [ ] Clone the production FreeScout version and relevant configuration into an isolated
  non-production instance.
- [x] Install the licensed OAuth Login module without enabling Force OAuth Login.
- [ ] Record the exact Sidebar Webhook upstream commit used as an MIT-licensed reference; do not
  plan to install it alongside the custom Rondo Integration module.
- [x] Configure a controlled test identity provider that can show redacted authorization, token and
  User Info request shapes.
- [ ] Configure the Rondo base URL without changing module code and record the normalized derived
  sidebar and access endpoints.
- [x] Create a synthetic FreeScout mailbox named `Ledenadministratie test`.
- [x] Enable and verify `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true` so mailbox authorization also
  constrains direct customer profile and edit routes.
- [ ] Create these synthetic FreeScout agents:
  - existing administrator with local login;
  - existing eligible agent with a unique email;
  - existing ineligible agent;
  - two agents or identity records with the same email;
  - new identity that has no FreeScout user.
- [ ] Create synthetic conversations representing one exact customer match, no match and an
  ambiguous shared-email match.
- [x] Preserve a tested local administrator login as break glass.
- [x] Confirm the environment cannot send real customer email or notifications.

**Evidence:** version record, module list, redacted configuration and test-data inventory.

## 2. Observe the paid add-on authorization request

Start a FreeScout login and capture the browser redirect to the test provider.

- [x] Record the exact authorization endpoint method and query parameters.
- [x] Confirm a fresh, unpredictable `state` value is sent on each login attempt.
- [x] Confirm FreeScout rejects a callback with a missing or changed `state`.
- [x] Record the exact redirect URI and confirm FreeScout uses the configured value consistently.
- [x] Record the requested scopes.
- [x] Record whether a PKCE `code_challenge` and `code_challenge_method=S256` are sent.
- [x] Record whether an OpenID Connect `nonce` is sent.
- [x] Confirm login cancellation returns a usable FreeScout login screen and clear error.

**Paid-add-on conclusion:** `state` passes, but this does not offset the blocking OIDC failures
recorded below.

## 3. Observe paid add-on token exchange and identity consumption

- [x] Record whether token exchange uses HTTP Basic, request-body credentials or another client
  authentication method.
- [x] Confirm token exchange occurs server-to-server and the client secret never reaches browser
  code or a URL.
- [x] Record whether FreeScout sends a PKCE `code_verifier`; the module initiates no PKCE flow and
  sends no verifier.
- [x] Determine whether FreeScout consumes an ID token, User Info, or both.
- [x] ID-token signing algorithm, issuer, audience and `nonce` checks are not applicable because
  this configuration consumes only User Info.
- [x] Record the minimum User Info claims required for a successful login.
- [x] Confirm an expired or invalid access token is rejected.
- [x] ID-token issuer, audience and signature rejection are not applicable because no ID token is
  consumed.
- [x] Inspect application logs and confirm codes, tokens and client secrets are absent.

**Evidence:** redacted HTTP shapes, accepted claim matrix and relevant FreeScout configuration.

## 3A. Prove the custom OIDC client

Repeat the login tests with the paid add-on disabled and the custom Rondo Integration module
responsible for the entire relying-party flow.

- [ ] Discover the configured Rondo issuer, authorization, token, UserInfo and JWKS endpoints.
- [ ] Generate and validate a fresh session-bound `state` for each attempt.
- [ ] Send PKCE `code_challenge_method=S256` and the matching token-request verifier.
- [ ] Generate a fresh session-bound `nonce` and reject an ID token with a missing or changed nonce.
- [ ] Authenticate the confidential client with `client_secret_basic`.
- [ ] Validate the ID-token signature, exact issuer, audience, `azp` when present, expiry, issued-at
  time and `at_hash` when present.
- [ ] Call UserInfo and reject a response whose `sub` differs from the ID-token `sub`.
- [ ] Reject missing `sub`, a missing email, or `email_verified` other than boolean `true`.
- [ ] Clear state, nonce, verifier, code and token material after every success or terminal failure.
- [ ] Confirm the login creates only the intended FreeScout session after binding succeeds.
- [ ] Confirm a denial or callback failure returns once to `/login?rondo_oauth=0` without a new
  authorization request.
- [ ] Confirm clearing `RONDO_FORCE_OAUTH_LOGIN` restores local login without Rondo.
- [ ] Inspect logs and confirm codes, tokens, secrets and complete claims are absent.

**Blocking failure:** any missing state, PKCE, nonce, ID-token, UserInfo-subject or non-looping
recovery control prevents the custom module from shipping.

## 4. Test paid add-on user matching and creation

Keep automatic user creation disabled for the first five tests.

- [x] A unique verified email maps to the correct existing FreeScout agent.
- [x] Email-case differences do not create a duplicate user.
- [x] Confirm and record that the paid module alone ignores `sub` and `email_verified` when matching
  an existing user by email.
- [x] With the proof Rondo Integration module enabled, a missing `sub` or `email_verified` other
  than boolean `true` is rejected before FreeScout login.
- [x] The first successful verified login creates a one-to-one subject-to-FreeScout-user binding.
- [x] A later login with the same subject resolves the bound user even after an email change.
- [x] A different subject with the same email cannot take over an existing bound user.
- [x] A subject already bound to another user cannot be rebound through OAuth login.
- [ ] Only the documented administrator recovery flow can unlink or replace a binding, and the
  change is audited.
- [ ] Confirm migrations create unique constraints for both active FreeScout user ID and
  issuer/subject identity fingerprint while retired identities remain reserved.
- [ ] Race two first-link callbacks for one subject and two users; only one final pair may commit.
- [ ] Race two subjects for one user; only one final pair may commit.
- [ ] Force a binding insert or audit failure and confirm the transaction leaves no partial binding
  and creates no FreeScout session.
- [ ] Confirm a disabled or recovery-pending binding cannot use ordinary email-based first link.
- [ ] Require administrator permission, recent local-password confirmation and a reason before
  disabling or replacing a binding.
- [ ] Confirm disabling invalidates the target's active sessions and remember tokens.
- [ ] Confirm replacement issues one hashed, single-use recovery token that expires after 10
  minutes and never accepts an administrator-typed subject.
- [ ] Complete the recovery through full Rondo OIDC validation; atomically retire the old identity,
  bind the new unbound subject, consume the recovery and write the audit event.
- [ ] Confirm an expired, reused, invalid or competing recovery leaves the target disabled and the
  previous/new bindings unchanged.
- [ ] Confirm binding logs and audit rows contain only shortened fingerprints, not raw subjects,
  tokens or claims.
- [x] FreeScout's unique user-email index prevents duplicate/ambiguous stored emails; an uppercase
  first-link test selected the one existing lowercase user and created no duplicate.
- [x] An identity without an existing FreeScout user is denied cleanly.
- [x] Record that OAuth Login `1.0.28` does not enforce `email_verified` without the Rondo identity
  guard.
- [x] Confirm Rondo's `is_user_approved()` compatibility method proves only user existence and does
  not justify `email_verified: true`.
- [ ] Confirm existing users receive no verification marker through blanket migration or
  administrator provisioning.
- [ ] Consume each approved emailed proof path and confirm it records the exact normalized email,
  verification time and method.
- [ ] Confirm password login, role/capability assignment, linked-person status and merely sending an
  email do not record verification.
- [ ] Start FreeScout authorization without a marker; confirm Rondo pauses it and sends a
  rate-limited, hashed, single-use verification link containing no OAuth parameters.
- [ ] Consume the link and confirm Rondo rechecks user, client, authorization request, address and
  uniqueness before resuming consent.
- [ ] Change the resolved address and confirm the old marker no longer yields
  `email_verified: true` until the new address is verified.
- [ ] Confirm a synthetic or shared address cannot be verified for the FreeScout client.

In the isolated environment only, enable automatic user creation and observe:

- [x] the default FreeScout role and permissions;
- [x] whether the user starts with zero mailboxes;
- [x] which login/user-created events fire and in what order;
- [x] whether a provisioning listener can run before the user reaches a conversation;
- [x] duplicate behavior when the same identity logs in again.

Return automatic creation to disabled after the tests.

Repeat creation with the custom Rondo Integration module:

- [ ] Confirm automatic creation defaults off and cannot be enabled before the base URL, local
  mailbox mappings, break-glass administrator and `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true` pass.
- [ ] With an unknown verified subject and current non-empty mapped access, create one ordinary
  OIDC-only user with the exact verified email and no usable local password.
- [ ] Confirm the user, subject binding, managed-user marker, initial mailbox relationships and
  audit events commit in one transaction before session creation.
- [ ] Confirm user-creation hooks send no welcome/password email and perform no external side effect
  before commit.
- [ ] Force failure at user, binding, managed marker, mailbox pivot and audit writes; every case
  leaves no partial row, relationship or authenticated session.
- [ ] Return unavailable, inactive, empty, invalid and unknown-key access responses; each creates
  nothing and shows a safe error.
- [ ] Confirm a Rondo administrator is created only as an ordinary FreeScout agent and is never
  auto-promoted.
- [ ] Confirm password login, password reset and remember-token creation are blocked for the
  Rondo-created account.
- [ ] Repeat and race the callback; every success resolves the same FreeScout user ID and no
  duplicate email, binding or mailbox relationship is created.
- [ ] Send a synthetic reply and confirm FreeScout records the created user as its author.
- [ ] Revoke the last managed mailbox: with no manual mailbox, confirm the account deactivates,
  sessions end and historical reply attribution remains.
- [ ] Restore mapped access and confirm the same bound account reactivates.
- [ ] Add a manual mailbox, revoke managed access and confirm the manual relationship and account
  status remain unchanged while OIDC-only login rules still apply.

**Blocking failure:** ambiguous or unverified identity can authenticate as an existing agent, a
subject binding can move through normal login, creation leaves partial state, or a new user session
exists before non-empty managed mailbox provisioning completes.

## 5. Prove the current-agent integration hook

Use a minimal test listener inside a proof build of the custom Rondo Integration module; do not
build the complete provisioning component during this spike.

- [x] Identify the event fired after a successful OAuth login for an existing user.
- [x] Identify the event fired after OAuth creates a new user.
- [x] Confirm the listener can read the authenticated FreeScout user ID and email server-side.
- [x] Confirm the listener can distinguish OAuth login from unrelated application events when
  necessary.
- [x] Confirm a temporary Rondo access-service failure can leave mailbox access unchanged while
  allowing a safe login result.
- [x] Confirm listener failure is logged without exposing tokens or blocking all local admin access.

**Gate:** record the exact event/class names and execution order needed by the production Rondo
Integration module.

## 6. Prove managed mailbox reconciliation

Use synthetic mailboxes and a disposable proof listener or console test.

- [x] Attach the test mailbox without replacing the user's complete mailbox collection.
- [x] Detach only a relationship previously recorded as integration-managed.
- [x] Preserve an unrelated manually assigned mailbox during grant and revoke.
- [x] Preserve a manually assigned instance of the mapped mailbox when it was never recorded as
  integration-managed.
- [x] Repeating the same desired state is idempotent.
- [x] A FreeScout administrator is never downgraded or detached.
- [x] A user with zero mailboxes cannot view test customers or conversations.
- [x] Record the FreeScout models/events needed for safe grant, revoke and audit.
- [ ] Configure a stable key to a selected local numeric mailbox ID and persist the displayed
  name/address verification snapshot.
- [ ] Rename or change the address of that mailbox; confirm drift is shown and no alternate mailbox
  is selected automatically.
- [ ] Delete, disable or replace the configured mailbox; confirm new grants stop, unrelated manual
  access remains and neither name nor address is used as a fallback lookup.
- [ ] Change the configured ID only through authenticated administrator selection, explicit
  name/address confirmation and an audit event.
- [ ] Confirm the mapping UI lists only stable keys advertised by the verified Rondo installation
  and local `Mailbox::isActive()` choices; no free-form key/capability/ID input exists.
- [ ] Validate the signed configuration response schema and reject unknown/duplicate keys, unknown
  policy versions, stale signatures and unsigned responses.
- [ ] Confirm the first-release signed catalog exposes only `ledenadministratie`; attempts to use
  the documented but unreleased `fairplay` or `contributie` keys are rejected.
- [ ] Confirm role names, committee/team membership, arbitrary WordPress capabilities and
  `financieel_read` cannot be submitted or interpreted as mailbox grants.
- [ ] In a later candidate spike, confirm one user with both approved effective capabilities can
  receive both managed mailboxes independently without replacing either relation.
- [ ] Verify a draft mapping and confirm it stores key, ID, name/address and policy-version
  snapshots without changing any user-mailbox relation.
- [ ] Activate after aggregate dry run and recent local-password confirmation; confirm displayed
  grant/unchanged/manual/failure counts equal the reconciliation result.
- [ ] Pause and confirm all current managed relations remain while no new grants or revocations run.
- [ ] Disable and revoke after impact preview; confirm the state remains Disabling until every
  managed relation is removed and manual relations remain.
- [ ] Change to a second active synthetic mailbox and confirm only managed relations move; retries
  are idempotent and the old ID remains auditable until its managed count is zero.
- [ ] Confirm duplicate stable-key and duplicate active-mailbox mappings are rejected.
- [ ] Configure an environment-locked mapping and confirm the UI shows its source but cannot
  override its locked fields.
- [ ] Supply malformed JSON, unknown keys and duplicate/non-positive IDs through
  `RONDO_MANAGED_MAILBOX_MAPPINGS`; activation must stop without changing last confirmed state.
- [ ] Confirm activate, change and disable reject missing CSRF, non-admin and stale/missing local
  password confirmation.
- [ ] Keep a session open while revoking one of multiple mailboxes; confirm the next protected
  request to the removed mailbox/customer/conversation is denied while the other mailbox works.
- [ ] Revoke the final mailbox and confirm all active sessions and remember tokens are invalidated.
- [ ] Confirm a Rondo-created zero-mailbox user deactivates, while a manually created user keeps its
  administrator-controlled account status.
- [ ] Preserve a manual mailbox and confirm neither it nor the active session is removed by managed
  mailbox revocation.
- [ ] Disable or replace a subject binding and confirm sessions end even when a mailbox remains.
- [ ] Restore mapped access and confirm the same Rondo-created user reactivates before login.
- [ ] Force session-invalidation failure and confirm mailbox authorization stays revoked, the error
  is audited and invalidation is retried.
- [ ] Miss a push event and confirm hourly repair revokes access; restore connectivity and confirm
  reconciliation runs immediately without claiming a hard deadline during the outage.
- [ ] Stop cron and restart the application after enqueueing a capability event; confirm the
  private `rondo_integration_event` post survives and is delivered when processing resumes.
- [ ] Replay the same event UUID and confirm FreeScout performs one idempotent reconciliation.
- [ ] Confirm successful or authoritatively reconciled queue posts are deleted only after an
  audit-safe result, while repeatedly failing posts remain visible as unresolved failures.
- [ ] Confirm transients are used only for short-lived locks and no queued subject depends on a
  transient for durability.
- [ ] Confirm managed provisioning cannot activate without a valid 90–730 day retention value,
  primary operational owner and escalation owner selection.
- [ ] Set retention to its `90`, `365` and `730` day boundaries; reject out-of-range, malformed and
  unsigned values without shortening the last verified policy.
- [ ] Set `RONDO_AUDIT_RETENTION_DAYS` on the Rondo server and confirm it overrides the Rondo option,
  is signed with source `environment`, and locks the displayed Rondo value; invalid environment
  values block configuration instead of falling back.
- [ ] Confirm FreeScout has no local retention override, consumes only the signed Rondo effective
  value/source, and an owner change requires recent local-password confirmation plus its own audit
  event.
- [ ] Run daily pruning with eligible, too-new and unresolved security/access events; only eligible
  rows are removed, bounded batches complete idempotently and aggregate deletion counts are kept.
- [ ] Resolve an excluded failure and confirm its retention clock restarts at closure.
- [ ] Confirm immediate critical/high notifications and the normal daily digest contain no names,
  emails, target IDs, subject fingerprints or payload fragments.
- [ ] Confirm escalation occurs after three failed runs or 24 hours and the health screen shows
  severity counts, oldest-open age, owners, retention, reconciliation and prune timestamps.

**Blocking failure:** safe reconciliation requires replacing all mailbox relationships, cannot
distinguish managed access from manual access, or a revoked mailbox remains usable through an
existing session.

## 7. Record paid add-on Force OAuth Login and recovery behavior

Enable Force OAuth Login only after the local administrator session and recovery path are ready.

- [x] Normal login redirects to the configured Rondo/test provider.
- [x] Provider denial returns a clear error without creating or changing a user.
- [x] Provider outage produces a clear failure and does not loop indefinitely.
- [x] The documented server-side setting disables Force OAuth Login without OAuth access.
- [x] After disabling it, the break-glass administrator can sign in locally.
- [x] Re-enabling Force OAuth Login does not invalidate the recovery account.
- [x] Record whether an already authenticated FreeScout session continues during provider outage.

**Mitigation proof:** the unmodified paid module loops after provider denial. The disposable Rondo
Integration module now intercepts only a failed `oauthlogin.callback` redirect to the login route
and changes it to `/login?oauth=0`. The retest made one authorization request, rendered the local
form and preserved the visible denial message. No user, role, password or subject binding changed.
The normal forced-login regression test also passed.

**Recovery proof:** `/login?oauth=0` bypasses forcing immediately. Clearing
`OAUTHLOGIN_FORCE_OAUTH_LOGIN` server-side and restarting FreeScout also restores local login; the
same break-glass administrator remained usable after re-enabling and disabling the setting.

These paths belong to the rejected add-on and are not production requirements. The custom module
must repeat the denial, outage and recovery tests using `/login?rondo_oauth=0` and
`RONDO_FORCE_OAUTH_LOGIN`.

## 8. Check FreeScout mobile behavior

- [ ] Verify the responsive browser UI supports the complete login, conversation and sidebar flow
  at the pilot's mobile browser widths.
- [ ] Observe whether the official native mobile client supports the custom OAuth flow and record
  its callback/deep-link and sidebar behavior as informational evidence.
- [ ] If the native client attempts OAuth, confirm failure leaves no usable code, token or session
  identifier in browser history; otherwise mark the native-client checks not applicable.
- [ ] Document unsupported native-client behavior without advertising native mobile support.

Version one supports the responsive browser UI only. Native-client incompatibility does not block a
GO decision unless it exposes a security issue or also breaks the required browser flow.

## 9. Prove sidebar authorization context

Use a minimal proof route in the custom Rondo Integration module, informed by the selected upstream
reference commit.

- [ ] Add authentication middleware to the sidebar AJAX route.
- [ ] Confirm an unauthenticated request is rejected before webhook dispatch.
- [ ] Confirm the server can read the current agent through FreeScout authentication.
- [ ] Confirm FreeScout's normal conversation policy denies an unauthorized agent.
- [ ] Confirm the authorized conversation's reloaded local mailbox ID resolves to its active,
  verified stable key and a missing, inactive or drifted mapping is rejected.
- [ ] Confirm customer identity is reloaded from the authorized conversation, not trusted from
  browser parameters.
- [ ] Capture the proposed signed payload and verify it contains the local FreeScout user ID, exact
  bound issuer/subject, stable mailbox key, conversation/customer IDs and one deduplicated current
  customer-email array.
- [ ] Confirm the payload contains no agent email, numeric mailbox ID, singular customer email,
  customer phone or browser-supplied identity value.
- [ ] Revoke the bound subject or change visible person data between two requests and confirm the
  second result is freshly authorized and rendered; no matched-person payload or sidebar HTML cache
  may serve the first result.
- [ ] Confirm no secret is included in the browser request or rendered page.

**Blocking failure:** the server cannot reliably identify and authorize the current agent before
sending Rondo data.

## 10. Prove response isolation and layout

Test at narrow, normal and wide FreeScout sidebar widths.

- [ ] Render sanitized, script-free Rondo markup through the module-owned sandboxed
  `iframe.srcdoc` shell.
- [ ] Run only the nonce-authorized module resize script; confirm returned scripts, nonce
  attributes and inline event handlers are removed and cannot execute.
- [ ] Confirm hostile returned CSS cannot affect the FreeScout parent page or make an external
  request.
- [ ] Confirm the sandbox has no `allow-same-origin`, form, download or top-navigation capability
  and cannot read FreeScout cookies, DOM or browser storage.
- [ ] Confirm server-generated, allowlisted Rondo links open in a new tab with `noopener` behavior.
- [ ] Confirm non-Rondo and malformed links are removed or inert.
- [ ] Confirm the resize observer sends only the versioned message type, per-render channel and
  finite integer height to the exact FreeScout parent origin.
- [ ] Confirm the parent ignores messages from the wrong window, type or channel and rejects
  missing, non-numeric, negative and oversized heights.
- [ ] Confirm accepted height changes are debounced and clamped between `160px` and `1600px`.
- [ ] Confirm expanding and collapsing an accordion updates iframe height without exposing markup,
  data, URLs or actions through the bridge.
- [ ] Confirm a missing or invalid resize message keeps the safe `480px` default with internal
  scrolling.
- [ ] Confirm multiple panels/accordions fit without covering FreeScout controls.
- [ ] Confirm loading, no-match, ambiguous, unauthorized and unavailable states fit cleanly.
- [ ] Confirm keyboard navigation and visible focus for links and refresh control.
- [ ] Confirm zoom at 200% remains usable without horizontal page scrolling.

**Blocking failure:** returned Rondo code can execute, iframe content can reach FreeScout state,
the parent accepts anything beyond a validated height, or dynamic content cannot remain usable
within the bounded iframe height.

## 10A. Prove the Ledenadministratie field contract

Use synthetic person records containing every included and excluded field. Capture only redacted
rendered evidence and response-key inventories.

- [ ] Confirm the summary derives status from `former_member` and `lid_tot`, transfer state from
  `wacht_op_overschrijving`, and current teams only from current `work_history` rows.
- [ ] Confirm Membership shows only the approved birthdate/age, age group and membership dates.
- [ ] Confirm Contact shows populated canonical email, mobile, telephone and labelled address
  values, with Home first and no placeholder rows.
- [ ] Confirm `is_deceased` disables communication links and shows Do not contact without returning
  `datum_overlijden`.
- [ ] Confirm Household is capped at six direct relationships, does not recurse, contains no
  contact/birthdate fields and independently omits inaccessible people.
- [ ] Confirm Process contains member-onboarding state, account-linked boolean/welcome time and the
  client-safe pass type/platform availability without user IDs, wallet URLs or role choices.
- [ ] Confirm Open tasks contains only open/awaiting tasks created by or assigned to the current
  agent, capped at three, with no notes, Lettermint or assignee data.
- [ ] Confirm person, task and Sportlink links are server-generated, stay on allowlisted origins and
  disappear when their identifier is absent.
- [ ] Confirm empty fields and groups are omitted, generation time is labelled Live from Rondo and
  no Sportlink sync timestamp is claimed.
- [ ] Confirm the response has no finance/Nikki, VOG, sponsor, discipline, private-note, arbitrary
  custom-field, `freescout_id`, raw-user-ID, image or historical-work-row keys.
- [ ] Confirm expanding every populated group remains usable at `280`, `360` and `420` pixels and
  at 200% zoom.

**Blocking failure:** a prohibited key leaves Rondo, a related person or task bypasses its own
visibility rule, a missing value is guessed, or the output cannot fit the bounded sidebar.

## 10B. Prove controlled appearance and width overrides

Run these tests with the unsupported Design module disabled. Use only the Rondo Integration
module's allowlisted settings and stylesheet.

- [ ] Record the unmodified header, active mailbox row, conversation toolbar, links, icons and
  `280px` desktop customer-sidebar baseline.
- [ ] Confirm FreeScout's existing header-color setting remains authoritative.
- [ ] Change the interface accent and confirm only audited links, actionable icons, active mailbox
  text and focus indicators change.
- [ ] Change the interface accent surface and confirm only audited selected/highlighted backgrounds
  change.
- [ ] Configure AWC's production pair `#006935` and `#CCE1D7`; confirm the actual rendered roles
  match the approved palette and preserve the recorded `6.84:1`, `4.99:1` and `9.22:1` contrast
  boundaries.
- [ ] Apply two different club color pairs to the same module build without changing source or
  assets; confirm an unconfigured installation retains FreeScout's native blue accents.
- [ ] Confirm success, warning, destructive, unread and availability colors remain semantic.
- [ ] Reject invalid hexadecimal values and insufficient-contrast color pairs.
- [ ] Confirm no setting accepts CSS, selectors, HTML or an external stylesheet URL.
- [ ] Confirm the isolated sidebar receives the semantic colors without reading parent-page styles.
- [ ] Test maximum customer-sidebar widths `280`, `360` and `420` pixels.
- [ ] At each width, confirm `#conv-layout-customer`, `#conv-layout-header` and
  `#conv-layout-main` reserve the same effective width without overlap.
- [ ] With AWC maximum `360`, confirm effective sidebar widths are `280`, `320`, `360` and `360px`
  at CSS viewports `1101`, `1280`, `1440` and `1920px` respectively.
- [ ] Test at viewport widths `1101`, `1280`, `1440` and `1920` pixels and at 200% zoom.
- [ ] At `1100px` and below, including the reduced CSS viewport at 200% zoom, confirm FreeScout's
  full-width stacked customer layout remains active and no horizontal mock scaling/scrolling occurs.
- [ ] Disable appearance overrides and confirm the core colors and `280px` desktop sidebar return
  without stale inline styles or modified core files.

**Blocking failure:** the override obscures conversation controls, changes semantic status colors,
permits arbitrary CSS, breaks the core responsive layout or survives after being disabled.

## 10C. Prove the module release supply chain

- [ ] Create the public `RondoHQ/freescout-rondo-integration` repository with
  `AGPL-3.0-only`, `THIRD_PARTY_NOTICES.md`, no installation-specific data and protected `main`.
- [ ] Confirm the ruleset rejects direct/force pushes and deletion and requires one maintainer
  approval, resolved threads, CODEOWNERS and all required CI checks.
- [ ] Enable GitHub immutable releases before creating `v1.0.0`; confirm release settings through
  the GitHub API and record the result.
- [ ] Confirm pull-request workflows use pinned actions, least-privilege read permissions and have
  no release environment or write token.
- [ ] Attempt release from a non-main commit, mismatched version/changelog, existing tag, failed
  check and unapproved environment; each must stop before a tag or release is published.
- [ ] Build a draft with exactly `module.json`, `rondo-integration.zip`, `SHA256SUMS` and
  `rondo-integration.spdx.json`; verify the ZIP has one `RondoIntegration/` root and no development,
  repository, environment or secret files.
- [ ] Test the draft ZIP against pinned FreeScout `1.8.238`, verify checksum/provenance and publish
  it once through the protected `release` environment.
- [ ] Confirm the published release and its assets are immutable; replacement or tag movement is
  rejected and a correction requires a new patch version.
- [ ] Confirm the fixed `/releases/latest/download/` manifest and ZIP URLs resolve to the same
  stable tag while a prerelease never becomes latest.
- [ ] Preflight an exact `--version=vX.Y.Z` and `--sha256=<64-hex>` without changing the installed
  module, then approve and install those same values using only tagged URLs.
- [ ] Publish a newer release after preflight and confirm it does not change the approved target;
  reject a missing version/SHA or any unresolved `latest` install target.
- [ ] Confirm the wrapper aborts before extraction on a version, checksum, provenance or
  archive-layout mismatch.
- [ ] Confirm publishing a release changes no FreeScout installation; non-production and production
  updates each require separate operator action.

**Blocking failure:** an unreviewed commit can publish, a release asset/tag remains mutable, the
archive or checksum is ambiguous, or publication can update production automatically.

## 11. Test timeout and failure containment

Use only the non-production endpoint and the expected pilot concurrency.

- [ ] DNS failure reaches the quiet unavailable state within the total timeout.
- [ ] Connection refusal reaches it within the total timeout.
- [ ] TLS/certificate failure reaches it within the total timeout.
- [ ] A server that accepts but never responds is stopped by the total timeout.
- [ ] A redirect is rejected.
- [ ] A response over 256 KiB is rejected.
- [ ] An unexpected content type is rejected.
- [x] A 4xx/5xx response is not retried automatically.
- [ ] FreeScout conversation controls remain interactive while the sidebar request runs.
- [ ] Repeated failures at expected pilot concurrency do not exhaust available PHP workers.
- [ ] Manual refresh is rate limited and cannot create an unbounded request storm.
- [ ] Confirm or adjust the proposed 2-second connection and 5-second total timeout using evidence.
- [ ] Change the configured Rondo base URL and confirm all Rondo-bound requests use only the new
  verified origin and path prefix.
- [ ] Confirm missing or invalid configuration disables integration requests without affecting
  normal FreeScout work.
- [ ] Confirm a redirect to another origin is rejected.

**Blocking failure:** a failed Rondo endpoint can make normal FreeScout conversation work
unavailable.

## 11A. Prove conversation activity delivery

Use synthetic conversations only; do not include real message content in fixtures or evidence.

- [ ] Subscribe to and record transaction timing for inbound `CustomerCreatedConversation` and
  agent-created `UserCreatedConversation`; both must reach Rondo Integration reliably.
- [ ] Subscribe to `ConversationCustomerChanged` and confirm it identifies the same stable
  conversation plus the new current customer.
- [ ] Deliver a signed minimal event containing only instance, conversation/customer identifiers,
  mailbox key, plain-text subject, creation time and all current normalized customer emails.
- [ ] Confirm Rondo compares those emails only with `email_1` and `email_2`, deduplicates by person
  ID and accepts exactly one integration-scope match, including a former member.
- [ ] Confirm two people sharing one address, or two customer addresses resolving to different
  people, returns only `ambiguous` and creates no visible activity.
- [ ] Confirm Rondo rejects synthetic/malformed addresses and ignores names, phones, Rondo/KNVB/
  FreeScout IDs, sidebar results and old SQLite mappings as matching inputs.
- [ ] Confirm Rondo creates one native `rondo_activity` comment with instance/conversation comment
  meta and no custom table.
- [ ] Replay and race the same event; exactly one activity may exist for the instance-and-
  conversation pair.
- [ ] Confirm the stored/rendered activity contains only escaped subject, creation date/time and a
  server-generated allowlisted FreeScout link.
- [ ] Confirm message bodies/previews, replies, recipients, attachments, customer payloads and
  agent identity are absent from request, storage and logs.
- [ ] Miss an event and prove the bounded repair path creates the pointer once after matching;
  unmatched or ambiguous events store only IDs/reason, reload current emails from FreeScout and
  remain retryable without attaching to a guessed person.
- [ ] Edit customer or Rondo emails and confirm a pending event may resolve while an approved
  historical pointer does not move automatically.
- [ ] Change the conversation's FreeScout customer: a unique new match moves the existing pointer;
  no unique match retains the same comment in non-approved pending state; repair restores or moves
  that comment through WordPress APIs without duplication.
- [ ] Confirm historical batch-created pointers survive cutover and rollback does not duplicate
  conversations already represented in Rondo.

**Blocking failure:** no reliable creation/repair signal exists, delivery can duplicate or attach
to a guessed person, or FreeScout message content leaves FreeScout.

## 12. Produce the compatibility matrix

Complete one row for every material behavior.

| Area | Observed behavior | PRD requirement | Result | Evidence | Decision/workaround |
|---|---|---|---|---|---|
| OAuth state | Paid add-on sends a fresh 40-character value and rejects missing or changed state | Custom module generates and validates fresh session-bound state | Retest required | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Reimplement and test in the custom client |
| PKCE S256 | Paid add-on sends no challenge or verifier | Required for every custom-client login | **Fail** | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Reject the paid add-on; custom client must use S256 |
| OIDC nonce | Paid add-on sends no nonce and consumes no ID token | Required and bound to the validated ID token | **Fail** | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Reject the paid add-on; custom client must validate nonce |
| Token client auth | Paid add-on uses server-side `client_secret_post` | Custom client uses `client_secret_basic` with PKCE | **Fail** | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Reject the paid add-on; implement the required method |
| ID token/UserInfo | Paid add-on consumes UserInfo without an ID token | Validate signed ID token and require matching UserInfo `sub` | **Fail** | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Reject the paid add-on; implement full OIDC validation |
| Existing-user match | Guard requires a unique verified email for first link; case difference created no duplicate | Unique verified email only | Pass | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Prove this path independently before enabling guarded creation |
| Rondo email proof | `is_user_approved()` checks only user existence; emailed activation/login/change flows have no durable account-wide OIDC marker | Exact current unique address must have a durable marker from consumed emailed proof | **Fail** | [`is_user_approved()` test](../../tests/Wpunit/WorkspacePermissionsTest.php), [activation service](../../includes/class-activation-service.php) and [profile service](../../includes/class-member-profile-service.php) | Add verification meta and resumable authorization; no historical backfill |
| Subject binding | Bound subject wins after email change or conflicting email; administrator recovery and concurrency remain untested | Database-enforced one-to-one binding with atomic first link and single-use administrator recovery | Provisional pass | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Prove migrations, races, rollback, session invalidation and recovery before release |
| Rondo base URL | | Configured, verified and not hardcoded | | | |
| Automatic creation | Paid add-on defaults on and isolated creation produced an ordinary zero-mailbox user before the proof listener ran | Guarded custom creation atomically commits an ordinary OIDC-only user, binding, non-empty mapped access and audit before login | Custom proof required | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Default off; enable only after atomic creation, rollback, attribution and lifecycle tests pass |
| Login event | Laravel `Login` fires for existing and newly created OAuth users before redirect | Current agent available | Pass | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Use the login event for binding and provisioning |
| Managed mailbox access | Targeted pivot attach/detach works; customer routes require the core visibility flag; production API resolves key `ledenadministratie` to mailbox ID `18`, name `Ledenadministratie`, address `ledenadministratie@svawc.nl` | Manual access preserved; local ID is verified configuration with no name/address fallback | Provisional pass | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) and [production mailbox mapping evidence](evidence/freescout-mailbox-mapping-2026-09-01.md) | Prove local enabled-state and mapping-drift gates; never use `sync()` |
| Mapping settings | No production module UI exists yet | Listed Rondo keys plus local active-mailbox selector, verification/dry run, explicit state transitions and protected mass-impact actions | Custom proof required | PRD contract | Build inside Rondo Integration; no free-form identifiers or silent retargeting |
| Audit retention and ownership | No production module policy exists yet | Rondo-signed effective 90–730 days; Rondo-server environment overrides Rondo option, then default 365; FreeScout has no override; unresolved security/access failures excluded until closure; primary and escalation administrators required | Custom proof required | PRD contract | Prove precedence, signed value/source, invalid-environment blocking, pruning, notification redaction and escalation |
| Provisioning event queue | No production queue exists yet | Private WordPress post queue, at-least-once delivery, UUID idempotency, bounded cron worker and visible unresolved failures; transients only for locks | Custom proof required | PRD contract | Prove restart durability, replay, terminal deletion and failure visibility |
| Module release supply chain | Proposed RondoHQ repository did not exist on 2026-09-01; FreeScout core updater does not pre-verify the third-party ZIP checksum | Public AGPL repository, protected main, immutable human-approved releases, tagged checksummed artifacts and no automatic production rollout | Custom proof required | [Module repository decision](evidence/freescout-module-release-repository-2026-09-01.md) | Create during Phase 2 and prove every repository, packaging, provenance and update gate before `v1.0.0` |
| Session revocation | Zero-mailbox customer/conversation denial is proven; conditional logout, binding recovery and invalidation failure remain untested | Next-request mailbox denial; logout for zero-mailbox or identity change; manual access preserved | Custom proof required | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Prove session/remember-token invalidation and retry without restoring revoked pivots |
| Force-login recovery | Paid add-on loops without a response filter; its patched `/login?oauth=0` paths work | Custom callback fails once to `/login?rondo_oauth=0`; server setting restores local login | Paid add-on **Fail**; custom retest required | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Implement recovery directly in Rondo Integration |
| Mobile | Not yet observed | Responsive browser UI is required; native FreeScout client support is outside version one | Browser retest required; native observation informational | PRD contract | Document native behavior without a support claim; native incompatibility does not block browser pilot unless it exposes a security issue |
| Sidebar authorization | | Agent and conversation authorized | | | |
| Ledenadministratie fields | Canonical field registry, person formatters and task visibility support the approved bounded view; no canonical Sportlink-sync timestamp exists | Exact fixed contract with independent related-person/task checks and prohibited-key filtering | Custom proof required | Source inspection 2026-09-01 | Omit unknown values and source-sync claims; test response keys and rendered groups with synthetic records |
| Conversation activity | Daily batch creates one subject/link activity through customer-ID to KNVB-ID to Rondo-ID SQLite mappings; core exposes inbound, agent-created and customer-change events plus customer emails | Keep the pointer long-term; exact unique `email_1`/`email_2` match, idempotent module events and deterministic reassignment/repair | Custom proof required | Rondo Sync and FreeScout 1.8.238 source inspection 2026-09-01 | Keep batch during rollout; never use IDs/names/phones to select a person or copy message content |
| Response isolation | | Opaque-origin iframe with a nonce-authorized, height-only resize bridge and no parent-page code execution | | | |
| Appearance controls | Design module disabled; core blue accent roles visible | Two semantic color settings, allowlisted selectors and no arbitrary CSS | Retest required | Post-removal screenshots reviewed 2026-09-01; screenshots not stored because they contain member data | Prove against current FreeScout CSS before release |
| AWC appearance values | Official house style publishes dark green `#006935` and light green `#CCE1D7`; calculated contrast passes intended text/icon roles | Configure this pair only on AWC; keep the module default club-neutral | Planning pass; rendered retest required | [AWC appearance evidence](evidence/awc-freescout-colors-2026-09-01.md) | Provision as installation settings and verify against the supported FreeScout selectors |
| Customer-sidebar width | FreeScout core desktop width is `280px`; narrow layout becomes full width | Coordinated responsive width up to configured maximum, default `360px` | Retest required | Current FreeScout stylesheet inspection | Update customer width plus header/main spacing together |
| Failure containment | Access-service `503` allowed login in 1.5 seconds and preserved access | Conversation work remains available | Pass | [OAuth identity spike evidence](evidence/freescout-oauth-identity-spike-2026-08-31.md) | Keep prior state; record redacted error; no login-flow retry |

## Go/no-go decision

### Paid OAuth Login add-on decision

**Decision:** `NO-GO`, approved 2026-09-01. Do not ship or require the paid add-on.

It lacks PKCE, nonce and ID-token validation; matches users by email without enforcing `sub` or
`email_verified`; enables automatic creation by default; and its forced-login denial path loops
without a custom patch. Since a custom module is already required for the sidebar and provisioning,
Rondo Integration will own the complete OIDC relying-party flow instead of wrapping these gaps.

This is not the final compatibility-spike sign-off. The custom OIDC client, administrator binding
recovery, guarded creation, sidebar isolation, timeout proof and the remaining rows must pass
before overall `GO`.

### GO

Choose `GO` only when all blocking conditions pass, required evidence exists and no production
security exception is needed.

### CONDITIONAL GO

Choose `CONDITIONAL GO` only for a bounded, documented compatibility gap with:

- the exact risk;
- the temporary mitigation;
- an owner and deadline;
- explicit approval before implementation.

Missing `state` validation, unsafe identity matching, absent current-agent authorization, unsafe
mailbox reconciliation, uncontained worker exhaustion or an unrecoverable forced-login flow cannot
receive a conditional go.

### NO-GO

Choose `NO-GO` when a blocking failure remains or the evidence is insufficient. Record which
architecture assumption must change before repeating the spike.

## Required deliverables

- [ ] Completed version and environment record.
- [ ] Completed checklist with evidence links.
- [ ] Redacted OAuth request/response shapes.
- [x] Login and user-creation event map.
- [x] Managed mailbox model/event notes.
- [ ] Production mailbox `18` local existence/enabled-state and drift-control proof.
- [ ] Mailbox mapping settings, aggregate preview, state-machine and protected-action proof.
- [ ] Subject-binding migration, concurrency and administrator-recovery proof.
- [ ] Durable Rondo email-verification and authorization-resume proof.
- [ ] Guarded automatic-creation, reply-attribution and deactivation/reactivation proof.
- [ ] Conditional session-revocation and invalidation-retry proof.
- [ ] Sidebar authorization and isolation proof.
- [ ] Ledenadministratie field-contract and prohibited-key proof.
- [ ] Conversation activity event, idempotency, matching and repair proof.
- [ ] Appearance and customer-sidebar width compatibility proof.
- [ ] Timeout/failure results at expected pilot concurrency.
- [ ] Completed compatibility matrix.
- [x] Signed-off paid OAuth Login add-on `NO-GO` decision.
- [ ] Signed-off `GO`, `CONDITIONAL GO` or `NO-GO` decision.
