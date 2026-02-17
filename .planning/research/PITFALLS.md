# Pitfalls Research: Mollie Payment Integration

**Domain:** Mollie payment links + webhooks added to existing WordPress PHP invoice system (alongside Rabobank)
**Researched:** 2026-02-17
**Confidence:** HIGH (webhook auth, SDK usage, URL requirements) / MEDIUM (two-provider coexistence patterns)
**Context:** Rondo Club — WordPress theme, PHP 8.0+, PSR-4 Composer, existing Rabobank betaalverzoek integration

---

## Critical Pitfalls

### Pitfall 1: WordPress Nonce Blocks Mollie Webhooks (403 / Unauthorized)

**What goes wrong:**
Mollie sends a plain POST request to the webhook URL containing only `id=tr_xxxxx` in the body. It does not send a WordPress nonce (`X-WP-Nonce` header) and it is not a logged-in WordPress user. If the webhook endpoint is registered with a `permission_callback` that calls `current_user_can()` or checks for a nonce, Mollie receives a 403 or 401. Mollie retries up to 10 times over 26 hours, then gives up — the invoice payment status never updates automatically.

**Why it happens:**
All other Rondo REST endpoints require the `financieel` capability via `check_financieel_permission()`. Developers copy that pattern for the webhook endpoint. The endpoint appears to work in browser testing (authenticated user) but silently fails for all real Mollie callbacks.

**How to avoid:**
Register the Mollie webhook endpoint with `'permission_callback' => '__return_true'` — it must be publicly accessible. Security is instead provided by:
1. Fetching the payment ID from Mollie's API directly (never trusting the POST body value alone)
2. Verifying the payment belongs to a known invoice in the database before acting
3. Optionally verifying the `X-Mollie-Signature` HMAC header (next-gen webhooks)

```php
register_rest_route( 'rondo/v1', '/mollie/webhook', [
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => [ $this, 'handle_webhook' ],
    'permission_callback' => '__return_true', // MUST be public — Mollie has no WP session
] );
```

**Warning signs:**
- Mollie Dashboard shows webhook calls with HTTP 403/401/302 responses
- Invoice status never transitions to "paid" automatically
- Webhook test from Mollie Dashboard fails
- WordPress debug log shows authentication failures on the webhook URL

**Phase to address:** Webhook handler implementation phase — add a comment in code explaining why `__return_true` is intentional security decision, not an oversight.

---

### Pitfall 2: Trusting the POST Body Instead of Re-fetching from Mollie API

**What goes wrong:**
The Mollie webhook POST body contains only `id=tr_d0b0E3EA3v`. Some developers treat this ID as authoritative and mark the invoice paid based on the POST body alone. A malicious actor who knows your webhook URL can POST any payment ID and trigger a "paid" status on any invoice.

**Why it happens:**
Developers assume the webhook payload is the complete, trustworthy status update (as with some other payment providers). Mollie intentionally keeps the payload minimal to force server-side verification.

**How to avoid:**
Always re-fetch the payment from Mollie's API using the ID from POST, then check the payment status from the API response:

```php
public function handle_webhook( \WP_REST_Request $request ) {
    $payment_id = sanitize_text_field( $request->get_param( 'id' ) );
    if ( empty( $payment_id ) ) {
        return new \WP_REST_Response( 'ok', 200 ); // return 200 even for empty
    }

    $mollie   = $this->get_mollie_client();
    $payment  = $mollie->payments->get( $payment_id ); // re-fetch from API
    $metadata = $payment->metadata;
    $invoice_id = $metadata->invoice_id ?? null;

    // Verify invoice exists in our system
    if ( ! $invoice_id || get_post_type( $invoice_id ) !== 'rondo_invoice' ) {
        return new \WP_REST_Response( 'ok', 200 ); // 200 even for unknown — don't leak info
    }

    if ( $payment->isPaid() ) {
        // mark invoice paid
    }

    return new \WP_REST_Response( 'ok', 200 );
}
```

