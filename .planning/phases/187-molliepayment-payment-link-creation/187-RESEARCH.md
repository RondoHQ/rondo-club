# Phase 187: MolliePayment — Payment Link Creation - Research

**Researched:** 2026-02-17
**Domain:** Mollie Payments API, PHP SDK v3, WordPress post meta, ACF field storage
**Confidence:** HIGH

## Summary

Phase 187 creates `MolliePayment`, a PHP class in `Rondo\Finance` that wraps `$mollie->payments->create()` from the SDK installed in Phase 186. The class has a single public method `create_payment_link($invoice_id)` that reads invoice ACF fields, calls the Mollie Payments API, stores the checkout URL in the ACF `payment_link` field, stores the Mollie payment ID (`tr_xxx`) in `_mollie_payment_id` post meta, and returns the checkout URL string (or `WP_Error` on failure).

The class mirrors the architecture of `RabobankPayment` (`class-rabobank-payment.php`): namespace `Rondo\Finance`, no REST routes registered in Phase 187 (REST integration is Phase 189), non-blocking on error (logs and returns `WP_Error` without disrupting the invoice send flow), and reuse of existing checkout URLs when the payment has already been created.

Phase 187 has exactly one notable decision that is not a direct analogy of the Rabobank code: choosing the `redirectUrl`. Mollie requires it for Payments API. Since invoice payment links are emailed to customers who pay externally, `home_url()` is sufficient as the redirect destination after payment. The `webhookUrl` is omitted on local/staging environments (localhost or `.local` in the site URL) per the success criteria.

**Primary recommendation:** Model `MolliePayment` on `RabobankPayment` — same namespace, same error logging pattern, same ACF update pattern — but use `MollieClient::get()->payments->create()` instead of raw `wp_remote_post()` calls, and register the class in `functions.php` inside the REST-only block.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `mollie/mollie-api-php` | `^3.9` (installed Phase 186) | `$mollie->payments->create(array)` Payments API call | Official PHP SDK already installed; only correct approach |
| `Rondo\Finance\MollieClient` | Phase 186 | Gets configured `MollieApiClient` | Thin wrapper already built; instantiate directly |

### WordPress/ACF Functions Used

