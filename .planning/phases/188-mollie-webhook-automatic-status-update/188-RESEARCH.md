# Phase 188: MollieWebhook — Automatic Status Update - Research

**Researched:** 2026-02-17
**Domain:** WordPress REST API (public endpoint) + Mollie PHP SDK webhook handling
**Confidence:** HIGH

## Summary

Phase 188 adds a dedicated public REST endpoint (`POST /wp-json/rondo/v1/mollie/webhook`) that Mollie calls after a payment status change. The handler re-fetches the payment from Mollie via the SDK, finds the matching invoice by `_mollie_payment_id` post meta, and transitions it to `rondo_paid` post status when Mollie confirms `paid`. The entire implementation is contained in a new `MollieWebhook` class (`includes/class-mollie-webhook.php`) in the `Rondo\Finance` namespace, registered in `functions.php` and wired to the `rest_api_init` hook.

All foundational dependencies are already implemented: `MollieClient` (Phase 186) provides the configured SDK client, `MolliePayment` (Phase 187) stores `_mollie_payment_id` post meta on invoices, and the `rondo_paid` post status is registered in `PostTypes`. The `status` ACF field (not `payment_status` — see Open Questions) stores the human-readable status string. The webhook URL `rondo/v1/mollie/webhook` is already hardcoded in `MolliePayment::create_payment_link()`.

**Primary recommendation:** Create `class-mollie-webhook.php` in `Rondo\Finance` namespace with a single public route registered via `rest_api_init`, using `'permission_callback' => '__return_true'` for public access, `WP_Query` for invoice lookup by meta, and always returning HTTP 200 regardless of errors.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Mollie PHP SDK | ^3.x (vendor installed) | Re-fetch payment by ID to confirm status | Already in vendor; MollieClient wraps it |
| WordPress REST API | WP 6.0+ | Route registration via `register_rest_route` | Native WP pattern used throughout codebase |
| WordPress WP_Query | WP 6.0+ | Find invoice by `_mollie_payment_id` post meta | Established codebase pattern — never raw SQL |
| ACF `update_field()` | ACF Pro | Update `status` ACF field to `'paid'` | Codebase standard for ACF field updates |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wp_update_post()` | WP core | Transition `post_status` to `rondo_paid` | Matches existing invoice status update pattern in `RestInvoices` |
| `error_log()` | PHP built-in | Log errors while still returning 200 | Required by WHKT-05 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Dedicated `MollieWebhook` class | Adding webhook route to `RestInvoices` | Separation of concerns — webhook is async server-to-server, not user-facing CRUD |
| `Rondo\Finance` namespace | `Rondo\REST` namespace | Finance namespace is correct; this is a finance domain class, not a user REST controller |

**No new packages to install.** All dependencies are already in vendor.

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── class-mollie-client.php      # EXISTS: Phase 186 — SDK wrapper
├── class-mollie-payment.php     # EXISTS: Phase 187 — payment link creation
└── class-mollie-webhook.php     # NEW: Phase 188 — webhook handler
```

### Pattern 1: Public REST Route Registration

**What:** Register a route with `'permission_callback' => '__return_true'` to bypass WordPress authentication. This is the correct approach for machine-to-machine webhooks from external services.

**When to use:** When an external service (Mollie) must call your endpoint without a WordPress session or nonce.

**Example:**
```php
// Source: Codebase — matches register_rest_route pattern used throughout includes/
add_action( 'rest_api_init', [ $this, 'register_routes' ] );

public function register_routes() {
    register_rest_route(
        'rondo/v1',
        '/mollie/webhook',
        [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ]
    );
}
```

### Pattern 2: Always-200 Webhook Handler

**What:** Mollie's webhook contract requires HTTP 200 on all responses, including errors. Log internally but never return non-200.

**When to use:** Any external payment provider webhook endpoint.

**Example:**
```php
// Source: Mollie official docs — https://docs.mollie.com/docs/webhooks
public function handle_webhook( \WP_REST_Request $request ) {
    $payment_id = sanitize_text_field( $request->get_param( 'id' ) );

    if ( empty( $payment_id ) ) {
        error_log( 'Mollie webhook: missing payment ID' );
        return rest_ensure_response( [ 'ok' => true ] ); // Still 200
    }

    try {
        $mollie_client = new MollieClient();
        $payment       = $mollie_client->get()->payments->get( $payment_id );
    } catch ( \Mollie\Api\Exceptions\ApiException $e ) {
        error_log( 'Mollie webhook: API exception for ' . $payment_id . ': ' . $e->getMessage() );
        return rest_ensure_response( [ 'ok' => true ] ); // Still 200
    }

    if ( ! $payment->isPaid() ) {
        // Not paid yet — nothing to do, return 200
        return rest_ensure_response( [ 'ok' => true ] );
    }

    // Find invoice by _mollie_payment_id meta
    $query = new \WP_Query( [
        'post_type'      => 'rondo_invoice',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'   => '_mollie_payment_id',
                'value' => $payment_id,
            ],
        ],
    ] );

    if ( empty( $query->posts ) ) {
        error_log( 'Mollie webhook: no invoice found for payment ' . $payment_id );
        return rest_ensure_response( [ 'ok' => true ] ); // Still 200
    }

    $invoice = $query->posts[0];

    // Idempotency: already paid — no-op
    if ( $invoice->post_status === 'rondo_paid' ) {
        return rest_ensure_response( [ 'ok' => true ] );
    }

    // Transition to paid
    wp_update_post( [
        'ID'          => $invoice->ID,
        'post_status' => 'rondo_paid',
    ] );
    update_field( 'status', 'paid', $invoice->ID );

    return rest_ensure_response( [ 'ok' => true ] );
}
```