**Warning signs:**
- Webhook handler reads `$_POST['status']` or trusts body values other than `id`
- No Mollie API call inside the webhook handler

**Phase to address:** Webhook handler implementation — the re-fetch is the security model, document it explicitly.

---

### Pitfall 3: Shared `payment_link` Field Overwritten When Both Providers Active

**What goes wrong:**
The existing system stores the payment link in `get_field( 'payment_link', $invoice_id )` (confirmed in `class-rest-invoices.php` line 822 and `class-rabobank-payment.php` line 415). When Mollie is added as a second provider, both providers write to the same ACF field. The last one to run wins — if a user generates a Rabobank link, then sends a Mollie link, the Rabobank link is silently replaced (and vice versa).

**Why it happens:**
The field was designed for a single payment provider. Adding Mollie without refactoring the storage model causes silent overwrites.

**How to avoid:**
Use provider-specific meta fields:
- `payment_link` — keep as display/active link (the one sent to the member)
- `_rabobank_payment_link` — Rabobank-specific post meta
- `_mollie_payment_link` — Mollie-specific post meta
- `_mollie_payment_id` — Mollie payment ID for status lookups

When creating a Mollie link, write to `_mollie_payment_link` and update `payment_link`. When looking up which provider created a payment, check `_mollie_payment_id` rather than the generic field.

**Warning signs:**
- Invoice detail response shows wrong payment link after creating links via both providers
- Rabobank QR code URL breaks because `payment_link` was overwritten
- Users report payment links not matching what they sent

**Phase to address:** Data model design phase — define field strategy before writing any Mollie code.

---

### Pitfall 4: Test/Live API Key Cross-Contamination

**What goes wrong:**
Test-mode payments (`test_xxx`) are created in the database alongside live payments, or live keys are used during development. Three specific failure modes:
1. Test payments appear in the Mollie Dashboard production transaction list
2. A live key used in testing creates real payment links sent to members
3. A customer/metadata object created in test mode cannot be used with live mode — cross-mode object references fail with "wrong mode" error from Mollie API

**Why it happens:**
- API keys look similar (`test_xxxxxx` vs `live_xxxxxx`) — easy to copy the wrong one
- WordPress options can be set once and forgotten
- No visual indicator in the Rondo UI showing which mode is active