| Function | Purpose |
|----------|---------|
| `get_field('invoice_number', $id)` | Invoice number for description |
| `get_field('total_amount', $id)` | Amount (float) for payment |
| `update_field('payment_link', $url, $id)` | Store checkout URL in ACF field |
| `get_post_meta($id, '_mollie_payment_id', true)` | Check for existing payment ID |
| `update_post_meta($id, '_mollie_payment_id', $value)` | Store Mollie `tr_xxx` ID |
| `get_post($id)` | Validate invoice exists |
| `get_site_url()` | Detect localhost/`.local` for webhook omission |
| `home_url()` | Build `redirectUrl` |
| `error_log()` | Non-blocking error logging |

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── class-mollie-payment.php     # NEW: Phase 187
├── class-mollie-client.php      # EXISTS: Phase 186
├── class-finance-config.php     # EXISTS: Phase 186 extensions
├── class-rabobank-payment.php   # REFERENCE: pattern to mirror
└── class-rabobank-oauth.php     # unchanged
functions.php                    # MODIFIED: + use + instantiation in REST block
```

### Pattern 1: MolliePayment Class Structure

**What:** New class `MolliePayment` in namespace `Rondo\Finance`, file `includes/class-mollie-payment.php`. Single public method `create_payment_link(int $invoice_id): string|\WP_Error`.

**Constructor:** No constructor needed if the class is stateless — `MollieClient` is instantiated inside `create_payment_link()`. However, for testability and consistency with `RabobankPayment`, an optional `MollieClient` constructor parameter may be added.

**Example skeleton:**

```php
<?php
/**
 * Mollie Payment Service
 *
 * Creates Mollie payments via the Payments API and stores the
 * checkout URL + payment ID on the invoice post.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MolliePayment {

	/**
	 * Create a Mollie payment for an invoice and store the checkout URL.
	 *
	 * If _mollie_payment_id already exists on the invoice, the existing
	 * checkout URL is reused without calling the Mollie API again.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return string|\WP_Error Checkout URL on success, WP_Error on failure.
	 */
	public function create_payment_link( int $invoice_id ): string|\WP_Error {
		// Validate invoice
		// Check for existing _mollie_payment_id (idempotency)
		// Load invoice fields
		// Format amount as "12.50" (2 decimal places, no floating point errors)
		// Build webhookUrl (omit on localhost/.local)
		// Call $mollie->payments->create([...])
		// Store $payment->getCheckoutUrl() via update_field('payment_link', ...)
		// Store $payment->id via update_post_meta(..., '_mollie_payment_id', ...)
		// Return checkout URL
	}
}
```

### Pattern 2: Amount Formatting

**Critical:** Mollie requires the amount value as a string with exactly 2 decimal places (e.g., `"12.50"`, not `12.5` or `12.500`). The ACF `total_amount` field stores a float.

**Correct approach:**
```php
// Source: Mollie Payments API documentation + SDK Money class (string $value required)
$amount_string = number_format( (float) $total_amount, 2, '.', '' );
// Result: "12.50" for 12.5, "100.00" for 100, "12345.67" for 12345.674
```

**Why this is correct:** `number_format()` handles all float-to-string edge cases. The 3rd arg `.` forces decimal separator (locale-safe). The 4th arg `''` removes thousands separators.

**Anti-pattern:**
```php
// WRONG: may produce "12.5" (missing trailing zero) or locale-specific "12,50"
(string) $total_amount
sprintf('%.2f', $total_amount)  // OK but less readable
```

`sprintf('%.2f', $total_amount)` is also acceptable, but `number_format` is clearer for the 2-decimal intent.

### Pattern 3: Idempotency — Reusing Existing Payment

**What:** PYMT-04 requires that if `_mollie_payment_id` already exists on the invoice, no new Mollie payment is created. The existing checkout URL is returned instead.

**Implementation:**
```php
// Check for existing Mollie payment ID
$existing_id = get_post_meta( $invoice_id, '_mollie_payment_id', true );
if ( ! empty( $existing_id ) ) {
    // Return the stored checkout URL without a new API call
    $existing_url = get_field( 'payment_link', $invoice_id );
    if ( ! empty( $existing_url ) ) {
        return $existing_url;
    }
    // payment_link field is empty despite having an ID — fall through to create
}
```

**Note:** There is an edge case where `_mollie_payment_id` exists but `payment_link` ACF field is empty (e.g., partial write failure). Falling through to create a new payment is safe in this case, though it would create a duplicate Mollie payment. For Phase 187 this edge case is acceptable — the idempotency check covers the normal case.

### Pattern 4: Webhook URL — Omit on Local/Staging

**What:** PYMT-06 requires that `webhookUrl` is omitted when the site URL contains `localhost` or `.local` (Mollie rejects webhook URLs that point to non-reachable addresses).

**Implementation:**
```php
// Source: Mollie documentation — webhook omission for local dev
$site_url    = get_site_url();
$webhook_url = null;
if ( strpos( $site_url, 'localhost' ) === false && strpos( $site_url, '.local' ) === false ) {
    $webhook_url = rest_url( 'rondo/v1/mollie/webhook' );
}
```

**Note:** The webhook endpoint (`rondo/v1/mollie/webhook`) is built in Phase 188. In Phase 187, the webhook URL string is correctly constructed but the endpoint doesn't exist yet. This is fine — Phase 187 only creates the payment, and the URL format is correct.

**How it goes into the payload:**
```php
$payload = [
    'amount'      => [
        'currency' => 'EUR',
        'value'    => $amount_string,
    ],
    'description' => 'Factuur ' . $invoice_number,
    'redirectUrl' => home_url( '/' ),
];

