# Phase 189: RestInvoices Provider Routing - Research

**Researched:** 2026-02-17
**Domain:** PHP — WordPress REST API, internal service routing, payment provider abstraction
**Confidence:** HIGH

## Summary

This phase is a targeted surgical change to a single method: `Rondo\REST\Invoices::send_invoice()`. The method currently contains a hard-coded Rabobank path (lines 669-677 in `class-rest-invoices.php`). The change replaces that hard-coded block with a two-branch `if/else` that reads `FinanceConfig::get_active_payment_provider()` and delegates either to `MolliePayment::create_payment_link()` (Mollie branch) or the existing `RabobankOAuth` / `RabobankPayment` path (Rabobank branch).

All infrastructure is already in place. `FinanceConfig::get_active_payment_provider()` exists and returns `'rabobank'` by default. `MolliePayment` is a pure service class with the correct return contract (`string|\WP_Error`). The `use Rondo\Finance\MolliePayment` import was added proactively in phase 187 — it already appears at line 78 of `functions.php` (in the global use block) but the import needed inside `class-rest-invoices.php` must be verified and added if absent. `RabobankPayment::create_payment_request()` returns `string|\WP_Error` — the same contract as `MolliePayment::create_payment_link()`, so error-handling symmetry is straightforward.

The only non-trivial design question is how to treat a Mollie payment-link failure: the existing Rabobank path is explicitly non-blocking (errors are logged but execution continues to PDF generation and email). The phase requirements say Rabobank behavior must be byte-for-byte identical, and the Mollie path must mirror that non-blocking pattern to avoid a regression where Mollie failures silently block invoice sending.

**Primary recommendation:** Add `use Rondo\Finance\MolliePayment;` to the import block in `class-rest-invoices.php`, then replace the Rabobank-only block in `send_invoice()` with a two-branch conditional. Keep both branches non-blocking (log on error, continue). No other files require changes.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `Rondo\Config\FinanceConfig` | current | Reads `active_payment_provider` option | Already imported in `class-rest-invoices.php`; `get_active_payment_provider()` returns `'rabobank'` default |
| `Rondo\Finance\MolliePayment` | phase 187 | Creates Mollie payment link; returns `string|\WP_Error` | Purpose-built service for this routing |
| `Rondo\Finance\RabobankOAuth` / `RabobankPayment` | existing | Rabobank path — unchanged | Already present; must not be modified |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `error_log()` | PHP built-in | Non-blocking failure logging | Both branches should log errors and continue, matching existing pattern |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Direct `if/else` in `send_invoice()` | Separate `PaymentRouter` class | Unnecessary abstraction for two providers; direct branch is simpler and matches existing codebase style |
| Blocking on Mollie failure | Non-blocking (log + continue) | Non-blocking matches the existing Rabobank treatment — invoice can still be sent without a payment link |

## Architecture Patterns

### Existing `send_invoice()` flow (current)

```
send_invoice()
  1. Validate invoice exists
  2. Check invoice is rondo_draft
  3. If RabobankOAuth->is_connected():
       RabobankPayment->create_payment_request($id)   // non-blocking
  4. InvoicePdfGenerator::generate($id)               // blocking
  5. InvoiceEmailSender::send($id)                    // blocking
  6. Transition to rondo_sent, set sent_date, due_date
  7. Mark discipline cases as charged
  8. Return formatted invoice
```

### Target `send_invoice()` flow (after phase 189)

```
send_invoice()
  1. Validate invoice exists
  2. Check invoice is rondo_draft
  3. $provider = FinanceConfig->get_active_payment_provider()
     if $provider === 'mollie':
       MolliePayment->create_payment_link($id)         // non-blocking
     else:  // rabobank (default)
       if RabobankOAuth->is_connected():
         RabobankPayment->create_payment_request($id)  // non-blocking
  4-8. Unchanged
```

### Pattern: Non-blocking payment link creation

```php
// Source: existing send_invoice() lines 669-677 in class-rest-invoices.php
$oauth = new RabobankOAuth();
if ( $oauth->is_connected() ) {
    $payment        = new RabobankPayment( $oauth );
    $payment_result = $payment->create_payment_request( $invoice_id );
    if ( is_wp_error( $payment_result ) ) {
        error_log( 'Rabobank payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
    }
}
```

Mollie mirrors this pattern:

```php
$mollie_payment = new MolliePayment();
$payment_result = $mollie_payment->create_payment_link( $invoice_id );
if ( is_wp_Error( $payment_result ) ) {
    error_log( 'Mollie payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
}
```

### Anti-Patterns to Avoid

- **Blocking on payment link failure:** Both providers treat payment link errors as non-fatal. If Mollie throws, execution must continue to PDF + email. Returning the `WP_Error` early would be a regression against Rabobank behavior.
- **Modifying RabobankPayment or RabobankOAuth:** WIRE-02 is explicit — those classes are not touched.
- **Checking mollie_has_api_key in send_invoice():** `MolliePayment::create_payment_link()` already guards for a missing API key and returns `WP_Error('mollie_not_configured')`. The routing layer does not need to duplicate this check.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Reading active provider | Custom option access | `FinanceConfig::get_active_payment_provider()` | Already exists; handles default `'rabobank'` correctly |
| Creating Mollie payment | Direct Mollie SDK call | `MolliePayment::create_payment_link()` | Handles idempotency, localhost webhook guard, error wrapping |
| Rabobank path | Any modification | Leave the existing block verbatim | WIRE-02 requirement |

