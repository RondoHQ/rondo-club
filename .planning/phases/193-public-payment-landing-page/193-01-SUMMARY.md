---
phase: 193-public-payment-landing-page
plan: 01
subsystem: payments
tags: [mollie, php, wordpress, rewrite-rules, installments, mobile]

# Dependency graph
requires:
  - phase: 192-data-model-foundation
    provides: rondo_invoice CPT, _installment_* meta schema, installment admin fee config, MollieClient, FinanceConfig

provides:
  - PublicPaymentPage class at /betaling/{token} (GET renderer + POST handler)
  - generate_token() static method setting _payment_token meta + payment_link ACF field
  - Installment meta writing (write_installment_meta) for 3 or 8 plans
  - Mollie payment creation for first installment with reverse-lookup meta
  - Mobile-first standalone HTML page (no auth, no React SPA, no external dependencies)

affects:
  - 194-mollie-webhook (uses reverse-lookup _mollie_pid_{payment_id} meta written here)
  - 195-scheduler (installment meta schema established here)
  - 196-bulk-invoice-creation (calls PublicPaymentPage::generate_token() when creating invoices)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "template_redirect priority 0 for public pages — fires before React SPA catch-all at priority 1"
    - "CSRF via token-in-URL + hidden POST field (no nonce needed for unauthenticated pages)"
    - "Idempotency guard: check _installment_1_mollie_payment_id before creating Mollie payment"
    - "Reverse-lookup meta pattern: _mollie_pid_{payment_id} = installment_number for O(1) webhook matching"
    - "Rounding remainder on last installment so amounts sum exactly to total"

key-files:
  created:
    - includes/class-public-payment-page.php
  modified:
    - functions.php
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "PublicPaymentPage uses template_redirect priority 0 — same as CardDAVServer, fires before SPA catch-all at priority 1"
  - "CSRF protection via matching submitted POST token against URL token — no WP nonce available for unauthenticated requests"
  - "generate_token() sets both _payment_token meta AND payment_link ACF field so InvoiceEmailSender {betaallink} variable works"
  - "All plan options shown unconditionally (full, 3, 8 installments) — plan-enable toggles deferred to Phase 196"
  - "Standalone PHP-rendered HTML page with inline CSS — no external dependencies, works offline/cached"

patterns-established:
  - "Public payment page pattern: rewrite rule + query var + template_redirect priority 0 + exit"
  - "Installment meta written on plan selection (not on invoice creation)"

# Metrics
duration: 5min
completed: 2026-02-18
---

# Phase 193 Plan 01: Public Payment Landing Page Summary

**PHP-rendered payment page at /betaling/{token} with full/3-installment/8-installment plan selection, Mollie checkout creation, mobile-first inline CSS, and CSRF + idempotency guards**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-18T22:15:09Z
- **Completed:** 2026-02-18T22:20:21Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- PublicPaymentPage class with complete GET/POST handling at `/betaling/{64-hex-token}`
- Token generation utility (`generate_token`) that sets both `_payment_token` meta and `payment_link` ACF field — ready for Phase 196 bulk invoice creation to wire into `InvoiceEmailSender`
- Standalone HTML page with inline mobile-first CSS: 3 plan forms, invoice summary, Dutch copy, 48px touch targets, 16px font-size on inputs to prevent iOS zoom
- POST handler: CSRF check, idempotency guard, installment meta storage, Mollie payment creation, reverse-lookup meta
- Deployed to production — `/betaling/000...000` returns 404 with Dutch error page (not SPA, not wp-login)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create PublicPaymentPage class** - `8692d99e` (feat)
2. **Task 2: Wire in functions.php and deploy** - `d7e1b9a7` (feat)

## Files Created/Modified
- `includes/class-public-payment-page.php` - New PublicPaymentPage class (GET renderer, POST handler, token generation, Mollie payment creation)
- `functions.php` - Added use statement, instantiation in rondo_init(), rewrite rules in rondo_theme_activation()
- `style.css` - Version bump to 27.2.0
- `package.json` - Version bump to 27.2.0
- `CHANGELOG.md` - Added [27.2.0] entry

## Decisions Made
- `template_redirect` priority 0 fires before the React SPA catch-all at priority 1 — same pattern as `CardDAVServer`
- CSRF via token-in-URL compared to hidden POST field — no WP nonce available for unauthenticated users
- `generate_token()` sets both `_payment_token` post meta AND `payment_link` ACF field so `InvoiceEmailSender` `{betaallink}` variable works without any additional wiring
- All three plan options shown on the page unconditionally — plan-enable toggles (Phase 196 decision) not needed here per must_have truths
- Rounding remainder assigned to last installment so sum of installment amounts equals invoice total exactly

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- PublicPaymentPage is deployed and live. Invalid token URLs return 404 with Dutch error.
- `generate_token()` static method is ready for Phase 196 to call during bulk invoice creation.
- Installment meta schema (`_installment_N_*`) written on plan selection — Phase 195 (scheduler) reads these.
- Reverse-lookup meta (`_mollie_pid_{payment_id}`) written on Mollie payment creation — Phase 194 (webhook) uses this for O(1) lookup.
- No blockers for Phase 194 or 195.

---
*Phase: 193-public-payment-landing-page*
*Completed: 2026-02-18*