if ( ! is_null( $webhook_url ) ) {
    $payload['webhookUrl'] = $webhook_url;
}
```

### Pattern 5: Mollie SDK Call and Response

**What:** `$mollie->payments->create(array $payload)` returns a `Mollie\Api\Resources\Payment` object. The checkout URL is accessed via `$payment->getCheckoutUrl()` which internally reads `$this->_links->checkout->href`.

**Verified from SDK source (`src/Resources/Payment.php`, line 517-524):**
```php
// Source: vendor/mollie/mollie-api-php/src/Resources/Payment.php
public function getCheckoutUrl(): ?string
{
    if (empty($this->_links->checkout)) {
        return null;
    }
    return $this->_links->checkout->href;
}
```

**Verified from SDK source (`src/EndpointCollection/PaymentEndpointCollection.php`, line 50-61):**
```php
// Source: vendor/mollie/mollie-api-php/src/EndpointCollection/PaymentEndpointCollection.php
public function create(array $payload = [], array $query = [], bool $testmode = false): Payment
{
    $request = CreatePaymentRequestFactory::new()
        ->withPayload($payload)
        ->withQuery($query)
        ->create();
    return $this->send($request->test($testmode));
}
```

**Payment ID property:** `$payment->id` contains the Mollie payment ID (e.g., `tr_C15LZwVISe`).

**Complete call pattern:**
```php
// Source: Official Mollie documentation + SDK verification
try {
    $mollie_client = new MollieClient();
    $mollie        = $mollie_client->get();
    $payment       = $mollie->payments->create( $payload );
} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
    error_log( 'Mollie payment creation failed for invoice ' . $invoice_id . ': ' . $e->getMessage() );
    return new \WP_Error(
        'mollie_api_error',
        sprintf( __( 'Mollie betaling aanmaken mislukt: %s', 'rondo' ), $e->getMessage() ),
        [ 'status' => 502 ]
    );
}

$checkout_url = $payment->getCheckoutUrl();
if ( empty( $checkout_url ) ) {
    return new \WP_Error( 'mollie_no_checkout_url', __( 'Geen checkout URL in Mollie response.', 'rondo' ), [ 'status' => 500 ] );
}

// Store checkout URL and payment ID
update_field( 'payment_link', $checkout_url, $invoice_id );
update_post_meta( $invoice_id, '_mollie_payment_id', $payment->id );

