# FreeScout OAuth identity spike evidence

**Date:** 2026-08-31
**Environment:** disposable local FreeScout; synthetic identities only
**FreeScout:** 1.8.238
**OAuth Login:** 1.0.28
**PHP:** 8.5.9
**Overall spike status:** in progress

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

FreeScout rejected callbacks with a missing or changed `state`. Provider denial returned to a
usable login screen with an authentication error.

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

## Current decision

The paid module remains usable only with the Rondo identity guard, one-to-one subject binding and
`APP_LIMIT_USER_CUSTOMER_VISIBILITY=true`. Identity binding and managed mailbox reconciliation are
provisional passes; the complete compatibility spike remains in progress.
