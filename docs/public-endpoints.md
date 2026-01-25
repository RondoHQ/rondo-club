# Public REST API Endpoints

This document lists REST API endpoints that do not require WordPress authentication (`permission_callback => __return_true`). Each has a documented security rationale.

## Slack Integration Endpoints

### POST /stadion/v1/slack/oauth/callback

**Purpose:** Receives OAuth redirect from Slack after user authorizes the app.

**Why public:** Browser redirect from Slack cannot carry WordPress authentication.

**Security mechanism:**
- Validates `state` parameter against stored transient (CSRF protection)
- State includes user ID, verified against stored value
- Transient expires in 10 minutes
- Code can only be exchanged once with Slack

### POST /stadion/v1/slack/commands

**Purpose:** Receives slash command requests from Slack.

**Why public:** Slack servers cannot authenticate as WordPress users.

**Security mechanism:**
- Validates `X-Slack-Signature` header using HMAC-SHA256
- Validates `X-Slack-Request-Timestamp` (rejects requests older than 5 minutes)
- Requires `STADION_SLACK_SIGNING_SECRET` to be configured

### POST /stadion/v1/slack/events

**Purpose:** Receives event subscription requests from Slack.

**Why public:** Slack servers cannot authenticate as WordPress users.

**Security mechanism:**
- Only handles URL verification challenge (returns challenge value)
- No sensitive data exposed
- Future event handling would validate Slack signature

## Security Review Checklist

When adding new public endpoints:
1. Document why authentication cannot be used
2. Implement alternative verification (signatures, tokens, state validation)
3. Rate limit if possible
4. Log suspicious activity
5. Add to this document
