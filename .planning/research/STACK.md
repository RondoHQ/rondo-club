# Stack Research: Mollie Payment Integration

**Domain:** Payment provider integration (WordPress PHP theme)
**Researched:** 2026-02-17
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| `mollie/mollie-api-php` | `^3.9` | Official Mollie PHP SDK — creates payment links, fetches payment status | Only official PHP SDK; v3 is the current major version with a modern request-object API (`$mollie->send(new CreatePaymentLinkRequest(...))`) replacing the v2 fluent API; released 2026-02-09 |
| `nyholm/psr7` | `^1.8` | PSR-7 HTTP message implementation required by Mollie SDK | Mollie SDK's only new transitive dependency not already in `vendor/`; pulled in automatically by `composer require mollie/mollie-api-php` |

**Existing stack components already in place (no additions needed):**

| Technology | Status | Notes |
|------------|--------|-------|
| `psr/http-client ^1.0` | Already installed | Pulled in by `google/apiclient`; Mollie SDK requires same version |
| `psr/http-factory ^1.1` | Already installed | Same |
| `psr/http-message ^1.1\|^2.0` | Already installed | Same |
| `guzzlehttp/guzzle 7.10.0` | Already installed | Mollie SDK uses Guzzle only as `require-dev`; production uses nyholm/psr7 internally |
| Sodium encryption (`CredentialEncryption`) | Already implemented | Use the existing pattern from `RabobankOAuth` for storing the Mollie API key |
| WordPress Options API | Already used | Store Mollie API key as encrypted option (same as Rabobank credentials) |
| `wp_remote_post` / REST API | Already used | **Do not use** for Mollie — use the SDK's `$mollie->send()` instead |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| None beyond Mollie SDK | — | — | The Mollie SDK is self-contained; no additional webhook verification library is needed |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Mollie test API key (`test_...`) | Sandbox testing without real payments | Set in wp-options; toggle between test/live with environment flag like existing `RabobankOAuth::get_environment()` pattern |

## Installation

```bash
# From rondo-club/ directory
composer require mollie/mollie-api-php:^3.9
```

This will pull in `nyholm/psr7:^1.8` as a new transitive dependency. All other PSR dependencies are already satisfied by existing `google/apiclient` requirements. Run `composer install` on the server after deploying the updated `composer.json` and `composer.lock`.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| `mollie/mollie-api-php` (official SDK) | Raw `wp_remote_post` calls to Mollie REST API | Never — the SDK handles authentication headers, response hydration, error handling, and PSR HTTP adapters correctly |
| `mollie/mollie-api-php` v3 | v2 (`^2.x`) | Never for new code — v2 uses deprecated fluent API (`$mollie->payments->create()`); v3 uses modern request objects and is actively maintained |
| `nyholm/psr7` (via SDK) | `guzzlehttp/psr7` (already installed) | Mollie SDK hard-requires `nyholm/psr7` specifically; cannot substitute `guzzlehttp/psr7` |

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `mollie/oauth2-mollie-php` | Only needed for Mollie Connect (OAuth flows for multi-merchant SaaS); discipline-case invoicing uses a single club API key, not OAuth | Simple API key stored encrypted in wp-options |
| `mollie/laravel-mollie` | Laravel-only package; will not work in WordPress | `mollie/mollie-api-php` directly |
| Custom HMAC webhook signature verification | Mollie does NOT send HMAC signatures — webhook body only contains `id=tr_xxx`; verification is done by fetching payment state from the API using that ID with authenticated SDK call | Fetch payment via `$mollie->send(new GetPaymentRequest($id))` after receiving webhook |
| Custom HTTP client wrapping `wp_remote_post` | Bypasses SDK's PSR HTTP adapter layer; creates maintenance burden | Use `$mollie->send()` from the SDK directly |

## Stack Patterns: Mollie-Specific

**Webhook security model (verified against official Mollie docs):**

Mollie does NOT use HMAC signatures on webhook POSTs. The webhook body contains only `id=tr_xxx`. Security works by:
1. Receiving the `id` from `$_POST['id']` at the webhook endpoint
2. Fetching the actual payment state from Mollie API using the SDK (authenticated call)
3. Never trusting the webhook body alone — always re-fetch

This means the webhook handler in WordPress must be a public REST endpoint (no `permission_callback` auth check), but must validate the fetched payment belongs to an invoice the system created.

**WordPress REST endpoint for webhooks:**

Register a public endpoint in the Mollie payment class:
```php
register_rest_route('rondo/v1', '/mollie/webhook', [
    'methods'             => \WP_REST_Server::CREATABLE,
    'callback'            => [$this, 'handle_webhook'],
    'permission_callback' => '__return_true', // Public — Mollie has no auth header
]);
```