return $checkout_url;
```

### Pattern 6: functions.php Registration

**What:** Add `use Rondo\Finance\MolliePayment;` to the imports at the top and instantiate `new MolliePayment()` in the REST-only block alongside `RabobankPayment`. However, looking at the Phase 187 success criteria — `MolliePayment` has no REST routes to register. It does NOT need to be instantiated in `rondo_init()` on every REST request.

**Key distinction from RabobankPayment:** `RabobankPayment::__construct()` registers REST routes via `add_action('rest_api_init', ...)`. `MolliePayment` does NOT register REST routes in Phase 187 — it is a pure service class. Phase 189 handles the routing integration.

**Conclusion:** `functions.php` needs the `use` import added, but `new MolliePayment()` does NOT need to go into the REST block. The class is autoloaded when instantiated by Phase 189 code. The `use` import should be added when Phase 189 references it, OR proactively in Phase 187 for cleanliness.

**Recommendation for Phase 187:** Add the `use Rondo\Finance\MolliePayment;` import to `functions.php` but do NOT call `new MolliePayment()` from `rondo_init()`. This matches the note in Phase 186's research: "The `use` import and instantiation in functions.php happens in Phase 187."

### Anti-Patterns to Avoid

- **Using the Payment Links API instead of Payments API:** `$mollie->paymentLinks->create()` creates a different resource. Phase 187 MUST use `$mollie->payments->create()`. The success criteria is explicit about this.
- **Floating point amount formatting:** Never cast to string directly. Always use `number_format((float) $total_amount, 2, '.', '')`.
- **`$payment->_links->checkout->href` directly:** Use `$payment->getCheckoutUrl()` which handles the null check safely.
- **Assuming webhook is always present in payload:** Build payload without `webhookUrl`, then conditionally add it only when not on localhost.
- **Throwing on API error instead of returning WP_Error:** Non-blocking per Phase 183-01 decision. Catch `ApiException`, log it, return `\WP_Error`. The invoice send flow must continue.
- **Registering REST routes in MolliePayment constructor:** No REST routes in Phase 187. This is deferred to Phase 189.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP request to Mollie API | `wp_remote_post()` + manual headers | `MollieClient::get()->payments->create()` | SDK handles Bearer auth, JSON encoding, response hydration, error throwing |
| Amount string formatting | Custom decimal logic | `number_format((float) $amount, 2, '.', '')` | Correct rounding, locale-safe, handles edge cases |
| Checkout URL extraction | `$payment->_links->checkout->href` | `$payment->getCheckoutUrl()` | SDK method handles null check |
| API key configuration | Re-reading FinanceConfig directly | `new MollieClient()` | Wrapper already exists; don't duplicate key setup |

**Key insight:** The entire HTTP layer, response parsing, and API key management are handled by the SDK and existing wrappers. `MolliePayment` is purely orchestration: read invoice data → format → call SDK → store results.

## Common Pitfalls

### Pitfall 1: Amount Formatted as Integer Instead of "12.50"

**What goes wrong:** Mollie API returns validation error: "The amount value has an invalid number of decimals." or "The value provided is not formatted correctly."

**Why it happens:** `(string) $total_amount` produces `"12.5"` or `"12"` for clean values. `number_format()` without the decimal separator argument may use locale-specific `,` (comma).

**How to avoid:** Always `number_format( (float) get_field('total_amount', $id), 2, '.', '' )`. The 3rd arg `.` forces period as decimal separator regardless of PHP locale settings.

**Warning signs:** Mollie API exception with message mentioning "decimals" or "formatted incorrectly".

### Pitfall 2: Missing redirectUrl Causes API Rejection

**What goes wrong:** Mollie Payments API returns HTTP 422: "The redirectUrl field is required."

**Why it happens:** `redirectUrl` is required by the Mollie API for all Payments API calls. Omitting it causes immediate rejection.

**How to avoid:** Always include `redirectUrl` in the payload. Use `home_url('/')` as a sensible default for invoice payment links (customer lands on home after paying).

**Warning signs:** `ApiException` with HTTP 422 or message mentioning `redirectUrl`.

### Pitfall 3: Webhook URL on Local Dev Causes Mollie Rejection

**What goes wrong:** When developing locally (site URL contains `localhost` or `.local`), Mollie rejects the payment creation because it cannot reach the webhook URL for validation.

**Why it happens:** Mollie validates that the webhook URL is reachable. Local URLs are not reachable from Mollie's servers.

**How to avoid:** Check `get_site_url()` for `localhost` or `.local` substring. If found, omit `webhookUrl` from the payload entirely.

**Warning signs:** `ApiException` during local dev with message mentioning "not reachable" or "invalid URL" for the webhook.

### Pitfall 4: MollieClient Constructor Throws When No API Key Is Set

**What goes wrong:** `new MollieClient()` calls `$this->client->setApiKey('')` which may throw `\Mollie\Api\Exceptions\ApiException` ("Invalid API key").

**Why it happens:** Mollie API key is not yet configured (empty string from `FinanceConfig::get_mollie_api_key()`).

**How to avoid:** Check whether a Mollie API key is configured before instantiating `MollieClient`. Use `FinanceConfig::get_active_payment_provider()` to confirm Mollie is selected. Alternatively, catch the exception from `new MollieClient()` and return `WP_Error` with a clear user-facing message.

**Recommended guard pattern:**
```php
$config = new \Rondo\Config\FinanceConfig();
if ( empty( $config->get_mollie_api_key() ) ) {
    return new \WP_Error(
        'mollie_not_configured',
        __( 'Mollie API-sleutel niet geconfigureerd.', 'rondo' ),
        [ 'status' => 400 ]
    );
}
```

**Warning signs:** Fatal error or uncaught `ApiException` on invoice send when Mollie is not configured.

### Pitfall 5: Idempotency Edge Case — ID Exists but URL Is Empty

**What goes wrong:** `_mollie_payment_id` is set but `payment_link` ACF field is empty. The idempotency check returns an empty string.

**Why it happens:** Partial write failure in a previous execution: `create()` succeeded, `update_post_meta` wrote the ID, but `update_field` for `payment_link` failed.

**How to avoid:** When the idempotency check finds an existing ID but no URL, fall through to create a new payment rather than returning an empty string. Accept that this creates a new Mollie payment (harmless — the old one expires naturally).

## Code Examples

### Complete create_payment_link() Implementation

```php
// Source: Verified against SDK source + Mollie Payments API docs

