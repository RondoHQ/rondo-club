# FreeScout OAuth identity spike evidence

**Date:** 2026-08-31
**Environment:** disposable local FreeScout; synthetic identities only
**FreeScout:** 1.8.238
**OAuth Login:** 1.0.28
**PHP:** 8.5.9
**Overall spike status:** in progress

**Successor evidence:** the paid add-on remained rejected; the independent custom-client Phase 0
proof subsequently passed. See
[FreeScout custom OIDC Phase 0 evidence](freescout-custom-oidc-phase0-2026-09-01.md).

## Safe test configuration

- Force OAuth Login: disabled.
- Automatic user creation: disabled except during the isolated new-user test, then disabled again.
- OAuth debug logging: disabled.
- Mail delivery: log driver only.
- Provider, users and tokens: synthetic local test values.
- A local administrator password remained available as break glass.

## Observed authorization request

```json
{
  "method": "GET",
  "parameters": ["client_id", "response_type", "scope", "state", "redirect_uri"],
  "response_type": "code",
  "scope": "openid email profile",
  "state_present": true,
  "state_length": 40,
  "code_challenge_present": false,
  "nonce_present": false
}
```

FreeScout rejected callbacks with a missing or changed `state`. With Force OAuth Login disabled,
provider denial returned to a usable login screen with an authentication error.

## Observed token and User Info requests

The token exchange was a server-side `POST` with these form fields:

```text
client_id
client_secret
grant_type
code
redirect_uri
```

The module used `client_secret_post`, not HTTP Basic, and sent no PKCE `code_verifier`. It then made
a `GET` request to User Info with a bearer access token. With a User Info URL configured, the login
used the User Info response rather than an ID token.

## Paid-module identity result

OAuth Login `1.0.28` matched existing FreeScout users only by the returned email. It did not enforce
`sub` or `email_verified`. A synthetic response with a different subject, the administrator email
and `email_verified: false` signed in as that administrator before the proof guard was installed.

Automatic user creation defaults to enabled in this module version. The test explicitly disabled
it; an unknown synthetic email was then denied without creating a user.

## Rondo identity-guard proof

A disposable Rondo Integration proof module registered `oauthlogin.get_user_data` before the paid
module's email lookup and listened for `Illuminate\Auth\Events\Login`.

Observed sequence:

1. The previously accepted unverified alternate subject was rejected before login.
2. The verified normal subject selected the unique existing user by email on its first login.
3. The successful login event committed a bidirectional subject-to-user binding.
4. A different, verified subject presenting the same email was rejected before login.
5. The original bound subject successfully logged in again.

The proof persisted one synthetic binding in both directions and used a pending server-side session
value to distinguish the OAuth login event from unrelated logins. This proves the hook and event
shape are viable; the production persistence mechanism, recovery UI and concurrency behavior remain
to be designed and tested.

## Automatic user-creation result

Automatic creation was enabled only for one synthetic, verified, previously unknown Rondo
identity. OAuth Login created this FreeScout account:

| Property | Observed value |
|---|---|
| FreeScout role | `1`, equal to `App\User::ROLE_USER` |
| Status | `1`, active |
| Mailbox memberships | `0` |
| Visible application access | Dashboard only; no mailbox was available |
| Subject binding | New Rondo subject bound bidirectionally to the new FreeScout user ID |

Installed source and the proof listener established this order for the new-user callback:

1. `oauthlogin.get_user_data` validated the provider, `sub`, verified email and pending binding.
2. `oauthlogin.user_data` filtered the proposed FreeScout user data.
3. `App\User::create()` persisted the user and fired the normal Eloquent creation events.
4. `Auth::login($user)` fired `Illuminate\Auth\Events\Login` with the created user.
5. The proof listener read the user's server-side ID and email and committed the subject binding.
6. OAuth Login redirected to the application only after the listener returned.

The `Login` event is therefore the safer production provisioning trigger than a model-created
event: it covers both existing and newly created OAuth users, supplies the authenticated user, and
runs before the browser reaches the application. A second login with the same synthetic identity
returned to the same user ID; the email count remained one, mailbox count remained zero and the
binding remained unchanged.

Automatic user creation was disabled again and the synthetic provider was restored to its normal
administrator identity after the test.

## Managed mailbox reconciliation proof

The disposable Rondo Integration listener called a synthetic access service from
`Illuminate\Auth\Events\Login`. The access service returned the stable key
`ledenadministratie`; local configuration translated that key to FreeScout mailbox ID `1`, named
`Ledenadministratie test`. Mailbox ID `2`, `Algemeen test`, represented unrelated manual access.