**Key insight:** This phase adds 8-12 lines of routing logic and one `use` import. It does not create or restructure any classes.

## Common Pitfalls

### Pitfall 1: Missing `use` import in class-rest-invoices.php
**What goes wrong:** `MolliePayment` is used in `functions.php` and `class-mollie-payment.php` but the `use` statement must also appear at the top of `class-rest-invoices.php` or the class call will fail with a fatal error.
**Why it happens:** The per-file import was not added in phase 187 (only `functions.php` import was added there).
**How to avoid:** Check the existing `use` block in `class-rest-invoices.php` (lines 9-16) and add `use Rondo\Finance\MolliePayment;` if absent.
**Verified:** Current file has `use Rondo\Finance\RabobankPayment;` at line 15 but NOT `use Rondo\Finance\MolliePayment;` — it must be added.

### Pitfall 2: Treating `'rabobank'` branch as requiring `is_connected()` check
**What goes wrong:** The existing Rabobank block wraps `create_payment_request()` in `if ($oauth->is_connected())`. This check is part of the Rabobank path and must remain for that branch. The Mollie branch has no equivalent "is connected" gate — `MolliePayment::create_payment_link()` handles the missing API key case internally.
**How to avoid:** Do not add an `is_configured()` gate to the Mollie branch in `send_invoice()`.

### Pitfall 3: Default provider assumption
**What goes wrong:** If no `active_payment_provider` option is stored, `get_active_payment_provider()` returns `'rabobank'`. The `else` branch must catch rabobank (not just `=== 'rabobank'`) to be safe. Using `else` rather than `elseif ('rabobank')` is the correct pattern.
**How to avoid:** Use `if ($provider === 'mollie') { ... } else { ... }` — any unknown value falls to Rabobank.

### Pitfall 4: `FinanceConfig` already instantiated later in `send_invoice()`
**What goes wrong:** `send_invoice()` already creates a `new FinanceConfig()` at line 707 for `get_payment_term_days()`. Creating a second instance at the top for `get_active_payment_provider()` is fine (no singleton constraint) but the plan should note this.
**How to avoid:** Create one `FinanceConfig` instance at the start of the payment routing block, or reuse the existing one by hoisting it. Either approach is acceptable. The simplest is a separate `$config` variable at the top of the routing block since both calls are well-separated.

## Code Examples

### Current Rabobank block (lines 669-677, class-rest-invoices.php)
```php
// Source: /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php
$oauth = new RabobankOAuth();
if ( $oauth->is_connected() ) {
    $payment        = new RabobankPayment( $oauth );
    $payment_result = $payment->create_payment_request( $invoice_id );
    // Log error but continue - payment link is non-blocking
    if ( is_wp_error( $payment_result ) ) {
        error_log( 'Rabobank payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
    }
}
```

### Target routing block (replaces lines 668-677)
```php
// Source: constructed from phase decisions + existing patterns
$finance_config = new FinanceConfig();
$active_provider = $finance_config->get_active_payment_provider();

if ( 'mollie' === $active_provider ) {
    $mollie_payment = new MolliePayment();
    $payment_result = $mollie_payment->create_payment_link( $invoice_id );
    // Log error but continue - payment link is non-blocking
    if ( is_wp_error( $payment_result ) ) {
        error_log( 'Mollie payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
    }
} else {
    $oauth = new RabobankOAuth();
    if ( $oauth->is_connected() ) {
        $payment        = new RabobankPayment( $oauth );
        $payment_result = $payment->create_payment_request( $invoice_id );
        // Log error but continue - payment link is non-blocking
        if ( is_wp_error( $payment_result ) ) {
            error_log( 'Rabobank payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
        }
    }
}
```

Note: The second `FinanceConfig` instance created later at line 707 (`$config = new FinanceConfig()`) can remain as-is or be deduplicated — both are correct.

### `use` import to add (class-rest-invoices.php, after line 15)
```php
use Rondo\Finance\MolliePayment;
```

### `FinanceConfig::get_active_payment_provider()` contract
```php
// Source: /Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php line 397-399
public function get_active_payment_provider(): string {
    return get_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hard-coded Rabobank in `send_invoice()` | Provider routing via `FinanceConfig` | Phase 189 | Enables Mollie path without breaking existing Rabobank installs |
| No Mollie import in `class-rest-invoices.php` | `use Rondo\Finance\MolliePayment;` | Phase 189 | Required for `new MolliePayment()` to resolve |

## Open Questions

None. All required components exist and their APIs are fully determined from code inspection.

## Sources

### Primary (HIGH confidence)
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php` — full `send_invoice()` implementation, existing imports, FinanceConfig usage
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-finance-config.php` — `get_active_payment_provider()` signature and default
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-payment.php` — `create_payment_link()` return type and contract
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-rabobank-payment.php` — `create_payment_request()` return type and contract
- `/Users/joostdevalk/Code/rondo/rondo-club/includes/class-mollie-webhook.php` — confirmed phase 188 complete; webhook at `rondo/v1/mollie/webhook`
- `/Users/joostdevalk/Code/rondo/rondo-club/functions.php` — confirmed `use Rondo\Finance\MolliePayment` and `MollieWebhook` already present

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all classes inspected from source
- Architecture: HIGH — exact line numbers identified, routing pattern directly derived from existing code
- Pitfalls: HIGH — verified from actual file contents (missing import confirmed)

**Research date:** 2026-02-17
**Valid until:** Until any of the four classes under research change (stable for this milestone)
