# Feature Research

**Domain:** Mollie Payment Integration for Discipline Case Invoices
**Researched:** 2026-02-17
**Confidence:** HIGH (Mollie PHP client v3.9.0, official docs verified)

## Context

This research covers only NEW features needed for Mollie integration. The following are already built and out of scope:
- Discipline case invoice CPT with lifecycle (draft/sent/paid/overdue)
- Invoice PDF generation (mPDF)
- Email delivery via wp_mail
- Rabobank betaalverzoek payment links in invoice emails
- Finance Settings page with Rabobank credentials section
- Facturen list page with status-driven actions

The existing `RabobankPayment` class and `FinanceConfig` class serve as the integration pattern to follow.

---

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Mollie API key storage (test + live) | Standard Mollie integration pattern; admins configure once, the key determines mode | LOW | Store test_/live_ key in `FinanceConfig`. Encrypt at rest using existing `CredentialEncryption` class. No OAuth dance needed — Mollie uses simple API key auth. |
| Default payment provider selector | Admin must choose between Rabobank and Mollie per club setup; one club won't use both simultaneously | LOW | Single dropdown in Finance Settings: "Rabobank" or "Mollie". Store in `FinanceConfig`. Invoice send flow reads this setting to pick the provider. |
| Mollie payment link creation | Core feature: generate a Mollie checkout URL for an invoice that the member opens to pay | LOW | Use `mollie/mollie-api-php` v3.9.0 Composer package. Call `/v2/payment-links` API. Include invoice amount, description ("Factuur {number}"), and webhook URL. Returns a `_links.paymentLink.href` URL. |
| iDEAL as primary payment method | iDEAL is used by ~70% of Dutch online consumers; B2C Dutch sports club context makes this non-negotiable | LOW | Mollie payment links show all enabled methods on the Mollie Dashboard by default — iDEAL is enabled by default for NL accounts. No method restriction needed for MVP. |
| Webhook endpoint for payment status | Mollie POSTs to a URL when payment status changes; without this, invoices never auto-update to Betaald | MEDIUM | Register a public (no nonce) WordPress REST endpoint at `rondo/v1/mollie/webhook`. Receives `id` param (payment link ID or payment ID), fetches current status from Mollie API, updates invoice `payment_status` to `paid`. Return HTTP 200. |
| Test mode / live mode switch | Admins must test integration without real money; Mollie's API key prefix (`test_` vs `live_`) determines mode | LOW | Prefix detection is automatic in the Mollie PHP client via `setToken()`. Display current mode in Finance Settings UI (derived from key prefix). No separate toggle needed — the key IS the mode. |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Automatic invoice status update via webhook | Members pay, invoice auto-moves to Betaald without admin action; closes the payment loop | MEDIUM | Webhook handler looks up invoice by `_mollie_payment_link_id` stored on the post. On `paid` status from Mollie API, calls `update_field('invoice_status', 'paid', $invoice_id)`. This is the key improvement over Rabobank (which has no webhook). |
| Payment link stored and reusable | Admin can resend invoice email with existing link; Mollie links don't expire by default | LOW | Store `_links.id` and `_links.paymentLink.href` in post meta. Reuse if already created (don't re-create for each email send). Same pattern as existing Rabobank `payment_link` ACF field. |
| Multi-method checkout (iDEAL + card) | Dutch clubs with international players or older members without iDEAL benefit from card option | LOW | Mollie shows all enabled methods automatically. No extra code — it's a Mollie Dashboard configuration. Note: card payments cost more (~1.2% + €0.25 vs flat iDEAL fee). |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| iDEAL-only enforcement via API | "We only want iDEAL for cost control" | Requires passing `method: ideal` on every payment link; breaks if customer has no NL bank account; Mollie charges same flat fee for iDEAL regardless | Leave method open in API. Admin controls which methods are active in Mollie Dashboard. Cost control is a Mollie Dashboard concern, not code. |
| Mollie refund initiation from Rondo | "Auto-refund when case is overturned" | Adds significant complexity; requires storing payment ID separately from link ID; refund UI needs careful access control; edge cases multiply | Out of scope. If needed, admin handles refunds in Mollie Dashboard. Rondo marks invoice as manually resolved. |
| Polling for payment status (no webhook) | "Simpler than setting up webhook URL" | Polling requires cron job, adds API calls, has delay; Mollie webhook has 26-hour retry window and is the correct integration pattern | Use webhook. Register the endpoint during plugin init. The URL is predictable: `{site_url}/wp-json/rondo/v1/mollie/webhook`. |
| Storing Mollie customer profiles | "Pre-fill payment method for repeat payers" | Members pay invoices rarely (discipline cases); one-time payment links don't need customer profiles; over-engineering for the use case | Use stateless payment links. No customer storage needed. |
| Both Rabobank and Mollie active simultaneously | "Maximum flexibility" | Confusing for admin; email template `{betaallink}` only holds one link; split attention in settings UI | One active provider at a time, configured in Finance Settings. Provider abstraction class handles routing to correct implementation. |