Observed results for the ordinary synthetic user:

| Step | Actual mailbox IDs | Module-owned IDs | Reconciliation result |
|---|---|---|---|
| Before grant | `[2]` | `[]` | Unrelated manual mailbox only |
| First desired-state grant | `[1, 2]` | `[1]` | Attached `[1]`; mailbox `2` preserved |
| Repeated identical grant | `[1, 2]` | `[1]` | Attached `[]`; detached `[]` |
| Desired-state revoke | `[2]` | `[]` | Detached `[1]`; mailbox `2` preserved |
| Manually attach mapped mailbox, then reconcile revoked state | `[1, 2]` | `[]` | Attached `[]`; detached `[]` |

Grant used `App\User::mailboxes()->attach($mailboxId)` and then
`App\User::syncPersonalFolders(null)`. Revocation used `detach($mailboxId)` only for IDs recorded
in module-owned state. The proof never called `sync()` on the complete mailbox collection. The
relation is FreeScout's `App\User` to `App\Mailbox` `belongsToMany` relation through
`mailbox_user`; module-owned IDs and a redacted last-result audit were stored separately in
`App\Option` for the spike.

An administrator login with revoked desired state kept role `App\User::ROLE_ADMIN`, retained both
mailbox relations and recorded `skipped_admin` with no attachment changes.

### Customer-visibility prerequisite

FreeScout denied mailbox `1` and its synthetic conversation to a user with zero mailbox relations.
However, with FreeScout's default `APP_LIMIT_USER_CUSTOMER_VISIBILITY=false`, that same user could
open the synthetic customer's profile and edit form directly by customer ID. The profile exposed
the customer email and the edit route rendered editable customer fields.

FreeScout core already provides the required restriction. After persisting and verifying
`APP_LIMIT_USER_CUSTOMER_VISIBILITY=true`, the same zero-mailbox session received `Access denied`
for all three direct routes:

- customer conversation list;
- customer edit form;
- mailbox conversation.

This setting is therefore a mandatory deployment invariant, not an optional hardening preference.
With it enabled, the managed mailbox reconciliation proof passes. The disposable environment was
left with automatic creation disabled, revoked synthetic desired state and the administrator signed
in.

## Access-service failure containment

The ordinary synthetic user first received mailbox ID `1` as an integration-managed grant. The
synthetic Rondo access service then returned one `503` response during the next OAuth login.

Observed result:

- the browser reached the FreeScout dashboard in approximately 1.5 seconds;
- `Ledenadministratie test` remained visible and usable;
- the actual mailbox IDs remained `[1]`;
- the module-owned mailbox IDs remained `[1]`;
- no relationship was attached or detached;
- the module audit recorded `access_service_error` with empty `attached` and `detached` arrays;
- the login flow made one access request and did not retry the `503` response.

The redacted audit contained only the FreeScout user ID, reason code, empty change lists and
timestamp. The access-request evidence contained only method, failure mode, presence flags,
FreeScout user ID and content type. Neither record contained an OAuth token, subject, email,
signature or response body.

The administrator then signed in successfully while the access service still returned failures.
The listener's administrator guard made no mailbox or role change. After the synthetic service
recovered with revoked desired state, the next ordinary-user login removed mailbox ID `1` normally.
The environment was returned to revoked desired state, zero mailbox relations for the test user,
automatic creation disabled and the administrator signed in.

The production rule is: transport errors, non-2xx responses, invalid response schemas and subject
mismatches yield no desired-state decision. Login continues, existing mailbox and module-owned
state remain unchanged, one redacted retryable error is recorded, and the login request does not
retry automatically.

## Additional identity edge cases

The next identity pass used the same disposable provider and proof guard.

| Test | Observed result | Result |
|---|---|---|
| State freshness | Two independent login attempts each sent a 40-character state value; the values differed | Pass |
| Case-insensitive first link | An uppercase provider email selected one existing lowercase synthetic user, created no duplicate and committed the expected subject binding | Pass |
| Bound subject after email change | The original administrator subject with a different provider email still resolved FreeScout user ID `1` | Pass |
| Bound subject with another user's email | The subject already bound to user ID `2` still resolved user ID `2`, not administrator user ID `1` | Pass |
| Minimal User Info | `sub`, `email` and boolean `email_verified: true` succeeded without `name` or `picture` | Pass |
| Missing email | The guard returned no user data; FreeScout rendered the local error page and authenticated nobody | Pass |
| Invalid access token | A synthetic User Info `401 invalid_token` rendered the local error page and authenticated nobody | Pass |
| Log inspection | Authorization-code, access-token and configured-client-secret occurrence counts were all zero with OAuth debug disabled | Pass |