### Pattern 3: Instantiation in functions.php

**What:** The new `MollieWebhook` class must be instantiated inside the `$is_rest` block in `rondo_init()`, matching the pattern for all other REST-domain classes.

**When to use:** Every class that registers REST routes.

**Example:**
```php
// Source: functions.php — existing pattern for REST classes
use Rondo\Finance\MollieWebhook;

// In rondo_init(), inside the $is_rest block:
if ( $is_rest ) {
    // ... existing classes ...
    new MollieWebhook();
}
```

### Anti-Patterns to Avoid

- **Trusting the POST body for payment status:** Mollie explicitly says never trust the `id` in the POST body alone — always re-fetch from the API. The phase requirements match this (WHKT-02).
- **Returning non-200 on errors:** Mollie retries on non-200 responses, causing repeated webhook calls. Always return 200 even if the invoice is not found or the API call fails (WHKT-05).
- **Using `Rondo\REST` namespace:** The webhook is a finance domain class (`Rondo\Finance`), not a user-facing REST controller. Keep it in `Finance` namespace alongside `MollieClient` and `MolliePayment`.
- **Singleton pattern:** `MollieClient` is explicitly non-singleton (Phase 186 decision). Instantiate fresh per use.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Payment ID → SDK client | Custom HTTP call to Mollie API | `MollieClient` + `->get()->payments->get($id)` | Already handles API key config, SDK initialization |
| Post meta lookup | Raw SQL or custom query | `WP_Query` with `meta_query` | Codebase rule — never raw SQL |
| Status string constants | Magic strings | `PaymentStatus::PAID` or `$payment->isPaid()` | SDK already defines these; `isPaid()` checks `paidAt` which is correct |

**Key insight:** The entire implementation reuses Phase 186 (`MollieClient`), Phase 187 (`_mollie_payment_id` meta), and existing invoice post status infrastructure. No new abstractions are needed.

## Common Pitfalls

### Pitfall 1: Using `$payment->status === 'paid'` instead of `$payment->isPaid()`

**What goes wrong:** The Mollie SDK's `isPaid()` method checks `!empty($this->paidAt)` (not just `$this->status === PaymentStatus::PAID`). For some payment methods, a payment can be marked paid via `paidAt` even when status has edge cases.

**Why it happens:** Developers assume `status` field is the only truth source.

**How to avoid:** Use `$payment->isPaid()` as shown in the code example above.

**Warning signs:** Logic branching on `$payment->status` directly.

### Pitfall 2: WordPress nonce validation blocking public endpoint

**What goes wrong:** WordPress REST API by default requires `X-WP-Nonce` for authenticated requests. If `permission_callback` is missing or incorrectly set, Mollie's webhook call (which has no WordPress auth) will get 401/403.

**Why it happens:** Forgetting to use `'permission_callback' => '__return_true'` for public endpoints.