**How to avoid:**
1. Store test and live keys separately in WordPress options: `mollie_test_api_key` and `mollie_live_api_key`
2. Add a distinct mode toggle in the finance settings UI (`mollie_mode: 'test' | 'live'`)
3. Display a visible banner in the invoice UI when test mode is active (like Rabobank's sandbox environment handling in `class-rabobank-payment.php` line 280)
4. In the Mollie client initialization, assert that key prefix matches configured mode:
   ```php
   $key = get_option( 'mollie_' . $mode . '_api_key' );
   // fail fast: test_ key should not be used in live mode
   if ( $mode === 'live' && str_starts_with( $key, 'test_' ) ) {
       throw new \RuntimeException( 'Live mode configured but test API key provided' );
   }
   ```
5. Never store a payment ID created in test mode as a reference in a live-mode invoice

**Warning signs:**
- Payment IDs starting with `tr_` in production that return errors from Mollie API
- Mollie API returns "the wrong mode is used" error message
- Members receive payment links that go to a test payment page

**Phase to address:** Settings/configuration phase — implement before creating any real payment links.

---

### Pitfall 5: Webhook URL Rejected by Mollie API at Payment Creation Time

**What goes wrong:**
When creating a payment or payment link via the Mollie API, you pass a `webhookUrl`. Mollie validates this URL immediately at creation time. If the URL is invalid, the API call returns an error and the payment is not created. Invalid means: localhost, .local TLD, private IP ranges, non-HTTPS in live mode, or any URL Mollie servers cannot reach.

**Why it happens:**
- Local development environment uses `http://localhost` or `http://rondo.local`
- The webhook endpoint does not exist yet when the first test payment is created
- HTTPS certificate not valid (self-signed cert on a test domain)
- Security group / firewall blocks inbound requests from Mollie's IP ranges

**How to avoid:**
1. During local development, omit the `webhookUrl` parameter entirely (Mollie skips webhook delivery) — do not pass an invalid URL
2. For staging/testing on a real server, use the actual public HTTPS URL
3. In test mode on production, the webhook URL must still be HTTPS and publicly reachable
4. For local webhook testing, use ngrok: `ngrok http 80` provides a temporary public HTTPS URL

```php
$payment_data = [
    'amount'      => [ 'currency' => 'EUR', 'value' => '25.00' ],
    'description' => 'Factuur ' . $invoice_number,
    'redirectUrl' => $redirect_url,
    'metadata'    => [ 'invoice_id' => $invoice_id ],
];

// Only set webhookUrl on real servers (not localhost)
$site_url = get_site_url();
if ( ! str_contains( $site_url, 'localhost' ) && ! str_contains( $site_url, '.local' ) ) {
    $payment_data['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
}
```

**Warning signs:**
- Mollie API returns `"The webhook location is invalid"` error
- Payment link creation fails with 422 Unprocessable Entity
- Works in production, fails locally

**Phase to address:** Payment link creation phase — handle webhookUrl omission in local dev from day one.

---

### Pitfall 6: Webhook Receives Multiple Calls for Same Payment — Double Processing

**What goes wrong:**
Mollie delivers webhooks multiple times for the same payment event (e.g., when payment moves from `open` to `paid`, then when the settlement is processed). If the webhook handler is not idempotent, it may send a duplicate "paid" confirmation email, mark an invoice paid multiple times, or trigger multiple status transitions.

**Why it happens:**
Mollie retries failed webhooks (up to 10 times over 26 hours). It also sends separate webhook calls for status changes vs. refunds/chargebacks. A slow webhook handler (over 15 seconds) causes Mollie to retry even if processing was successful.

**How to avoid:**
1. Check current invoice status before processing: only act if the status is actually changing
2. Process the webhook quickly (under 5 seconds) — defer heavy operations (email, PDF) to `wp_schedule_single_event()`
3. Use database-level idempotency: fetch current `post_status` before writing

```php
if ( $payment->isPaid() ) {
    $invoice = get_post( $invoice_id );
    // Guard: only update if not already paid
    if ( $invoice->post_status !== 'rondo_paid' ) {
        $this->mark_invoice_paid( $invoice_id );
    }
}
```

**Warning signs:**
- Member receives multiple "payment confirmed" emails for one payment
- Invoice shows multiple payment timestamps
- Webhook handler execution time exceeds 10 seconds (visible in error logs)

**Phase to address:** Webhook handler implementation — idempotency check is mandatory, not optional.

---

## Moderate Pitfalls

### Pitfall 7: Mollie SDK Guzzle Dependency Conflicts with Existing Composer Packages

**What goes wrong:**
Older versions of the Mollie PHP SDK (`mollie/mollie-api-php` < v3.x) depend on Guzzle. The existing `composer.json` includes `google/apiclient`, `mpdf/mpdf`, and `sabre/dav` — all of which may require their own versions of Guzzle or PSR HTTP libraries. Version conflicts cause Composer to fail or load unexpected library versions.

**Why it happens:**
WordPress themes loading multiple Composer packages often hit Guzzle version conflicts because it's widely used as an HTTP client.

**How to avoid:**
Use `mollie/mollie-api-php` v3.x+ — Guzzle was removed as a direct dependency in the SDK refactor. Verify with:
```bash
composer require mollie/mollie-api-php
composer why-not guzzlehttp/guzzle 7.0  # check for conflicts
```
If conflicts appear, use PHP's stream context (`CURLOPT_*`) directly rather than adding Guzzle.

**Warning signs:**
- `composer install` fails with version conflict errors involving `guzzlehttp/guzzle` or `psr/http-message`
- Classes from wrong library versions loaded at runtime
- `composer diagnose` reports issues

**Phase to address:** SDK installation phase — run `composer install` and check for conflicts before writing any integration code.

---

### Pitfall 8: Payment Link Amount as String vs Float — API Format Error

**What goes wrong:**
Mollie's API requires the `amount.value` as a decimal string with exactly 2 decimal places (e.g., `"25.00"`, not `25`, not `25.0`, not `25.000`). Passing an integer or a float formatted differently causes a 422 Unprocessable Entity response.

**Why it happens:**
PHP's `(string) 25.5` produces `"25.5"` not `"25.50"`. The Rabobank integration uses `amountCents` (integer), so the existing amount calculation code produces integers — copying that pattern to Mollie breaks the API call.

**How to avoid:**
Always format the amount with exactly 2 decimal places:
```php
'amount' => [
    'currency' => 'EUR',
    'value'    => number_format( (float) $total_amount, 2, '.', '' ),
],
```
Never use `intval()`, `(int)`, or raw float casting for Mollie amounts.

**Warning signs:**
- Mollie API returns 422 with "The amount is invalid" or "field validation failed"
- Amounts with cents work fine but round numbers fail (or vice versa)

**Phase to address:** Payment link creation phase — add explicit format assertion in code.

---

### Pitfall 9: Payment Link vs Direct Payment — Different Webhook Behavior

**What goes wrong:**
When using the Payment Links API (`$mollie->paymentLinks->create()`), the webhook URL you provide may be replaced by Mollie internally with `https://paymentlink.mollie.com/` in the Mollie Dashboard view. This has confused developers into thinking their webhook is not configured. The webhook IS called (confirmed by Mollie as a display bug), but relying on this behavior is fragile.

Additionally, payment links can be paid multiple times by different people (unless `numPayers` is set). An invoice could be "paid" multiple times if a link is shared.

**How to avoid:**
1. Use the direct Payments API (`$mollie->payments->create()`) and generate a `_links->checkout->href` to send to the member — this gives full control over webhook configuration
2. If using Payment Links API, set `numPayers: 1` (Mollie supports limiting to single-use)
3. After receiving a webhook, verify via API that only one successful payment exists for the invoice

**Warning signs:**
- Mollie Dashboard shows webhook URL as `paymentlink.mollie.com` even when you configured a custom URL
- Invoice marked paid multiple times

**Phase to address:** Architecture decision phase — choose Payments API or Payment Links API before implementation, document the choice.

---

### Pitfall 10: Webhook Handler Returns Non-200 Causes Mollie Retry Storm

**What goes wrong:**
If the webhook handler throws an uncaught exception, returns a WordPress error response, or WordPress itself returns a redirect (301/302 for `www` canonicalization), Mollie treats it as failure and retries. With retries up to 10 times over 26 hours, a single failed invoice webhook generates 10 webhook calls. If many invoices fail simultaneously, this creates a retry storm that adds server load.

**Why it happens:**
- WordPress REST API returns 301 redirect if site URL doesn't match exactly (`https://` vs `http://`, `www` vs non-`www`)
- PHP exceptions bubble up as 500 errors
- WordPress authentication errors return 403 (see Pitfall 1)

**How to avoid:**
1. Configure Mollie webhook URL to exactly match `get_rest_url()` output including protocol and www/non-www
2. Wrap webhook handler in try/catch — always return 200, log errors internally:
```php
try {
    $this->process_mollie_webhook( $payment_id );
} catch ( \Exception $e ) {
    error_log( 'Mollie webhook error: ' . $e->getMessage() );
}
return new \WP_REST_Response( 'ok', 200 ); // Always 200
```
3. Test the exact webhook URL from the command line: `curl -X POST https://rondo.svawc.nl/wp-json/rondo/v1/mollie/webhook -d "id=tr_test"`

**Warning signs:**
- Mollie Dashboard shows repeated webhook attempts on same payment
- error_log fills with repeated webhook processing attempts
- `curl` to webhook URL returns 301 instead of 200

**Phase to address:** Webhook handler implementation and pre-launch testing.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Store Mollie payment ID in same field as Rabobank ID | Simpler data model | Cannot determine which provider paid; webhook lookup fails when two providers used | Never — use provider-specific fields |
| Skip webhook, poll payment status from UI | No webhook implementation complexity | Member must reload page; paid status can lag hours; no automatic invoice state transitions | Never for invoices — polling is not a payment confirmation mechanism |
| Hardcode `live_` API key in code | Quick to set up | Key exposed in git history; impossible to test safely | Never — always use WordPress options |
| Use `__return_true` without any secondary verification | Fastest webhook implementation | Malicious actor can trigger paid status on any invoice by POSTing any ID | Acceptable ONLY if paired with mandatory re-fetch from Mollie API |
| Omit `webhookUrl` entirely | No need for public URL, no auth issues | Invoices never auto-update to paid status; manual confirmation only | Acceptable for MVP if manual status updates are the workflow |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Mollie API | Pass `amount` as PHP float or integer | Always `number_format( $amount, 2, '.', '' )` as string |
| Mollie webhooks | Use authenticated WordPress endpoint | Use `'permission_callback' => '__return_true'`, verify via re-fetch |
| Mollie webhook URL | Use same URL as site URL (may have www redirect) | Use `get_rest_url()` and verify no redirect with curl |
| Mollie test mode | Use test key in production settings | Store test/live keys separately; show mode banner in UI |
| Rabobank + Mollie | Both write to `payment_link` ACF field | Use `_mollie_payment_link` and `_rabobank_payment_link` post meta; `payment_link` = active link |
| Mollie SDK | `composer require mollie/mollie-api-php` without checking conflicts | Check Composer conflicts first; use v3+ (no Guzzle dependency) |
| WordPress REST API | Mollie webhook triggers `www` redirect | Configure webhook URL to match exact site URL with correct protocol |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Trust POST body `id` value without re-fetching from Mollie | Attacker POSTs any payment ID → invoice marked paid for free | Always call `$mollie->payments->get( $payment_id )` before acting |
| Register webhook with nonce-protected permission callback | All real Mollie webhooks fail silently | Use `__return_true` + secondary verification via Mollie API re-fetch |
| Store API keys in `wp-config.php` constants without environment separation | Live key used in test; test key used in live; keys visible in logs | Store in WordPress options with separate test/live entries; validate key prefix matches mode |
| No rate limiting on webhook endpoint | Retry storm from Mollie overloads server | Webhook idempotency + always return 200 prevents Mollie retries |
| Log full API response including payment data | Payment details in server logs accessible to hosting provider | Log only payment ID and status, not full response body |

---

## "Looks Done But Isn't" Checklist

- [ ] **Webhook endpoint:** Appears to work because you tested it authenticated in browser — verify Mollie can reach it unauthenticated via `curl -X POST` from external network
- [ ] **Test mode:** Payment links created and tested — verify you are using `test_` key, not `live_` key, during development
- [ ] **Invoice status:** Payment link created and sent — verify invoice actually transitions to `rondo_paid` when webhook fires (not just when link is created)
- [ ] **Amount format:** Payment created successfully — verify amount formatted as `"25.00"` not `25` or `"25.5"` (test with cents, e.g., `€7.50`)
- [ ] **Dual-provider field:** Mollie link stored — verify existing Rabobank `payment_link` not overwritten for invoices that already have a Rabobank link
- [ ] **Idempotency:** Webhook fires — verify sending the same webhook payload twice does not send two confirmation emails or create two "paid" records
- [ ] **Redirect URL:** Payment complete — verify `redirectUrl` takes member to a useful page (invoice confirmation), not a dead URL

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Webhook blocked (403) — invoices never auto-paid | MEDIUM | Fix permission_callback; re-register route; test via curl; Mollie retries stop after 26h so backfill status manually via Mollie Dashboard |
| Wrong API key used in production | HIGH | Revoke compromised key in Mollie Dashboard immediately; rotate key; audit which payments were created under wrong key; notify if test payments were sent to members |
| payment_link field overwritten | LOW | Re-run payment link creation for affected provider; check `_mollie_payment_id` and `_rabobank_payment_request_id` post meta to reconstruct correct links |
| Duplicate webhook processing — invoice paid twice in system | MEDIUM | Query invoices with double `paid_date` entries; manually revert extra status transitions; idempotency fix prevents recurrence |
| Webhook URL invalid at creation time | LOW | Remove `webhookUrl` from payment creation request; recreate payment link with correct URL; Mollie does not retroactively add webhooks to existing payments |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| WordPress nonce blocks webhooks | Webhook implementation | `curl -X POST {webhook_url} -d "id=test"` returns 200 unauthenticated |
| Trusting POST body without re-fetch | Webhook implementation | Code review: Mollie API call must exist inside handler |
| Shared payment_link field overwritten | Data model / settings phase | Separate meta fields defined before any payment code written |
| Test/live key cross-contamination | Settings/configuration phase | Mode banner visible in UI; key prefix validation on save |
| Webhook URL rejected by Mollie | Payment creation phase | Local dev omits webhookUrl; staging uses public HTTPS URL |
| Duplicate webhook processing | Webhook implementation | Test: POST same webhook ID twice → only one email sent, one status change |
| Guzzle Composer conflicts | SDK installation (first) | `composer install` succeeds with no conflicts |
| Amount format error | Payment creation phase | Test payment with amount that has cents (e.g., 7.50) |
| Payment link vs direct payment webhook | Architecture decision | Decision documented before implementation begins |
| Non-200 response causes retry storm | Pre-launch testing | curl test + Mollie Dashboard webhook logs show 200 |

---

## Sources

### HIGH Confidence (Official Documentation)

- [Mollie Webhooks Reference](https://docs.mollie.com/reference/webhooks) — POST body contains only `id`; must re-fetch; 15s timeout; 10 retries over 26h
- [Mollie Next-Gen Webhooks](https://docs.mollie.com/reference/webhooks-new) — HMAC-SHA256 `X-Mollie-Signature` header; signing secret rotation
- [Mollie Webhooks Best Practices](https://docs.mollie.com/reference/webhooks-best-practices) — HTTPS requirement; signature verification; timing-safe comparison
- [Mollie PHP SDK v3.9.0 — GitHub](https://github.com/mollie/mollie-api-php) — Current SDK; PHP 7.4+; no Guzzle dependency in v3+
- [Mollie PHP SDK — Webhook Handling Recipe](https://github.com/mollie/mollie-api-php/blob/master/docs/recipes/payments/handle-webhook.md) — Process status before refunds; idempotency; always return 200
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — Nonce auth requires logged-in user; not suitable for external webhooks

### MEDIUM Confidence (Official Sources + Community Verification)

- [Mollie Troubleshoot Common Issues](https://docs.mollie.com/docs/troubleshoot-common-issues) — API key verification; payment method activation; webhook failures
- [Mollie Guzzle Conflicts — WooCommerce Wiki](https://github.com/mollie/WooCommerce/wiki/Composer-Guzzle-conflicts) — Guzzle removed from Mollie SDK v3+; update plugins to resolve
- [Mollie Issue #177 — Webhook URL replaced with paymentlink.mollie.com](https://github.com/mollie/laravel-mollie/issues/177) — Payment Links API webhook display bug; webhook still fires
- [Mollie Issue #111 — Webhook location is invalid](https://github.com/mollie/mollie-api-php/issues/111) — localhost and .local TLD rejected at payment creation time
- [WordPress Plugin Composer Conflicts](https://pressidium.com/blog/wordpress-plugin-conflicts-how-to-prevent-composer-dependency-hell/) — Namespace isolation for Composer packages in WP

### LOW Confidence (WebSearch — Needs Validation)

- Mollie Magento2 webhook communication patterns — architectural guidance from Magento integration, not directly verified for WP custom themes
- Multiple payment provider field conflict patterns — inferred from codebase analysis + general payment orchestration patterns

---

*Pitfalls research for: Mollie payment integration added to existing Rondo Club WordPress invoice system*
*Researched: 2026-02-17*