The endpoint must return HTTP 200 within 15 seconds. Mollie retries up to 10 times over 26 hours if it receives a non-200 response.

**SDK initialization pattern (follow RabobankOAuth storage pattern):**

```php
use Mollie\Api\MollieApiClient;

$mollie = new MollieApiClient();
$mollie->setApiKey($this->get_api_key()); // decrypt from wp-options via CredentialEncryption
```

**Creating a payment link (v3 SDK pattern):**

```php
use Mollie\Api\Http\Requests\CreatePaymentLinkRequest;
use Mollie\Api\Http\Data\Money;

$payment_link = $mollie->send(new CreatePaymentLinkRequest(
    description: 'Factuur ' . $invoice_number,
    amount: new Money('EUR', number_format($total_amount, 2, '.', '')),
    redirectUrl: get_site_url() . '/mollie/betaald/',
    webhookUrl: rest_url('rondo/v1/mollie/webhook'),
));

$checkout_url = $payment_link->getCheckoutUrl();
```

**Handling webhook to update invoice status:**

```php
use Mollie\Api\Http\Requests\GetPaymentRequest;

public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response {
    $payment_id = $request->get_param('id');
    if (empty($payment_id)) {
        return rest_ensure_response(['status' => 'ok']); // Always 200
    }

    $payment = $mollie->send(new GetPaymentRequest($payment_id));

    if ($payment->isPaid()) {
        // Update invoice status: 'paid' + store payment_id
        wp_update_post(['ID' => $invoice_id, 'post_status' => 'paid']);
        update_post_meta($invoice_id, '_mollie_payment_id', $payment_id);
    }

    return rest_ensure_response(['status' => 'ok']); // Always return 200
}
```

**Linking payment link ID to invoice:**

Store the Mollie payment link ID on the invoice post meta (`_mollie_payment_link_id`) so the webhook handler can look up the invoice by the payment's associated payment link.

## Version Compatibility

| Package | Version | Compatible With | Notes |
|---------|---------|-----------------|-------|
| `mollie/mollie-api-php` | `^3.9` | PHP `^8.0` | Satisfies existing `composer.json` PHP requirement |
| `mollie/mollie-api-php` | `^3.9` | `psr/http-client ^1.0` | Already installed via google/apiclient |
| `mollie/mollie-api-php` | `^3.9` | `psr/http-message ^1.1\|^2.0` | Already installed |
| `nyholm/psr7` | `^1.8` | `psr/http-factory ^1.1` | New package; no conflicts expected with guzzlehttp/psr7 |
| `guzzlehttp/guzzle` | `7.10.0` (existing) | `mollie/mollie-api-php ^3.9` | Mollie v3 uses Guzzle only in `require-dev`; production adapter is `nyholm/psr7` based |

**Potential conflict to verify:** Both `guzzlehttp/psr7` and `nyholm/psr7` implement `psr/http-message`. Composer resolves this via virtual packages (`psr/http-message-implementation`). This is standard practice and will not conflict.

## Sources

- [Packagist: mollie/mollie-api-php](https://packagist.org/packages/mollie/mollie-api-php) — latest version v3.9.0, PHP requirements (HIGH confidence)
- [GitHub: mollie/mollie-api-php composer.json](https://github.com/mollie/mollie-api-php/blob/master/composer.json) — exact dependency versions (HIGH confidence)
- [GitHub: mollie/mollie-api-php src/Http/Requests](https://github.com/mollie/mollie-api-php/tree/master/src/Http/Requests) — `CreatePaymentLinkRequest.php` exists with constructor signature (HIGH confidence)
- [GitHub: mollie/mollie-api-php webhook recipe](https://github.com/mollie/mollie-api-php/blob/master/docs/recipes/payments/handle-webhook.md) — webhook handling pattern (HIGH confidence)
- [Mollie Docs: Webhooks](https://docs.mollie.com/reference/webhooks) — no HMAC, POST body is `id=tr_xxx` only, 15s timeout, 10 retries over 26h (HIGH confidence)
- [Mollie Docs: Create Payment Link](https://docs.mollie.com/reference/create-payment-link) — `webhookUrl` parameter, response includes checkout URL (HIGH confidence)
- Existing codebase: `composer.json`, `vendor/composer/installed.php` — confirmed existing PSR deps and guzzle versions (HIGH confidence)

---
*Stack research for: Mollie payment links + webhook integration in WordPress PHP 8.0+ theme*
*Researched: 2026-02-17*
