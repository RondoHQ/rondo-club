# Project Research Summary

**Project:** v27.0 Mollie Payment Integration
**Domain:** Payment provider integration — adding Mollie alongside Rabobank for discipline case invoices
**Researched:** 2026-02-17
**Confidence:** HIGH

## Executive Summary

Adding Mollie as a second payment provider to the existing Rondo Club invoice system is a well-scoped, low-risk feature addition. The existing codebase already has all the architectural patterns needed: encrypted credential storage (`CredentialEncryption`), a finance config layer (`FinanceConfig`), a separate payment class (`RabobankPayment`), and REST-based invoice management (`RestInvoices`). Mollie integration requires adding 3 new PHP classes, modifying 3 existing ones, and installing one Composer package. The total surface area is small and the patterns are established.

The recommended approach is: install `mollie/mollie-api-php ^3.9` via Composer, mirror the `RabobankPayment` pattern into a new `MolliePayment` class using the Payments API (not the Payment Links API), add a dedicated `MollieWebhook` class with a public REST endpoint, and update `FinanceConfig` and `RestInvoices` for provider routing. The key differentiator over the existing Rabobank integration is automatic payment status updates via webhook — when a member pays, the invoice transitions to `rondo_paid` without admin intervention, because Mollie retries webhook delivery for up to 26 hours.

The primary risks are webhook-specific and all preventable with explicit code patterns: the Mollie webhook must be publicly accessible (no WordPress nonce), payment status must always be re-fetched from the Mollie API (never trusted from the POST body alone), the handler must always return HTTP 200, and the handler must be idempotent to avoid retry storms. There are no architectural unknowns — this is a standard Mollie integration into an existing invoice system following established in-codebase patterns.

## Key Findings

### Recommended Stack

The only new dependency is `mollie/mollie-api-php ^3.9` (released 2026-02-09, PHP >= 7.4), which pulls in `nyholm/psr7 ^1.8` as its sole new transitive dependency. All other PSR HTTP dependencies (`psr/http-client`, `psr/http-factory`, `psr/http-message`) are already installed via `google/apiclient`. No Guzzle conflicts are expected because Mollie v3+ removed Guzzle as a production dependency. The existing `CredentialEncryption` class handles Mollie API key storage without changes — same sodium-encrypt pattern as Rabobank credentials.

**Core technologies:**
- `mollie/mollie-api-php ^3.9`: Official Mollie PHP SDK — the only correct option; v3 uses the modern `payments->create()` pattern; do not use v2's deprecated fluent API
- `nyholm/psr7 ^1.8`: New transitive dependency pulled in automatically; no conflicts with existing packages
- `CredentialEncryption` (existing): Reuse for Mollie API key storage — no new crypto code needed
- WordPress Options API (existing): Store `rondo_finance_mollie_api_key` (encrypted) and `rondo_finance_active_payment_provider`

**What NOT to use:**
- `mollie/oauth2-mollie-php` — only for multi-merchant SaaS; single-club API key auth is sufficient here
- Payment Links API (`$mollie->paymentLinks->create()`) — designed for reusable/shared links; use the Payments API for per-invoice one-time payments
- Custom HMAC webhook verification — Mollie does not send HMAC signatures on standard webhooks; security is provided by re-fetching payment state via authenticated SDK call

### Expected Features

**Must have (table stakes):**
- Mollie API key storage — encrypted in WordPress options via `CredentialEncryption`; key prefix (`test_` vs `live_`) determines mode automatically, no separate toggle needed
- Default payment provider selector — single dropdown (Rabobank / Mollie) in Finance Settings; stored in `FinanceConfig`; default remains `rabobank` so existing behavior is unchanged until Mollie is explicitly configured
- Mollie payment link creation — `MolliePayment::create_payment_link()` using Payments API; checkout URL stored in shared ACF `payment_link` field; Mollie payment ID stored in `_mollie_payment_id` post meta for webhook lookup
- Webhook endpoint for automatic payment status update — public REST endpoint at `rondo/v1/mollie/webhook`; re-fetches payment status via SDK; transitions invoice to `rondo_paid`; idempotent
- Finance Settings UI update — Mollie API key field, provider selector dropdown, derived mode display (Test / Live badge)

**Should have (competitive advantage):**
- Automatic invoice status update via webhook — closes the payment loop without admin action; key improvement over Rabobank which has no webhook
- Payment link reuse — store link on invoice and reuse if already created; do not re-create on each email send
- Test/live mode indicator — visible badge in Finance Settings derived from key prefix; never expose full API key in REST responses

**Defer to v2+:**
- iDEAL-only method restriction — Mollie Dashboard controls enabled methods; code-level enforcement adds complexity without practical benefit for a Dutch sports club context
- Mollie refund initiation from Rondo — admin handles refunds in Mollie Dashboard; discipline case appeals are infrequent enough that UI-driven refunds are not warranted