**How to avoid:** Explicitly set `permission_callback` to `'__return_true'`. Verify with `curl -X POST <url> -d "id=test"` (success criterion #1).

**Warning signs:** `curl -X POST` returns 401 Unauthorized.

### Pitfall 3: Non-idempotent status transition

**What goes wrong:** Calling `wp_update_post` even when `post_status` is already `rondo_paid` causes unnecessary writes and may reset other fields.

**Why it happens:** No guard check before the transition.

**How to avoid:** Check `$invoice->post_status === 'rondo_paid'` before updating (shown in pattern above). Return early as a no-op.

**Warning signs:** Database writes on every duplicate webhook call.

### Pitfall 4: `payment_status` field name vs `status` field

**What goes wrong:** The phase requirements mention "payment_status ACF field updates to `paid`" — but the actual ACF field on `rondo_invoice` is named `status` (as seen in `acf-json/group_invoice_fields.json` and used throughout `RestInvoices`).

**Why it happens:** Requirement specification discrepancy.

**How to avoid:** Use `update_field( 'status', 'paid', $invoice->ID )` — not `update_field( 'payment_status', 'paid', $invoice->ID )`. This matches the existing pattern in `RestInvoices::update_invoice_status()` (line 489).

**Warning signs:** Using `update_field( 'payment_status', ... )` which updates a non-existent field.

### Pitfall 5: Class not loaded for REST requests on MollieWebhook

**What goes wrong:** The new class is defined but not instantiated in `rondo_init()`, so routes are never registered.

**Why it happens:** Forgetting to add `new MollieWebhook()` inside the `$is_rest` block in `functions.php`.

**How to avoid:** Add both `use Rondo\Finance\MollieWebhook;` at the top of `functions.php` and `new MollieWebhook();` in the `$is_rest` block.

**Warning signs:** Endpoint returns 404 not found.

## Code Examples

Verified patterns from official sources:

### Fetching Payment by ID from Mollie SDK

```php
// Source: vendor/mollie/mollie-api-php/src/MollieApiClient.php — @example $client->payments->get('tr_xxx')
// Source: vendor/mollie/mollie-api-php/src/Resources/Payment.php — isPaid() method
$mollie_client = new MollieClient();
$mollie        = $mollie_client->get();
$payment       = $mollie->payments->get( $payment_id );

if ( $payment->isPaid() ) {
    // Payment confirmed — isPaid() checks !empty($this->paidAt)
}
```

### WP_Query Invoice Lookup by Meta

```php
// Source: includes/class-rest-invoices.php — WP_Query meta_query pattern
$query = new \WP_Query( [
    'post_type'      => 'rondo_invoice',
    'post_status'    => 'any',   // Must include all statuses (rondo_sent, rondo_overdue, etc.)
    'posts_per_page' => 1,
    'meta_query'     => [
        [
            'key'   => '_mollie_payment_id',
            'value' => $payment_id,
        ],
    ],
] );
```

Note: `post_status' => 'any'` is required because `rondo_sent` and `rondo_overdue` are custom statuses not included in the default query.

### Correct Status Transition (matches existing RestInvoices pattern)

```php
// Source: includes/class-rest-invoices.php lines 481-489 (update_invoice_status)
wp_update_post( [
    'ID'          => $invoice_id,
    'post_status' => 'rondo_paid',
] );
update_field( 'status', 'paid', $invoice_id );
```

### Public REST Route (no WordPress auth)

```php
// Source: WordPress REST API docs — permission_callback controls auth
register_rest_route(
    'rondo/v1',
    '/mollie/webhook',
    [
        'methods'             => \WP_REST_Server::CREATABLE,
        'callback'            => [ $this, 'handle_webhook' ],
        'permission_callback' => '__return_true',  // Public — no WP auth required
    ]
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Trust webhook payload status | Re-fetch from Mollie API | Mollie API v2 | More secure — prevents spoofed webhooks |
| Return 4xx on errors | Always return 200, log errors | Mollie standard | Prevents retry storms from Mollie |

**Deprecated/outdated:**
- None applicable for this phase.

## Open Questions

1. **`payment_status` vs `status` field name in requirements**
   - What we know: The phase description says "payment_status ACF field updates to `paid`" but `acf-json/group_invoice_fields.json` has no `payment_status` field. The field is named `status`.
   - What's unclear: Whether `payment_status` is an intentional new field to add, or a specification error.
   - Recommendation: Use `update_field( 'status', 'paid', $invoice->ID )` to match the existing pattern. The `status` field is what `RestInvoices` uses and what the frontend reads. Adding a separate `payment_status` field would require an ACF schema change and is not mentioned in any prior phases.

2. **Should webhook handler also update `payment_link` field?**
   - What we know: The `payment_link` ACF field stores the Mollie checkout URL. Once paid, the link is no longer needed.
   - What's unclear: Whether to clear or preserve it after payment.
   - Recommendation: Leave `payment_link` unchanged. No requirement mentions clearing it, and preserving it provides an audit trail.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/vendor/mollie/mollie-api-php/src/Types/PaymentStatus.php` — confirmed payment status constants
- `/Users/joostdevalk/Code/rondo/rondo-club/vendor/mollie/mollie-api-php/src/Resources/Payment.php` — confirmed `isPaid()` checks `paidAt`
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-client.php` — confirmed `MollieClient` API: instantiate fresh, call `->get()->payments->get($id)`
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-payment.php` — confirmed `_mollie_payment_id` meta key and webhook URL constant `rondo/v1/mollie/webhook`
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` — confirmed `status` ACF field name, `rondo_paid` post status, `WP_Query` patterns
- `/Users/joostdevalk/Code/rondo/rondo-club/acf-json/group_invoice_fields.json` — confirmed ACF field is `status`, NOT `payment_status`
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-post-types.php` — confirmed `rondo_paid` post status registration

### Secondary (MEDIUM confidence)
- https://docs.mollie.com/docs/webhooks — confirmed: POST with `id` parameter, must return HTTP 200, must re-fetch payment status from API (not trust POST body)

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries verified in vendor and codebase
- Architecture: HIGH — patterns verified against existing `RestInvoices`, `RabobankPayment`, and Mollie docs
- Pitfalls: HIGH — field name discrepancy confirmed by reading ACF JSON; Mollie 200-always confirmed by official docs

**Research date:** 2026-02-17
**Valid until:** 2026-03-17 (Mollie SDK is stable; WordPress patterns are stable)
