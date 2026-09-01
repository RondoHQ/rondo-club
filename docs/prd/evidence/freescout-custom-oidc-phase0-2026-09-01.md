# FreeScout custom OIDC Phase 0 evidence

**Date:** 2026-09-01<br>
**Environment:** disposable local FreeScout; synthetic identities and conversations only<br>
**FreeScout:** 1.8.238<br>
**PHP:** 8.5.9<br>
**Rondo Integration proof module:** 0.1.0, disposable compatibility build<br>
**Phase 0 gate:** `PASS`<br>
**Production compatibility:** not signed off

## Decision

The custom Rondo Integration module can own the complete OpenID Connect relying-party flow on the
tested FreeScout release without the paid OAuth Login add-on. The Phase 0 implementation gate is
passed, so product Phases 1 through 5 may start.

This is not a production `GO`. The proof used a synthetic issuer and intentionally lightweight
Option-backed identity state. Production migrations, transactional concurrency, audit,
session-revocation, full sidebar authorization, response sanitization, realistic conversation
content, width/zoom coverage and the remaining compatibility checklist still have to pass.

## Isolated setup and final state

- FreeScout ran locally with mail delivery set to the log driver and no production member data.
- The licensed OAuth Login module was installed but disabled.
- The custom module supplied the only OIDC login and callback routes.
- OIDC discovery started from a configured issuer; the authorization, token, UserInfo and JWKS
  endpoints came from its metadata document rather than being embedded in the module.
- `APP_LIMIT_USER_CUSTOMER_VISIBILITY=true` remained enabled.
- A local administrator login and `/login?rondo_oauth=0` remained available as break glass.
- The synthetic provider was restored to normal mode and forced login was disabled after testing.
- The temporary force-login diagnostic route and test helpers were removed. The remaining proof
  routes require authentication.

## Authorization and token request shapes

Two independent authorization requests contained different 43-character `state` and `nonce`
values and different PKCE S256 challenges. The server retained the matching verifier only in the
FreeScout session. The request used:

```text
response_type=code
scope=openid email profile
state=<fresh value>
nonce=<fresh value>
code_challenge=<S256 value>
code_challenge_method=S256
```

The token request was server-to-server, contained the authorization code and matching
`code_verifier`, and authenticated the client with `Authorization: Basic ...`. The client secret
was absent from the request body, browser URLs and inspected logs. Evidence retained only presence,
length and redacted fingerprints, never codes, tokens, signatures, cookies or the secret.

## OIDC validation matrix

The successful path completed discovery, authorization, token exchange, ID-token validation,
UserInfo validation, binding and FreeScout session creation. It landed on the FreeScout dashboard.
The same bound subject continued to resolve the same FreeScout user after its verified email
changed.

Every negative case below was rejected without authenticating the supplied identity:

| Group | Rejected cases |
|---|---|
| Authorization flow | Provider denial, missing state, changed state and callback replay |
| ID-token structure | Missing token, missing subject, wrong algorithm and unknown key ID |
| Signature and claims | Bad signature, wrong issuer, wrong audience, multiple audiences without matching `azp`, wrong nonce, expired token and future `iat` |
| Access-token binding | Missing access token, invalid bearer token type and invalid `at_hash` |
| UserInfo | Invalid access token, missing email, non-boolean or false `email_verified`, and UserInfo `sub` mismatch |
| Transport response | Cross-origin redirect response, response over 256 KiB and unexpected content type |
| Identity binding | A different verified subject presenting the bound user's email |

The validator accepted only RS256 with a matching JWKS `kid`; verified signature, exact issuer,
audience and `azp` where required; enforced `exp`, `iat`, nonce and boolean `email_verified`; checked
`at_hash` when supplied; required a bearer access token; and required UserInfo to return the same
subject and a verified usable email.

The callback removed the complete pending flow from the session before processing it. Replaying the
same callback returned `flow_expired` and generated zero additional token requests.

