---
phase: 202-email-verification
plan: 01
subsystem: infra
tags: [lettermint, email, wp_mail, notifications, php]

# Dependency graph
requires:
  - phase: 201-lettermint-setup
    provides: Lettermint plugin activated on production as wp_mail transport
provides:
  - EmailChannel sends From notifications@svawc.nl (root domain, verified in Lettermint)
  - MentionNotifications sends with explicit From header at notifications@svawc.nl
  - Both invoice types (discipline + membership fee) confirmed through Lettermint with PDF attachments (HTTP 202)
affects:
  - 202-02 (email verification for all notification channels now has working transport baseline)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Root domain extraction: wp_parse_url + explode('.', $host) + array_slice($parts, -2) — standard pattern for extracting root domain from subdomain URLs"
    - "MentionNotifications explicit From header: add From header to wp_mail headers array rather than using wp_mail_from filter"

key-files:
  created: []
  modified:
    - includes/class-email-channel.php
    - includes/class-mention-notifications.php

key-decisions:
  - "DRY deferred: root domain extraction duplicated in 2 files (EmailChannel + MentionNotifications) — only 3 lines in 2 places, not worth extracting to helper yet; refactor if/when a third email sender needs this"
  - "wp_parse_url used (WordPress wrapper) instead of bare parse_url — follows WordPress coding standards"

patterns-established:
  - "Root domain extraction pattern: wp_parse_url + array_slice($parts, -2) — use when From address must match verified sending domain"

# Metrics
duration: 2min
completed: 2026-02-20
---

# Phase 202 Plan 01: Email FROM-address fix Summary

**Root domain extraction fix in EmailChannel and MentionNotifications so From address is notifications@svawc.nl (verified domain), not @rondo.svawc.nl (unverified subdomain); both invoice email types confirmed delivered through Lettermint with PDF attachment (HTTP 202)**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-20T10:41:00Z
- **Completed:** 2026-02-20T10:43:09Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Fixed `EmailChannel.set_email_from_address()` to extract root domain using `wp_parse_url` + `array_slice($parts, -2)` — returns `notifications@svawc.nl` instead of `notifications@rondo.svawc.nl`
- Fixed `MentionNotifications.send_immediate_notification()` to include explicit `From: {site_name} <notifications@svawc.nl>` header in `wp_mail()` headers array
- Deployed to production; Lettermint confirmed HTTP 202 for both discipline invoice 6187 and membership fee invoice 6189 with PDF attachment

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix FROM-address in EmailChannel and MentionNotifications** - `bb4840ce` (fix)
2. **Task 2: Deploy and verify invoice email with PDF through Lettermint** - no code files changed (deploy + verification only)

**Plan metadata:** _(to be committed with SUMMARY.md)_

## Files Created/Modified
- `includes/class-email-channel.php` - `set_email_from_address()` now uses `wp_parse_url` + `array_slice` for root-domain extraction
- `includes/class-mention-notifications.php` - `send_immediate_notification()` now adds `From:` header with root-domain extraction

## Decisions Made
- DRY deferred: root domain extraction is 3 lines in 2 files — acceptable duplication for a 2-occurrence pattern; will refactor if a third email sender needs it
- Used `wp_parse_url` (WordPress wrapper) instead of bare `parse_url` — follows WordPress coding standards

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- FROM-address bug resolved, Lettermint transport confirmed functional with PDF attachments
- Ready for Phase 202-02: verify all notification email types (digest, mention immediate, installment reminders) through Lettermint

## Self-Check: PASSED
- includes/class-email-channel.php: FOUND
- includes/class-mention-notifications.php: FOUND
- 202-01-SUMMARY.md: FOUND
- Commit bb4840ce: FOUND

---
*Phase: 202-email-verification*
*Completed: 2026-02-20*