---

## Feature Dependencies

```
Mollie API Key Storage (FinanceConfig)
    └──required by──> Mollie Payment Link Creation
    └──required by──> Webhook Status Update (needs API key to verify payment)

Default Payment Provider Selector (FinanceConfig)
    └──required by──> Invoice Send Flow (determines which provider to call)

Mollie Payment Link Creation
    └──stores──> payment_link (ACF field, already exists) + _mollie_payment_link_id (post meta, new)
    └──enables──> Webhook Status Update (needs stored ID to match webhook to invoice)

Webhook Endpoint
    └──required by──> Automatic Invoice Status Update
    └──depends on──> Mollie API Key (to call back and verify payment status)
    └──depends on──> _mollie_payment_link_id stored on invoice post
```

### Dependency Notes

- **Mollie API key storage required before everything else:** The key is used both to create payment links and to verify webhook payloads by fetching the payment from Mollie's API.
- **Payment link creation stores the Mollie ID:** Mollie's webhook only sends an `id` param. The webhook handler looks up which invoice has `_mollie_payment_link_id = {id}` to find the correct post to update.
- **Provider selector is independent of Mollie-specific code:** It routes the existing invoice send flow to either `RabobankPayment` or a new `MolliePayment` class. Both implement the same `create_payment_request($invoice_id)` interface.

---

## MVP Definition

### Launch With (v1)

- [ ] **Mollie API key storage** — Finance Settings: single API key field (test_ or live_). Encrypt at rest. Display derived mode (Test/Live). Required for all other features.
- [ ] **Default payment provider selector** — Finance Settings: dropdown (Rabobank / Mollie). Required to route invoice send to correct provider.
- [ ] **`MolliePayment` class** — Mirrors `RabobankPayment` interface. Calls Mollie Payment Links API. Stores payment link URL in ACF `payment_link` field. Stores Mollie payment link ID in `_mollie_payment_link_id` post meta. Uses `mollie/mollie-api-php` Composer package.
- [ ] **Webhook endpoint** — Public REST endpoint at `rondo/v1/mollie/webhook`. Receives Mollie `id` POST param. Fetches payment status from Mollie API. On `paid`: updates invoice status to `paid` via `update_field`. Returns 200.
- [ ] **Finance Settings UI update** — Add Mollie section alongside existing Rabobank section. Show current mode derived from key prefix.

### Add After Validation (v1.x)

- [ ] **Webhook failure logging** — Log when Mollie webhooks arrive for unknown invoice IDs. Useful for debugging missed payments. Add when first webhook issues reported.
- [ ] **Payment link expiry** — Allow admin to set expiry date on Mollie payment links (API supports optional `expiresAt`). Add if club requests time-limited invoices.

### Future Consideration (v2+)

- [ ] **Payment method restriction to iDEAL** — Only if club has cost concerns about card fees and wants enforcement at API level rather than Dashboard.
- [ ] **Mollie refund from Rondo** — Only if discipline case appeals become frequent enough to warrant UI-driven refunds.

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Mollie API key storage | HIGH | LOW | P1 |
| Default provider selector | HIGH | LOW | P1 |
| MolliePayment class (link creation) | HIGH | LOW | P1 |
| Webhook endpoint + status update | HIGH | MEDIUM | P1 |
| Finance Settings UI (Mollie section) | HIGH | LOW | P1 |
| Webhook failure logging | MEDIUM | LOW | P2 |
| Payment link expiry option | LOW | LOW | P2 |
| iDEAL-only enforcement | LOW | LOW | P3 |
| Mollie refund UI | LOW | HIGH | P3 |