## Subject binding and administrator recovery

The disposable proof confirmed these policy behaviors:

- first login created one issuer/subject-to-FreeScout-user binding;
- the bound subject, rather than a later email value, selected the user;
- a second subject could not take over the bound user through email matching;
- an ordinary agent could not issue a replacement;
- an administrator had to provide a reason before issuing a replacement;
- the recovery token was random, stored only as a hash, valid for 10 minutes and single use;
- the new subject was accepted only after the complete OIDC validation flow;
- the retired old subject could not sign in or relink through ordinary login;
- the original subject could be restored through a second administrator-authorized recovery.

The final synthetic state had one active subject, one user, one retired subject and no open
recovery. This proves the flow shape, not production persistence: database uniqueness, row locks,
rollback, audit records, password re-confirmation and session invalidation remain required tests.

## Forced login and recovery

With the exact server-side `RONDO_FORCE_OAUTH_LOGIN=true` setting loaded, `/login` redirected into
the custom OIDC flow and reached the FreeScout dashboard after four redirects. While forcing was
enabled, `/login?rondo_oauth=0` returned the local login form directly with zero redirects.

Provider denial made one authorization request and returned once to the local error page. A
provider outage returned one contained `503` after two redirects and did not loop. Removing the
environment setting and clearing FreeScout's configuration cache restored `/login` with status
`200`, zero redirects and the optional **Sign in with Rondo** button.

## Current-agent hook

An authenticated proof route read the signed-in FreeScout user server-side and returned the
synthetic administrator's local user ID and administrator status. An unauthenticated request was
redirected to login before the route ran. This proves current-agent availability; conversation and
mailbox authorization remain separate production gates.

## Opaque sidebar shell

The proof rendered synthetic content in `iframe.srcdoc` with this sandbox:

```text
allow-scripts allow-popups allow-popups-to-escape-sandbox
```

It deliberately omitted `allow-same-origin`, forms, downloads and top navigation. The child could
not access the FreeScout parent DOM. Only the nonce-authorized module script ran. Its resize message
contained an exact type, per-render channel and integer height; the parent accepted only the exact
child window, type and channel, rejected invalid messages, debounced updates and clamped height to
160–1600 pixels.

Expand/collapse changed the frame from 160 to 305 and back to 160 pixels. With the bridge disabled,
the frame retained a 480-pixel scrolling fallback. At a 390 by 844 CSS-pixel viewport, the
342-pixel frame introduced no horizontal page overflow. Full sanitizer, link allowlist, hostile CSS,
all UI states, supported desktop widths and 200% zoom remain later compatibility tests.

## Failure containment

| Failure | Observed terminal time | Result |
|---|---:|---|
| DNS failure | 0.033 s | Pass |
| Connection refused | 0.020 s | Pass |
| TLS failure | 0.030 s | Pass |
| Slow token endpoint | 5.043 s | Pass |
| Redirect response | 0.017 s | Pass |
| Oversized response | 0.018 s | Pass |
| Unexpected content type | 0.017 s | Pass |

The client used a 2-second connection timeout, 5-second total timeout, no redirect following,
JSON-only successful responses and a 256 KiB response limit. Expected pilot concurrency and normal
conversation-control responsiveness still require the full sidebar implementation.

## Threat-model conclusion

The tested design closes the paid add-on's decisive gaps: no PKCE or nonce, no signed ID-token
validation, email-only account takeover risk, request-body client secrets and looping forced-login
failures. It also establishes an opaque rendering boundary between Rondo content and FreeScout.

The remaining material risks are implementation risks rather than a FreeScout compatibility
blocker: production-grade binding transactions and audit, durable Rondo email proof, guarded user
creation, session revocation, conversation authorization, strict response sanitization and URL
policy, worker behavior under concurrency, responsive width/zoom behavior and the release supply
chain. They remain blocking before production activation.
