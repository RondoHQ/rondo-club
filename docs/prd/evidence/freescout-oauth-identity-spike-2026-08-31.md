# FreeScout OAuth identity spike evidence

**Date:** 2026-08-31
**Environment:** disposable local FreeScout; synthetic identities only
**FreeScout:** 1.8.238
**OAuth Login:** 1.0.28
**PHP:** 8.5.9
**Overall spike status:** in progress

## Safe test configuration

- Force OAuth Login: disabled.
- Automatic user creation: disabled.
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

## Current decision

The paid module remains usable only with the Rondo identity guard and one-to-one subject binding.
The OAuth identity-guard proof is a provisional pass; the complete compatibility spike remains in
progress.