### Architecture Approach

The integration follows strict provider abstraction: `RestInvoices::send_invoice()` reads the active provider from `FinanceConfig` and branches to either `MolliePayment` or `RabobankPayment`. Both implement the same one-method contract (`create_payment_link($invoice_id): string|WP_Error`). A dedicated `MollieClient` class wraps SDK initialization so both `MolliePayment` and `MollieWebhook` can share API access without a singleton. The webhook lives in its own class (`MollieWebhook`) with a public permission callback, cleanly separated from the authenticated `RestInvoices` routes — following the existing pattern where `RabobankOAuth` has its own class.

**Major components:**
1. `FinanceConfig` (modified) — adds Mollie API key methods + active provider setting; option keys `rondo_finance_mollie_api_key` and `rondo_finance_active_payment_provider`
2. `MollieClient` (new) — thin SDK wrapper; reads encrypted key from `FinanceConfig`; both `MolliePayment` and `MollieWebhook` instantiate this
3. `MolliePayment` (new) — creates Mollie payment via Payments API; stores checkout URL in ACF `payment_link` field + Mollie payment ID in `_mollie_payment_id` post meta
4. `MollieWebhook` (new) — public REST endpoint (`'permission_callback' => '__return_true'`); re-fetches payment via SDK; idempotently marks invoice paid; always returns 200
5. `RestInvoices` (modified) — provider branching in `send_invoice()`; default remains `'rabobank'` — existing Rabobank path untouched
6. Finance Settings React UI (modified) — Mollie key field, provider selector, mode badge derived from key prefix

### Critical Pitfalls

1. **WordPress nonce blocks Mollie webhooks** — Register webhook with `'permission_callback' => '__return_true'`. If the endpoint requires a WordPress nonce or user session, Mollie receives 403, retries 10 times over 26 hours, and gives up — invoice never auto-updates to paid. Security is provided instead by the mandatory API re-fetch (see pitfall 2).

2. **Trusting the POST body without re-fetching from Mollie API** — Mollie's webhook POST body contains only `id=tr_xxx`. Always call `$mollie->payments->get($payment_id)` inside the handler before acting. Skipping this lets any attacker POST a fake payment ID to fraudulently mark invoices paid. The API re-fetch is the security verification step.

3. **Shared `payment_link` ACF field overwritten by both providers** — The existing system uses `get_field('payment_link', $invoice_id)` for Rabobank links. Mollie must store its payment ID in a separate `_mollie_payment_id` post meta field. Using `payment_link` as the "active sent link" for both providers is fine; mixing the payment IDs used for webhook lookup in a shared field is not.

4. **Non-200 webhook responses trigger Mollie retry storm** — Wrap the entire webhook handler in try/catch. Log errors internally. Always return HTTP 200 regardless of errors. Returning 4xx or 5xx (even for legitimate issues like an invoice not found) causes Mollie to retry up to 10 times over 26 hours.

5. **Webhook URL rejected by Mollie at payment creation time** — Mollie validates `webhookUrl` when the payment is created, not at delivery time. On local dev (`localhost`, `.local` TLD), omit `webhookUrl` entirely — Mollie skips delivery gracefully. On production, `rest_url('rondo/v1/mollie/webhook')` produces the correct HTTPS URL automatically.

## Implications for Roadmap

The dependency chain is strict and drives the phase order: config before client, client before payment service, payment service before webhook (webhook needs the payment ID stored by `MolliePayment` to look up the invoice), both payment service and webhook before `RestInvoices` branching, everything before UI. The architecture research defines a 5-phase build order that maps directly to this dependency graph.

### Phase 1: SDK Installation + FinanceConfig + MollieClient

**Rationale:** Everything depends on the API key being stored and the SDK being initialized. This phase is safe to deploy in isolation — no REST routes are registered, no user-visible changes occur. It also validates the Composer installation before any integration code is written.

**Delivers:** `composer require mollie/mollie-api-php ^3.9` installed and deployed; `FinanceConfig` extended with `get_mollie_api_key()`, `update_mollie_api_key()`, `get_active_payment_provider()`, `update_active_payment_provider()`; `class-mollie-client.php` created in `includes/`.

**Addresses:** Mollie API key storage (table stakes); test/live mode derivation from key prefix; encrypted credential storage following existing `CredentialEncryption` pattern.

**Avoids:** Composer Guzzle conflicts (Pitfall 7) — verify `composer install` succeeds and `composer why-not` shows no conflicts before writing any integration code.

### Phase 2: MolliePayment — Payment Link Creation

**Rationale:** Core feature. Depends on Phase 1 (`MollieClient` + `FinanceConfig` Mollie methods). Independently testable by calling the method directly and checking the ACF `payment_link` field and `_mollie_payment_id` post meta on a test invoice.