public function create_payment_link( int $invoice_id ): string|\WP_Error {
    // Validate invoice
    $invoice = get_post( $invoice_id );
    if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
        return new \WP_Error(
            'invalid_invoice',
            __( 'Ongeldige factuur.', 'rondo' ),
            [ 'status' => 404 ]
        );
    }

    // Idempotency: reuse existing checkout URL if payment already created
    $existing_payment_id = get_post_meta( $invoice_id, '_mollie_payment_id', true );
    if ( ! empty( $existing_payment_id ) ) {
        $existing_url = get_field( 'payment_link', $invoice_id );
        if ( ! empty( $existing_url ) ) {
            return $existing_url;
        }
        // Has ID but no URL — fall through to create a new payment
    }

    // Check Mollie API key is configured
    $config = new \Rondo\Config\FinanceConfig();
    if ( empty( $config->get_mollie_api_key() ) ) {
        return new \WP_Error(
            'mollie_not_configured',
            __( 'Mollie API-sleutel niet geconfigureerd.', 'rondo' ),
            [ 'status' => 400 ]
        );
    }

    // Load invoice data
    $invoice_number = get_field( 'invoice_number', $invoice_id );
    $total_amount   = get_field( 'total_amount', $invoice_id );

    // Format amount as string with exactly 2 decimal places
    $amount_string = number_format( (float) $total_amount, 2, '.', '' );

    // Build payload
    $payload = [
        'amount'      => [
            'currency' => 'EUR',
            'value'    => $amount_string,
        ],
        'description' => 'Factuur ' . $invoice_number,
        'redirectUrl' => home_url( '/' ),
    ];

    // Add webhookUrl only for non-local environments
    $site_url = get_site_url();
    if ( strpos( $site_url, 'localhost' ) === false && strpos( $site_url, '.local' ) === false ) {
        $payload['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
    }

    // Create Mollie payment
    try {
        $mollie_client = new MollieClient();
        $mollie        = $mollie_client->get();
        $payment       = $mollie->payments->create( $payload );
    } catch ( \Mollie\Api\Exceptions\ApiException $e ) {
        error_log( 'Mollie payment creation failed for invoice ' . $invoice_id . ': ' . $e->getMessage() );
        return new \WP_Error(
            'mollie_api_error',
            sprintf( __( 'Mollie betaling aanmaken mislukt: %s', 'rondo' ), $e->getMessage() ),
            [ 'status' => 502 ]
        );
    }

    // Extract checkout URL
    $checkout_url = $payment->getCheckoutUrl();
    if ( empty( $checkout_url ) ) {
        error_log( 'Mollie payment created but checkout URL is missing for invoice ' . $invoice_id );
        return new \WP_Error(
            'mollie_no_checkout_url',
            __( 'Geen checkout URL in Mollie response.', 'rondo' ),
            [ 'status' => 500 ]
        );
    }

    // Store results
    update_field( 'payment_link', $checkout_url, $invoice_id );
    update_post_meta( $invoice_id, '_mollie_payment_id', $payment->id );

    return $checkout_url;
}
```

### functions.php Registration

```php
// Source: Pattern from existing functions.php lines 73-77
// Add at top of file with other use imports:
use Rondo\Finance\MolliePayment;

// Note: MolliePayment has no REST routes to register in Phase 187.
// Do NOT add `new MolliePayment()` to the REST block.
// Phase 189 will call MolliePayment::create_payment_link() directly
// from within RestInvoices::send_invoice() — no constructor hook needed.
```

### Mollie Payments API Payload Reference

```php
// Source: https://docs.mollie.com/docs/accepting-payments-in-your-app (official docs)
// Confirmed against: vendor/mollie/mollie-api-php/src/Factories/CreatePaymentRequestFactory.php

$payload = [
    'amount'      => [
        'currency' => 'EUR',   // required
        'value'    => '12.50', // required, string, exactly 2 decimal places
    ],
    'description' => 'Factuur 2024-001',  // required
    'redirectUrl' => 'https://example.com/', // required
    'webhookUrl'  => 'https://example.com/wp-json/rondo/v1/mollie/webhook', // optional
    // method, metadata, etc. — all optional, not needed for Phase 187
];

$payment = $mollie->payments->create($payload);
// Returns: Mollie\Api\Resources\Payment
// $payment->id           => "tr_C15LZwVISe" (payment ID for webhook lookup)
// $payment->getCheckoutUrl() => "https://pay.mollie.com/..." (customer checkout)
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Mollie SDK v2 fluent API (`$mollie->createPayment()`) | SDK v3 collection-based API (`$mollie->payments->create()`) | v3.0 | Phase must use v3 syntax — v2 methods don't exist |
| Raw `wp_remote_post()` to Rabobank | Mollie PHP SDK handling HTTP | Phase 187 | No manual header management, cURL, or auth for Mollie |

**Deprecated/outdated:**
- SDK v2 `createPayment()` method: Does not exist in v3.9 (installed). Use `$mollie->payments->create()`.
- `$payment->_links->checkout->href` direct access: Works but fragile — use `$payment->getCheckoutUrl()` which has null safety.