FreeScout's `users.email` schema has a unique index. The case test created one mailbox-free user
before login while automatic creation remained disabled, then verified that the OAuth login reused
that row. Cleanup removed its proof binding and used FreeScout's native `deleteUser()` workflow;
the environment returned to two active users.

The binding map remained one-to-one throughout the email-change and conflicting-email tests. This
proves that an ordinary OAuth login cannot move an existing binding. The administrator-only unlink,
replacement and audit flow remains to be designed and tested separately.

## Force OAuth Login and recovery proof

Force OAuth Login was enabled only in the disposable environment after preserving a local
administrator session and confirming the recovery account. The test then exercised normal login,
provider denial, provider outage and two recovery paths.

| Scenario | Observed result | Result |
|---|---|---|
| Normal forced login | FreeScout redirected to the provider and returned to the intended settings page in approximately 4.2 seconds | Pass |
| Provider denial before mitigation | The callback redirected to the bare login route, which immediately restarted OAuth; six consecutive authorization requests ended in `ERR_TOO_MANY_REDIRECTS` | **Fail** |
| Provider denial after mitigation | One authorization request returned to `/login?oauth=0` in approximately 1.9 seconds with the local form and `Authentication error: access_denied — Synthetic user denial` visible | Pass |
| Mutation across denial tests | User count, administrator role, password fingerprint and subject bindings remained unchanged | Pass |
| Provider outage for a new session | One redirect reached the provider's synthetic `503`; FreeScout did not loop | Pass |
| Existing authenticated session during outage | The administrator continued to use the mailbox | Pass |
| Direct break glass | `/login?oauth=0` displayed the local login form while Force OAuth Login was enabled, and the administrator signed in | Pass |
| Server-side recovery | Clearing `OAUTHLOGIN_FORCE_OAUTH_LOGIN` and restarting FreeScout restored the normal local login form without provider access | Pass |
| Recovery account after re-enable | Force OAuth Login was re-enabled, disabled again server-side and the same local administrator signed in; the password fingerprint was unchanged | Pass |

The denial loop comes from the paid module's callback error path: it stores a floating error and
redirects to the bare login route. The Force OAuth middleware sees that route and immediately
redirects to the provider again. The middleware skips forcing when the request contains
`oauth=0`, which makes `/login?oauth=0` a working immediate break-glass URL.

A disposable Rondo Integration proof hardened the paid module through
`middleware.web.custom_handle.response`. At priority `20`, after the paid module's priority `10`
filter, it changes the response only when all three conditions hold:

1. the request route is `oauthlogin.callback`;
2. the response is a redirect;
3. the redirect target path equals FreeScout's login-route path.

That response becomes `/login?oauth=0`. The existing floating error remains in the session and is
rendered on the local login page. Successful callback redirects are unchanged: the normal forced
login regression test completed in approximately 1.9 seconds and made one authorization request,
one token request and one User Info request.

This proves why the paid add-on is not acceptable without custom interception. The production
Rondo Integration module will instead own the callback and implement the non-looping failure path
directly; it will not depend on this filter or either paid-add-on recovery setting.

The environment was restored with the provider in normal mode, Force OAuth Login and automatic
creation disabled, the visibility restriction enabled and the local administrator signed in.

## Current decision

**Decision, 2026-09-01:** the paid OAuth Login add-on is `NO-GO` and will be removed. There is no
production compatibility exception.

The decisive gaps are the absence of PKCE, nonce and ID-token validation; email-only matching that
does not enforce `sub` or `email_verified`; automatic user creation being enabled by default; and
the forced-login denial loop. Wrapping those gaps would leave the custom Rondo Integration module
responsible for most of the security-critical login behavior while retaining a paid runtime
dependency.

The production Rondo Integration module will therefore implement authorization code flow with PKCE
S256, `state`, `nonce`, `client_secret_basic`, signed ID-token validation, UserInfo subject matching,
one-to-one subject binding, and its own non-looping recovery path. The paid-module results above are
retained as rejection evidence and regression-test cases only. The custom Phase 0 proof subsequently
repeated the identity-binding and recovery shape; production persistence and managed mailbox
reconciliation remain compatibility gates.

Standards basis: [RFC 9700 section 2.1.1](https://www.rfc-editor.org/rfc/rfc9700.html#section-2.1.1),
[RFC 6749 section 2.3.1](https://www.rfc-editor.org/rfc/rfc6749.html#section-2.3.1) and
[OpenID Connect Core token/UserInfo validation](https://openid.net/specs/openid-connect-core-1_0-18.html#TokenResponseValidation).