**Delivers:** `class-mollie-payment.php` with `create_payment_link($invoice_id): string|WP_Error`; uses `$mollie->payments->create()` (Payments API, not Payment Links API); stores checkout URL in ACF `payment_link` field; stores Mollie payment ID in `_mollie_payment_id` post meta; `functions.php` updated to instantiate in REST-only block.

**Addresses:** Mollie payment link creation (table stakes); payment link reuse (store and check `_mollie_payment_id` before creating a new one).

**Avoids:** Payment Links API anti-pattern (Pitfall 9) — use `$mollie->payments->create()`, not `$mollie->paymentLinks->create()`; amount format error (Pitfall 8) — always `number_format($amount, 2, '.', '')`; shared field overwrite (Pitfall 3) — Mollie payment ID in provider-specific `_mollie_payment_id` meta; webhook URL validation (Pitfall 5) — omit `webhookUrl` when site URL contains `localhost` or `.local`.

### Phase 3: MollieWebhook — Automatic Status Update

**Rationale:** Depends on Phase 2 because the webhook handler looks up invoices by `_mollie_payment_id`, which only exists after `MolliePayment` has stored it. This is the key differentiator over Rabobank — automatic payment confirmation. Keep in a dedicated class separate from `RestInvoices`.

**Delivers:** `class-mollie-webhook.php` registering `POST /rondo/v1/mollie/webhook` with `'permission_callback' => '__return_true'`; handler re-fetches payment via `MollieClient`; idempotently updates invoice to `rondo_paid` via `wp_update_post` + `update_field`; always returns 200; wrapped in try/catch with error logging.

**Addresses:** Webhook endpoint for automatic payment status update (table stakes); idempotent processing (prevents double-email on Mollie retries).

**Avoids:** WordPress nonce blocking webhooks (Pitfall 1) — public endpoint with comment explaining intentional security model; trusting POST body (Pitfall 2) — mandatory `$mollie->payments->get($payment_id)` re-fetch; duplicate processing (Pitfall 6) — `post_status !== 'rondo_paid'` guard before writing; retry storm (Pitfall 10) — always return 200 inside try/catch.

### Phase 4: RestInvoices — Provider Branching

**Rationale:** This is the only modification to currently working code. Deferred to Phase 4 to minimize the risk window — by the time this runs, `MolliePayment` is fully tested and functional. The default remains `'rabobank'`, so existing behavior is completely unchanged until a Mollie API key is configured.

**Delivers:** Modified `RestInvoices::send_invoice()` that reads `FinanceConfig::get_active_payment_provider()` and routes to `MolliePayment` or `RabobankPayment`; existing Rabobank code path is untouched; `functions.php` instantiation updated.

**Avoids:** Regression in Rabobank path — only a new `if ($provider === 'mollie')` branch is added around existing Rabobank code; no Rabobank classes are modified.

### Phase 5: Finance Settings UI — Mollie Configuration

**Rationale:** Admin-facing configuration. Depends on all backend phases being stable. Uses the existing settings REST endpoint — no new backend endpoints needed. Can be the final phase because the backend is fully functional before the UI exposes it to admins.

**Delivers:** Mollie API key input in Finance Settings React component; payment provider selector (Rabobank / Mollie); mode badge (Test / Live, derived from key prefix in API response); key display shows only prefix + last 4 characters, never full key.

**Addresses:** Finance Settings UI update (table stakes); test/live mode indicator (should-have).

**Avoids:** Test/live key cross-contamination (Pitfall 4) — visible mode indicator in UI makes active mode impossible to miss; key exposure — `FinanceConfig::get_all_settings()` returns `mollie_has_api_key` (bool) and `mollie_environment` (string), never the raw key.

### Phase Ordering Rationale

- Config → Client → Payment Service → Webhook → `RestInvoices` branching reflects strict dependencies: each component requires the previous to be deployed and functional before it can be implemented and tested
- `RestInvoices` modification is deferred to Phase 4 (not Phase 2) to keep the existing Rabobank path completely isolated from Mollie work until the Mollie classes are proven functional
- UI (Phase 5) is last because the React component needs stable API responses from all backend phases to build against
- Each phase produces an independently deployable, testable artifact with a clear verification step

### Research Flags

**Phases with standard, well-documented patterns (no additional research needed):**
- **Phase 1:** Composer installation and WordPress options storage — fully documented; mirrors existing Rabobank/`CredentialEncryption` pattern exactly
- **Phase 4:** Provider branching in `RestInvoices` — simple conditional; no new patterns; no risk to Rabobank path
- **Phase 5:** Finance Settings UI — mirrors existing Rabobank UI section in the same settings page; no new patterns