## Open Questions

1. **REST endpoint registration for MolliePayment**
   - What we know: `RabobankPayment` registers its own `POST /invoices/{id}/payment-link` REST endpoint. Phase 189 handles provider routing in `send_invoice()`. Phase 187's roadmap says "MolliePayment class + functions.php registration."
   - What's unclear: Should `MolliePayment` also register a `POST /invoices/{id}/payment-link` endpoint (shadowing/replacing Rabobank's) or is REST fully deferred to Phase 189?
   - Recommendation: Based on the roadmap, Phase 187 is purely the service class — no REST routes. Phase 189 handles routing. The planner should confirm: 1 plan, no REST endpoint in Phase 187. `MolliePayment` is a service called by Phase 189's `send_invoice()` branching logic.

2. **redirectUrl value**
   - What we know: Mollie requires `redirectUrl`. For invoice payments (emailed link to customer), the redirect destination after payment is not critical — there is no in-app checkout flow.
   - What's unclear: Is `home_url('/')` sufficient, or should a specific "payment confirmed" page URL be used?
   - Recommendation: `home_url('/')` is standard for invoice-only payment flows. No special page is needed. This can be enhanced in a future phase.

3. **Catch `\Mollie\Api\Exceptions\RequestException` vs `ApiException`**
   - What we know: The SDK throws `ApiException extends RequestException` for most errors. Specific subclasses: `UnauthorizedException`, `NotFoundException`, `ValidationException`, `TooManyRequestsException`.
   - What's unclear: Whether catching the parent `ApiException` is sufficient or if `RequestException` should be caught instead.
   - Recommendation: Catch `\Mollie\Api\Exceptions\ApiException`. The parent `RequestException` is an interface-level concern. All user-facing errors from `payments->create()` throw `ApiException` or its subclasses.

## Sources

### Primary (HIGH confidence)

- `/Users/joostdevalk/Code/rondo/rondo-club/vendor/mollie/mollie-api-php/src/EndpointCollection/PaymentEndpointCollection.php` — verified `create(array $payload, ...): Payment` signature
- `/Users/joostdevalk/Code/rondo/rondo-club/vendor/mollie/mollie-api-php/src/Resources/Payment.php` — verified `$payment->id`, `$payment->getCheckoutUrl()`, `$payment->_links->checkout->href` (lines 301, 517-524)
- `/Users/joostdevalk/Code/rondo/rondo-club/vendor/mollie/mollie-api-php/src/Factories/CreatePaymentRequestFactory.php` — verified payload fields: `amount`, `description`, `redirectUrl`, `webhookUrl`
- `/Users/joostdevalk/Code/rondo/rondo-club/vendor/mollie/mollie-api-php/src/Http/Data/Money.php` — verified Money is `{currency: string, value: string}` and `value` must be a string
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rabobank-payment.php` — reference pattern for class structure, error handling, ACF storage, non-blocking error logging
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-client.php` — verified `get(): MollieApiClient` method exists and returns configured client
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` — verified `payment_link` ACF field name, `_payment_request_id` meta pattern (analogous to `_mollie_payment_id`), `send_invoice()` non-blocking pattern (lines 668-677)
- `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json` — confirmed `payment_link` is a URL ACF field
- `/Users/joostdevalk/Code/rondo/rondo-club/.planning/ROADMAP.md` — Phase 187 scope, success criteria, phase boundary (no REST in 187, routing in 189)
- `/Users/joostdevalk/Code/rondo/rondo-club/functions.php` — verified REST-only block pattern, `use` import style (lines 73-77, 375-378)

### Secondary (MEDIUM confidence)

- Mollie official docs at `https://docs.mollie.com/docs/accepting-payments-in-your-app` — PHP snippet showing `$mollie->payments->create()` call with array payload (verified via Context7)
- Mollie API docs showing `redirectUrl` as required, `webhookUrl` as optional (verified via Context7 query)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — SDK installed and source read directly; all method signatures verified in vendor code
- Architecture: HIGH — patterns read directly from `class-rabobank-payment.php` and `class-rest-invoices.php`; no inference needed
- Pitfalls: HIGH — amount formatting verified against SDK Money class; webhook omission verified against phase success criteria; MollieClient API key validation verified against constructor source

**Research date:** 2026-02-17
**Valid until:** 2026-03-17 (stable SDK; Mollie API v2 is stable; patterns are from production codebase)
