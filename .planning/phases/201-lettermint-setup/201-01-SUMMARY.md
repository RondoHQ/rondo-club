---
phase: 201-lettermint-setup
plan: 01
status: complete
started: 2026-02-20
completed: 2026-02-20
duration: 5min
---

## Summary

Activated Lettermint as the email transport on production WordPress (rondo.svawc.nl), replacing Gravity SMTP's Postmark integration with the European GDPR-compliant Lettermint service.

## What was built

- Deactivated Gravity SMTP plugin (Postmark, US-based email transport)
- Activated Lettermint plugin v1.4.2 (European email transport)
- Configured API token (`lm_...`) in `lettermint_api_token` WordPress option
- Verified logging enabled (`lettermint_enable_logs = 1`)
- Sent test email through wp_mail() with HTML content and custom From header
- User confirmed delivery in Lettermint dashboard and inbox

## Tasks completed

| # | Task | Status |
|---|------|--------|
| 1 | Deactivate Gravity SMTP and activate Lettermint | ✓ |
| 2 | Obtain Lettermint API token from dashboard (human checkpoint) | ✓ |
| 3 | Configure API token and send test email | ✓ |
| 4 | Verify delivery in Lettermint dashboard and inbox (human checkpoint) | ✓ |

## Key files

### Modified (production WordPress options)
- `lettermint_api_token` — API token for Lettermint service
- `lettermint_enable_logs` — Email logging enabled

### Plugin state changes
- `gravitysmtp` — deactivated (was v2.1.3, Postmark transport)
- `lettermint` — activated (v1.4.2, European email transport)

## Decisions

- [201-01]: No "force" options enabled (`lettermint_force_email`, `lettermint_force_from_name`) — theme email classes already set correct From headers
- [201-01]: Test email sent to joost@joost.blog from penningmeester@svawc.nl — confirmed delivery through Lettermint dashboard

## Deviations

None.

## Self-Check: PASSED
- [x] Gravity SMTP deactivated on production
- [x] Lettermint activated on production
- [x] API token configured
- [x] Test email accepted by Lettermint API (wp_mail returned true)
- [x] User verified delivery in dashboard and inbox