**Phases that need attention during implementation (not research — execution care):**
- **Phase 2:** Verify `mollie/mollie-api-php` v3.9 installed SDK method signatures against the code patterns in ARCHITECTURE.md before finalizing `MolliePayment` — the API changed significantly from v2 to v3 and the SDK installed via Composer is authoritative
- **Phase 3:** After implementation, verify the webhook endpoint returns 200 unauthenticated via `curl -X POST https://rondo.svawc.nl/wp-json/rondo/v1/mollie/webhook -d "id=test"` before considering this phase complete; also test idempotency by sending the same payload twice

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Official Mollie SDK on Packagist verified; PSR dependency versions checked against existing `vendor/composer/installed.php`; Guzzle conflict risk assessed and found negligible for v3+ |
| Features | HIGH | Mollie Payments API and Payment Links API officially documented and compared; iDEAL 2.0 migration confirmed as handled transparently by Mollie |
| Architecture | HIGH | Existing codebase is authoritative for patterns; Mollie webhook security model from official SDK docs and Mollie documentation; component boundaries mirror established Rabobank patterns |
| Pitfalls | HIGH (critical webhook/security pitfalls) / MEDIUM (two-provider field coexistence) | Webhook auth, security model, and retry behavior from official Mollie docs; two-provider field storage patterns inferred from codebase analysis and general payment orchestration patterns |

**Overall confidence:** HIGH

### Gaps to Address

- **`_mollie_payment_id` naming consistency:** STACK.md and ARCHITECTURE.md use `_mollie_payment_id` (correct for Payments API) while FEATURES.md mentions `_mollie_payment_link_id` (appropriate for Payment Links API). The architecture decision to use the Payments API resolves this: use `_mollie_payment_id` consistently throughout. Confirm during Phase 2 implementation before writing any meta.

- **Invoice CPT `rondo_paid` post status:** ARCHITECTURE.md uses `rondo_paid` as the target post status in webhook code samples. Verify this matches the actual registered post status name in `class-post-types.php` during Phase 3 before the webhook handler is finalized.

- **`redirectUrl` destination:** ARCHITECTURE.md suggests `home_url('/financien/')` as the redirect URL after payment completion. Verify the correct React router path is accessible to members (not admin-only) before Phase 2 deployment. Consider redirecting to the invoice detail view instead.

## Sources

### Primary (HIGH confidence)
- [Packagist: mollie/mollie-api-php](https://packagist.org/packages/mollie/mollie-api-php) — v3.9.0, PHP >= 7.4, dependency versions
- [GitHub: mollie/mollie-api-php composer.json](https://github.com/mollie/mollie-api-php/blob/master/composer.json) — exact transitive dependencies
- [Mollie Docs: Webhooks](https://docs.mollie.com/reference/webhooks) — POST body format (`id` only), no HMAC, 15s timeout, 10 retries over 26h
- [Mollie Docs: Create Payment](https://docs.mollie.com/reference/create-payment) — Payments API, `_links->checkout->href`, `webhookUrl` parameter, amount format
- [Mollie PHP SDK: webhook recipe](https://github.com/mollie/mollie-api-php/blob/master/docs/recipes/payments/handle-webhook.md) — idempotency pattern, always return 200
- [Mollie Docs: Webhooks Best Practices](https://docs.mollie.com/reference/webhooks-best-practices) — HTTPS requirement, re-fetch verification
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — nonce auth requires logged-in user; not suitable for external webhooks
- Existing codebase: `class-rabobank-oauth.php`, `class-rabobank-payment.php`, `class-rest-invoices.php`, `class-finance-config.php`, `class-credential-encryption.php` — authoritative for integration patterns

### Secondary (MEDIUM confidence)
- [Mollie Docs: Payment Links API](https://docs.mollie.com/reference/payment-links-api) — used to confirm Payments API is the correct choice for per-invoice use
- [Mollie Docs: Next-Gen Webhooks](https://docs.mollie.com/reference/webhooks-new) — HMAC-SHA256 `X-Mollie-Signature` header option (not required for standard webhooks)
- [iDEAL 2.0 Migration](https://www.mollie.com/growth/ideal-2-0) — confirmed Mollie handles transparently, no code changes needed
- [Mollie GitHub Issue: Webhook URL display bug](https://github.com/mollie/laravel-mollie/issues/177) — Payment Links API webhook display quirk (not relevant if using Payments API)
- [Mollie WooCommerce Wiki: Guzzle conflicts](https://github.com/mollie/WooCommerce/wiki/Composer-Guzzle-conflicts) — Guzzle removed from Mollie SDK v3+; confirms v3 is conflict-safe

### Tertiary (LOW confidence)
- Mollie Magento2 webhook communication patterns — architectural guidance from Magento integration, not directly verified for WordPress custom themes
- Multiple payment provider field conflict patterns — inferred from codebase analysis and general payment orchestration patterns rather than Mollie-specific documentation

---
*Research completed: 2026-02-17*
*Ready for roadmap: yes*