**Priority key:**
- P1: Must have for launch — this is a complete feature, not a partial
- P2: Add when first practical need arises
- P3: Future, only if explicitly requested

---

## Implementation Notes for Roadmap

### Mollie PHP Client

Use `mollie/mollie-api-php` v3.9.0 (released 2026-02-09, requires PHP >= 7.4). Install via Composer:

```bash
composer require mollie/mollie-api-php
```

Client initialization — key prefix determines mode automatically:

```php
$mollie = new \Mollie\Api\MollieApiClient();
$mollie->setToken( $api_key ); // test_... or live_... prefix auto-detected
```

### Payment Link Creation (Core API Call)

```php
$payment_link = $mollie->paymentLinks->create([
    'amount'      => [ 'currency' => 'EUR', 'value' => '25.00' ], // string, 2 decimal places
    'description' => 'Factuur ' . $invoice_number,                 // max 255 chars
    'webhookUrl'  => rest_url( 'rondo/v1/mollie/webhook' ),
    'redirectUrl' => admin_url( 'admin.php?page=facturen' ),       // where member lands after payment
]);

$payment_url = $payment_link->_links->paymentLink->href;
$mollie_id   = $payment_link->id;
```

Amount MUST be a string with exactly 2 decimal places. Use `number_format($amount, 2, '.', '')`.

### Webhook Handler Pattern

Mollie sends POST with body `id=pl_xxxxx` (or `id=tr_xxxxx` for payments). The handler must:

1. Extract `id` from `$_POST['id']` (or request body)
2. Fetch the resource from Mollie API to get verified status
3. Find the invoice with matching `_mollie_payment_link_id`
4. Update status if paid
5. Return HTTP 200

The webhook URL must be HTTPS and publicly accessible (no WordPress auth). Register with `'permission_callback' => '__return_true'`. Mollie retries 10 times over 26 hours if 200 not received.

### Test vs Live Mode

The API key prefix IS the mode. No separate toggle needed:
- `test_dHar...` = test mode, test checkout UI, no real money
- `live_xyz...` = live mode, real payments

Display in UI: extract prefix from stored key and show badge ("Test" or "Live"). Never expose the full key in API responses — show only the prefix and last 4 characters.

### Dutch Payment Context

For Dutch B2C (sports club members):
- **iDEAL**: Primary method, ~70% of Dutch online transactions. Flat fee (~€0.29/transaction). Enabled by default on all Dutch Mollie accounts.
- **Credit/debit card**: Secondary. 1.2% + €0.25. Useful for edge cases. Auto-enabled by Mollie if merchant applies.
- **Bancontact, SOFORT**: Irrelevant for this use case (Belgian/German methods).
- **iDEAL 2.0**: iDEAL migrated to iDEAL 2.0 by March 2025. Mollie handles this transparently — no code changes needed for existing iDEAL integrations.

No method restriction needed in code. Leave payment method selection to Mollie checkout UI.

---

## Sources

- [Mollie Payment Links API](https://docs.mollie.com/docs/payment-links) — Official docs (verified)
- [Mollie Create Payment Link Reference](https://docs.mollie.com/reference/create-payment-link) — Official API reference (verified)
- [Mollie Webhooks](https://docs.mollie.com/reference/webhooks) — Retry policy, payload format (verified)
- [Mollie PHP Client v3.9.0](https://github.com/mollie/mollie-api-php) — Composer package, setToken() API (verified)
- [Mollie Testing](https://docs.mollie.com/reference/testing) — Test mode, magic amounts (verified)
- [iDEAL on Mollie](https://docs.mollie.com/docs/ideal) — Dutch payment context (verified)
- [iDEAL 2.0 Migration](https://www.mollie.com/growth/ideal-2-0) — Migration timeline, Mollie handles transparently (MEDIUM confidence)

---
*Feature research for: Mollie payment integration — Rondo Club discipline invoice payment links*
*Researched: 2026-02-17*
